<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 08/08/2018
 * Time: 13:46.
 */

namespace App\Controller;

use App\Entity\Author;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthorController.
 *
 * @Route("/auteurs", name="authors_")
 */
class AuthorController extends Controller
{
    /**
     * @Route("/", name="home")
     */
    public function authorsAction()
    {
        $authors = $this->getDoctrine()->getRepository(Author::class)->findAll();

        return $this->render('main/authors.html.twig', [
            'authors' => $authors,
        ]);
    }

    /**
     * @Route("/{username}", name="profile")
     */
    public function authorAction(string $username)
    {
        $author = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername($username);

        if (null == $author) {
            throw $this->createNotFoundException('Author not found');
        }

        return $this->render('main/author.html.twig', [
            'author' => $author,
        ]);
    }
}
