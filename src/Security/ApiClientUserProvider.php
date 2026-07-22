<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Lets the security component store/refresh an ApiClientUser in the session, the same way it
 * does for the User entity - needed even though ApiTokenAuthenticator loads the user itself via
 * a self-contained UserBadge closure, because Symfony's ContextListener still requires some
 * registered provider to supportsClass(ApiClientUser) in order to persist the authenticated
 * token at the end of the request. refreshUser() re-checks the database, so a token deleted
 * mid-session stops working immediately rather than staying valid until the session expires.
 */
class ApiClientUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiClientUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        $apiToken = $this->apiTokenRepository->find($user->getTokenId());
        if ($apiToken === null) {
            throw new UserNotFoundException('API token no longer exists.');
        }

        return new ApiClientUser($apiToken->getId(), $apiToken->getLabel());
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiClientUser::class || is_subclass_of($class, ApiClientUser::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('ApiClientUser can only be loaded via ApiTokenAuthenticator.');
    }
}
