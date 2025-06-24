<?php

namespace App\DTO;
/**
 * Class which represent the type of the body of the sign-up request.
 */
class UserDTO
{
    public string $login;
    public string $pass;
    public string $token;
}