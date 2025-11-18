<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findBySearchCriteriaNoYear(?string $query, ?string $genre): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r');

        if ($query) {
            $qb->andWhere('a.title LIKE :query OR a.artist LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($genre) {
            $qb->andWhere('a.genre = :genre')
                ->setParameter('genre', $genre);
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function findBySearchCriteria(?string $query, ?string $genre, ?int $year): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('r')
            ->leftJoin('a.createdBy', 'u')
            ->addSelect('u');

        // Only filter if query is provided
        if ($query && trim($query) !== '') {
            $qb->andWhere('a.title LIKE :query OR a.artist LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        // Only filter if genre is provided
        if ($genre && trim($genre) !== '') {
            $qb->andWhere('a.genre = :genre')
                ->setParameter('genre', $genre);
        }

        // Only filter if year is provided AND greater than 0
        if ($year && $year > 0) {
            $qb->andWhere('a.releaseYear = :year')
                ->setParameter('year', $year);
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllGenres(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.genre')
            ->orderBy('a.genre', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findTopRatedAlbums(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.reviews', 'r')
            ->addSelect('AVG(r.rating) as HIDDEN avgRating')
            ->groupBy('a.id')
            ->having('COUNT(r.id) >= 3')
            ->orderBy('avgRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
