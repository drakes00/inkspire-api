<?php

namespace App\Service;

use App\DTO\UserDTO;
use App\Entity\Dir;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OAuthProvider;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;

class UserService
{
    public function __construct(private UserRepository $ur, private DataBase $db)
    {
    }

    /**
     * Create a token and associate it with a user
     * @param User $u
     * @return void
     * @throws RandomException
     */
    public function generateToken(User $u): void
    {
        $user = new User();
        while ($user != null) {
            $token = bin2hex(random_bytes(32));
            $user = $this->ur->findByToken($token);
        }

        $u->setToken($token);
        $this->db->saveDB();
    }

    /**
     * Try to connect a user
     * @param UserDTO $user
     * @return bool
     * @throws RandomException
     */
    public function connect(UserDTO $user): bool
    {
        $users = $this->ur->findAll();

        if (!isset($users)) {
            return false;
        }

        $us_login = $this->ur->findByLogin($user->login);

        if (!isset($us_login)) {
            return false;
        } else {
            if ($us_login->getPassword() === $user->pass) {
                $this->generateToken($us_login);
                return true;
            }
        }
        return false;
    }

    /**
     * Reset the token of a user
     * @param string $token
     * @return bool
     */
    public function resetUserToken(string $token): bool
    {
        $user = $this->ur->findByToken($token);
        if (!isset($user)) {
            return false;
        } else {
            $user->setToken(null);

            $stt = $this->db->saveDB();

            if ($stt == '') {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Return all the files and directory of a user in an array in the correct order (parent, child)
     * This is used in the front-end application
     * @param User $user
     * @return array
     */
    public function getTree(User $user): array
    {
        // We get all the files
        $lFiles = $this->ur->getAllFilesFromUser($user->getLogin());

        // We get all the directories
        $lDir = $this->ur->getAllDirFromUser($user->getLogin());

        $return = array();

        // We sort the to get the directory at the root first
        usort($lDir, function ($a, $b) {
            // If $belong is null, we want to put it first (root)
            if ($a['belong'] === null && $b['belong'] !== null) {
                return 1;
            }
            if ($a['belong'] !== null && $b['belong'] === null) {
                return -1;
            }

            // If there's no null, compare normally
            return $b['belong'] <=> $a['belong'];
        });

        // We start by managing the directory
        while (!empty($lDir)) {
            // $poped has the directory we are working on
            $poped = array_pop($lDir);

            // We check if the directory is at the root
            if ($poped['belong'] != null) {
                // We are in the case where the current directory is not at the root

                // We have a list of the discovered node
                $marked_dir = [];

                foreach($return as $key => $value) {
                    if ($poped['belong'] === $value['id']) {
                        // On set the directory as "children" of the directory he belongs to
                        $return[$key]['children'][] = [
                            "id" => $poped['id'],
                            "name" => $poped['name'],
                            "type" => 'D',
                            'children' => []
                        ];
                    }

                    // If the directory we are looking into is not in the list of the discovered node, we explore it
                    if (!in_array($value['id'], $marked_dir) && isset($value['children'])){
                        $this->explorer($marked_dir, $return[$key], $poped, 'D');
                    }
                }

            } else {
                // Set the directory in the return array in the "first level"
                $return[] = [
                    "id" => $poped['id'],
                    "name" => $poped['name'],
                    "type" => 'D',
                    'children' => []
                ];
            }
        }

        // We do exactly the same thing for the files
        foreach ($lFiles as $file) {
            $marked_dir = [];

            if ($file['belong'] === null) {
                $return[] = [
                    "id" => $file['id'],
                    "name" => $file['name'],
                    "type" => 'F'
                ];
            }

            foreach ($return as $key => $actual_dir) {
                // If it's a file, we skip it because a file cannot contain anything
                if ($actual_dir['type'] === 'F') {
                    break;
                }

                $marked_dir[] = $actual_dir['id'];

                if ($file['belong'] === $actual_dir['id']) {
                    $return[$key]['children'][] = [
                        "id" => $file['id'],
                        "name" => $file['name'],
                        "type" => 'F'
                    ];
                }

                if (isset($actual_dir['children'])) {
                    $this->explorer($marked_dir, $return[$key], $file, 'F');
                }
            }
        }

        return $return;
    }

    /**
     *
     * Visit all the "children" (sub directories / sub files) until he find the correct place where set the current
     * directory or file
     *
     * @param array $marked
     * @param array $child
     * @param array $search
     * @param string $type
     * @return void
     */
    public function explorer(array $marked, array &$child, array $search, string $type) : void
    {
        $marked[] = $child['id'];

        foreach ($child['children'] as $key => &$value){

            if ($value['id'] === $search['belong'] && $value['type'] === 'D'){
                // If we find it, we add it as children and we stop the research
                $value['children'][] = [
                    "id" => $search['id'],
                    "name" => $search['name'],
                    "type" => $type
                ];
                return;
            }

            if (!in_array($value, $marked) && isset($value['children'])){
                $this->explorer($marked, $value, $search, $type);
            }
        }
    }
}