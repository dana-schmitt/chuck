<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A read-only bearer token for non-browser API clients (e.g. the MCP server) - entirely
 * separate from user accounts, since a token isn't tied to a person. Only the SHA-256 hash
 * is stored; the raw value is shown once at creation time (app:api-token:create) and never
 * persisted or logged anywhere.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $tokenHash, string $label)
    {
        $this->tokenHash = $tokenHash;
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
