<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates non-browser API clients (e.g. the MCP server) via a static bearer token,
 * entirely separate from the session-based browser login handled by LoginFormAuthenticator on
 * the same firewall. Only activates for requests under /api carrying an "Authorization: Bearer
 * ..." header, so it never interferes with normal cookie-based access to /api from a logged-in
 * browser session (which keeps working exactly as before, since this authenticator simply
 * doesn't support that request).
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api')
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $rawToken = substr((string) $request->headers->get('Authorization'), \strlen('Bearer '));

        $apiToken = $this->apiTokenRepository->findByRawToken($rawToken);
        if ($apiToken === null) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        $tokenId = $apiToken->getId();
        $label = $apiToken->getLabel();

        return new SelfValidatingPassport(new UserBadge(
            'api-token-'.$tokenId,
            static fn () => new ApiClientUser($tokenId, $label),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Invalid or missing API token.'], Response::HTTP_UNAUTHORIZED);
    }
}
