<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

class FinancialAmount
{
    public const MAX_CENTS = 999999999999;
    public static function check(float $amount): void
    {
        if(!is_finite($amount) || abs($amount)>self::MAX_CENTS/100)
            throw ValidationException::withMessages(['amount'=>'İşlem tutarı desteklenen sınırı aşıyor (9.999.999.999,99 TL). Miktar ve fiyatı kontrol edin.']);
    }
    public static function multiply($price,int $quantity=1): string
    {
        $price=(float)$price;self::check($price);
        $unit=(int)round($price*100);
        if($quantity!==0 && abs($unit)>intdiv(self::MAX_CENTS,abs($quantity)))self::check(INF);
        return number_format($unit*$quantity/100,2,'.','');
    }
}
