<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 13/06/2018
 * Time: 20:21
 */

namespace App\Controller;


use App\Entity\Author;
use App\Entity\Status;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller {

    /**
     * @Route("/", name="homepage")
     * @throws \Exception
     */
    function mainAction() {
        $toots = $this->getFictions();
        return $this->render('main/homepage.html.twig', [
            'toots' => $toots
        ]);
    }

    /**
     * Gets the fictions published at last Wednesday.
     * In order to deal with the time zones, "Wednesday" is defined as the interval between Tuesday 12:00:00PM
     * and Thursday 11:59:59AM
     * @param \DateTime|null $beginInterval the interval from which we start the search (by default, last Tuesday at noon)
     * @param bool $andBefore if true and if no fictions have been found at the interval beginning on $beginInterval, look for fictions before
     * @return Status[]
     * @throws \Exception
     */
    public function getFictions(\DateTime $beginInterval = null, bool $andBefore = true): array {
        if($beginInterval == null) {
            $beginInterval = new \DateTime('last tuesday noon');
        }
        dump($beginInterval->format('Y-m-d H:i:s'));
        $endInterval = clone $beginInterval;
        $endInterval->add(new \DateInterval('P2D'))
            ->sub(new \DateInterval('PT1S'));

        $fictions = $this->getDoctrine()->getRepository(Status::class)->findByInterval($beginInterval, $endInterval);

        if($andBefore && empty($fictions)) {
            return $this->getFictions($beginInterval->sub(new \DateInterval('P1W')));
        }

        return $fictions;
    }

    /**
     * @Route("/a-propos", name="about")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    function aboutAction() {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));
        $admin = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername(getenv('ADMIN'));
        
        return $this->render('main/about.html.twig', [
            'bot' => $bot,
            'admin' => $admin
        ]);
    }

    /**
     * @Route("/inscription", name="subscribe")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    function subscriptionAction() {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));
        
        $admin = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername(getenv('ADMIN'));
            
        return $this->render('main/subscribe.html.twig', [
            'bot' => $bot,
            'admin' => $admin
        ]);
    }

    /**
     * @Route("/bot", name="bot-manual")
     */
    function botManualAction() {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));

        $admin = new Author();
        $admin->setUsername(getenv('ADMIN'));
        
        return $this->render('main/bot-manual.html.twig', [
            'bot' => $bot,
            'admin' => $admin
        ]);
    }

}