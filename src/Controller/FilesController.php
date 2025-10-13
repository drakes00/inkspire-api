<?php

namespace App\Controller;

use App\Entity\Dir;
use App\Entity\User;
use App\Entity\File;
use App\Repository\DirRepository;
use App\Repository\FileRepository;
use App\Service\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Controller for handling file and directory operations.
 */
#[Route('/api', name: 'app_api')]
class FilesController extends AbstractController
{
    public function __construct(private readonly FilePathGenerator $filePathGenerator)
    {
    }

    /**
     * Retrieves the file and directory tree for the authenticated user.
     *
     * @param User|null $user The current user.
     * @param Request $request The request object.
     * @return Response The JSON response.
     */
    #[Route('/tree', name: 'tree', methods: ['GET'])]
    public function tree(#[CurrentUser] ?User $user, Request $request): Response
    {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Initialize an array to store file details.
        $resultFiles = [];
        // Get all files associated with the user.
        $files = $user->getFiles();
        foreach ($files as $file) {
            // Check if the file belongs to a directory or is stray.
            if ($file->getDir() === null) {
                // Store the name and path of each file in the resultFiles array.
                $resultFiles[$file->getID()] = [
                    "name" => $file->getName(),
                    "path" => $file->getPath(),
                ];
            }
        }

        // Initialize an array to store directory details.
        $resultDirs = [];
        // Get all directories associated with the user.
        $dirs = $user->getDirs();
        foreach ($dirs as $dir) {
            // Store the name and summary of each directory in the resultDirs array.
            $resultDirs[$dir->getID()] = [
                "name" => $dir->getName(),
                "summary" => $dir->getSummary(),
            ];
        }

        // Return user details and their associated files and directories in JSON format.
        return $this->json([
            'user' => $user->getUserIdentifier(),
            'files' => $resultFiles,
            'dirs' => $resultDirs,
        ]);
    }

    /**
     * Retrieves information for a specific file.
     *
     * @param User|null $user The current user.
     * @param File $file The file entity.
     * @return Response The JSON response.
     */
    #[Route('/file/{id}', name: 'file_info', methods: ['GET'])]
    public function file_info(#[CurrentUser] ?User $user, File $file): Response
    {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the user has access to the file.
        if ($file->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this file',
            ], Response::HTTP_FORBIDDEN);
        }

        // Return the file information.
        return $this->json([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
        ]);
    }

    /**
     * Retrieves information for a specific directory.
     *
     * @param User|null $user The current user.
     * @param Dir $dir The directory entity.
     * @return Response The JSON response.
     */
    #[Route('/dir/{id}', name: 'dir_info', methods: ['GET'])]
    public function dir_info(#[CurrentUser] ?User $user, Dir $dir): Response
    {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the user has access to the directory.
        if ($dir->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this directory',
            ], Response::HTTP_FORBIDDEN);
        }

        // Retrieve files under the directory.
        $files = [];
        foreach ($dir->getFiles() as $file) {
            $files[$file->getId()] = [
                'name' => $file->getName(),
                'path' => $file->getPath(),
            ];
        }

