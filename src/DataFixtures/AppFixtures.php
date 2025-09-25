<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Entity\File;
use App\Entity\Dir;

class AppFixtures extends Fixture
{
    private string $email = 'admin@admin.com';
    private string $password = 'aaaaaa';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create a user.
        $user = new User();
        $user->setEmail($this->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $this->password));

        // Create a loose file.
        $looseFile = new File();
        $looseFile->setUser($user);
        $looseFile->setName('Loose File');
        $looseFile->setPath('/loose-file.md');

        // Create a directory.
        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Test Dir');
        $dir->setSummary('This is a test directory.');

        // Create a file inside the directory.
        $fileInDir = new File();
        $fileInDir->setUser($user);
        $fileInDir->setName('File in Dir');
        $fileInDir->setPath('/test-dir/file-in-dir.md');
        $fileInDir->setDir($dir);

        $manager->persist($user);
        $manager->persist($looseFile);
        $manager->persist($dir);
        $manager->persist($fileInDir);
        $manager->flush();
    }
}
