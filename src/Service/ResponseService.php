<?php

namespace App\Service;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Json;

class ResponseService
{
    /**
     * Return a Response for the bad API call
     * @param int $code
     * @param string $error
     * @param string $message
     * @return Response
     */
    public function badResponse(int $code, string $error, string $message) : Response
    {
        $res = [
            'code' => $code,
            'error' => $error,
            'message' => $message
        ];
        return new Response(json_encode($res, flags: JSON_PRETTY_PRINT),$code);
    }

    /**
     * Create a 200 Response
     * @return Response
     */
    public function goodRequest($param = null, $debug = null) : Response
    {
        if (isset($debug)){
            $res = [
                'code' => 200,
                'debug'=> json_encode($debug)
            ];
        } elseif (isset($param)){
            $res = [
                'code' => 200,
                'message'=> 'OK',
                'param' => $param
            ];
        }
        else {
            $res = [
                'code' => 200,
                'message' => 'OK'
            ];
        }
        return new Response(json_encode($res,flags: JSON_PRETTY_PRINT), 200);
    }
}