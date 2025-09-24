<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'app_api')]
class FilesController extends AbstractController
{
    #[Route('/tree', name: 'tree')]
    public function tree(#[CurrentUser] ?User $user, Request $request): Response {
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $resultFiles = [];
        $files = $user->getFiles();
        foreach ($files as $file) {
            $resultFiles[$file->getID()] = [
                "name" => $file->getName(),
                "path" => $file->getPath(),
            ];
        }

        $resultDirs = [];
        $dirs = $user->getDirs();
        foreach ($dirs as $dir) {
            $resultDirs[$dir->getID()] = [
                "name" => $dir->getName(),
                "summary" => $dir->getSummary(),
            ];
        }

        return $this->json([
            'user' => $user->getUserIdentifier(),
            'files' => $resultFiles,
            'dirs' => $resultDirs,
        ]);
    }
}
