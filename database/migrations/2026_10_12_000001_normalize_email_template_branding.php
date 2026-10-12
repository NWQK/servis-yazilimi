<?php

use App\Services\EmailBranding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) return;
        DB::transaction(function () {
            DB::table('notifications')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach (['name','subject','message'] as $field) {
                        $normalized = $field === 'message' ? EmailBranding::html($row->{$field}) : EmailBranding::text($row->{$field});
                        if ($normalized !== $row->{$field}) $changes[$field] = $normalized;
                    }
                    if ($changes) DB::table('notifications')->where('id',$row->id)->update($changes);
                }
            });
        });
    }

    public function down(): void
    {
        // Keep corrected branding and subsequent administrator edits.
    }
};
