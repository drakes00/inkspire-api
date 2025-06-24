<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByLogin(string $login) : ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.login = :login')
            ->setParameter('login', $login)
            ->getQuery()
            ->getOneOrNullResult();

    }

    public function findByToken(string $token) : ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAllFilesFromUser(string $login)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery('
            SELECT f.id, f.name, IDENTITY(f.belong_to) AS belong
            FROM  App\Entity\User AS u,  App\Entity\File AS f
            WHERE u.login = f.login
            AND u.login = :login
        ')->setParameter('login', $login);

        return $query->getResult();
    }

    public function getAllDirFromUser(string $login)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery('
            SELECT d.name, IDENTITY(d.belong_to) AS belong, d.id
            FROM  App\Entity\User as u,  App\Entity\Dir as d
            WHERE u.login = d.login
            AND u.login = :login
        ')->setParameter('login', $login);

        return $query->getResult();
    }
}
