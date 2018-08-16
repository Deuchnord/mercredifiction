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
     */
    function mainAction() {
        $toots = $this->getDoctrine()->getRepository(Status::class)->findAllNotBlacklisted();
        return $this->render('main/homepage.html.twig', [
            'toots' => $toots
        ]);
    }

    /**
     * @Route("/a-propos", name="about")
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