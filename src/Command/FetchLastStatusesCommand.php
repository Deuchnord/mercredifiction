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
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchLastStatusesCommand extends ContainerAwareCommand {
    public const COMMAND_NAME = "app:status:update";
    public const COMMAND_DESCRIPTION = "Calls Mastodon's API to get the latest status with #mercredifiction hashtag";

    protected function configure() {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $mastodonInstance = getenv('MASTODON_INSTANCE');
        $hashtag = getenv('HASHTAG');

        if(!$mastodonInstance || !$hashtag) {
            $output->writeln([
                "You are lacking some configuration variable in your .env file.",
                "Please check you have the following environment variables and try again:",
                "- MASTODON_INSTANCE (the Mastodon instance's URL whose API you will consume)",
                "- HASHTAG (the hashtag you want to read, without hash)"
            ]);

            return;
        }

        $em = $this->getEntityManager();

        $authors = $em->getRepository(Author::class)->findAll();
        $statuses = $em->getRepository(Status::class)->findAll();

        $json = MastodonUtils::getLastStatuses($authors, $endId);

        $data = json_decode($json, true);

        foreach($data as $d) {
            $author = null;
            $status = null;

            try {
                $author = $em->getRepository(Author::class)->findOneByUsername($d['account']['acct']);
                $status = $em->getRepository(Status::class)->findOneByUrl($d['url']);
            } catch (NonUniqueResultException $e) {
            }

            if($status != null || $author == null) {
                continue;
            }

            $output->writeln($d['account']['acct']);

            $content = strip_tags($d['content']);
            $content = str_ireplace('#mercredifiction', '', $content);

            $status = new Status();
            $status->setUrl($d['url'])
                ->setIdMastodon($d['id'])
                ->setAuthor($author)
                ->setBlacklisted(false)
                ->setDate(new \DateTime($d['created_at']))
                ->setContent($content);

            $em->persist($status);
        }

        $em->flush();
    }

    private function getEntityManager(): ObjectManager {
        return $this->getContainer()->get('doctrine')->getManager();
    }

}