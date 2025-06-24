<?php

namespace App\DTO;

class FileDTO
{
    public int $id;
    public string $token;
    public string $name;
    public string $content;
    public ?int $belong_to;
}