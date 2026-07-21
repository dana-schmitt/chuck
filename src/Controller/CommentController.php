<?php

namespace App\Controller;

use App\Entity\Joke;
use App\Entity\JokeComment;
use App\Entity\User;
use App\Enum\ReactionEmoji;
use App\Form\CommentFormType;
use App\Repository\CommentReactionRepository;
use App\Repository\JokeCommentRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractController
{
    #[Route('/joke/{id}/comments', name: 'app_comment_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(
        Joke $joke,
        Request $request,
        JokeCommentRepository $comments,
        RateLimiterFactory $commentActionLimiter,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(CommentFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('comment_error', 'Please write a comment before submitting.');

            return $this->redirectToRoute('app_joke_show', ['id' => $joke->getId()]);
        }

        if (!$commentActionLimiter->create((string) $user->getId())->consume(1)->isAccepted()) {
            $this->addFlash('comment_error', 'Too many comments. Please slow down.');

            return $this->redirectToRoute('app_joke_show', ['id' => $joke->getId()]);
        }

        $comments->add(new JokeComment($joke, $user, (string) $form->get('body')->getData()));

        $this->addFlash('success', 'Comment added.');

        return $this->redirectToRoute('app_joke_show', ['id' => $joke->getId()]);
    }

    #[Route('/joke/{jokeId}/comments/{id}/delete', name: 'app_comment_delete', requirements: ['jokeId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['jokeId' => 'id'])]
        Joke $joke,
        JokeComment $comment,
        Request $request,
        JokeCommentRepository $comments,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($comment->getJoke() !== $joke) {
            throw $this->createNotFoundException('Comment not found on this joke.');
        }

        if ($comment->getAuthor() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You cannot delete this comment.');
        }

        if (!$this->isCsrfTokenValid('delete-comment'.$comment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $comments->remove($comment);

        $this->addFlash('success', 'Comment deleted.');

        return $this->redirectToRoute('app_joke_show', ['id' => $joke->getId()]);
    }

    #[Route('/joke/{jokeId}/comments/{id}/react', name: 'app_comment_react', requirements: ['jokeId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function react(
        #[MapEntity(mapping: ['jokeId' => 'id'])]
        Joke $joke,
        JokeComment $comment,
        Request $request,
        CommentReactionRepository $reactions,
        RateLimiterFactory $reactionActionLimiter,
    ): JsonResponse {
        if ($comment->getJoke() !== $joke) {
            return $this->json(['error' => 'Comment not found on this joke.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('react-comment'.$comment->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $emoji = ReactionEmoji::tryFrom((string) $request->request->get('emoji'));
        if ($emoji === null) {
            return $this->json(['error' => 'Unknown reaction.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$reactionActionLimiter->create((string) $user->getId())->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Too many requests. Please slow down.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $reacted = $reactions->toggle($user, $comment, $emoji);

        return $this->json([
            'reacted' => $reacted,
            'counts' => $reactions->countsByComment($comment),
        ]);
    }
}
