<?php

namespace App\Support;

class TemporaryPasswordGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';

    public static function generate(int $length = 12): string
    {
        $length = max(8, $length);
        $max = strlen(self::ALPHABET) - 1;
        $chars = [];

        $chars[] = 'ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 23)];
        $chars[] = 'abcdefghijkmnopqrstuvwxyz'[random_int(0, 23)];
        $chars[] = '23456789'[random_int(0, 7)];
        $chars[] = '@#$%'[random_int(0, 3)];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = self::ALPHABET[random_int(0, $max)];
        }

        shuffle($chars);

        return implode('', $chars);
    }
}
