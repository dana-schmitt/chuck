<?php

namespace App\Services;

use App\Exception\JokeFetchException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JokeFetcher
{
    public function __construct(
        #[Autowire(service: 'chuck_norris_jokes.client')]
        protected HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(?string $category = null): FetchedJoke
    {
        try {
            $response = $this->httpClient->request('GET', '/jokes/random', [
                'query' => $category !== null ? ['category' => $category] : [],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new JokeFetchException(sprintf('Unexpected status code %d from the jokes API.', $response->getStatusCode()));
            }

            $data = $response->toArray();

            return new FetchedJoke($data['value'], $data['categories'] ?? []);
        } catch (JokeFetchException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new JokeFetchException('Could not fetch a joke from the jokes API.', previous: $throwable);
        }
    }
}
