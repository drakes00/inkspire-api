<?php

namespace App\Controller;

use App\DTO\FileDTO;
use App\Repository\FileRepository;
use App\Service\FileService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;


#[Route("/api/v1/file", name: "file_")]
#[AsController]
class FileController extends AbstractController
{
    public function __construct(private FileRepository $fr, private ResponseService $rs,
                                private FileService $fs, private HtmlSanitizerInterface $hsi)
    {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function createNewFile(#[MapRequestPayload] FileDTO $fileDTO)
    {
        foreach ($fileDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($fileDTO->token) || !isset($fileDTO->name)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->fs->createFile($fileDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during the creation of the file');
        }

        return $this->rs->goodRequest();
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function deleteFile(#[MapRequestPayload] FileDTO $fileDTO) : Response
    {
        foreach ($fileDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($fileDTO->token) || !isset($fileDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->fs->deleteFile($fileDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during the creation of the file');
        }

        return $this->rs->goodRequest();
    }

    #[Route('/rename', name: 'rename', methods: ['POST'])]
    public function renameFile(#[MapRequestPayload] FileDTO $fileDTO)
    {
        foreach ($fileDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($fileDTO->token) || !isset($fileDTO->id) || !isset($fileDTO->name)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->fs->renameFile($fileDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during the creation of the file');
        }

        return $this->rs->goodRequest();
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function saveFile(#[MapRequestPayload] FileDTO $fileDTO)
    {
        foreach ($fileDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($fileDTO->token) || !isset($fileDTO->id) || !isset($fileDTO->content)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->fs->saveContent($fileDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during the creation of the file');
        }

        return $this->rs->goodRequest();
    }

    #[Route('/get', name: 'get', methods:['POST'])]
    public function getFileContent(#[MapRequestPayload] FileDTO $fileDTO) {

        if (!isset($fileDTO->token) || !isset($fileDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $file = $this->fr->findById($fileDTO->id);

        if ($file === null) {
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during get the content of the file');
        }

        return $this->rs->goodRequest(param: [
            'id' => $file->getId(),
            'name'=> $file->getName(),
            'content'=> $file->getContent()
        ]);
    }
    #[Route('/get_context', name: 'get_context', methods:['POST'])]
    public function getFileContext(#[MapRequestPayload] FileDTO $fileDTO) {

        if (!isset($fileDTO->token) || !isset($fileDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $context = $this->fs->getContextFromId($fileDTO->id);

        if ($context === null) {
            return $this->rs->badResponse(400, 'Server error',
                'Error occurred during get the content of the file');
        }

        return $this->rs->goodRequest(param: [
            'context' => $context
        ]);
    }

}