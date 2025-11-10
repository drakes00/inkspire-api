<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'app_api_text')]
class TextController extends AbstractController
{
    #[Route('/file/{id}/contents', name: 'file_contents', methods: ['GET'])]
    public function file_contents(#[CurrentUser] ?User $user, File $file): Response
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

        $path = $file->getPath();

        if (!file_exists($path)) {
            return $this->json([
                'message' => 'File not found on disk',
            ], Response::HTTP_NOT_FOUND);
        }

        $content = file_get_contents($path);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    #[Route('/file/{id}/contents', name: 'update_file_contents', methods: ['POST'])]
    public function update_file_contents(#[CurrentUser] ?User $user, File $file, Request $request): Response
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

        $path = $file->getPath();

        if (!file_exists($path)) {
            return $this->json([
                'message' => 'File not found on disk',
            ], Response::HTTP_NOT_FOUND);
        }

        $content = $request->getContent();
        file_put_contents($path, $content);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
