<?php

namespace App\Service;

class FilePathGenerator
{
    public function generate(): string
    {
        // @TODO: MS-42 - Implement the actual path generation logic.
        return 'path/to/' . uniqid();
    }
}
