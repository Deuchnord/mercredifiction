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
     * @Route("/auteurs/", name="authors")
     */
    function authorsAction() {
        $authors = $this->getDoctrine()->getRepository(Author::class)->findAll();
        return $this->render('main/authors.html.twig', [
            'authors' => $authors
        ]);
    }

    /**
     * @Route("/auteurs/{username}", name="author-profile")
     */
    function authorAction(string $username) {
        $author = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername($username);

        if($author == null) {
            throw $this->createNotFoundException("Author not found");
        }

        return $this->render('main/author.html.twig', [
            'author' => $author
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
        
        return $this->render('main/bot-manual.html.twig', [
            'bot' => $bot
        ]);
    }

}