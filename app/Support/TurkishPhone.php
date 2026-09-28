<?php
namespace App\Support;

class TurkishPhone
{
    public static function national(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone ?? '');
        if (strlen($digits) === 14 && str_starts_with($digits,'0090')) $digits = substr($digits,4);
        elseif (strlen($digits) === 12 && str_starts_with($digits,'90')) $digits = substr($digits,2);
        elseif (strlen($digits) === 11 && str_starts_with($digits,'0')) $digits = substr($digits,1);
        return $digits;
    }
    public static function mobile(?string $phone): ?string
    {
        $national = self::national($phone);
        return preg_match('/\A5[0-9]{9}\z/',$national) ? '+90'.$national : null;
    }
}
