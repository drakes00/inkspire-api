<?php

namespace App\Service;

use App\DTO\DirDTO;
use App\Entity\Dir;
use App\Mapper\DirMapper;
use App\Repository\DirRepository;
use App\Repository\UserRepository;
use Exception;

class DirService
{
    public function __construct(private UserRepository $ur, private DataBase $db, private OllamaService $os,
                                private DirRepository  $dr)
    {
    }

    /**
     * Create a directory
     * @param DirDTO $dirDTO
     * @return string
     */
    public function createDir(DirDTO $dirDTO): string
    {
        // On vérifie que l'user est bien connecté
        $user = $this->ur->findByToken($dirDTO->token);

        if (!isset($user)) {
            return "Auth error";
        }

        if (!isset($dirDTO->context)) {
            $dirDTO->context = "You're an expert in redaction, you receive some texts and you are able to reformulate the content given.";
        } else {
            $dirDTO->context = $this->os->changeDirectoryContext($dirDTO->context);
        }

        if (isset($dirDTO->belong_to)) {
            $d = $this->dr->findById($dirDTO->belong_to);

            if (!isset($d)) {
                return "Server Error";
            }

            $d = DirMapper::toDir($dirDTO, $user, $d);
        } else {
            $d = DirMapper::toDir($dirDTO, $user);
        }


        // We check if a directory doesn't have the same name and belong to the same directory
        $lDir = $this->ur->getAllDirFromUser($user->getLogin());
        if ($d->getBelongTo() !== null) {
            foreach ($lDir as $dir) {
                if ($d->getBelongTo() != null) {
                    if ($dir['belong'] == $d->getBelongTo()->getId() && $d->getName() == $dir['name']) {
                        return "Server Error";
                    }
                }
            }
        } else {
            foreach ($lDir as $dir) {
                if ($dir['belong'] == null && $d->getName() == $dir['name']) {
                    return "Server Error";
                }
            }
        }


        try {
            $this->db->saveObject($d);
        } catch (Exception $e) {
            return "Database error";
        }

        try {
            $this->db->saveDB();
        } catch (Exception $e) {
            return "Database error";
        }

        return "";
    }

    /**
     * Rename a directory
     * @param DirDTO $dirDTO
     * @return bool
     */
    public function renameDir(DirDTO $dirDTO): bool
    {
        $user = $this->ur->findByToken($dirDTO->token);

        if (!isset($user)) {
            return false;
        }

        // On vérifie que le dossier existe
        $dir = $this->dr->findById($dirDTO->id);
        if (!isset($dir)) {
            return false;
        }

        $dir->setName($dirDTO->name);

        try {
            $this->db->saveDB();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a directory
     * @param DirDTO $dirDTO
     * @return bool
     */
    public function deleteDir(DirDTO $dirDTO): bool
    {
        $user = $this->ur->findByToken($dirDTO->token);

        if (!isset($user)) {
            return false;
        }

        // We check if the directory exists in the database
        $dir = $this->dr->findById($dirDTO->id);
        if (!isset($dir)) {
            return false;
        }

        // If the directory is the parent of a file or directory, we should not delete it
        $res = $this->dr->findByIdIfParent($dirDTO->id);
        if (!isset($res) || $res !== [] || count($res) !== 0) {
            return false;
        }

        $this->dr->deleteById($dirDTO->id);

        return true;
    }

    /**
     * Change the context
     * @param DirDTO $dirDTO
     * @return bool
     */
    public function renameContext(DirDTO $dirDTO) : bool
    {
        $user = $this->ur->findByToken($dirDTO->token);

        if (!isset($user)) {
            return false;
        }

        // We check if the directory exists in the database
        $dir = $this->dr->findById($dirDTO->id);
        if (!isset($dir)) {
            return false;
        }

        $dir->setContext($dirDTO->context);
        $this->db->saveDB();
        return true;
    }

    /**
     * Return the context of a directory
     * @param DirDTO $dirDTO
     * @return string
     */
    public function getContextDir(DirDTO $dirDTO): string
    {
        $user = $this->ur->findByToken($dirDTO->token);

        if (!isset($user)) {
            return "";
        }

        // We check if the directory exists in the database
        $dir = $this->dr->findById($dirDTO->id);
        if (!isset($dir)) {
            return "";
        }

        return $dir->getContext();
    }
}