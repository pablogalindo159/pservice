<?php

namespace App\Support;

use Illuminate\Support\Str;

class Stages
{
    /** @return array<int,string> */
    public static function all(): array
    {
        return config('pservice.stages');
    }

    public static function slug(string $stage): string
    {
        return Str::upper(Str::slug($stage, '_'));
    }
}
