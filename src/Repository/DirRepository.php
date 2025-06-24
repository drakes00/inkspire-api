<?php

namespace App\Repository;

use App\Entity\Dir;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dir>
 */
class DirRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dir::class);
    }

    public function findById(int $id) : ?Dir
    {
        return $this->createQueryBuilder('d')
            ->where('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdIfParent(int $id)
    {
        return $this->createQueryBuilder('d')
            ->where('d.belong_to = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    public function deleteById(int $id)
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
