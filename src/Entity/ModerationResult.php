<?php

namespace App\Entity;

use App\Enum\ModerationFlag;
use App\Enum\ModerationRecommendation;
use App\Repository\ModerationResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModerationResultRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MODERATION_RESULT_JOKE', fields: ['joke'])]
class ModerationResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Joke $joke = null;

    #[ORM\Column(length: 20, enumType: ModerationRecommendation::class)]
    private ModerationRecommendation $recommendation;

    #[ORM\Column]
    private float $confidence;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $reasons;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $flags;

    // A joke the submission looks like a duplicate of, found via embedding similarity - not
    // cascaded: if that joke is later deleted, this result just loses the cross-reference
    // rather than being deleted itself.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Joke $duplicateOf = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param string[]        $reasons
     * @param ModerationFlag[] $flags
     */
    public function __construct(
        Joke $joke,
        ModerationRecommendation $recommendation,
        float $confidence,
        array $reasons,
        array $flags,
        ?Joke $duplicateOf = null,
    ) {
        $this->joke = $joke;
        $this->recommendation = $recommendation;
        $this->confidence = max(0.0, min(1.0, $confidence));
        $this->reasons = $reasons;
        $this->flags = array_map(static fn (ModerationFlag $flag) => $flag->value, $flags);
        $this->duplicateOf = $duplicateOf;
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

    public function getRecommendation(): ModerationRecommendation
    {
        return $this->recommendation;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * @return ModerationFlag[]
     */
    public function getFlags(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $flag) => ModerationFlag::tryFrom($flag),
            $this->flags,
        )));
    }

    public function getDuplicateOf(): ?Joke
    {
        return $this->duplicateOf;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
