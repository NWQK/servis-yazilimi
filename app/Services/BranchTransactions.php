<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class BranchTransactions
{
    public static function run(callable $work, int $attempts=1)
    {
        if(DB::transactionLevel()>0) return DB::transaction($work,$attempts);
        for($attempt=1;;$attempt++){
            // The access check reads the group before waiting on the owner lock.
            // A repeatable-read snapshot taken there would hide a concurrently committed payment/transfer.
            // This applies only to the next transaction, never to the connection's session default.
            if(DB::getDriverName()==='mysql') DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            try{return DB::transaction($work,1);}
            catch(QueryException $e){
                if($attempt >= $attempts || !in_array((int)($e->errorInfo[1] ?? 0),[1205,1213],true)) throw $e;
                usleep(random_int(5000,20000));
            }
        }
    }
}
