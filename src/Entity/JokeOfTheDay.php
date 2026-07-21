<?php

namespace App\Entity;

use App\Repository\JokeOfTheDayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokeOfTheDayRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_JOKE_OF_THE_DAY_DATE', fields: ['date'])]
class JokeOfTheDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Joke $joke = null;

    public function __construct(\DateTimeImmutable $date, Joke $joke)
    {
        $this->date = $date;
        $this->joke = $joke;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getJoke(): Joke
    {
        return $this->joke;
    }
}
