<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/models', name: 'app_api_models_')]
class ModelController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user, OllamaService $ollamaService): Response
    {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $models = $ollamaService->getAvailableModels();
            return $this->json($models);
        } catch (\Exception $e) {
            // In a real app, you would inject a logger and log the error.
            // For now, we return a generic error message.
            return $this->json([
                'message' => 'Could not retrieve models from the Ollama service.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
