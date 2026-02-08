<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\File;
use App\Service\OllamaServiceInterface;
use App\Tests\Mock\MockOllamaService;
use Symfony\Component\HttpFoundation\Response;

class OllamaControllerTest extends AuthenticatedWebTestCase
{
    private MockOllamaService $mockOllamaService;

    protected function setUp(): void
    {
        parent::setUp();

        // Récupérer le mock service AVANT toute requête
        $this->mockOllamaService = static::getContainer()->get(OllamaServiceInterface::class);

        // Vérifier que c'est bien le mock
        $this->assertInstanceOf(MockOllamaService::class, $this->mockOllamaService,
            'OllamaServiceInterface should be mocked with MockOllamaService');

        // Reset le mock avant chaque test
        MockOllamaService::reset();
    }

    protected function tearDown(): void
    {
        // Reset le mock après chaque test
        MockOllamaService::reset();

        parent::tearDown();
    }

    public function test_01_generate_success(): void
    {
        $generatedText = 'This is the AI generated text.';
        $this->mockOllamaService->setReturnedText($generatedText);

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $this->email]);

        $file = new File();
        $file->setUser($user);
        $file->setName('Ollama Test File');
        $path = $this->filePathGenerator->generate('Ollama Test File');
        $file->setPath($path);
        $initialContent = 'Initial content. ';
        file_put_contents($path, $initialContent);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->client->jsonRequest('POST', '/api/ollama/generate', [
            'id' => $file->getId(),
            'model' => 'test-model',
            'prompt' => 'Write a story'
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('snippet', $data);
        $this->assertEquals($generatedText, $data['snippet']);

        $updatedContent = file_get_contents($path);
        $this->assertEquals($initialContent . $generatedText, $updatedContent);
    }

    public function test_02_generate_missing_params(): void
    {
        $this->mockOllamaService->setReturnedText('dummy');

        $this->client->jsonRequest('POST', '/api/ollama/generate', [
            'id' => 1
            // missing model and prompt
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_03_generate_file_forbidden(): void
    {
        $this->mockOllamaService->setReturnedText('dummy');

        // Create a second user
        $user2 = $this->createUser('user2_ollama@example.com', 'password2');
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        // Create a file owned by that second user
        $foreignFile = new File();
        $foreignFile->setUser($user2);
        $foreignFile->setName('OtherUserFile.md');
        $path = $this->filePathGenerator->generate('OtherUserFile.md');
        $foreignFile->setPath($path);
        file_put_contents($path, 'some content');

        $this->entityManager->persist($foreignFile);
        $this->entityManager->flush();

        // Attempt to generate using user1's credentials
        $this->client->jsonRequest('POST', '/api/ollama/generate', [
            'id' => $foreignFile->getId(),
            'model' => 'test-model',
            'prompt' => 'prompt'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_04_generate_unauthorized(): void
    {
        $this->mockOllamaService->setReturnedText('dummy');

        $this->deauthenticateClient();
        $this->client->jsonRequest('POST', '/api/ollama/generate', [
            'id' => 1,
            'model' => 'test-model',
            'prompt' => 'prompt'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_05_list_models_success(): void
    {
        $mockModels = [['name' => 'model1'], ['name' => 'model2']];
        $this->mockOllamaService->setAvailableModels($mockModels);

        $this->client->request('GET', '/api/ollama/models');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($mockModels, $data);
    }
}
