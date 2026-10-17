<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\User;
class CheckIntegrity extends Command {
    protected $signature='platform:check-integrity {--owner=}';
    protected $description='Stok, fatura ve ödeme tutarlılığını veri değiştirmeden kontrol eder.';
    public function handle(): int {
        User::where('type','owner')->when($this->option('owner'),fn($q)=>$q->where('id',$this->option('owner')))->orderBy('id')->chunkById(50,function($owners){foreach($owners as $owner){
            if(\DB::table('business_recovery_locks')->where('owner_id',$owner->id)->exists())continue;
            app(\App\Services\IntegrityCheck::class)->run($owner->id);
        }});
        $this->info('Tutarlılık kontrolü tamamlandı.');return 0;
    }
}
