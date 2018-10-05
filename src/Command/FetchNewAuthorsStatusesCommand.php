<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 02/07/2018
 * Time: 13:13.
 */

namespace App\Command;

use App\Entity\Author;
use App\Entity\Status;
use App\Utils\MastodonUtils;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchNewAuthorsStatusesCommand extends ContainerAwareCommand
{
    public const COMMAND_NAME = 'app:authors:new:get-statuses';
    public const COMMAND_DESCRIPTION = "Fetches the new authors' statuses";

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    private function getEntityManager(): ObjectManager
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $em = $this->getEntityManager();

        $authors = $em->getRepository(Author::class)->findByState(Author::STATE_NEW);

        foreach ($authors as $author) {
            $io->write('Fetching '.$author->getUsername()."'s statuses... ");
            $author->setState(Author::STATE_IMPORTING_STATUSES);
            $em->persist($author);
            $em->flush();

            try {
                $lastStatus = $em->getRepository(Status::class)->findLastStatus($author);

                $statuses = MastodonUtils::getAuthorStatuses($author, $lastStatus);
                $io->write(count($statuses).' statuses to save... ');

                $i = 1;

                foreach ($statuses as $status) {
                    $status->setAuthor($author);
                    $em->persist($status);

                    if (0 == $i % 10) {
                        // Flush the statuses every 10 statuses persisted
                        $em->flush();
                    }

                    ++$i;
                }

                $io->writeln('Done');

                $author->setState(Author::STATE_OK);
                $em->persist($author);
                $em->flush();
            } catch (\Exception $e) {
                CommandUtils::writeError($io, 'Could not get '.$author->getUsername()."'s statuses'", $e);

                $io->write("Reverting the author's state... ");
                $author->setState(Author::STATE_NEW);
                $em->persist($author);

                $em->flush();

                $io->writeln('Done.');

                $io->writeln('A new attempt will be made at next execution for '.$author->getUsername().'.');

                try {
                    MastodonUtils::sendStatus('@'.getenv('ADMIN')." An error occurred while fetching a new author's statuses");
                } catch (\Exception $e) {
                    CommandUtils::writeError($io, 'Could not send error message to '.getenv('ADMIN'), $e);
                }
            }
        }
    }
}
