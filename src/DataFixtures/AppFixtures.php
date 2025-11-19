<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        // ADMIN
        $admin = new User();
        $admin->setFirstname('Admin');
        $admin->setLastname('User');
        $admin->setEmail('admin@test.com');
        $admin->setPassword(
        $this->hasher->hashPassword($admin, 'admin123')
    );

        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setBirthdate(new \DateTimeImmutable('1990-01-01'));
        $admin->setCreatedAt($now);
        $admin->setUpdatedAt($now);

        $manager->persist($admin);

        // USER
        $user = new User();
        $user->setFirstname('Regular');
        $user->setLastname('User');
        $user->setEmail('user@test.com');
        $user->setPassword(
            $this->hasher->hashPassword($user, 'user123')
        );
        $user->setRoles(['ROLE_USER']);
        $user->setBirthdate(new \DateTime('1995-01-01'));

        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);

        $manager->persist($user);
        $manager->flush();
    }
}
