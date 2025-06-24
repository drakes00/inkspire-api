<?php

namespace App\Controller;

use App\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\DTO\SignUpRequest;
use App\Mapper\SignUpReqToUser;
use App\Repository\UserRepository;
use App\Service\DataBase;
use App\Service\ResponseService;
use App\Service\OllamaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;


#[Route("/api/v1/ollama", "ollama_")]
#[AsController]
class OllamaController extends AbstractController
{
    public function __construct(private OllamaService $ollama_server, private ResponseService $rs,
                                private FileService   $fileServices)
    {
    }

    #[Route('/dir_context', name: 'dir_context', methods: ['POST'])]
    public function getDirContext(#[MapRequestPayload] FileDTO $fileDTO): Response
    {
        if (!isset($fileDTO->token) || !isset($fileDTO->name)) {
            return $this->rs->badResponse(400, "File is required", "File is missing");
        }
        $context = $this->fileServices->getContextFromId($fileDTO->id);
        $rep_query = $this->ollama_server->changeDirectoryContext($context);
        return $this->rs->goodRequest(param: $rep_query);
    }

    #[Route('/addRequest', name: 'addRequest', methods: ['POST'])]
    public function addButtonRequest(Request $request)
    {
        # Modification de la limite de temps d'execution d'ollama pour attendre la réponse d'Ollama
        set_time_limit(3000);
        $requestOllamadto = json_decode($request->getContent(), true);
        $userQuery = $requestOllamadto['userQuery'];
        if (!isset($requestOllamadto['token']) || !isset($requestOllamadto['id'])) {
            return $this->rs->badResponse(400, "File is required", "File is missing");
        }
        if (!isset($userQuery)) {
            return $this->rs->badResponse(400, "User request is missing", "User request is missing");
        }
        $rep_query = $this->ollama_server->addReformulation($requestOllamadto['text'], $userQuery, $requestOllamadto['id']);
        if (!$rep_query) {
            return $this->rs->badResponse(400, "Error while completion", "Error during the request");
        }
        return $this->rs->goodRequest(param: $rep_query);
    }

    #[Route('/rephraseRequest', name: "rephraseRequest", methods: ['POST'])]
    public function rephraseRequest(Request $request)
    {
        # Modification de la limite de temps d'execution d'ollama pour attendre la réponse d'Ollama
        set_time_limit(3000);
        $requestOllamadto = json_decode($request->getContent(), true);
        if (!isset($requestOllamadto['token']) || !isset($requestOllamadto['id'])) {
            return $this->rs->badResponse(400, "File is required", "File is missing");
        }
        $rep_query = $this->ollama_server->rephraseReformulation($requestOllamadto['text'], $requestOllamadto['id']);
        if (!$rep_query) {
            return $this->rs->badResponse(400, "Error while completion", "Error during the request");
        }
        return $this->rs->goodRequest(param: $rep_query);
    }

    #[Route('/translateRequest', name: "translateRequest", methods: ['POST'])]
    public function translateRequest(Request $request)
    {
        # Modification de la limite de temps d'execution d'ollama pour attendre la réponse d'Ollama
        set_time_limit(3000);
        $requestOllamadto = json_decode($request->getContent(), true);
        $userQuery = $requestOllamadto['userQuery'];
        if (!$userQuery) {
            return $this->rs->badResponse(400, "User request is missing", "User request is missing");
        }
        if (!isset($requestOllamadto['token']) || !isset($requestOllamadto['id'])) {
            return $this->rs->badResponse(400, "File is required", "File is missing");
        }
        $context = $this->fileServices->getContextFromId($requestOllamadto['id']);
        $rep_query = $this->ollama_server->translateReformulation($requestOllamadto['text'], $requestOllamadto['userQuery'], $requestOllamadto['id']);
        if (!$rep_query) {
            return $this->rs->badResponse(400, "Error while completion", "Error during the request");
        }
        return $this->rs->goodRequest(param: $rep_query);
    }
}
