<?php

namespace App\Controller;

use App\Entity\Joke;
use App\Entity\User;
use App\Enum\JokeCategory;
use App\Form\JokeSubmissionFormType;
use App\Repository\JokeLikeRepository;
use App\Repository\JokeRepository;
use App\Services\JokeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
    public function joke(Request $request, JokeLikeRepository $jokeLikes): Response
    {
        $category = JokeCategory::tryFrom((string) $request->query->get('category'))?->value;

        $joke = $this->jokeManager->getJoke($category);

        /** @var User|null $user */
        $user = $this->getUser();
        $liked = $joke !== null && $user !== null && $joke->getId() !== null
            && $jokeLikes->isLikedBy($user, $joke);
        $likeCount = $joke !== null && $joke->getId() !== null ? $jokeLikes->countByJoke($joke) : 0;

        return $this->render('joke/index.html.twig', [
            'joke' => $joke,
            'liked' => $liked,
            'likeCount' => $likeCount,
            'category' => $category,
            'availableCategories' => array_map(static fn (JokeCategory $case) => $case->value, JokeCategory::cases()),
        ]);
    }

    #[Route('/joke/{id}', name: 'app_joke_show', requirements: ['id' => '\d+'])]
    public function show(Joke $joke, JokeLikeRepository $jokeLikes): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$joke->isApproved() && $joke->getSubmittedBy() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('This joke is still awaiting review.');
        }

        $liked = $user !== null && $jokeLikes->isLikedBy($user, $joke);

        return $this->render('joke/index.html.twig', [
            'joke' => $joke,
            'liked' => $liked,
            'likeCount' => $jokeLikes->countByJoke($joke),
            'category' => null,
        ]);
    }

    #[Route('/joke/{id}/like', name: 'app_joke_like', methods: ['POST'])]
    public function toggleLike(Joke $joke, Request $request, JokeLikeRepository $jokeLikes, RateLimiterFactory $likeActionLimiter): JsonResponse
    {
        if (!$this->isCsrfTokenValid('like'.$joke->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$likeActionLimiter->create((string) $user->getId())->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Too many requests. Please slow down.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

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

    #[Route('/joke/submit', name: 'app_joke_submit')]
    public function submit(Request $request, JokeRepository $jokeRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $joke = new Joke();
        $form = $this->createForm(JokeSubmissionFormType::class, $joke);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $joke->setSubmittedBy($user);
            $joke->setApproved(false);

            $jokeRepository->addJoke($joke);

            $this->addFlash('success', "Thanks! Your joke has been submitted and is awaiting review.");

            return $this->redirectToRoute('app_joke_submit');
        }

        return $this->render('joke/submit.html.twig', [
            'submissionForm' => $form,
        ]);
    }

    #[Route('/search', name: 'app_joke_search')]
    public function search(Request $request, JokeRepository $jokeRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('joke/search.html.twig', [
            'query' => $query,
            'results' => $query !== '' ? $jokeRepository->search($query) : [],
        ]);
    }
}
