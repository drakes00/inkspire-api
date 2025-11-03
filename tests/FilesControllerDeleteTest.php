<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Dir;
use App\Entity\File;
use Symfony\Component\HttpFoundation\Response;

class FilesControllerDeleteTest extends AuthenticatedWebTestCase
{
    public function test_01_deleteFile_success(): void
    {
        // Retrieve the authenticated user created in setUp()
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        // Create a file owned by the authenticated user
        $file = new File();
        $file->setUser($user);
        $file->setName('ToDelete.md');
        $path = $this->filePathGenerator->generate('ToDelete.md');
        $file->setPath($path);
        touch($path);
        $this->assertFileExists($path);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        // Attempt deletion through API
        $this->client->request('DELETE', '/api/file/' . $file->getId());

        // Expect 204 No Content on success
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify the file no longer exists in the database
        $deleted = $this->entityManager->getRepository(File::class)->find($file->getId());
        $this->assertNull($deleted);

        // Verify the file no longer exists on disk
        $this->assertFileDoesNotExist($path);
    }

    public function test_02_deleteFile_forbidden(): void
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
        touch($path);
        $this->assertFileExists($path);

        $this->entityManager->persist($foreignFile);
        $this->entityManager->flush();

        // Attempt deletion using user1's credentials
        $this->client->request('DELETE', '/api/file/' . $foreignFile->getId());

        // Expect 403 Forbidden
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // The file must still exist in the database
        $stillExists = $this->entityManager->getRepository(File::class)->find($foreignFile->getId());
        $this->assertNotNull($stillExists);

        // Verify the file still exists on disk
        $this->assertFileExists($path);
    }

    public function test_03_deleteFile_unauthorized(): void
    {
        // Create a standalone user and file
        $user = $this->createUser('unauth@example.com', 'pw');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $file = new File();
        $file->setUser($user);
        $file->setName('UnauthorizedDelete.md');
        $path = $this->filePathGenerator->generate('UnauthorizedDelete.md');
        $file->setPath($path);
        touch($path);
        $this->assertFileExists($path);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        // Create an unauthenticated client (no token)
        $this->deauthenticateClient();
        $this->client->request('DELETE', '/api/file/' . $file->getId());

        // Expect 401 Unauthorized
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // Verify the file still exists on disk
        $this->assertFileExists($path);
    }

    public function test_04_deleteDir_success(): void
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

    public function test_05_deleteDir_forbidden(): void
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

    public function test_06_deleteDir_unauthorized(): void
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
}
