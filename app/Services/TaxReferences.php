<?php
namespace App\Services;

use App\Models\Tax;
use Illuminate\Support\Facades\DB;

class TaxReferences
{
    private static function contains($query, string $column, int $id)
    {
        return $query->where(function ($q) use ($column, $id) {
            $q->where($column,(string)$id)->orWhere($column,'like',$id.',%')
                ->orWhere($column,'like','%,'.$id)->orWhere($column,'like','%,'.$id.',%');
        });
    }
    public static function invoiced(Tax $tax): bool
    {
        foreach(['invoice_items','invoice_services'] as $table)
            if(self::contains(DB::table($table)->where('parent_id',$tax->parent_id),'tax',$tax->id)->exists()) return true;
        return false;
    }
    public static function used(Tax $tax): bool
    {
        if(self::invoiced($tax)) return true;
        foreach(['items'=>'taxs','service_types'=>'tax','service_items'=>'tax','quotation_items'=>'tax','quotation_services'=>'tax'] as $table=>$column)
            if(\Schema::hasColumn($table,$column) && self::contains(DB::table($table)->where('parent_id',$tax->parent_id),$column,$tax->id)->exists()) return true;
        return DB::table('services')->where('parent_id',$tax->parent_id)->where('external_labor_tax_id',$tax->id)->exists();
    }
}
