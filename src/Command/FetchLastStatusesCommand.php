<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 13/06/2018
 * Time: 20:44
 */

namespace App\Command;


use App\Entity\Author;
use App\Entity\Status;
use App\Utils\MastodonUtils;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\PhpUnit\TextUI\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchLastStatusesCommand extends ContainerAwareCommand {
    public const COMMAND_NAME = "app:status:update";
    public const COMMAND_DESCRIPTION = "Calls Mastodon's API to get the latest status with #mercredifiction hashtag";

    protected function configure() {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $em = $this->getEntityManager();

        try {
            $lastStatus = $em->getRepository(Status::class)->findLastStatus();
            $authors = $em->getRepository(Author::class)->findAll();

            if($lastStatus == null) {
                $statuses = MastodonUtils::getLastStatuses($authors);
            } else {
                $statuses = MastodonUtils::getLastStatuses($authors, $lastStatus->getIdMastodon());
            }

            $io->writeln("Found " . count($statuses) . " new fictions");

            $n = 0;

            foreach($statuses as $status) {
                $io->writeln($status->getAuthor()->getUsername() . ': ' . $status->getContent() . ' (' . $status->getUrl() . ')');

                $author = $em->getRepository(Author::class)->findOneByUsername($status->getAuthor()->getUsername());

                if($author != null) {
                    $status->setAuthor($author);
                }

                $em->persist($status);
                $n++;

                if($n % 50 == 0) {
                    // Flush every 50 statuses
                    $em->flush();
                }
            }

            if($n % 50 != 0) {
                // Last flush
                $em->flush();
            }

            $io->success("New fictions saved!");
        } catch (NonUniqueResultException $e) {
            CommandUtils::writeError($io, "Error while getting the lastId!", $e);
            return;
        } catch (\Exception $e) {
            CommandUtils::writeError($io, "Could not get the last statuses!", $e);
            return;
        }

    }

    private function getEntityManager(): ObjectManager {
        return $this->getContainer()->get('doctrine')->getManager();
    }

}