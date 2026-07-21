<?php

namespace App\Entity;

use App\Repository\JokeLikeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokeLikeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_JOKE', fields: ['user', 'joke'])]
class JokeLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Joke $joke = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $likedAt;

    public function __construct(User $user, Joke $joke)
    {
        $this->user = $user;
        $this->joke = $joke;
        $this->likedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getJoke(): ?Joke
    {
        return $this->joke;
    }

    public function getLikedAt(): \DateTimeImmutable
    {
        return $this->likedAt;
    }
}
