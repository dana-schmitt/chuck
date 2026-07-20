<?php

namespace App\Controller;

use App\Services\JokeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class JokeController extends AbstractController
{
    public function __construct(
        protected JokeManager $jokeManager
    ) {
    }

    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        return $this->render('joke/homepage.html.twig');
    }

    #[Route('/joke', name: 'app_joke')]
    public function joke(): Response
    {
        return $this->render('joke/index.html.twig', [
            'joke' => $this->jokeManager->getJoke(),
        ]);
    }
}
