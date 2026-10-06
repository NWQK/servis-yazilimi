<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefaultInventoryCatalog
{
    public const VAT_RATES = [20, 10, 1];
    public const UNITS = [
        'Adet'=>['adet','pcs'], 'Litre'=>['litre','lt','l','liter','liters'],
        'Mililitre'=>['mililitre','ml','milliliter','millilitre'],
        'Kilogram'=>['kilogram','kg'], 'Gram'=>['gram','gr','g'],
        'Metre'=>['metre','m','meter'], 'Santimetre'=>['santimetre','cm'],
        'Takım'=>['takim','set'], 'Çift'=>['cift','pair'],
        'Paket'=>['paket','pack'], 'Kutu'=>['kutu','box'], 'Bidon'=>['bidon'],
    ];

    private function normalize(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim($value ?? ''), 'UTF-8')));
    }

    public function seed(int $ownerId): void
    {
        DB::transaction(function () use ($ownerId) {
            User::whereKey($ownerId)->where('type','owner')->lockForUpdate()->firstOrFail();
            $taxes = DB::table('taxes')->where('parent_id',$ownerId)->get();
            foreach (self::VAT_RATES as $rate) {
                $exists = $taxes->contains(function ($tax) use ($rate) {
                    $name = $this->normalize($tax->title);
                    return (float)$tax->rate === (float)$rate &&
                        (str_starts_with($name,'kdv') || str_starts_with($name,'katmadegervergisi'));
                });
                if (!$exists) {
                    DB::table('taxes')->insert(['parent_id'=>$ownerId,'title'=>'KDV %'.$rate,'rate'=>$rate,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
            $existingUnits = DB::table('units')->where('parent_id',$ownerId)->pluck('unit')->map(fn($value)=>$this->normalize($value));
            foreach (self::UNITS as $name=>$aliases) {
                if ($existingUnits->intersect($aliases)->isNotEmpty()) { continue; }
                DB::table('units')->insert(['parent_id'=>$ownerId,'unit'=>$name,'created_at'=>now(),'updated_at'=>now()]);
            }
        });
    }
}
