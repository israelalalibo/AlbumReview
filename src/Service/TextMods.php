<?php

namespace App\Service;

class TextMods
{
    public function highlightImportant(string $s) : string
    {
        return preg_replace('/\bimportant\b/i', 'IMPORTANT', $s);

    }
}
