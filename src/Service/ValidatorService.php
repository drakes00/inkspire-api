<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidatorService
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validateObject(Object $o) : string
    {
        $errors = $this->validator->validate($o);

        if (count($errors) > 0) {
            $errorstring = '';
            foreach ($errors as $error){
                $errorstring .= $error->getMessageTemplate();
            }
            return $errorstring;
        }
        return "OK";
    }

}