        // Return the directory information.
        return $this->json([
            'id' => $dir->getId(),
            'name' => $dir->getName(),
            'summary' => $dir->getSummary(),
            'files' => $files,
        ]);
    }

    /**
     * Creates a new file.
     *
     * @param User|null $user The current user.
     * @param Request $request The request object.
     * @param FileRepository $fileRepository The file repository.
     * @param DirRepository $dirRepository The directory repository.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @return Response The JSON response.
     */
    #[Route('/file', name: 'file_create', methods: ['POST'])]
    public function file_create(
        #[CurrentUser] ?User $user,
        Request $request,
        FileRepository $fileRepository,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Decode the request content.
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? 'new file';
        $dir = null;
        if (array_key_exists('dir', $data) and $data['dir'] !== null) {
            $dir = $dirRepository->findOneBy(['user' => $user, 'id' => $data['dir']]);
        }

        // Ensure the file name is unique.
        $originalName = $name;
        $i = 1;
        while ($fileRepository->findOneBy(['user' => $user, 'name' => $name])) {
            $name = $originalName . ' (' . $i++ . ')';
        }

        // Create and persist the new file entity.
        $file = new File();
        $file->setUser($user);
        $file->setName($name);
        $file->setPath($this->filePathGenerator->generate());
        if ($dir !== null) {
            $file->setDir($dir);
        }

        $entityManager->persist($file);
        $entityManager->flush();

        // Return the new file's information.
        return $this->json([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'dir' => $dir !== null ? $file->getDir()->getId() : null,
        ], Response::HTTP_CREATED);
    }

    /**
     * Creates a new directory.
     *
     * @param User|null $user The current user.
     * @param Request $request The request object.
     * @param DirRepository $dirRepository The directory repository.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @return Response The JSON response.
     */
    #[Route('/dir', name: 'dir_create', methods: ['POST'])]
    public function dir_create(
        #[CurrentUser] ?User $user,
        Request $request,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Decode the request content.
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? 'new directory';
        $summary = $data['summary'] ?? null;

        // Ensure the directory name is unique.
        $originalName = $name;
        $i = 1;
        while ($dirRepository->findOneBy(['user' => $user, 'name' => $name])) {
            $name = $originalName . ' (' . $i++ . ')';
        }

        // Create and persist the new directory entity.
        $dir = new Dir();
        $dir->setUser($user);
        $dir->setName($name);
        $dir->setSummary($summary);

        $entityManager->persist($dir);
        $entityManager->flush();

        // Return the new directory's information.
        return $this->json([
            'id' => $dir->getId(),
            'name' => $dir->getName(),
            'summary' => $dir->getSummary(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Updates an existing file.
     *
     * @param User|null $user The current user.
     * @param Request $request The request object.
     * @param File $file The file entity to update.
     * @param FileRepository $fileRepository The file repository.
     * @param DirRepository $dirRepository The directory repository.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @return Response The JSON response.
     */
    #[Route('/file/{id}', name: 'file_update', methods: ['PUT'])]
    public function file_update(
        #[CurrentUser] ?User $user,
        Request $request,
        File $file,
        FileRepository $fileRepository,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the user has access to the file.
        if ($file->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this file',
            ], Response::HTTP_FORBIDDEN);
        }

        // Decode the request content.
        $data = json_decode($request->getContent(), true);

        // Update file properties if provided in the request.
        if (array_key_exists('name', $data) && $data['name'] !== $file->getName()) {
            $existingFile = $fileRepository->findOneBy(['user' => $user, 'name' => $data['name']]);
            if ($existingFile) {
                return $this->json(['message' => 'File name already exists'], Response::HTTP_CONFLICT);
            }
            $file->setName($data['name']);
        }
        if (array_key_exists('dir', $data)) {
            $dir = null;
            if ($data['dir'] !== null) {
                $dir = $dirRepository->findOneBy(['user' => $user, 'id' => $data['dir']]);
                if (!$dir) {
                    return $this->json(['message' => 'Directory not found'], Response::HTTP_BAD_REQUEST);
                }
            }
            $file->setDir($dir);
        }

        $entityManager->flush();

        // Return the updated file's information.
        return $this->json([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'dir' => $file->getDir() ? $file->getDir()->getId() : null,
        ]);
    }

    /**
     * Updates an existing directory.
     *
     * @param User|null $user The current user.
     * @param Request $request The request object.
     * @param Dir $dir The directory entity to update.
     * @param DirRepository $dirRepository The directory repository.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @return Response The JSON response.
     */
    #[Route('/dir/{id}', name: 'dir_update', methods: ['PUT'])]
    public function dir_update(
        #[CurrentUser] ?User $user,
        Request $request,
        Dir $dir,
        DirRepository $dirRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the user has access to the directory.
        if ($dir->getUser() !== $user) {
            return $this->json([
                'message' => 'You do not have access to this directory',
            ], Response::HTTP_FORBIDDEN);
        }

        // Decode the request content.
        $data = json_decode($request->getContent(), true);

        // Update directory properties if provided in the request.
        if (array_key_exists('name', $data) && $data['name'] !== $dir->getName()) {
            $existingDir = $dirRepository->findOneBy(['user' => $user, 'name' => $data['name']]);
            if ($existingDir) {
                return $this->json(['message' => 'Directory name already exists'], Response::HTTP_CONFLICT);
            }
            $dir->setName($data['name']);
        }
        if (array_key_exists('summary', $data)) {
            $dir->setSummary($data['summary']);
        }

        $entityManager->flush();

        // Return the updated directory's information.
        return $this->json([
            'id' => $dir->getId(),
            'name' => $dir->getName(),
            'summary' => $dir->getSummary(),
        ]);
    }
}
