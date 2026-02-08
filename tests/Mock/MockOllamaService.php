<?php

namespace App\Tests\Mock;

use App\Service\OllamaServiceInterface;

class MockOllamaService implements OllamaServiceInterface
{
    // Variables STATIQUES partagées entre TOUTES les instances
    private static string $returnedText = '';
    private static array $availableModels = [];

    public function setReturnedText(string $text): void
    {
        self::$returnedText = $text;
    }

    public function setAvailableModels(array $models): void
    {
        self::$availableModels = $models;
    }

    public function generateText(string $model, string $prompt): string
    {
        return self::$returnedText;
    }

    public function getAvailableModels(): array
    {
        return self::$availableModels;
    }

    /**
     * Reset static state between tests
     */
    public static function reset(): void
    {
        self::$returnedText = '';
        self::$availableModels = [];
    }
}
