<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OllamaServiceInterface;
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
    public function listModels(#[CurrentUser] ?User $user, OllamaServiceInterface $ollamaService): Response
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
     * Generates text using a specified model and prompt, and persists it to the file.
     */
    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(
        Request $request,
        #[CurrentUser] ?User $user,
        OllamaServiceInterface $ollamaService,
        \App\Repository\FileRepository $fileRepository
    ): Response {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->toArray();
        $fileId = $data['id'] ?? null;
        $model = $data['model'] ?? null;
        $prompt = $data['prompt'] ?? null;

        if (!$fileId || !$model || !$prompt) {
            return $this->json(['message' => 'Missing "id", "model" or "prompt" in request body'], Response::HTTP_BAD_REQUEST);
        }

        $file = $fileRepository->find($fileId);
        if (!$file || $file->getUser() !== $user) {
            return $this->json(['message' => 'File not found or access denied'], Response::HTTP_FORBIDDEN);
        }

        try {
            $generatedText = $ollamaService->generateText($model, $prompt);

            // Persisting to file
            $path = $file->getPath();
            if (file_exists($path)) {
                $currentContent = file_get_contents($path);
                file_put_contents($path, $currentContent . $generatedText);
            } else {
                return $this->json(['message' => 'File not found on disk'], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'snippet' => $generatedText
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['message' => 'An error occurred while communicating with the Ollama service: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
