<?php

namespace App\Controller;

use App\Entity\Joke;
use App\Entity\User;
use App\Repository\JokeLikeRepository;
use App\Repository\JokeRepository;
use App\Services\JokeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JokeController extends AbstractController
{
    public function __construct(
        protected JokeManager $jokeManager,
    ) {
    }

    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        return $this->render('joke/homepage.html.twig');
    }

    #[Route('/joke', name: 'app_joke')]
    public function joke(JokeLikeRepository $jokeLikes): Response
    {
        $joke = $this->jokeManager->getJoke();

        /** @var User|null $user */
        $user = $this->getUser();
        $liked = $joke !== null && $user !== null && $joke->getId() !== null
            && $jokeLikes->isLikedBy($user, $joke);
        $likeCount = $joke !== null && $joke->getId() !== null ? $jokeLikes->countByJoke($joke) : 0;

        return $this->render('joke/index.html.twig', [
            'joke' => $joke,
            'liked' => $liked,
            'likeCount' => $likeCount,
        ]);
    }

    #[Route('/joke/{id}/like', name: 'app_joke_like', methods: ['POST'])]
    public function toggleLike(Joke $joke, Request $request, JokeLikeRepository $jokeLikes): JsonResponse
    {
        if (!$this->isCsrfTokenValid('like'.$joke->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $liked = $jokeLikes->toggle($user, $joke);

        return $this->json(['liked' => $liked, 'likeCount' => $jokeLikes->countByJoke($joke)]);
    }

    #[Route('/liked', name: 'app_liked')]
    public function liked(JokeLikeRepository $jokeLikes): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('joke/liked.html.twig', [
            'likes' => $jokeLikes->findRecentByUser($user),
        ]);
    }

    #[Route('/top', name: 'app_top_jokes')]
    public function topJokes(JokeRepository $jokeRepository): Response
    {
        return $this->render('joke/top.html.twig', [
            'topJokes' => $jokeRepository->findTopLiked(),
        ]);
    }
}
