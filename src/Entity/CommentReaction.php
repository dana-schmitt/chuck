<?php

namespace App\Entity;

use App\Enum\ReactionEmoji;
use App\Repository\CommentReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentReactionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_COMMENT_EMOJI', fields: ['user', 'comment', 'emoji'])]
class CommentReaction
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
    private ?JokeComment $comment = null;

    #[ORM\Column(length: 8, enumType: ReactionEmoji::class)]
    private ?ReactionEmoji $emoji = null;

    public function __construct(User $user, JokeComment $comment, ReactionEmoji $emoji)
    {
        $this->user = $user;
        $this->comment = $comment;
        $this->emoji = $emoji;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getComment(): ?JokeComment
    {
        return $this->comment;
    }

    public function getEmoji(): ?ReactionEmoji
    {
        return $this->emoji;
    }
}
