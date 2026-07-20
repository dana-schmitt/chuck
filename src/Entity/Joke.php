<?php

namespace App\Entity;

use App\Repository\JokeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokeRepository::class)]
class Joke
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $joke = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoke(): ?string
    {
        return $this->joke;
    }

    public function setJoke(string $joke): static
    {
        $this->joke = $joke;

        return $this;
    }
}
