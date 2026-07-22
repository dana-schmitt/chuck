<?php

namespace App\Entity;

use App\Repository\JokeEmbeddingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A separate table (rather than a JSON column on Joke) so the vector - the largest, least
 * frequently accessed piece of data about a joke - never has to be loaded/hydrated just because
 * a Joke entity is, and so a future re-embed (different model) has somewhere to record which
 * model produced the stored vector without overloading Joke's own schema.
 */
#[ORM\Entity(repositoryClass: JokeEmbeddingRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_JOKE_EMBEDDING_JOKE', fields: ['joke'])]
class JokeEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Joke $joke = null;

    #[ORM\Column(length: 100)]
    private string $model;

    /**
     * @var float[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $vector;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param float[] $vector
     */
    public function __construct(Joke $joke, string $model, array $vector)
    {
        $this->joke = $joke;
        $this->model = $model;
        $this->vector = $vector;
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

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return float[]
     */
    public function getVector(): array
    {
        return $this->vector;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
