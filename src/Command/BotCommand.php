<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 21/06/2018
 * Time: 13:29.
 */

namespace App\Command;

use App\Entity\Author;
use App\Entity\Cache;
use App\Entity\Status;
use App\Utils\MastodonUtils;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BotCommand extends ContainerAwareCommand
{
    public const COMMAND_NAME = 'app:bot:run';
    public const COMMAND_DESCRIPTION = "Reads the mentions on the account configured in the .env file and reacts to the commands received.\n".
                                        'This command should be run periodically, for instance via a Cron job.';

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->getEntityManager();

        $this->processCommands($em, $io);
        $this->updateAuthors($em, $io);
    }

    /**
     * @param ObjectManager $entityManager
     * @param SymfonyStyle  $io
     */
    private function processCommands(ObjectManager $entityManager, SymfonyStyle $io): void
    {
        try {
            $lastMentionId = $entityManager->getRepository(Cache::class)->getValue('LAST_MENTION_ID');

            if (null === $lastMentionId) {
                $lastMentionId = new Cache();
                $lastMentionId
                    ->setName('LAST_MENTION_ID')
                    ->setValue(-1);
            }

            $notifications = MastodonUtils::getLastMentions($lastMentionId->getValue());

            foreach ($notifications as $notification) {
                $idNotification = $notification['idNotification'];
                /** @var Status $mention */
                $mention = $notification['status'];

                // If in dev mode, ignore the DM from any person who is not the ADMIN defined in the .env
                if ('dev' == getenv('APP_ENV') &&
                    strtolower($mention->getAuthor()->getUsername()) != strtolower(getenv('ADMIN'))) {
                    continue;
                }

                if (stripos($mention->getContent(), '#nocommand')) {
                    continue;
                }

                if (preg_match('#d[ée]sinscri[ts][ -]moi#iu', $mention->getContent()) ||
                    preg_match('#supprimes? mon compte#i', $mention->getContent())) {
                    /* Delete account command */
                    $this->deleteProfile($io, $mention);
                } elseif (preg_match('#inscri[ts][ -]moi#i', $mention->getContent())) {
                    /* Create account command */
                    $this->subscribe($io, $mention);
                } elseif (preg_match("#masquer?(.+)(https?:\/\/[a-z0-9.-]+\/@[a-z0-9_-]+\/[0-9]+)#i", $mention->getContent(), $matches)) {
                    $this->changeStatusVisibility($io, $mention, $matches[2], false);
                } elseif (preg_match("#((affiche)|(restaure)r?)(.+)(https?:\/\/[a-z0-9.-]+\/@[a-z0-9_-]+\/[0-9]+)#i", $mention->getContent(), $matches)) {
                    $this->changeStatusVisibility($io, $mention, $matches[5], true);
                } else {
                    $this->sendManual($mention, $io);
                }

                if ($lastMentionId->getValue() < $idNotification) {
                    $lastMentionId->setValue($idNotification);
                }
            }

            $entityManager->persist($lastMentionId);
            $entityManager->flush();
        } catch (\Exception $e) {
            CommandUtils::writeError($io, 'Could not get the last mentions.', $e);
        }
    }

    private function getEntityManager(): ObjectManager
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    private function sendManual(Status $mention, SymfonyStyle $io)
    {
        try {
            $io->writeln('Sending manual to '.$mention->getAuthor()->getUsername());
            MastodonUtils::sendStatus("Désolé, mes capacités de réponse sont limitées. Tu dois me poser la bonne question. 😉\n\nTu trouveras toutes les infos sur mes commandes ici : ".getenv('SITE_URL').'/bot 😉', $mention);
        } catch (\Exception $e) {
            CommandUtils::writeError($io, 'Could not answer to a mention.', $e);
        }
    }

    private function subscribe(SymfonyStyle $io, Status $mention)
    {
        $author = $mention->getAuthor();
        $em = self::getEntityManager();

        $io->write('Subscribing '.$author->getUsername().'...');

        $url = getenv('SITE_URL').'/auteurs/'.$author->getUsername();

        try {
            if (null !== $em->getRepository(Author::class)->findOneByUsername($author->getUsername())) {
                $io->writeln(' Already subscribed, ignoring.');

                try {
                    MastodonUtils::sendStatus("Mais... tu es déjà inscrit•e ! 😳\nTon profil est ici : ".$url.' !', $mention);
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not answer to the new author', $e);
                }

                return;
            }

            $em->persist($author);

            try {
                MastodonUtils::follow($author);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not follow '.$author->getUsername(), $e);
            }

            $io->writeln(' Done!');

            try {
                MastodonUtils::sendStatus("Salut, ton inscription a bien été prise en compte ! 😄\n\nTon profil se trouve juste ici : ".$url."\nIl risque d'être un peu vide dans un premier temps, mais ne t'inquiète pas, je m'occupe de tout ! 😉", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not tell the new author they have been subscribed.', $e);
            }
        } catch (NonUniqueResultException $e) {
            try {
                CommandUtils::writeError($io, 'Error while checking if '.$author->getUsername().' is already subscribed', $e);
                MastodonUtils::sendStatus("Woops, désolé, j'ai bugué !\n\n@".getenv('ADMIN').", À L'AAAAAAIIIIIIIIDE ! 😭", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Error while sending an error message', $e);
            }
        }
    }

    private function updateAuthors(ObjectManager $entityManager, SymfonyStyle $io)
    {
        $authors = $entityManager->getRepository(Author::class)->findAll();

        foreach ($authors as $author) {
            try {
                $auth = MastodonUtils::getAuthor($author->getIdMastodon());

                if ($auth !== $author) {
                    $author->setUsername($auth->getUsername())
                        ->setDisplayName($auth->getDisplayName())
                        ->setAvatar($auth->getAvatar());

                    $entityManager->persist($author);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not search for update for author '.$author->getUsername(), $e);
            }
        }
    }

    private function deleteProfile(SymfonyStyle $io, Status $mention)
    {
        $io->write('Deleting '.$mention->getAuthor()->getDisplayName()."'s profile...");

        $entityManager = self::getEntityManager();

        try {
            $author = $entityManager->getRepository(Author::class)->findOneByUsername($mention->getAuthor()->getUsername());

            if (null == $author) {
                $io->writeln('Not subscribed, ignoring.');
                MastodonUtils::sendStatus("Vous n'avez pas de profil sur le site 🤔", $mention);

                return;
            }

            $entityManager->remove($author);
            $entityManager->flush();
            MastodonUtils::unfollow($author);
            $io->writeln(' Done!');

            MastodonUtils::sendStatus("J'ai bien supprimé ton profil. À bientôt ! 👋", $mention, MastodonUtils::VISIBILITY_DIRECT);
        } catch (NonUniqueResultException $e) {
            CommandUtils::writeError($io, 'Could not delete '.$mention->getAuthor()->getUsername()."'s profile: more than one authors found!'");

            try {
                MastodonUtils::sendStatus("Désolé, une erreur s'est produite, je n'ai pas pu supprimer ton profil 😭\nPssst, @".getenv('ADMIN').", j'ai besoin de ton aide !", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not send a status to alert about the previous error!');
            }
        } catch (\Exception $e) {
            CommandUtils::writeError($io, 'Could not answer the demand!', $e);
        }
    }

    private function changeStatusVisibility(SymfonyStyle $io, Status $mention, string $url, bool $visible)
    {
        $io->write(($visible ? 'Displaying' : 'Hiding')." the status at $url...");
        $em = self::getEntityManager();

        try {
            $status = $em->getRepository(Status::class)->findOneByUrl($url);

            if (null == $status) {
                $io->writeln(' Not in the database, ignoring');

                try {
                    MastodonUtils::sendStatus("Je n'ai pas retrouvé le message\nVérifie que l'URL est correcte et que le pouet associé est bien présent sur ta page de profil (".getenv('SITE_URL').'/'.$mention->getAuthor()->getUsername().')', $mention);
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not send information to '.$mention->getAuthor()->getUsername(), $e);
                }
            } elseif ($status->getAuthor()->getUsername() !== $mention->getAuthor()->getUsername() && $mention->getAuthor()->getUsername() !== getenv('ADMIN')) {
                $io->writeln(' Does not belong to '.$mention->getAuthor()->getUsername().', ignoring');

                try {
                    MastodonUtils::sendStatus("Bien essayé, mais ce pouet ne t'appartient pas 😉", $mention);
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not send information to '.$mention->getAuthor()->getUsername(), $e);
                }
            } elseif ($status->isBlacklisted() == !$visible) {
                $io->writeln(' Already '.($visible ? 'visible' : 'hidden').', ignoring.');

                try {
                    MastodonUtils::sendStatus('Hmmm, le pouet est déjà '.($visible ? 'visible' : 'masqué').' sur le site... 🧐', $mention);
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not send information to '.$mention->getAuthor()->getUsername(), $e);
                }
            } else {
                $status->setBlacklisted(!$visible);
                $em->persist($status);
                $em->flush();

                $io->writeln(' Done!');

                try {
                    if (!$visible) {
                        MastodonUtils::sendStatus("Le pouet a bien été masqué, il ne sera plus visible sur le site.\n".
                            "Note que pour des raisons techniques (notamment pour permettre de le réafficher), je l'ai conservé dans".
                            " ma base de données. Si toutefois, tu souhaites qu'il soit définitivement supprimé, tu peux envoyer un".
                            " message à l'administrateur. Attention, cette action sera irréversible !", $mention);
                    } else {
                        MastodonUtils::sendStatus('La fiction est maintenant visible sur ton profil ! 😎', $mention);
                    }
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not send information to '.$mention->getAuthor()->getUsername(), $e);
                }
            }
        } catch (NonUniqueResultException $e) {
            CommandUtils::writeError($io, 'Could not hide status.', $e);

            try {
                MastodonUtils::sendStatus("Aïe, je n'ai pas réussi à masquer le pouet 😱\n@".getenv('ADMIN').", j'ai besoin de touuuaaaaaaaaa 😭", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not signal problem via Mastodon.', $e);
            }
        }
    }
}
