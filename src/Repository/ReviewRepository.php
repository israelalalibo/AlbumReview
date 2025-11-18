<?php


namespace App\Repository;

use App\Entity\Review;
use App\Entity\Album;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findUserReviewForAlbum(User $user, Album $album): ?Review
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.album = :album')
            ->setParameter('user', $user)
            ->setParameter('album', $album)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestReviews(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.album', 'a')
            ->join('r.user', 'u')
            ->addSelect('a', 'u')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
