<?php

namespace App\Services;

use App\Exception\JokeFetchException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JokeFetcher
{
    /**
     * Short-lived on purpose: this only exists to absorb bursts of concurrent requests
     * (e.g. a traffic spike) hitting the external API 1:1, not to make "Chuck me" less random.
     */
    private const CACHE_TTL_SECONDS = 2;

    public function __construct(
        #[Autowire(service: 'chuck_norris_jokes.client')]
        protected HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.joke_fetch')]
        private readonly CacheInterface $cache,
    ) {
    }

    public function fetch(?string $category = null): FetchedJoke
    {
        try {
            return $this->cache->get(
                'joke_fetch.'.($category ?? 'any'),
                function (ItemInterface $item) use ($category) {
                    $item->expiresAfter(self::CACHE_TTL_SECONDS);

                    return $this->fetchFromApi($category);
                },
            );
        } catch (JokeFetchException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new JokeFetchException('Could not fetch a joke from the jokes API.', previous: $throwable);
        }
    }

    private function fetchFromApi(?string $category): FetchedJoke
    {
        $response = $this->httpClient->request('GET', '/jokes/random', [
            'query' => $category !== null ? ['category' => $category] : [],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new JokeFetchException(sprintf('Unexpected status code %d from the jokes API.', $response->getStatusCode()));
        }

        $data = $response->toArray();

        return new FetchedJoke($data['value'], $data['categories'] ?? []);
    }
}
