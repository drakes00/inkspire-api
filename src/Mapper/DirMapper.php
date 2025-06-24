<?php

namespace App\Mapper;

use App\DTO\DirDTO;
use App\Entity\Dir;
use App\Entity\User;

class DirMapper
{

    /**
     * Map a DirDTO in Dir
     * @param DirDTO $dirDTO
     * @param User $user
     * @param Dir|null $dir
     * @return Dir
     */
    public static function toDir(DirDTO $dirDTO, User $user, Dir $dir = null) : Dir
    {
        $d = new Dir();

        if (isset($dirDTO->name)){
            $d->setName($dirDTO->name);
        }

        $d->setLogin($user);

        if (isset($dirDTO->belong_to)){
            $d->setBelongTo($dir);
        }

        if (isset($dirDTO->context)){
            $d->setContext($dirDTO->context);
        } else {
            $d->setContext("Prompt système a remplir"); //TODO
        }

        return $d;
    }
}