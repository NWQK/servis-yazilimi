<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB, Crypt};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('twofa_last_used_at')->nullable();
            $table->text('twofa_recovery_codes')->nullable();
            $table->boolean('twofa_required')->default(false);
        });
        Schema::create('two_factor_admin_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action',20);
            $table->string('reason',500);
            $table->timestamp('created_at');
            $table->index(['owner_id','created_at']);
        });
        DB::table('users')->whereNotNull('twofa_secret')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (preg_match('/^[A-Z2-7]+$/D', $user->twofa_secret)) {
                    DB::table('users')->where('id',$user->id)->update(['twofa_secret'=>Crypt::encryptString($user->twofa_secret)]);
                }
            }
        });
    }
    public function down(): void
    {
        DB::table('users')->whereNotNull('twofa_secret')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (!preg_match('/^[A-Z2-7]+$/D', $user->twofa_secret)) {
                    DB::table('users')->where('id',$user->id)->update(['twofa_secret'=>Crypt::decryptString($user->twofa_secret)]);
                }
            }
        });
        Schema::dropIfExists('two_factor_admin_actions');
        Schema::table('users', fn(Blueprint $table)=>$table->dropColumn(['twofa_last_used_at','twofa_recovery_codes','twofa_required']));
    }
};
