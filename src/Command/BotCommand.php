<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 21/06/2018
 * Time: 13:29
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

class BotCommand extends ContainerAwareCommand {
    public const COMMAND_NAME = "app:bot:run";
    public const COMMAND_DESCRIPTION =  "Reads the mentions on the account configured in the .env file and reacts to the commands received.\n" .
                                        "This command should be run periodically, for instance via a Cron job.";

    protected function configure() {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $em = $this->getEntityManager();

        $this->processCommands($em, $io);
        $this->updateAuthors($em, $io);
    }

    /**
     * @param ObjectManager $entityManager
     * @param SymfonyStyle $io
     */
    private function processCommands(ObjectManager $entityManager, SymfonyStyle $io): void {
        try {
            $lastMentionId = $entityManager->getRepository(Cache::class)->getValue('LAST_MENTION_ID');

            if ($lastMentionId === null) {
                $lastMentionId = new Cache();
                $lastMentionId
                    ->setId('LAST_MENTION_ID')
                    ->setValue(-1);
            }

            $mentions = MastodonUtils::getLastMentions($lastMentionId->getValue());

            foreach ($mentions as $mention) {
                // If in dev mode, ignore the DM from any person who is not the ADMIN defined in the .env
                if (getenv('APP_ENV') == 'dev' &&
                    strtolower($mention->getAuthor()->getUsername()) != strtolower(getenv('ADMIN'))) {
                    break;
                }

                if (stripos($mention->getContent(), '#nocommand')) {
                    break;
                }

                if(preg_match("#d[ée]sinscri[ts][ -]moi#i", $mention->getContent()) ||
                    preg_match("#supprimes? mon compte#i", $mention->getContent())) {
                    /** Delete account command */
                    $this->deleteProfile($io, $mention);
                }
                elseif(preg_match('#inscri[ts][ -]moi#i', $mention->getContent())) {
                    /** Create account command */
                    $this->subscribe($io, $mention);
                } else {
                    $this->sendManual($mention, $io);
                }

                if ($lastMentionId->getValue() < $mention->getIdMastodon()) {
                    $lastMentionId->setValue($mention->getIdMastodon());
                }
            }

            $entityManager->persist($lastMentionId);
            $entityManager->flush();
        } catch (\Exception $e) {
            CommandUtils::writeError($io, "Could not get the last mentions.", $e);
        }
    }

    private function getEntityManager(): ObjectManager {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    private function sendManual(Status $mention, SymfonyStyle $io) {
        try {
            $io->writeln("Sending manual to " . $mention->getAuthor()->getUsername());
            MastodonUtils::sendStatus("Désolé, mes capacités de réponse sont limitées. Tu dois me poser la bonne question. 😉\n\nTu trouveras toutes les infos sur mes commandes ici : " . getenv('SITE_URL') . "/bot 😉", $mention);
        } catch(\Exception $e) {
            CommandUtils::writeError($io, "Could not answer to a mention.", $e);
        }
    }

    private function subscribe(SymfonyStyle $io, Status $mention) {
        $author = $mention->getAuthor();
        $em = self::getEntityManager();

        $io->write("Subscribing " . $author->getUsername() . "...");

        $url = getenv('SITE_URL') . '/auteurs/' . $author->getUsername();

        try {
            if ($em->getRepository(Author::class)->findOneByUsername($author->getUsername()) !== null) {
                $io->writeln(" Already subscribed, ignoring.");
                try {
                    MastodonUtils::sendStatus( "Mais... tu es déjà inscrit•e ! 😳\nTon profil est ici : " . $url . " !", $mention);
                } catch(\Exception $e) {
                    CommandUtils::writeError($io, "Could not answer to the new author", $e);
                }
                return;
            }

            $em->persist($author);
            $io->writeln(" Done!");

            try {
                MastodonUtils::sendStatus( "Salut, ton inscription a bien été prise en compte ! 😄\n\nTon profil se trouve juste ici : " . $url . "\nIl risque d'être un peu vide dans un premier temps, mais ne t'inquiète pas, je m'occupe de tout ! 😉", $mention);
            } catch(\Exception $e) {
                CommandUtils::writeError($io, "Could not tell the new author they have been subscribed.", $e);
            }
        } catch (NonUniqueResultException $e) {
            try {
                CommandUtils::writeError($io, "Error while checking if " . $author->getUsername() . " is already subscribed", $e);
                MastodonUtils::sendStatus( "Woops, désolé, j'ai bugué !\n\n@" . getenv('ADMIN') . ", À L'AAAAAAIIIIIIIIDE ! 😭", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, "Error while sending an error message", $e);
            }
        }
    }

    private function updateAuthors(ObjectManager $entityManager, SymfonyStyle $io) {
        $authors = $entityManager->getRepository(Author::class)->findAll();

        foreach ($authors as $author) {
            try {
                $auth = MastodonUtils::getAuthor($author->getIdMastodon());
                if($auth !== $author) {
                    $author->setUsername($auth->getUsername())
                        ->setDisplayName($auth->getDisplayName())
                        ->setAvatar($auth->getAvatar());

                    $entityManager->persist($author);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                CommandUtils::writeError($io, "Could not search for update for author " . $author->getUsername(), $e);
            }

        }

    }

    private function deleteProfile(SymfonyStyle $io, Status $mention) {
        $io->write("Deleting " . $mention->getAuthor()->getDisplayName() . "'s profile...");

        $entityManager = self::getEntityManager();

        try {
            $author = $entityManager->getRepository(Author::class)->findOneByUsername($mention->getAuthor()->getUsername());

            if($author == null) {
                $io->writeln("Not subscribed, ignoring.");
                MastodonUtils::sendStatus("Vous n'avez pas de profil sur le site 🤔", $mention);
                return;
            }

            $entityManager->remove($author);
            $entityManager->flush();
            $io->writeln(" Done!");

            // TODO: add Adibou's GIF, with a focus on 300,130
            MastodonUtils::sendStatus("J'ai bien supprimé ton profil. À bientôt ! 👋", $mention, MastodonUtils::VISIBILITY_DIRECT);
        } catch (NonUniqueResultException $e) {
            CommandUtils::writeError($io, "Could not delete " . $mention->getAuthor()->getUsername() . "'s profile: more than one authors found!'");
            try {
                MastodonUtils::sendStatus("Désolé, une erreur s'est produite, je n'ai pas pu supprimer ton profil 😭\nPssst, @" . getenv('ADMIN') . ", j'ai besoin de ton aide !", $mention);
            } catch (\Exception $e) {
                CommandUtils::writeError($io, "Could not send a status to alert about the previous error!");
            }
        } catch (\Exception $e) {
            CommandUtils::writeError($io, "Could not answer the demand!", $e);
        }
    }

}