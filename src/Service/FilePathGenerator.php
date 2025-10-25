<?php

namespace App\Service;

class FilePathGenerator
{
    private string $projectRoot;
    private string $basePathRelative = "var/files";

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function generate(string $title, string $extension = 'ink'): string
    {
        // 1. Convert to lowercase.
        $filename = strtolower($title);

        // 2. Replace non-alphanumeric characters (except spaces and dashes) with nothing.
        // This removes parentheses, special symbols, etc.
        $filename = preg_replace('/[^\w\s-]/', '', $filename);

        // 3. Replace all spaces and underscores with a single dash.
        $filename = preg_replace('/[\s_]+/', '-', $filename);

        // 4. Collapse multiple dashes into a single dash.
        $filename = preg_replace('/-+/', '-', $filename);

        // 5. Trim any leading or trailing dashes (to enforce no dash at start/end).
        $filename = trim($filename, '-');

        // 6. Ensure the filename is not empty (e.g., if input was just symbols).
        if (empty($filename)) {
            return 'default-file.' . strtolower($extension);
        }

        // 7. Append the extension.
        $filename .=  '.' . strtolower($extension);

        // Check that the form is correct.
        assert(preg_match("/^[a-z]+(-[a-z]+)*(-[0-9]+)?\.ink$/i", $filename) === 1);
        return $this->projectRoot . '/' . $this->basePathRelative . "/" . $filename;
    }
}
