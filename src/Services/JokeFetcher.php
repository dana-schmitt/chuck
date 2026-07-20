<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JokeFetcher
{
    public function __construct(
        #[Autowire(service: 'chuck_norris_jokes.client')]
        protected HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(): string
    {
        try {
            $response = $this->httpClient->request('GET', '/jokes/random');
            if ($response->getStatusCode() === 200) {
                return $response->toArray()['value'];
            }
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('FOOBAR', previous: $throwable);
        }
    }
}
