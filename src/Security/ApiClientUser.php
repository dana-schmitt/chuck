<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents an authenticated API-token client (e.g. the MCP server) - not backed by the
 * User entity, since a token isn't tied to a person. Carries ROLE_API, mapped to ROLE_USER
 * via role_hierarchy so it satisfies the same access_control rules a logged-in browser user
 * already does, without granting anything beyond what the read-only JSON API exposes.
 */
final readonly class ApiClientUser implements UserInterface
{
    public function __construct(
        private int $tokenId,
        private string $label,
    ) {
    }

    public function getTokenId(): int
    {
        return $this->tokenId;
    }

    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'api-token-'.$this->tokenId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
