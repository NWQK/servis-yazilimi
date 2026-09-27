<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Carbon\Carbon;

return new class extends Migration {
    public function up(): void
    {
        foreach (['cancellation_cutoff_hours'=>2, 'pending_timeout_hours'=>12] as $name=>$default) {
            if (!Schema::hasColumn('appointment_profiles', $name)) {
                Schema::table('appointment_profiles', fn (Blueprint $t) => $t->unsignedSmallInteger($name)->default($default));
            }
        }
        foreach (['pending_expires_at', 'customer_cancel_until', 'cancelled_at', 'cancellation_read_at'] as $name) {
            if (!Schema::hasColumn('appointments', $name)) {
                Schema::table('appointments', fn (Blueprint $t) => $t->dateTime($name)->nullable());
            }
        }
        if (!Schema::hasColumn('appointments', 'cancelled_by_customer')) {
            Schema::table('appointments', fn (Blueprint $t) => $t->boolean('cancelled_by_customer')->default(false));
        }
        DB::table('appointments')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $profile=DB::table('appointment_profiles')->where('id',$row->appointment_profile_id)->first();
                $start=Carbon::parse($row->starts_at);
                $values=[];
                if (!$row->customer_cancel_until) $values['customer_cancel_until']=$start->copy()->subHours($profile->cancellation_cutoff_hours ?? 2);
                if ($row->status==='pending' && !$row->pending_expires_at) {
                    $expiry=Carbon::parse($row->created_at)->addHours($profile->pending_timeout_hours ?? 12);
                    $values['pending_expires_at']=$expiry->min($start);
                }
                if ($values) DB::table('appointments')->where('id',$row->id)->update($values);
            }
        });
    }
    public function down(): void
    {
        // Keep expired records terminal for older application versions too.
        DB::table('appointments')->where('status','expired')->update(['status'=>'cancelled','occupied_at'=>null]);
        Schema::table('appointments',fn (Blueprint $t)=>$t->dropColumn(['pending_expires_at','customer_cancel_until','cancelled_at','cancellation_read_at','cancelled_by_customer']));
        Schema::table('appointment_profiles',fn (Blueprint $t)=>$t->dropColumn(['cancellation_cutoff_hours','pending_timeout_hours']));
    }
};
