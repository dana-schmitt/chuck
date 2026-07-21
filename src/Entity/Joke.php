<?php

namespace App\Entity;

use App\Repository\JokeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokeRepository::class)]
#[ORM\Index(name: 'joke_fulltext', columns: ['joke'], flags: ['fulltext'])]
class Joke
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $joke = null;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $submittedBy = null;

    /**
     * Jokes fetched from the external API are auto-approved; user-submitted jokes
     * default to false until an admin reviews them (see AdminController).
     */
    #[ORM\Column]
    private bool $approved = true;

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

    /**
     * @return string[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param string[] $categories
     */
    public function setCategories(array $categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }
}
