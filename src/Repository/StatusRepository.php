<?php

namespace App\Repository;

use App\Entity\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Status|null find($id, $lockMode = null, $lockVersion = null)
 * @method Status|null findOneBy(array $criteria, array $orderBy = null)
 * @method Status[]    findAll()
 * @method Status[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Status::class);
    }

    /**
     * @return Status[]
     */
    public function findAllNotBlacklisted() {
        return $this->createQueryBuilder('s')
            ->andWhere('s.blacklisted = false')
            ->orderBy('s.date', 'desc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $url
     * @return Status|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByUrl($url): ?Status
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.url = :url')
            ->setParameter('url', $url)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Status|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLastStatus(): ?Status {
        $qb = $this->createQueryBuilder('s');
        return $qb->select('s')
            ->where('s.idMastodon = (SELECT MAX(s2.idMastodon) FROM App\Entity\Status s2)')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param \DateTime $beginInterval
     * @param \DateTime $endInterval
     * @return Status[]
     */
    public function findByInterval(\DateTime $beginInterval, \DateTime $endInterval, bool $getBlacklisted = false): array {
        return $this->createQueryBuilder('s')
            ->where('s.date BETWEEN :begin AND :end AND s.blacklisted = :blacklisted')
            ->setParameter('begin', $beginInterval->format('Y-m-d H:i:s'))
            ->setParameter('end', $endInterval->format('Y-m-d H:i:s'))
            ->setParameter('blacklisted', $getBlacklisted)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Status[] Returns an array of Status objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Status
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
