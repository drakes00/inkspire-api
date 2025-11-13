<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/ollama', name: 'app_api_ollama_')]
class OllamaController extends AbstractController
{
    /**
     * Lists available Ollama models.
     */
    #[Route('/models', name: 'models_list', methods: ['GET'])]
    public function listModels(#[CurrentUser] ?User $user, OllamaService $ollamaService): Response
    {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $models = $ollamaService->getAvailableModels();
            return $this->json($models);
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Could not retrieve models from the Ollama service.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generates text using a specified model and prompt.
     */
    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(
        Request $request,
        #[CurrentUser] ?User $user,
        OllamaService $ollamaService
    ): Response {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->toArray();
        $model = $data['model'] ?? null;
        $prompt = $data['prompt'] ?? null;

        if (!$model || !$prompt) {
            return $this->json(['message' => 'Missing "model" or "prompt" in request body'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $generatedText = $ollamaService->generateText($model, $prompt);
            return $this->json(['response' => $generatedText]);
        } catch (\Exception $e) {
            return $this->json(
                ['message' => 'An error occurred while communicating with the Ollama service.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}