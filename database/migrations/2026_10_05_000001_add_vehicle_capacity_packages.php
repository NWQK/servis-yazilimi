<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('subscriptions', 'vehicle_limit')) Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('vehicle_limit')->nullable();
        });
        if (!Schema::hasColumn('subscriptions', 'vehicle_block_amount')) Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('vehicle_block_amount', 12, 2)->nullable();
        });
        if (!Schema::hasTable('vehicle_capacity_requests')) Schema::create('vehicle_capacity_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedInteger('vehicles')->default(500);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        // Existing assignments and packages are retained. Prices must be set by the administrator.
        foreach ([50, 500, 1000, 2000, 3000] as $limit) {
            if (!DB::table('subscriptions')->where('vehicle_limit', $limit)->exists()) {
                $data = ['title' => $limit === 50 ? 'Demo' : $limit . ' Araç', 'vehicle_limit' => $limit, 'package_amount' => 0,
                    'interval' => $limit === 50 ? 'Unlimited' : 'Monthly', 'user_limit' => 0, 'client_limit' => 0, 'employee_limit' => 0,
                    'enabled_logged_history' => 1, 'created_at' => now(), 'updated_at' => now()];
                if (DB::table('subscriptions')->where('title', $data['title'])->exists()) {
                    $data['title'] .= ' — ' . $limit . ' araç paketi';
                }
                foreach (['enabled_openai', 'enabled_n8n'] as $column) {
                    if (Schema::hasColumn('subscriptions', $column)) $data[$column] = 0;
                }
                DB::table('subscriptions')->insert($data);
            }
        }
    }

    public function down()
    {
        // Keep commercial history and package assignments intact on rollback.
        // This additive migration intentionally does not delete capacity or package records.
    }
};
