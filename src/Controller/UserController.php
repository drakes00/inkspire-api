<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\Mapper\UserMapper;
use App\Repository\UserRepository;
use App\Service\DataBase;
use App\Service\ResponseService;
use App\Service\UserService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use mysql_xdevapi\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;


#[Route("/api/v1/user", "user_")]
#[AsController]
class UserController extends AbstractController
{
    // On injecte le service de response avec l'auto wiring
    public function __construct(private ResponseService $rs, private DataBase $db,
                                private UserRepository $ur, private ValidatorService $vs,
                                private UserService $us, private HtmlSanitizerInterface $hsi)
    {
    }

    /**
     * Endpoint for the signup of Users.
     * @param UserDTO $data
     * @return Response
     */
    #[Route('/signup', name: 'signup', methods: ['POST'])]
    public function signUp(#[MapRequestPayload] UserDTO $data) : Response
    {
        foreach ($data as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        $login = $data->login ?? null;
        $pass = $data->pass ?? null;

        if (!$login || !$pass || $login == "" || $pass == ""){
            return $this->rs->badResponse(400,
                'Bad Request',
                "The request has bad format");
        }
        $u = UserMapper::toEntity($data);

        $this->us->generateToken($u);

        $res = $this->vs->validateObject($u);

        if ($res != "OK"){
            return $this->rs->badResponse(400,
            'Bad Request',
            $res);
        }


        $stt = $this->db->saveObject($u);
        if (!$stt){
            return $this->rs->badResponse('500',
                'Server Error',
                'Unexpect error occurred, try again');
        }

        $error = $this->db->saveDB();
        if ($error != ""){
            return $this->rs->badResponse(400,
                'Database error',
                $error);
        }

        return $this->rs->goodRequest(param: [
            "token" => $u->getToken()
        ]);
    }

    /**
     *  Endpoint for connection
     * @param UserDTO $user
     * @return Response
     */
    #[Route("/signin", name: 'signin', methods: ['POST'])]
    public function signIn(#[MapRequestPayload] UserDTO $user) : Response
    {
        foreach ($user as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        $login = $user->login ?? null;
        $pass = $user->pass ?? null;


        if (!$login || !$pass || $login == "" || $pass == ""){
            return $this->rs->badResponse(400,
                'Bad Request',
                "The request has bad format");
        }

        $stt = $this->us->connect($user);
        $user = $this->ur->findByLogin($user->login);

        if ($stt){
            return $this->rs->goodRequest(param: [
                "token" => $user->getToken()
            ]);
        } else {
            return $this->rs->badResponse('400', 'Error connect', 'The login or password is incorrect');
        }
    }

    #[Route('/deco/{token}', name: 'deco', methods: ['POST'])]
    public function decoUserToken(string $token)
    {
        $this->hsi->sanitize($token);

        $stt = $this->us->resetUserToken($token);

        if ($stt){
            return $this->rs->goodRequest();
        } else {
            return $this->rs->badResponse(500, 'Server Error', 'Unexpected error, try again');
        }
    }


    #[Route('/tree', 'tree', methods: ['POST'])]
    public function getTreeFromUser(#[MapRequestPayload] UserDTO $userDTO)
    {
        foreach ($userDTO as $value) {
            if (isset($value)) {
                $this->hsi->sanitize($value);
            }
        }

        if (!isset($userDTO->token)){
            return $this->rs->badResponse(400,
            'Bad Request',
            'Request has bad format');
        }

        $user = $this->ur->findByToken($userDTO->token);

        if (!isset($user)){
            return $this->rs->badResponse(400, 'Unauthorized', "The token doesn't exist");
        }

        $tree = $this->us->getTree($user);

        return $this->rs->goodRequest(
            param: $tree
        );
    }
}
