<?php

namespace App\DTO;

class DirDTO
{
    public int $id;
    public string $name;
    public string $token;
    public ?int $belong_to;
    public string $context;
}