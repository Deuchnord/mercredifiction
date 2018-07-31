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
     * @return int|null
     */
    public function findMaxIdMastodon(): int {
        return $this->createQueryBuilder('s')
            ->select('s, MAX(s.idMastodon) as maxId')
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
