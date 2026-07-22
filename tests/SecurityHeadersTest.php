<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityHeadersTest extends WebTestCase
{
    public function testHomepageSendsSecurityHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertNotNull($response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testImportmapScriptNoncesMatchTheCspHeader(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        preg_match_all("/'nonce-([^']+)'/", $csp, $matches);
        $headerNonces = $matches[1];

        self::assertNotEmpty($headerNonces, 'The CSP header should list at least one script nonce.');

        $scriptNonces = $crawler->filter('script[nonce]')->each(
            static fn ($node) => $node->attr('nonce'),
        );

        self::assertNotEmpty($scriptNonces, 'The page should render at least one nonce\'d script tag.');
        foreach ($scriptNonces as $nonce) {
            self::assertContains($nonce, $headerNonces, 'Every script nonce must be listed in the CSP header.');
        }
    }
}
