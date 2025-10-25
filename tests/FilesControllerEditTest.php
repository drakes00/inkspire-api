<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Dir;
use App\Entity\File;
use App\Service\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FilesControllerEditTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;
    private FilePathGenerator $filePathGenerator;

    private string $email = 'test@example.com';
    private string $password = 'password';

    private ?string $token = null;

    protected function deauthenticateClient(): void
    {
        $this->client->setServerParameter('HTTP_Authorization', '');
    }

    protected function authenticateClient(): void
    {
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer ' . $this->token);
    }

    protected function createAuthenticatedClient($client, string $email, string $password)
    {
        $client->jsonRequest('POST', '/auth', [
            'username' => $email,
            'password' => $password,
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->token = $data['token'];
        $this->authenticateClient();

        return $client;
    }

    private function createUser(string $email, string $password): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get('security.user_password_hasher');
        $this->filePathGenerator = $container->get(FilePathGenerator::class);

        $user = (new User())->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        return $user;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        // Clean up the database before each test
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\File')->execute();
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\Dir')->execute();
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();

        $user = $this->createUser($this->email, $this->password);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client = $this->createAuthenticatedClient($this->client, $this->email, $this->password);
    }

    public function test_01_fileUpdateName(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Update Test File');
        $originalPath = $this->filePathGenerator->generate('Update Test File');
        $file->setPath($originalPath);
        touch($originalPath);
        $this->assertFileExists($originalPath);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $fileId = $file->getId();

        // Test updating file name
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'name' => 'Updated File Name',
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated File Name', $response['name']);

        // Verify file name was updated in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $updatedFile = $fileRepository->find($fileId);
        $this->assertNotNull($updatedFile);
        $this->assertEquals('Updated File Name', $updatedFile->getName());
        $expectedNewPath = $this->filePathGenerator->generate('Updated File Name');
        $this->assertEquals($expectedNewPath, $updatedFile->getPath());
        $this->assertFileDoesNotExist($originalPath);
        $this->assertFileExists($expectedNewPath);
        $this->assertEquals(preg_match("/^[a-z]+(-[a-z]+)*(-[0-9]+)?\\.ink$/i", basename($updatedFile->getPath())), 1);
        $this->assertNull($updatedFile->getDir());
        $this->assertEquals($this->email, $updatedFile->getUser()->getEmail());
    }

    public function test_02_fileMoveToDirAndBack(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Update Test Dir');
        $this->entityManager->persist($dir);

        $file = new File();
        $file->setUser($user);
        $file->setName('Move Test File');
        $path = $this->filePathGenerator->generate('Move Test File');
        $file->setPath($path);
        touch($path);
        $this->assertFileExists($path);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $fileId = $file->getId();
        $dirId = $dir->getId();

        // Test moving file to a directory
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'dir' => $dirId,
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($dirId, $response['dir']);

        // Verify file was moved to directory in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $movedFile = $fileRepository->find($fileId);
        $this->assertNotNull($movedFile);
        $this->assertEquals('Move Test File', $movedFile->getName());
        $this->assertEquals($path, $movedFile->getPath());
        $this->assertFileExists($path);
        $this->assertNotNull($movedFile->getDir());
        $this->assertEquals($dirId, $movedFile->getDir()->getId());
        $this->assertEquals($this->email, $movedFile->getUser()->getEmail());

        // Test moving file back to root
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'dir' => null,
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($response['dir']);

        // Verify file was moved back to root in database
        $this->entityManager->clear();
        $movedFile = $fileRepository->find($fileId);
        $this->assertNotNull($movedFile);
        $this->assertEquals('Move Test File', $movedFile->getName());
        $this->assertEquals($path, $movedFile->getPath());
        $this->assertFileExists($path);
        $this->assertNull($movedFile->getDir());
        $this->assertEquals($this->email, $movedFile->getUser()->getEmail());
    }

    public function test_03_fileUpdateNameConflict(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Original Name');
        $originalPath = $this->filePathGenerator->generate('Original Name');
        $file->setPath($originalPath);
        touch($originalPath);
        $this->assertFileExists($originalPath);
        $this->entityManager->persist($file);

        $otherFile = new File();
        $otherFile->setUser($user);
        $otherFile->setName('Existing Name');
        $existingPath = $this->filePathGenerator->generate('Existing Name');
        $otherFile->setPath($existingPath);
        touch($existingPath);
        $this->assertFileExists($existingPath);
        $this->entityManager->persist($otherFile);
        $this->entityManager->flush();

        $fileId = $file->getId();

        // Test name conflict
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'name' => 'Existing Name',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        // Verify file name was NOT changed in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $unchangedFile = $fileRepository->find($fileId);
        $this->assertNotNull($unchangedFile);
        $this->assertEquals('Original Name', $unchangedFile->getName());
        $this->assertEquals($originalPath, $unchangedFile->getPath());
        $this->assertFileExists($originalPath);
        $this->assertFileExists($existingPath);
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals($this->email, $unchangedFile->getUser()->getEmail());
    }

    public function test_04_fileMoveToNonExistentDir(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Test File');
        $path = $this->filePathGenerator->generate('Test File');
        $file->setPath($path);
        touch($path);
        $this->assertFileExists($path);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $fileId = $file->getId();

        // Test moving to non-existent directory
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'dir' => 9999,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        // Verify file directory was NOT changed in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $unchangedFile = $fileRepository->find($fileId);
        $this->assertNotNull($unchangedFile);
        $this->assertEquals('Test File', $unchangedFile->getName());
        $this->assertEquals($path, $unchangedFile->getPath());
        $this->assertFileExists($path);
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals($this->email, $unchangedFile->getUser()->getEmail());
    }

    public function test_05_fileUpdateForbidden(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);

        // Create user2 and their file
        $user2 = $this->createUser('user2-for-update@example.com', 'password');
        $this->entityManager->persist($user2);

        $user2File = new File();
        $user2File->setUser($user2);
        $user2File->setName('User2 File');
        $path = $this->filePathGenerator->generate('User2 File');
        $user2File->setPath($path);
        touch($path);
        $this->assertFileExists($path);
        $this->entityManager->persist($user2File);
        $this->entityManager->flush();

        $fileId = $user2File->getId();

        // User1 (already authenticated via $this->client) tries to update user2's file
        $this->client->jsonRequest('PUT', '/api/file/' . $fileId, [
            'name' => 'Trying to steal user2 file',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Verify file was NOT modified in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $unchangedFile = $fileRepository->find($fileId);
        $this->assertNotNull($unchangedFile);
        $this->assertEquals('User2 File', $unchangedFile->getName());
        $this->assertEquals($path, $unchangedFile->getPath());
        $this->assertFileExists($path);
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals('user2-for-update@example.com', $unchangedFile->getUser()->getEmail());
    }

    public function test_06_dirUpdateName(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Original Dir Name');
        $dir->setSummary('Original summary');
        $this->entityManager->persist($dir);
        $this->entityManager->flush();

        $dirId = $dir->getId();

        // Test updating directory name
        $this->client->jsonRequest('PUT', '/api/dir/' . $dirId, [
            'name' => 'Updated Dir Name',
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Dir Name', $response['name']);
        $this->assertEquals('Original summary', $response['summary']);

        // Verify directory name was updated in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $updatedDir = $dirRepository->find($dirId);
        $this->assertNotNull($updatedDir);
        $this->assertEquals('Updated Dir Name', $updatedDir->getName());
        $this->assertEquals('Original summary', $updatedDir->getSummary());
        $this->assertEquals($this->email, $updatedDir->getUser()->getEmail());
    }

    public function test_07_dirUpdateSummary(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Test Dir');
        $dir->setSummary('Original summary');
        $this->entityManager->persist($dir);
        $this->entityManager->flush();

        $dirId = $dir->getId();

        // Test updating directory summary
        $this->client->jsonRequest('PUT', '/api/dir/' . $dirId, [
            'summary' => 'Updated summary',
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Test Dir', $response['name']);
        $this->assertEquals('Updated summary', $response['summary']);

        // Verify directory summary was updated in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $updatedDir = $dirRepository->find($dirId);
        $this->assertNotNull($updatedDir);
        $this->assertEquals('Test Dir', $updatedDir->getName());
        $this->assertEquals('Updated summary', $updatedDir->getSummary());
        $this->assertEquals($this->email, $updatedDir->getUser()->getEmail());
    }

    public function test_08_dirUpdateNameAndSummary(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Original Dir');
        $dir->setSummary('Original summary');
        $this->entityManager->persist($dir);
        $this->entityManager->flush();

        $dirId = $dir->getId();

        // Test updating both name and summary
        $this->client->jsonRequest('PUT', '/api/dir/' . $dirId, [
            'name' => 'Updated Dir',
            'summary' => 'Updated summary',
        ]);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Dir', $response['name']);
        $this->assertEquals('Updated summary', $response['summary']);

        // Verify directory name and summary were updated in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $updatedDir = $dirRepository->find($dirId);
        $this->assertNotNull($updatedDir);
        $this->assertEquals('Updated Dir', $updatedDir->getName());
        $this->assertEquals('Updated summary', $updatedDir->getSummary());
        $this->assertEquals($this->email, $updatedDir->getUser()->getEmail());
    }

    public function test_09_dirUpdateNameConflict(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $dir1 = new Dir();
        $dir1->setUser($user);
        $dir1->setName('Dir One');
        $dir1->setSummary('First dir');
        $this->entityManager->persist($dir1);

        $dir2 = new Dir();
        $dir2->setUser($user);
        $dir2->setName('Existing Dir Name');
        $dir2->setSummary('Second dir');
        $this->entityManager->persist($dir2);
        $this->entityManager->flush();

        // Test name conflict
        $this->client->jsonRequest('PUT', '/api/dir/' . $dir1->getId(), [
            'name' => 'Existing Dir Name',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        // Verify directory was NOT changed in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $unchangedDir = $dirRepository->find($dir1->getId());
        $this->assertNotNull($unchangedDir);
        $this->assertEquals('Dir One', $unchangedDir->getName());
        $this->assertEquals('First dir', $unchangedDir->getSummary());
        $this->assertEquals($this->email, $unchangedDir->getUser()->getEmail());
    }

    public function test_10_dirUpdateForbidden(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);

        // Create user2 and their directory
        $user2 = $this->createUser('user2-dir-update@example.com', 'password');
        $this->entityManager->persist($user2);

        $user2Dir = new Dir();
        $user2Dir->setUser($user2);
        $user2Dir->setName('User2 Directory');
        $user2Dir->setSummary('Belongs to user2');
        $this->entityManager->persist($user2Dir);
        $this->entityManager->flush();

        // User1 (already authenticated via $this->client) tries to update user2's directory
        $this->client->jsonRequest('PUT', '/api/dir/' . $user2Dir->getId(), [
            'name' => 'Trying to steal user2 dir',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Verify directory was NOT modified in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $unchangedDir = $dirRepository->find($user2Dir->getId());
        $this->assertNotNull($unchangedDir);
        $this->assertEquals('User2 Directory', $unchangedDir->getName());
        $this->assertEquals('Belongs to user2', $unchangedDir->getSummary());
        $this->assertEquals('user2-dir-update@example.com', $unchangedDir->getUser()->getEmail());
    }


    protected function tearDown(): void
    {
        $container = static::getContainer();
        $projectRoot = $container->getParameter('kernel.project_dir');
        $filesDir = $projectRoot . '/var/files';
        $files = glob($filesDir . '/*.ink');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
