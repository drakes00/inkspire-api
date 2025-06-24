<?php

namespace App\Mapper;

use App\DTO\FileDTO;
use App\Entity\Dir;
use App\Entity\File;
use App\Entity\User;
use App\Repository\UserRepository;
use phpDocumentor\Reflection\Types\Static_;

class FileMapper
{
    public function __construct()
    {
    }

    /**
     * Map a FileDTO in a File object
     * @param FileDTO $fileDTO
     * @param User $user
     * @param Dir|null $dir
     * @return File
     */
    public static function toFile(FileDTO $fileDTO, User $user, ?Dir $dir = null) : File
    {
        $f = new File();

        $f->setLogin($user);
        $f->setName($fileDTO->name);

        if (isset($fileDTO->belong_to)){
            $f->setBelongTo($dir);
        }

        return $f;
    }
}