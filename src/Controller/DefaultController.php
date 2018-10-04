<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 13/06/2018
 * Time: 20:21.
 */

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Status;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /** @var int */
    const GET_FICTIONS_MAX_NB_LOOPS = 100;

    /** @var int */
    private $nbLoops;

    public function __construct()
    {
        $this->nbLoops = 0;
    }

    /**
     * @Route("/", name="homepage")
     *
     * @throws \Exception
     */
    public function mainAction()
    {
        $toots = $this->getFictions();

        return $this->render('main/homepage.html.twig', [
            'toots' => $toots,
        ]);
    }

    /**
     * Gets the fictions published at last Wednesday.
     * In order to deal with the time zones, "Wednesday" is defined as the interval between Tuesday 12:00:00PM
     * and Thursday 11:59:59AM.
     *
     * @param \DateTime|null $beginInterval the interval from which we start the search (by default, last Tuesday at noon)
     * @param bool           $andBefore     if true and if no fictions have been found at the interval beginning on $beginInterval, look for fictions before
     *
     * @return Status[]
     *
     * @throws \Exception
     */
    public function getFictions(\DateTime $beginInterval = null, bool $andBefore = true): array
    {
        if (null == $beginInterval) {
            $beginInterval = new \DateTime('last tuesday noon');
        }
        $endInterval = clone $beginInterval;
        $endInterval->add(new \DateInterval('P2D'))
            ->sub(new \DateInterval('PT1S'));

        $fictions = $this->getDoctrine()->getRepository(Status::class)->findByInterval($beginInterval, $endInterval);

        if ($andBefore && empty($fictions) && $this->nbLoops < self::GET_FICTIONS_MAX_NB_LOOPS) {
            ++$this->nbLoops;

            return $this->getFictions($beginInterval->sub(new \DateInterval('P1W')));
        }

        return $fictions;
    }

    /**
     * @Route("/a-propos", name="about")
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function aboutAction()
    {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));
        $admin = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername(getenv('ADMIN'));

        return $this->render('main/about.html.twig', [
            'bot' => $bot,
            'admin' => $admin,
        ]);
    }

    /**
     * @Route("/inscription", name="subscribe")
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function subscriptionAction()
    {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));

        $admin = $this->getDoctrine()->getRepository(Author::class)->findOneByUsername(getenv('ADMIN'));

        return $this->render('main/subscribe.html.twig', [
            'bot' => $bot,
            'admin' => $admin,
        ]);
    }

    /**
     * @Route("/bot", name="bot-manual")
     */
    public function botManualAction()
    {
        $bot = new Author();
        $bot->setUsername(getenv('BOT_ACCOUNT'));

        $admin = new Author();
        $admin->setUsername(getenv('ADMIN'));

        return $this->render('main/bot-manual.html.twig', [
            'bot' => $bot,
            'admin' => $admin,
        ]);
    }
}
