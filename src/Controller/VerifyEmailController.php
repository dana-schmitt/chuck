<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request->getUri(), $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_joke');
        }

        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_joke');
    }

    #[Route('/verify/email/resend', name: 'app_verify_email_resend')]
    public function resend(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified()) {
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user, (new TemplatedEmail())
                ->from(new Address('noreply@chuckify.app', 'Chuckify'))
                ->to((string) $user->getEmail())
                ->subject('Please confirm your email')
                ->htmlTemplate('registration/confirmation_email.html.twig')
            );
            $this->addFlash('success', 'Verification email sent. Please check your inbox.');
        }

        return $this->redirectToRoute('app_joke');
    }
}
