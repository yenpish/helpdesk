<?php

namespace App\Support;

class MalaysianPhoneNumber
{
    public static function canonicalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '60' . substr($digits, 1);
        }

        return $digits;
    }
}
