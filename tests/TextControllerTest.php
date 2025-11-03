<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\File;
use Symfony\Component\HttpFoundation\Response;

class TextControllerTest extends AuthenticatedWebTestCase
{
    public function test_01_file_contents_success(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Test File');
        $path = $this->filePathGenerator->generate('Test File');
        $file->setPath($path);
        $fileContent = 'This is the content of the test file.';
        file_put_contents($path, $fileContent);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/file/' . $file->getId() . '/contents');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertEquals($fileContent, $this->client->getResponse()->getContent());
    }

    public function test_02_file_contents_forbidden(): void
    {
        // Create a second user
        $user2 = $this->createUser('user2@example.com', 'password2');
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        // Create a file owned by that second user
        $foreignFile = new File();
        $foreignFile->setUser($user2);
        $foreignFile->setName('OtherUserFile.md');
        $path = $this->filePathGenerator->generate('OtherUserFile.md');
        $foreignFile->setPath($path);
        file_put_contents($path, 'some content');

        $this->entityManager->persist($foreignFile);
        $this->entityManager->flush();

        // Attempt to get content using user1's credentials
        $this->client->request('GET', '/api/file/' . $foreignFile->getId() . '/contents');

        // Expect 403 Forbidden
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_03_file_contents_unauthorized(): void
    {
        $user = $this->createUser('unauth@example.com', 'pw');
        $this->entityManager->persist($user);

        $file = new File();
        $file->setUser($user);
        $file->setName('UnauthorizedFile.md');
        $path = $this->filePathGenerator->generate('UnauthorizedFile.md');
        $file->setPath($path);
        file_put_contents($path, 'some content');

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->deauthenticateClient();
        $this->client->request('GET', '/api/file/' . $file->getId() . '/contents');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_04_file_contents_not_found_on_disk(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('File not on disk');
        $path = $this->filePathGenerator->generate('File not on disk');
        $file->setPath($path);
        // We don't write the file to disk
        // file_put_contents($path, 'some content');

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/file/' . $file->getId() . '/contents');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
