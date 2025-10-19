<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Dir;
use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FilesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;

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

    public function test_01_treeEndpoint(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        // Create a loose file
        $looseFile = new File();
        $looseFile->setUser($user);
        $looseFile->setName('Loose File');
        $looseFile->setPath('/loose-file.md');
        $this->entityManager->persist($looseFile);

        // Create a directory
        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Test Dir');
        $dir->setSummary('This is a test directory.');
        $this->entityManager->persist($dir);

        // Create a file inside the directory
        $fileInDir = new File();
        $fileInDir->setUser($user);
        $fileInDir->setName('File in Dir');
        $fileInDir->setPath('/test-dir/file-in-dir.md');
        $fileInDir->setDir($dir);
        $this->entityManager->persist($fileInDir);

        $this->entityManager->flush();

        $looseFileId = $looseFile->getId();
        $dirId = $dir->getId();
        $fileInDirId = $fileInDir->getId();

        $this->client->request('GET', '/api/tree');
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Assert that the user is correct
        $this->assertEquals($this->email, $response['user']);

        // Assert that the loose file is present and has correct data
        $this->assertCount(1, $response['files']);
        $this->assertArrayHasKey($looseFileId, $response['files']);
        $this->assertEquals('Loose File', $response['files'][$looseFileId]['name']);

        // Assert that the directory is present and has correct data
        $this->assertCount(1, $response['dirs']);
        $this->assertArrayHasKey($dirId, $response['dirs']);
        $this->assertEquals('Test Dir', $response['dirs'][$dirId]['name']);
        $this->assertEquals('This is a test directory.', $response['dirs'][$dirId]['summary']);

        // Verify all entities in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $userRepository = $this->entityManager->getRepository(User::class);

        $foundLooseFile = $fileRepository->find($looseFileId);
        $this->assertNotNull($foundLooseFile);
        $this->assertEquals('Loose File', $foundLooseFile->getName());
        $this->assertEquals('/loose-file.md', $foundLooseFile->getPath());
        $this->assertNull($foundLooseFile->getDir());
        $this->assertEquals($this->email, $foundLooseFile->getUser()->getEmail());

        $foundDir = $dirRepository->find($dirId);
        $this->assertNotNull($foundDir);
        $this->assertEquals('Test Dir', $foundDir->getName());
        $this->assertEquals('This is a test directory.', $foundDir->getSummary());
        $this->assertEquals($this->email, $foundDir->getUser()->getEmail());

        $foundFileInDir = $fileRepository->find($fileInDirId);
        $this->assertNotNull($foundFileInDir);
        $this->assertEquals('File in Dir', $foundFileInDir->getName());
        $this->assertEquals('/test-dir/file-in-dir.md', $foundFileInDir->getPath());
        $this->assertNotNull($foundFileInDir->getDir());
        $this->assertEquals($dirId, $foundFileInDir->getDir()->getId());
        $this->assertEquals($this->email, $foundFileInDir->getUser()->getEmail());
    }

    public function test_02_fileInfoEndpoint(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Test File Info');
        $file->setPath('/test/path');
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $fileId = $file->getId();

        $this->client->request('GET', '/api/file/' . $fileId);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Test File Info', $response['name']);

        // Verify file in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $foundFile = $fileRepository->find($fileId);
        $this->assertNotNull($foundFile);
        $this->assertEquals('Test File Info', $foundFile->getName());
        $this->assertEquals('/test/path', $foundFile->getPath());
        $this->assertEquals($this->email, $foundFile->getUser()->getEmail());
    }

    public function test_03_dirInfoEndpoint(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        // Create a directory
        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('Another Test Dir');
        $dir->setSummary('Awesome summary');
        $this->entityManager->persist($dir);

        // Create a file inside the directory
        $fileInDir = new File();
        $fileInDir->setUser($user);
        $fileInDir->setName('Another File in Dir');
        $fileInDir->setPath('/test-dir/another-file-in-dir.md');
        $fileInDir->setDir($dir);
        $this->entityManager->persist($fileInDir);

        $this->entityManager->flush();

        $dirId = $dir->getId();
        $fileInDirId = $fileInDir->getId();

        $this->client->request('GET', '/api/dir/' . $dirId);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Another Test Dir', $response['name']);
        $this->assertEquals('Awesome summary', $response['summary']);

        // Assert that the file inside the directory is present and has correct data
        $this->assertCount(1, $response['files']);
        $this->assertArrayHasKey($fileInDirId, $response['files']);
        $this->assertEquals('Another File in Dir', $response['files'][$fileInDirId]['name']);

        // Verify directory and file in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $fileRepository = $this->entityManager->getRepository(File::class);

        $foundDir = $dirRepository->find($dirId);
        $this->assertNotNull($foundDir);
        $this->assertEquals('Another Test Dir', $foundDir->getName());
        $this->assertEquals('Awesome summary', $foundDir->getSummary());
        $this->assertEquals($this->email, $foundDir->getUser()->getEmail());

        $foundFile = $fileRepository->find($fileInDirId);
        $this->assertNotNull($foundFile);
        $this->assertEquals('Another File in Dir', $foundFile->getName());
        $this->assertEquals('/test-dir/another-file-in-dir.md', $foundFile->getPath());
        $this->assertEquals($dirId, $foundFile->getDir()->getId());
        $this->assertEquals($this->email, $foundFile->getUser()->getEmail());
    }

    public function test_04_fileAndDirCreation(): void
    {
        // Test directory creation
        $this->client->jsonRequest('POST', '/api/dir', [
            'name' => 'My Test Directory',
            'summary' => 'Awesome summary',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $dirResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $dirResponse);
        $this->assertEquals('My Test Directory', $dirResponse['name']);
        $this->assertEquals('Awesome summary', $dirResponse['summary']);

        $dirId = $dirResponse['id'];

        // Verify directory was created in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $dir = $dirRepository->find($dirId);
        $this->assertNotNull($dir);
        $this->assertEquals('My Test Directory', $dir->getName());
        $this->assertEquals('Awesome summary', $dir->getSummary());
        $this->assertEquals($this->email, $dir->getUser()->getEmail());

        // Test file creation
        $this->client->jsonRequest('POST', '/api/file', [
            'name' => 'My Test File',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $fileResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $fileResponse);
        $this->assertEquals('My Test File', $fileResponse['name']);
        $this->assertEquals(null, $fileResponse['dir']);

        $fileId = $fileResponse['id'];

        // Verify file was created in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $file = $fileRepository->find($fileId);
        $this->assertNotNull($file);
        $this->assertEquals('My Test File', $file->getName());
        $this->assertNotEmpty($file->getPath());
        $this->assertNull($file->getDir());
        $this->assertEquals($this->email, $file->getUser()->getEmail());

        // Test file creation with directory
        $this->client->jsonRequest('POST', '/api/file', [
            'name' => 'My Other Test File',
            'dir' => $dirId,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $fileResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $fileResponse);
        $this->assertEquals('My Other Test File', $fileResponse['name']);
        $this->assertEquals($dirId, $fileResponse['dir']);

        $fileWithDirId = $fileResponse['id'];

        // Verify file with directory was created in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $fileWithDir = $fileRepository->find($fileWithDirId);
        $this->assertNotNull($fileWithDir);
        $this->assertEquals('My Other Test File', $fileWithDir->getName());
        $this->assertNotEmpty($fileWithDir->getPath());
        $this->assertNotNull($fileWithDir->getDir());
        $this->assertEquals($dirId, $fileWithDir->getDir()->getId());
        $this->assertEquals($this->email, $fileWithDir->getUser()->getEmail());
    }

    public function test_05_creationDuplicateName(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file1 = new File();
        $file1->setUser($user);
        $file1->setName('Duplicate Name');
        $file1->setPath('');
        $this->entityManager->persist($file1);

        $dir1 = new Dir();
        $dir1->setUser($user);
        $dir1->setName('Duplicate Name');
        $dir1->setSummary('');
        $this->entityManager->persist($dir1);
        $this->entityManager->flush();

        $this->client->jsonRequest('POST', '/api/file', ['name' => 'Duplicate Name']);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Duplicate Name (1)', $response['name']);

        $createdFileId = $response['id'];

        // Verify file was created with auto-renamed name in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $createdFile = $fileRepository->find($createdFileId);
        $this->assertNotNull($createdFile);
        $this->assertEquals('Duplicate Name (1)', $createdFile->getName());
        $this->assertNotEmpty($createdFile->getPath());
        $this->assertEquals($this->email, $createdFile->getUser()->getEmail());

        $this->client->jsonRequest('POST', '/api/dir', ['name' => 'Duplicate Name']);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Duplicate Name (1)', $response['name']);

        $createdDirId = $response['id'];

        // Verify directory was created with auto-renamed name in database
        $this->entityManager->clear();
        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $createdDir = $dirRepository->find($createdDirId);
        $this->assertNotNull($createdDir);
        $this->assertEquals('Duplicate Name (1)', $createdDir->getName());
        $this->assertEquals($this->email, $createdDir->getUser()->getEmail());
    }

    public function test_06_fileInfoForbidden(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);
        $user2 = $this->createUser('user2@example.com', 'password');
        $this->entityManager->persist($user2);

        $file = new File();
        $file->setUser($user2);
        $file->setName('User2 File');
        $file->setPath('');
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $fileId = $file->getId();

        $this->client->request('GET', '/api/file/' . $fileId);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Verify file still belongs to user2 in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $foundFile = $fileRepository->find($fileId);
        $this->assertNotNull($foundFile);
        $this->assertEquals('User2 File', $foundFile->getName());
        $this->assertEquals('user2@example.com', $foundFile->getUser()->getEmail());
    }

    public function test_07_fileUpdateName(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Update Test File');
        $file->setPath('/update-test-file.md');
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
        $this->assertEquals('/update-test-file.md', $updatedFile->getPath());
        $this->assertNull($updatedFile->getDir());
        $this->assertEquals($this->email, $updatedFile->getUser()->getEmail());
    }

    public function test_08_fileMoveToDirAndBack(): void
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
        $file->setPath('/move-test-file.md');
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
        $this->assertEquals('/move-test-file.md', $movedFile->getPath());
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
        $this->assertEquals('/move-test-file.md', $movedFile->getPath());
        $this->assertNull($movedFile->getDir());
        $this->assertEquals($this->email, $movedFile->getUser()->getEmail());
    }

    public function test_09_fileUpdateNameConflict(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Original Name');
        $file->setPath('/original.md');
        $this->entityManager->persist($file);

        $otherFile = new File();
        $otherFile->setUser($user);
        $otherFile->setName('Existing Name');
        $otherFile->setPath('/existing-name.md');
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
        $this->assertEquals('/original.md', $unchangedFile->getPath());
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals($this->email, $unchangedFile->getUser()->getEmail());
    }

    public function test_10_fileMoveToNonExistentDir(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Test File');
        $file->setPath('/test-file.md');
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
        $this->assertEquals('/test-file.md', $unchangedFile->getPath());
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals($this->email, $unchangedFile->getUser()->getEmail());
    }

    public function test_11_fileUpdateForbidden(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);

        // Create user2 and their file
        $user2 = $this->createUser('user2-for-update@example.com', 'password');
        $this->entityManager->persist($user2);

        $user2File = new File();
        $user2File->setUser($user2);
        $user2File->setName('User2 File');
        $user2File->setPath('/user2-file.md');
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
        $this->assertEquals('/user2-file.md', $unchangedFile->getPath());
        $this->assertNull($unchangedFile->getDir());
        $this->assertEquals('user2-for-update@example.com', $unchangedFile->getUser()->getEmail());
    }

    public function test_12_fileCreateWithInvalidDir(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);

        // Create user2 and their directory
        $user2 = $this->createUser('user2-dir@example.com', 'password');
        $this->entityManager->persist($user2);

        $user2Dir = new Dir();
        $user2Dir->setUser($user2);
        $user2Dir->setName('User2 Directory');
        $this->entityManager->persist($user2Dir);
        $this->entityManager->flush();

        // User1 tries to create a file in user2's directory
        $this->client->jsonRequest('POST', '/api/file', [
            'name' => 'Sneaky File',
            'dir' => $user2Dir->getId(),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Verify no file was created with this name in database
        $this->entityManager->clear();
        $fileRepository = $this->entityManager->getRepository(File::class);
        $sneakyFile = $fileRepository->findOneBy(['name' => 'Sneaky File']);
        $this->assertNull($sneakyFile);
    }

    public function test_13_dirUpdateName(): void
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

    public function test_14_dirUpdateSummary(): void
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

    public function test_15_dirUpdateNameAndSummary(): void
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

    public function test_16_dirUpdateNameConflict(): void
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

    public function test_17_dirUpdateForbidden(): void
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

    public function test_18_deleteFile_success(): void
    {
        // Retrieve the authenticated user created in setUp()
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        // Create a file owned by the authenticated user
        $file = new File();
        $file->setUser($user);
        $file->setName('ToDelete.md');
        $file->setPath('/to-delete.md');
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        // Attempt deletion through API
        $this->client->request('DELETE', '/api/file/' . $file->getId());

        // Expect 204 No Content on success
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify the file no longer exists in the database
        $deleted = $this->entityManager->getRepository(File::class)->find($file->getId());
        $this->assertNull($deleted);
    }

    public function test_19_deleteFile_forbidden(): void
    {
        // Create a second user
        $user2 = $this->createUser('user2@example.com', 'password2');
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        // Create a file owned by that second user
        $foreignFile = new File();
        $foreignFile->setUser($user2);
        $foreignFile->setName('OtherUserFile.md');
        $foreignFile->setPath('/other-user-file.md');
        $this->entityManager->persist($foreignFile);
        $this->entityManager->flush();

        // Attempt deletion using user1's credentials
        $this->client->request('DELETE', '/api/file/' . $foreignFile->getId());

        // Expect 403 Forbidden
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // The file must still exist in the database
        $stillExists = $this->entityManager->getRepository(File::class)->find($foreignFile->getId());
        $this->assertNotNull($stillExists);
    }

    public function test_20_deleteFile_unauthorized(): void
    {
        // Create a standalone user and file
        $user = $this->createUser('unauth@example.com', 'pw');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $file = new File();
        $file->setUser($user);
        $file->setName('UnauthorizedDelete.md');
        $file->setPath('/unauth-delete.md');
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        // Create an unauthenticated client (no token)
        $this->deauthenticateClient();
        $this->client->request('DELETE', '/api/file/' . $file->getId());

        // Expect 401 Unauthorized
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_21_deleteDir_success(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        // Create a directory owned by the authenticated user
        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('DeleteDir');
        $dir->setSummary('Directory to delete');
        $this->entityManager->persist($dir);
        $this->entityManager->flush();

        // Attempt deletion through API
        $this->client->request('DELETE', '/api/dir/' . $dir->getId());

        // Expect 204 No Content on success
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify the directory no longer exists in the database
        $deleted = $this->entityManager->getRepository(Dir::class)->find($dir->getId());
        $this->assertNull($deleted);
    }

    public function test_22_deleteDir_forbidden(): void
    {
        // Create a second user and their directory
        $user2 = $this->createUser('user2@example.com', 'password2');
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        $foreignDir = new Dir();
        $foreignDir->setUser($user2);
        $foreignDir->setName('OtherUserDir');
        $foreignDir->setSummary('Not owned by current user');
        $this->entityManager->persist($foreignDir);
        $this->entityManager->flush();

        // Attempt deletion using user1's credentials
        $this->client->request('DELETE', '/api/dir/' . $foreignDir->getId());

        // Expect 403 Forbidden
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Directory must still exist
        $stillExists = $this->entityManager->getRepository(Dir::class)->find($foreignDir->getId());
        $this->assertNotNull($stillExists);
    }

    public function test_23_deleteDir_unauthorized(): void
    {
        // Create a standalone user and directory
        $user = $this->createUser('unauth@example.com', 'pw');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName('UnauthorizedDir');
        $dir->setSummary('Unauthorized deletion attempt');
        $this->entityManager->persist($dir);
        $this->entityManager->flush();

        // Create an unauthenticated client (no token)
        $this->deauthenticateClient();
        $this->client->request('DELETE', '/api/dir/' . $dir->getId());

        // Expect 401 Unauthorized
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }


    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
