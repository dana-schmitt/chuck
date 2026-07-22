<?php

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findByRawToken(string $rawToken): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => self::hash($rawToken)]);
    }

    public function save(ApiToken $apiToken): void
    {
        $this->getEntityManager()->persist($apiToken);
        $this->getEntityManager()->flush();
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
