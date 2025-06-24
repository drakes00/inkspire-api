<?php

namespace App\Controller;

use App\DTO\DirDTO;
use App\Repository\DirRepository;
use App\Service\DataBase;
use App\Service\DirService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/v1/dir", "dir_")]
#[AsController]
class DirController extends AbstractController
{
    public function __construct(private DirService $ds, private HtmlSanitizerInterface $hsi,
                                private ResponseService $rs)
    {
    }

    #[Route('/create', name: 'create', methods: ["POST"])]
    public function createDir( #[MapRequestPayload] DirDTO $dirDTO) : Response
    {
        foreach ($dirDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($dirDTO->token) || !isset($dirDTO->name)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->ds->createDir($dirDTO);

        if ($stt != ""){
            return $this->rs->badResponse(500, $stt,
                'Error occurred during the creation of the dir');
        }

        return $this->rs->goodRequest();
    }

    #[Route('/rename', name: 'rename', methods: ['POST'])]
    public function renameDir(#[MapRequestPayload] DirDTO $dirDTO) : Response
    {
        foreach ($dirDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($dirDTO->token) || !isset($dirDTO->name) || !isset($dirDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->ds->renameDir($dirDTO);
        if (!$stt){
            return $this->rs->badResponse(500, 'Error',
                'Error occured during the rename of the dir');
        }
        return $this->rs->goodRequest();
    }

    #[Route('/delete', name: 'delete', methods: ["POST"])]
    public function deleteDir(#[MapRequestPayload] DirDTO $dirDTO) : Response
    {
        foreach ($dirDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($dirDTO->token) || !isset($dirDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->ds->deleteDir($dirDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Error',
                'Error occured during the delete of the dir');
        }
        return $this->rs->goodRequest();
    }

    #[Route('/context/rename', name: 'rename_context', methods: ["POST"])]
    public function renameContextDir(#[MapRequestPayload] DirDTO $dirDTO) : Response
    {
        foreach ($dirDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($dirDTO->token) || !isset($dirDTO->id) || !isset($dirDTO->context)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $stt = $this->ds->renameContext($dirDTO);

        if (!$stt){
            return $this->rs->badResponse(400, 'Error',
                'Error occured during the delete of the dir');
        }
        return $this->rs->goodRequest();
    }

    #[Route('/context/get', name: 'get_context', methods: ["POST"])]
    public function getContextDir(#[MapRequestPayload] DirDTO $dirDTO) : Response
    {
        foreach ($dirDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($dirDTO->token) || !isset($dirDTO->id)){
            return $this->rs->badResponse(400, 'Bad Request',
                'The request has bad format.');
        }

        $context = $this->ds->getContextDir($dirDTO);

        if ($context === ""){
            return $this->rs->badResponse(400, 'Error',
                'Error occured during the delete of the dir');
        }
        return $this->rs->goodRequest(param: [
            "context" => $context,
        ]);
    }
}