<?php

namespace App\Service;

interface OllamaServiceInterface
{
    /**
     * Generates text using a specified model and prompt from the Ollama API.
     *
     * @param string $model The name of the model to use.
     * @param string $prompt The user's prompt.
     * @return string The generated text response.
     */
    public function generateText(string $model, string $prompt): string;

    /**
     * Fetches the list of available models from the Ollama API.
     *
     * @return array The list of available models.
     */
    public function getAvailableModels(): array;
}
