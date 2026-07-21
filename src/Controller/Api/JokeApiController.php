<?php

namespace App\Controller\Api;

use App\Entity\Joke;
use App\Repository\JokeLikeRepository;
use App\Repository\JokeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jokes')]
class JokeApiController extends AbstractController
{
    #[Route('', name: 'app_api_jokes_index', methods: ['GET'])]
    public function index(Request $request, JokeRepository $jokeRepository, JokeLikeRepository $jokeLikes): JsonResponse
    {
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));
        $category = $request->query->get('category');

        $jokes = $jokeRepository->findApproved($category, $limit);

        return $this->json(array_map(fn (Joke $joke) => $this->serialize($joke, $jokeLikes), $jokes));
    }

    #[Route('/random', name: 'app_api_jokes_random', methods: ['GET'])]
    public function random(JokeRepository $jokeRepository, JokeLikeRepository $jokeLikes): JsonResponse
    {
        $joke = $jokeRepository->findRandom();
        if ($joke === null) {
            return $this->json(['error' => 'No jokes available yet.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($joke, $jokeLikes));
    }

    #[Route('/top', name: 'app_api_jokes_top', methods: ['GET'])]
    public function top(Request $request, JokeRepository $jokeRepository): JsonResponse
    {
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));

        $entries = array_map(
            static fn (array $entry) => [
                'id' => $entry['joke']->getId(),
                'joke' => $entry['joke']->getJoke(),
                'categories' => $entry['joke']->getCategories(),
                'likeCount' => $entry['likeCount'],
            ],
            $jokeRepository->findTopLiked($limit),
        );

        return $this->json($entries);
    }

    #[Route('/{id}', name: 'app_api_jokes_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Joke $joke, JokeLikeRepository $jokeLikes): JsonResponse
    {
        if (!$joke->isApproved()) {
            return $this->json(['error' => 'Joke not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($joke, $jokeLikes));
    }

    /**
     * @return array{id: int|null, joke: string|null, categories: string[], likeCount: int, submittedBy: string|null}
     */
    private function serialize(Joke $joke, JokeLikeRepository $jokeLikes): array
    {
        return [
            'id' => $joke->getId(),
            'joke' => $joke->getJoke(),
            'categories' => $joke->getCategories(),
            'likeCount' => $jokeLikes->countByJoke($joke),
            'submittedBy' => $joke->getSubmittedBy()?->getDisplayName(),
        ];
    }
}
