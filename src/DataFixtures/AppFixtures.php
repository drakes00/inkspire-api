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
        // --- User ---
        $userRepository = $manager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($this->email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $this->password));
            $manager->persist($user);
        }

        // --- Directory ---
        $dirRepository = $manager->getRepository(Dir::class);
        $dir = $dirRepository->findOneBy(['name' => 'Test Dir', 'user' => $user]);

        if (!$dir) {
            $dir = new Dir();
            $dir->setUser($user);
            $dir->setName('Test Dir');
            $dir->setSummary('This is a test directory.');
            $manager->persist($dir);
        }

        // --- Files ---
        $fileRepository = $manager->getRepository(File::class);
        $looseFile = $fileRepository->findOneBy(['path' => '/loose-file.md', 'user' => $user]);

        if (!$looseFile) {
            $looseFile = new File();
            $looseFile->setUser($user);
            $looseFile->setName('Loose File');
            $looseFile->setPath('/loose-file.md');
            $manager->persist($looseFile);
        }

        $fileInDir = $fileRepository->findOneBy(['path' => '/test-dir/file-in-dir.md', 'user' => $user]);

        if (!$fileInDir) {
            $fileInDir = new File();
            $fileInDir->setUser($user);
            $fileInDir->setName('File in Dir');
            $fileInDir->setPath('/test-dir/file-in-dir.md');
            $fileInDir->setDir($dir);
            $manager->persist($fileInDir);
        }

        $manager->flush();
    }
}
