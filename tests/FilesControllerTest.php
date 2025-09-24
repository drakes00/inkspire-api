<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Dir;
use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class FilesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;

    private $email = 'test@example.com';
    private $password = 'password';


    /**
    * Create a client with a default Authorization header.
    *
    * @param string $username
    * @param string $password
    *
    * @return \Symfony\Bundle\FrameworkBundle\Client
    */
    protected function createAuthenticatedClient($client, string $email, string $password)
    {
        $client->jsonRequest(
            'POST',
            '/auth',
            [
                'username' => $email,
                'password' => $password,
            ]
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));

        return $client;
    }

    private function createUser(string $email, string $password): User
    {
        $container = static::getContainer();
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new User())->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

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

        $this->createUser($this->email, $this->password);
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

        $this->client->request('GET', '/api/tree');
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Assert that the user is correct
        $this->assertEquals($this->email, $response['user']);

        // Assert that the loose file is present and has correct data
        $this->assertCount(1, $response['files']);
        $looseFileId = $looseFile->getId();
        $this->assertArrayHasKey($looseFileId, $response['files']);
        $this->assertEquals('Loose File', $response['files'][$looseFileId]['name']);
        $this->assertEquals('/loose-file.md', $response['files'][$looseFileId]['path']);

        // Assert that the directory is present and has correct data
        $this->assertCount(1, $response['dirs']);
        $dirId = $dir->getId();
        $this->assertArrayHasKey($dirId, $response['dirs']);
        $this->assertEquals('Test Dir', $response['dirs'][$dirId]['name']);
        $this->assertEquals('This is a test directory.', $response['dirs'][$dirId]['summary']);
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

        $this->client->request('GET', '/api/file/' . $file->getId());
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Test File Info', $response['name']);
        $this->assertEquals('/test/path', $response['path']);
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

        $this->client->request('GET', '/api/dir/' . $dir->getId());
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Another Test Dir', $response['name']);
        $this->assertEquals('Awesome summary', $response['summary']);

        // Assert that the file inside the directory is present and has correct data
        $fileInDirId = $fileInDir->getId();
        $this->assertCount(1, $response['files']);
        $this->assertArrayHasKey($fileInDirId, $response['files']);
        $this->assertEquals('Another File in Dir', $response['files'][$fileInDirId]['name']);
        $this->assertEquals('/test-dir/another-file-in-dir.md', $response['files'][$fileInDirId]['path']);
    }

    public function test_04_fileAndDirCreation(): void
    {
        // Test directory creation
        $this->client->jsonRequest('POST', '/api/dir', [
            'name' => 'My Test Directory',
            'summary' => 'Awesome summary',
        ]);
        $this->assertResponseIsSuccessful();
        $dirResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $dirResponse);
        $this->assertEquals('My Test Directory', $dirResponse['name']);
        $this->assertEquals('Awesome summary', $dirResponse['summary']);

        $dirRepository = $this->entityManager->getRepository(Dir::class);
        $dir = $dirRepository->findOneBy(['id' => $dirResponse['id']]);
        $this->assertNotNull($dir);
        $this->assertEquals('My Test Directory', $dir->getName());
        $this->assertEquals('Awesome summary', $dir->getSummary());

        // Test file creation
        $this->client->jsonRequest('POST', '/api/file', [
            'name' => 'My Test File',
        ]);
        $this->assertResponseIsSuccessful();
        $fileResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $fileResponse);
        $this->assertEquals('My Test File', $fileResponse['name']);
        $this->assertEquals(null, $fileResponse['dir']);

        $fileRepository = $this->entityManager->getRepository(File::class);
        $file = $fileRepository->findOneBy(['id' => $fileResponse['id']]);
        $this->assertNotNull($file);
        $this->assertEquals('My Test File', $file->getName());
        $this->assertEquals(null, $file->getDir());


        // Test file creation with directory.
        $dirId = $dirResponse['id'];
        $this->client->jsonRequest('POST', '/api/file', [
            'name' => 'My Other Test File',
            'dir' => $dirId,
        ]);
        $this->assertResponseIsSuccessful();
        $fileResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $fileResponse);
        $this->assertEquals('My Other Test File', $fileResponse['name']);
        $this->assertEquals($dirId, $fileResponse['dir']);

        $fileRepository = $this->entityManager->getRepository(File::class);
        $file = $fileRepository->findOneBy(['id' => $fileResponse['id']]);
        $this->assertNotNull($file);
        $this->assertEquals('My Other Test File', $file->getName());
        $this->assertEquals($dir, $file->getDir());
    }

    public function test_05_creationDuplicateName(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);
        var_dump($user->getId());

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
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Duplicate Name (1)', $response['name']);

        $this->client->jsonRequest('POST', '/api/dir', ['name' => 'Duplicate Name']);
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Duplicate Name (1)', $response['name']);
    }

    public function test_06_fileInfoForbidden(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user1 = $userRepository->findOneBy(['email' => $this->email]);
        $user2 = $this->createUser('user2@example.com', 'password');

        $file = new File();
        $file->setUser($user2);
        $file->setName('User2 File');
        $file->setPath('');
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/file/' . $file->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
