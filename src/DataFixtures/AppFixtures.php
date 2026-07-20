<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('chuck@local.wip');
        $user->setPassword('$2y$13$N5tqVh4n5vRIhsW6uosgXO1H/nzv.ZfegAEXXEVZpTHuutLxe9VEq');

        $manager->persist($user);
        $manager->flush();
    }
}
