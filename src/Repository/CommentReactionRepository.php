<?php

namespace App\Repository;

use App\Entity\CommentReaction;
use App\Entity\JokeComment;
use App\Entity\User;
use App\Enum\ReactionEmoji;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReaction>
 */
class CommentReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReaction::class);
    }

    public function findOneByUserCommentAndEmoji(User $user, JokeComment $comment, ReactionEmoji $emoji): ?CommentReaction
    {
        return $this->findOneBy(['user' => $user, 'comment' => $comment, 'emoji' => $emoji]);
    }

    /**
     * Toggles the given user's reaction on a comment and returns the new state (true = added).
     */
    public function toggle(User $user, JokeComment $comment, ReactionEmoji $emoji): bool
    {
        $entityManager = $this->getEntityManager();
        $existing = $this->findOneByUserCommentAndEmoji($user, $comment, $emoji);

        if ($existing !== null) {
            $entityManager->remove($existing);
            $entityManager->flush();

            return false;
        }

        $entityManager->persist(new CommentReaction($user, $comment, $emoji));
        $entityManager->flush();

        return true;
    }

    /**
     * @return array<string, int> reaction counts keyed by emoji value, zero-count emojis omitted
     */
    public function countsByComment(JokeComment $comment): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.emoji AS emoji', 'COUNT(r.id) AS reactionCount')
            ->andWhere('r.comment = :comment')
            ->setParameter('comment', $comment)
            ->groupBy('r.emoji')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[self::emojiValue($row['emoji'])] = (int) $row['reactionCount'];
        }

        return $counts;
    }

    /**
     * @return string[] emoji values the given user has already reacted with on this comment
     */
    public function findReactedEmojisByUser(User $user, JokeComment $comment): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.emoji AS emoji')
            ->andWhere('r.comment = :comment')
            ->andWhere('r.user = :user')
            ->setParameter('comment', $comment)
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row) => self::emojiValue($row['emoji']), $rows);
    }

    /**
     * Doctrine's scalar hydration doesn't consistently convert enum-typed fields to their
     * backed value across versions - normalize here so callers always get the plain string.
     */
    private static function emojiValue(ReactionEmoji|string $emoji): string
    {
        return $emoji instanceof ReactionEmoji ? $emoji->value : $emoji;
    }
}
