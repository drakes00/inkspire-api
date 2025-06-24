<?php

namespace App\Mapper;

use App\DTO\UserDTO;
use App\Entity\User;

class UserMapper
{
    /**
     * Map a UserDTO in User object
     * @param UserDTO $sr
     * @return User
     */
    public static function toEntity(UserDTO $sr) : User
    {
        $u = new User();
        $u->setLogin($sr->login);
        $u->setPassword($sr->pass);

        return $u;
    }
}