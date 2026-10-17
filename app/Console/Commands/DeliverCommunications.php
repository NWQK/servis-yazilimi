<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class DeliverCommunications extends Command {
    protected $signature='communications:work {--limit=40}';
    protected $description='Şifreli mesaj kuyruğunu işler; belirsiz gönderimleri tekrar etmez.';
    public function handle(): int {
        $count=app(\App\Services\CommunicationOutbox::class)->work(max(1,min(200,(int)$this->option('limit'))));
        $ids=DB::table('communication_outbox')->whereIn('status',['accepted','failed','unknown','cancelled'])->where('updated_at','<',now()->subDays(30))->orderBy('id')->limit(500)->pluck('id');
        DB::table('communication_outbox')->whereIn('id',$ids)->delete();
        $this->info($count.' mesaj işlendi.');return 0;
    }
}
