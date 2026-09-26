<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->where('name','CURRENCY')->update(['value'=>'TRY']);
        DB::table('settings')->where('name','CURRENCY_SYMBOL')->update(['value'=>'₺']);
        $names = DB::table('settings')->distinct()->pluck('name')->filter(fn ($name) => preg_match('/^(stripe|paypal|flutterwave|razorpay|paystack)_/i', $name))->all();
        if ($names) DB::table('settings')->whereIn('name',$names)->delete();
    }
    public function down(): void
    {
        // Retired secrets and previous display preferences are intentionally not restored.
    }
};
