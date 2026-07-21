<?php

namespace App\Controller;

use App\Entity\Joke;
use App\Entity\User;
use App\Repository\JokeLikeRepository;
use App\Repository\JokeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(UserRepository $users, JokeRepository $jokes, JokeLikeRepository $jokeLikes): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'userCount' => \count($users->findAll()),
            'jokeCount' => \count($jokes->findAll()),
            'likeCount' => \count($jokeLikes->findAll()),
            'pendingCount' => \count($jokes->findPendingSubmissions()),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/users/{id}/toggle-admin', name: 'app_admin_user_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('toggle-admin'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('admin_error', 'You cannot change your own admin status.');

            return $this->redirectToRoute('app_admin_users');
        }

        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            $roles = array_values(array_diff($roles, ['ROLE_ADMIN']));
        } else {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Updated admin status for %s.', (string) $user->getEmail()));

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/jokes', name: 'app_admin_jokes')]
    public function jokes(JokeRepository $jokes, JokeLikeRepository $jokeLikes): Response
    {
        $allJokes = $jokes->findBy([], ['id' => 'DESC']);

        $likeCounts = [];
        foreach ($allJokes as $joke) {
            $likeCounts[$joke->getId()] = $jokeLikes->countByJoke($joke);
        }

        return $this->render('admin/jokes.html.twig', [
            'jokes' => $allJokes,
            'likeCounts' => $likeCounts,
        ]);
    }

    #[Route('/jokes/{id}/delete', name: 'app_admin_joke_delete', methods: ['POST'])]
    public function deleteJoke(Joke $joke, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-joke'.$joke->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($joke);
        $entityManager->flush();

        $this->addFlash('success', 'Joke deleted.');

        return $this->redirectToRoute('app_admin_jokes');
    }

    #[Route('/jokes/{id}/approve', name: 'app_admin_joke_approve', methods: ['POST'])]
    public function approveJoke(Joke $joke, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('approve-joke'.$joke->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $joke->setApproved(true);
        $entityManager->flush();

        $this->addFlash('success', 'Joke approved.');

        return $this->redirectToRoute('app_admin_jokes');
    }
}
