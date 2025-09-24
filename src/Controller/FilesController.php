<?php

namespace App\Controller;

use App\Entity\Dir;
use App\Entity\User;
use App\Entity\File;
use App\Repository\DirRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'app_api')]
class FilesController extends AbstractController
{
    #[Route('/tree', name: 'tree', methods: ['GET'])]
    public function tree(#[CurrentUser] ?User $user, Request $request): Response
    {
        // Check if user is authenticated
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Initialize array to store file details
        $resultFiles = [];
        // Get all files associated with the user
        $files = $user->getFiles();
        foreach ($files as $file) {
            // Check if file belongs to a dir or is stray.
            if ($file->getDir() === null) {
                // Store name and path of each file in the resultFiles array
                $resultFiles[$file->getID()] = [
                    "name" => $file->getName(),
                    "path" => $file->getPath(),
                ];
            }
        }

        // Initialize array to store directory details
        $resultDirs = [];
        // Get all directories associated with the user
        $dirs = $user->getDirs();
        foreach ($dirs as $dir) {
            // Store name and summary of each directory in the resultDirs array
            $resultDirs[$dir->getID()] = [
                "name" => $dir->getName(),
                "summary" => $dir->getSummary(),
            ];
        }

        // Return user details and their associated files and directories in JSON format
        return $this->json([
            'user' => $user->getUserIdentifier(),
            'files' => $resultFiles,
            'dirs' => $resultDirs,
        ]);
    }

    #[Route('/file/{id}', name: 'file_info', methods: ['GET'])]
    public function file_info(#[CurrentUser] ?User $user, File $file): Response
    {
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($file->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this file',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
        ]);
    }

    #[Route('/dir/{id}', name: 'dir_info', methods: ['GET'])]
    public function dir_info(#[CurrentUser] ?User $user, Dir $dir): Response
    {
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($dir->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this directory',
            ], Response::HTTP_FORBIDDEN);
        }

        // Retrieve files under the directory.
        $files = [];
        foreach ($dir->getFiles() as $file) {
            $files[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'path' => $file->getPath(),
            ];
        }

        return $this->json([
            'id' => $dir->getId(),
            'name' => $dir->getName(),
            'summary' => $dir->getSummary(),
            'files' => $files,
        ]);
    }

    #[Route('/file', name: 'file_create', methods: ['POST'])]
    public function file_create(
        #[CurrentUser] ?User $user,
        Request $request,
        FileRepository $fileRepository,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? 'new file';
        if ($data['dir'] !== null) {
            $dir = $dirRepository->findOneBy(['user' => $user, 'id' => $data['dir']]);
        }

        $originalName = $name;
        $i = 1;
        while ($fileRepository->findOneBy(['user' => $user, 'name' => $name])) {
            $name = $originalName . ' (' . $i++ . ')';
        }

        $file = new File();
        $file->setUser($user);
        $file->setName($name);
        $file->setPath(''); // As requested, leave the path blank
        $file->setDir($dir);

        $entityManager->persist($file);
        $entityManager->flush();

        return $this->json([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/dir', name: 'dir_create', methods: ['POST'])]
    public function dir_create(
        #[CurrentUser] ?User $user,
        Request $request,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? 'new directory';

        $originalName = $name;
        $i = 1;
        while ($dirRepository->findOneBy(['user' => $user, 'name' => $name])) {
            $name = $originalName . ' (' . $i++ . ')';
        }

        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName($name);

        $entityManager->persist($dir);
        $entityManager->flush();

        return $this->json([
            'id' => $dir->getId(),
            'name' => $dir->getName(),
        ], Response::HTTP_CREATED);
    }
}
