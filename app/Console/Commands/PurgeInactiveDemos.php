<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\DemoCleanup;

class PurgeInactiveDemos extends Command
{
    protected $signature='subscriptions:purge-demos {--execute : Permanently delete eligible demo businesses}';
    protected $description='Preview or remove expired, never-paid Demo businesses without a login for 30 days';
    public function handle(DemoCleanup $cleanup): int
    {
        $count=0;
        $cleanup->candidates()->select('users.id')->chunkById(50,function($owners) use($cleanup,&$count){
            foreach($owners as $owner) {
                if (!$this->option('execute') || $cleanup->delete($owner->id)) $count++;
            }
        });
        $this->info(($this->option('execute') ? 'Silinen demo işletmesi: ' : 'Silinmeye uygun demo işletmesi (ön izleme): ').$count);
        return self::SUCCESS;
    }
}
