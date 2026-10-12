<?php

namespace App\Tagging;

final class TagName
{
    public static function display(string $name): string
    {
        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }
}
