<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class OllamaService
{
    private string $ollamaServiceUrl;
    private const CACHE_KEY_MODELS = 'ollama_available_models';
    private const CACHE_TTL_MODELS = 3600; // Cache for 1 hour (3600 seconds)

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheInterface $cache, // Changed from ItemInterface
        string $ollamaServiceUrl
    ) {
        $this->ollamaServiceUrl = $ollamaServiceUrl;
    }

    /**
     * Fetches the list of available models from the Ollama API via the Python backend,
     * utilizing a time-based cache.
     *
     * @return array The list of available models.
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     */
    public function getAvailableModels(): array
    {
        return $this->cache->get(self::CACHE_KEY_MODELS, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL_MODELS);

            $response = $this->client->request(
                'GET',
                $this->ollamaServiceUrl . '/api/tags'
            );

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                return [];
            }

            $data = $response->toArray();
            $modelsData = $data['models'] ?? $data;

            $modelNames = [];
            foreach ($modelsData as $model) {
                if (isset($model['name'])) {
                    $modelNames[] = ['name' => $model['name']];
                }
            }

            return $modelNames;
        });
    }
}
