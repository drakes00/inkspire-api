<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Entity\File;
use App\Entity\Dir;
use App\Service\FilePathGenerator;

class AppFixtures extends Fixture
{
    private string $email = 'admin@admin.com';
    private string $password = 'aaaaaa';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private FilePathGenerator $filePathGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Ensure the base directory exists
        $baseDir = dirname($this->filePathGenerator->generate('temp'));
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

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
        $looseFileName = 'Loose File';
        $looseFilePath = $this->filePathGenerator->generate($looseFileName);
        $looseFile = $fileRepository->findOneBy(['path' => $looseFilePath, 'user' => $user]);

        if (!$looseFile) {
            $looseFile = new File();
            $looseFile->setUser($user);
            $looseFile->setName($looseFileName);
            $looseFile->setPath($looseFilePath);
            file_put_contents($looseFilePath, '# Loose File Content');
            $manager->persist($looseFile);
        }

        $fileInDirName = 'File in Dir';
        $fileInDirPath = $this->filePathGenerator->generate($fileInDirName);
        $fileInDir = $fileRepository->findOneBy(['path' => $fileInDirPath, 'user' => $user]);

        if (!$fileInDir) {
            $fileInDir = new File();
            $fileInDir->setUser($user);
            $fileInDir->setName($fileInDirName);
            $fileInDir->setPath($fileInDirPath);
            $fileInDir->setDir($dir);
            file_put_contents($fileInDirPath, '# File in Dir Content');
            $manager->persist($fileInDir);
        }

        $manager->flush();
    }
}
