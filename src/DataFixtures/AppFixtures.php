<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\SharedResource;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setFirstname('John');
        $user->setLastname('Doe');
        $user->setEmail('john.doe@test.com');
        $user->setPassword('test');
        $user->setBirthdate(new \DateTime('1990-01-01'));
        $user->setAvatar(null);
        $user->setBio('Utilisateur de test');
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($user);

        $resource = new SharedResource();
        $resource->setTitle('Doc de test');
        $resource->setDescription('Première ressource de test');
        $resource->setResourceType('document');
        $resource->setPath('/fake/path/doc.pdf');
        $resource->setMimeType('application/pdf');
        $resource->setSize(123456);
        $resource->setIsPublic(false);
        $resource->setMetadata([
            'version' => '1.0',
            'tags' => ['test', 'demo'],
        ]);
        $resource->setCreator($user);
        $resource->setCreatedAt(new \DateTimeImmutable());
        $resource->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($resource);

        $manager->flush();
    }
}
