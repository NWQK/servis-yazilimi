<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegister
{
    public const CHANNELS=['cash'=>'Nakit','card'=>'Kart','transfer'=>'Havale / EFT','unclassified'=>'Belirtilmemiş'];
    public static function cents($value): int {return (int)round((float)$value*100);}
    public static function money(int $value): string {return number_format($value/100,2,'.','');}
    public function summary(int $owner,string $date): array
    {
        $income=$expense=array_fill_keys(array_keys(self::CHANNELS),0);$hash=hash_init('sha256');
        foreach(['invoice_payments','counter_sales','expenses'] as $table){
            $q=DB::table($table)->where($table.'.parent_id',$owner);
            if($table==='invoice_payments'){
                $q->where('payment_date',$date)->where(fn($q)=>$q->whereNull('payment_status')->orWhereIn('payment_status',['success','succeeded']))
                    ->whereExists(fn($q)=>$q->selectRaw('1')->from('invoices')->whereColumn('invoices.id','invoice_payments.invoice_id')->where('invoices.parent_id',$owner));
            }else $q->where($table==='expenses'?'date':'sale_date',$date);
            $q->select($table.'.*')->orderBy($table.'.id')->chunkById(200,function($rows)use($table,&$income,&$expense,$hash){foreach($rows as $row){
                $channel=array_key_exists($row->cash_channel,self::CHANNELS)?$row->cash_channel:'unclassified';
                $amount=self::cents($row->amount);if($table==='expenses')$expense[$channel]+=$amount;else $income[$channel]+=$amount;
                hash_update($hash,json_encode([$table,$row->id,$row->amount,$channel,$row->updated_at??null]));
            }},$table.'.id','id');
        }
        return ['income'=>$income,'expense'=>$expense,'hash'=>hash_final($hash)];
    }
    public function close(int $owner,array $data): object
    {
        return app(InventoryAccounting::class)->transaction($owner,function()use($owner,$data){
            $prior=DB::table('cash_closings')->where('parent_id',$owner)->where('business_date',$data['business_date'])->lockForUpdate()->first();
            if($prior){
                if(self::cents($prior->opening)!==self::cents($data['opening']) || self::cents($prior->counted)!==self::cents($data['counted']) || (string)$prior->note!==(string)($data['note']??''))
                    throw ValidationException::withMessages(['closing'=>'Bu gün zaten kapatılmış. Kapanış kaydı değiştirilemez.']);
                return $prior;
            }
            $summary=$this->summary($owner,$data['business_date']);
            if(!hash_equals($summary['hash'],$data['source_hash']))throw ValidationException::withMessages(['closing'=>'Tahsilat veya giderler değişti. Sayfayı yenileyip kasayı yeniden kontrol edin.']);
            $expected=self::cents($data['opening'])+$summary['income']['cash']-$summary['expense']['cash'];
            $id=DB::table('cash_closings')->insertGetId(['parent_id'=>$owner,'business_date'=>$data['business_date'],'actor_id'=>auth()->id(),
                'actor_name'=>auth()->user()->name,'opening'=>$data['opening'],'counted'=>$data['counted'],'expected'=>self::money($expected),
                'difference'=>self::money(self::cents($data['counted'])-$expected),'snapshot'=>json_encode($summary),'source_hash'=>$summary['hash'],
                'note'=>$data['note']??null,'created_at'=>now(),'updated_at'=>now()]);
            return DB::table('cash_closings')->find($id);
        });
    }
}
