<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ModelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/models', name: 'app_api_models_')]
class ModelController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user, ModelRepository $modelRepository): Response
    {
        // Check if the user is authenticated.
        if (null === $user) {
            return $this->json([
                'message' => 'missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $models = $modelRepository->findAll();

        $data = [];
        foreach ($models as $model) {
            $data[] = [
                'id' => $model->getId(),
                'name' => $model->getName(),
            ];
        }

        return $this->json($data);
    }
}
