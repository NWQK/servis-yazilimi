<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if(DB::getDriverName()!=='mysql')return;
        $columns=['items'=>['purchase_price','sales_price'],'invoice_items'=>['amount'],'invoice_payments'=>['amount'],
            'invoice_services'=>['rate'],'quotation_items'=>['amount'],'quotation_services'=>['rate'],
            'package_transactions'=>['amount'],'subscriptions'=>['package_amount'],'service_items'=>['rate'],'service_types'=>['rate']];
        foreach($columns as $table=>$names)foreach($names as $name){
            if(!Schema::hasColumn($table,$name))continue;
            // Keep existing nullability/defaults. Only widen numeric capacity and use exact decimal storage.
            $column=collect(DB::select("SHOW COLUMNS FROM `{$table}`"))->first(fn($c)=>$c->Field===$name);
            $null=$column->Null==='YES'?'NULL':'NOT NULL';
            $default=$column->Default===null?($column->Null==='YES'?' DEFAULT NULL':''):' DEFAULT '.(float)$column->Default;
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$name}` DECIMAL(15,2) {$null}{$default}");
        }
    }
    public function down(): void
    {
        // Intentionally do not narrow back to DOUBLE(8,2): valid new amounts would be truncated.
    }
};
