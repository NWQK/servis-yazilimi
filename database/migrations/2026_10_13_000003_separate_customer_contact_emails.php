<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('users','contact_email')) Schema::table('users', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->index();
        });
        DB::transaction(function () {
            // Old customer reset links must not become usable on a future account with that email.
            DB::table('password_resets')->whereIn('email', DB::table('users')->select('email')->where('type','client')->whereNotNull('email'))->delete();
            DB::table('users')->where('type','client')->whereNotNull('email')->update([
                'contact_email'=>DB::raw('email'), 'email'=>null,
            ]);
            DB::table('users')->where('type','client')->update(['remember_token'=>null, 'email_verification_token'=>null]);
        });
    }

    public function down()
    {
        $contacts = DB::table('users')->where('type','client')->whereNotNull('contact_email');
        $duplicate = (clone $contacts)->select('contact_email')->groupBy('contact_email')->havingRaw('COUNT(*) > 1')->exists();
        $collision = (clone $contacts)->whereIn('contact_email', DB::table('users')->select('email')->whereNotNull('email'))->exists();
        if ($duplicate || $collision) throw new RuntimeException('Müşteri e-postaları giriş hesaplarıyla çakışıyor. Veri kaybını önlemek için geri alma durduruldu.');
        DB::table('users')->where('type','client')->update(['email'=>DB::raw('contact_email')]);
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_contact_email_index');
            $table->dropColumn('contact_email');
        });
    }
};
