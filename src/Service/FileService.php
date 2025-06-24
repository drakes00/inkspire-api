<?php

namespace App\Service;

use App\DTO\FileDTO;
use App\Entity\File;
use App\Mapper\FileMapper;
use App\Repository\DirRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception;

class FileService
{
    public function __construct(private UserRepository $ur, private DataBase $db, private DirRepository $dr,
                                private FileRepository $fr)
    {
    }


    /**
     * Create a file
     * @param FileDTO $fileDTO
     * @return bool
     */
    public function createFile(FileDTO $fileDTO): bool
    {
        $user = $this->ur->findByToken($fileDTO->token);

        if (!isset($user)) {
            return false;
        }

        if (isset($fileDTO->belong_to)){
            $d = $this->dr->findById($fileDTO->belong_to);

            if (!isset($d)){
                return false;
            }

            $f = FileMapper::toFile($fileDTO, $user, $d);
        } else{
            $f = FileMapper::toFile($fileDTO, $user);
        }

        // We check if a file doesn't have the same name and belong to the same directory
        $lFiles = $this->ur->getAllFilesFromUser($user->getLogin());
        if ($f->getBelongTo() !== null){
            foreach ($lFiles as $file){
                if ($file['belong'] == $f->getBelongTo()->getId() && $f->getName() == $file['name']){
                    return false;
                }
            }
        } else {
            foreach ($lFiles as $file) {
                if ($file['belong'] == null && $f->getName() == $file['name']){
                    return false;
                }
            }
        }

        try {
            $this->db->saveObject($f);
        } catch (Exception $e) {
            return false;
        }

        try {
            $this->db->saveDB();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a file
     * @param FileDTO $fileDTO
     * @return bool
     */
    public function deleteFile(FileDTO $fileDTO) : bool
    {
        $user = $this->ur->findByToken($fileDTO->token);

        if (!isset($user)) {
            return false;
        }

        $this->fr->deleteById($fileDTO->id);

        return true;
    }

    /**
     * Rename a file
     * @param FileDTO $fileDTO
     * @return bool
     */
    public function renameFile(FileDTO $fileDTO) : bool
    {
        $user = $this->ur->findByToken($fileDTO->token);

        if (!isset($user)) {
            return false;
        }

        $file = $this->fr->findById($fileDTO->id);

        if (!isset($file)) {
            return false;
        }

        $file->setName($fileDTO->name);

        try {
            $this->db->saveObject($file);
        } catch (Exception $e) {
            return false;
        }

        try {
            $this->db->saveDB();
        } catch (Exception $file) {
            return false;
        }

        return true;
    }

    /**
     * Return the context of the directory the file is the child.
     * @param int $id
     * @return string
     */
    public function getContextFromId(int $id): string
    {
        $file = $this->fr->findById($id);
        if (gettype($file) === "object"){
            $dir_belong = $file->getBelongTo();

            if (isset($dir_belong)){
                return $dir_belong->getContext();
            }
        }
        return "";
    }
    
    public function getTextFromId(int $id): string
    {
        $file = $this->fr->findById($id);
        if (gettype($file) === "object"){
            return $file->getText();
        }
        return "";
    }


    /**
     * Save all the changes of the content of a file
     * @param FileDTO $fileDTO
     * @return bool
     */
    public function saveContent(FileDTO $fileDTO) : bool
    {
        $user = $this->ur->findByToken($fileDTO->token);

        if (!isset($user)) {
            return false;
        }

        $file = $this->fr->findById($fileDTO->id);

        if (!isset($file)) {
            return false;
        }

        $file->setContent($fileDTO->content);

        try {
            $this->db->saveObject($file);
        } catch (Exception $e) {
            return false;
        }

        try {
            $this->db->saveDB();
        } catch (Exception $file) {
            return false;
        }

        return true;
    }
}