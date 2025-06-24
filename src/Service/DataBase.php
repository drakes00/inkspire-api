<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\Cast\Object_;

class DataBase
{
    public function __construct(private EntityManagerInterface $emi)
    {
    }

    /**
     * Persist an object in the db.
     * Return true if there's no error
     * @param Object $o
     * @return bool
     */
    public function saveObject(Object $o) : bool
    {
        try {
            $this->emi->persist($o);
        } catch (Exception $e){
            return false;
        }
        return true;
    }

    /**
     * Save the updated object and persist.
     * @return string
     */
    public function saveDB(): string
    {
        try {
            $this->emi->flush();
        } catch (Exception $e){
            // We change the message if the username already exist in order to show it correctly in the front-end
            if ($e->getMessage() == 'An exception occurred whilsi un username existe déjàe executing a query: SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: user.login'){
                return 'Username already exists';
            } else {
                return $e->getMessage();
            }
        }
        return "";
    }
}