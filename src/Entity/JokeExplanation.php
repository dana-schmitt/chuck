<?php

namespace App\Entity;

use App\Repository\JokeExplanationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokeExplanationRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_JOKE_EXPLANATION_JOKE_LOCALE', fields: ['joke', 'locale'])]
class JokeExplanation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Joke $joke = null;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column(type: Types::TEXT)]
    private string $explanation;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Joke $joke, string $locale, string $explanation)
    {
        $this->joke = $joke;
        $this->locale = $locale;
        $this->explanation = $explanation;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoke(): Joke
    {
        return $this->joke;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
