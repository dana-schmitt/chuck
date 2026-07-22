<?php

namespace App\DataFixtures;

use App\Entity\Joke;
use App\Entity\JokeLike;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $chuck = new User();
        $chuck->setEmail('chuck@local.wip');
        $chuck->setDisplayName('Chuck');
        // Pre-hashed "Norris", kept as a literal hash (rather than hashing at fixture-load time)
        // so the documented dev login always works even if the hasher algorithm ever changes.
        $chuck->setPassword('$2y$13$N5tqVh4n5vRIhsW6uosgXO1H/nzv.ZfegAEXXEVZpTHuutLxe9VEq');
        $chuck->setRoles(['ROLE_ADMIN']);
        $chuck->setIsVerified(true);
        $manager->persist($chuck);

        // A few more users so /top (liked by everyone) and /liked (liked by just the logged-in
        // user) actually look different in dev - with a single user those two lists coincide.
        $ada = $this->createUser($manager, 'ada@local.wip', 'Ada');
        $grace = $this->createUser($manager, 'grace@local.wip', 'Grace');

        $jokes = [
            $this->createJoke($manager, 'Chuck Norris can divide by zero.', ['math']),
            $this->createJoke($manager, "Chuck Norris's keyboard doesn't have a Ctrl key because nothing controls Chuck Norris.", ['dev']),
            $this->createJoke($manager, 'Chuck Norris counts to infinity. Twice.', ['math']),
            $this->createJoke($manager, "Death once had a near-Chuck Norris experience.", []),
            $this->createJoke($manager, 'Chuck Norris can compile syntax errors.', ['dev']),
            $this->createJoke($manager, 'Chuck Norris writes code that optimizes itself.', ['dev']),
            $this->createJoke($manager, 'Chuck Norris can win a game of Connect Four in three moves.', ['sport']),
            $this->createJoke($manager, "Fear of spiders is arachnophobia. Fear of Chuck Norris is called common sense.", []),
        ];

        $manager->flush();

        // Distribute likes unevenly: jokes[0] is liked by everyone (most popular overall),
        // jokes[1] by two users, the rest by just one user each - and chuck's own likes are a
        // distinct subset of that, so /top and /liked diverge visibly.
        $likes = [
            [$chuck, $jokes[0]],
            [$ada, $jokes[0]],
            [$grace, $jokes[0]],
            [$ada, $jokes[1]],
            [$grace, $jokes[1]],
            [$chuck, $jokes[2]],
            [$chuck, $jokes[3]],
            [$ada, $jokes[4]],
            [$grace, $jokes[5]],
            [$ada, $jokes[6]],
        ];

        foreach ($likes as [$user, $joke]) {
            $manager->persist(new JokeLike($user, $joke));
        }

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, string $displayName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Norris'));
        $user->setIsVerified(true);
        $manager->persist($user);

        return $user;
    }

    /**
     * @param string[] $categories
     */
    private function createJoke(ObjectManager $manager, string $text, array $categories): Joke
    {
        $joke = (new Joke())->setJoke($text)->setCategories($categories)->setApproved(true);
        $manager->persist($joke);

        return $joke;
    }
}
