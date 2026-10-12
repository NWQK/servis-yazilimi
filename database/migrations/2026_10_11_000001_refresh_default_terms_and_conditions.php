<?php

use App\Services\TermsAndConditions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pages')) return;
        $pages = DB::table('pages')->where('slug', TermsAndConditions::SLUG)->get();
        foreach ($pages as $page) {
            // Preserve content edited by an administrator and the publication switch.
            if (TermsAndConditions::isLegacy($page->content)) {
                DB::table('pages')->where('id', $page->id)->update([
                    'title' => TermsAndConditions::TITLE, 'content' => TermsAndConditions::content(), 'updated_at' => now(),
                ]);
            }
        }
        if ($pages->isEmpty()) {
            DB::table('pages')->insert([
                'title' => TermsAndConditions::TITLE, 'slug' => TermsAndConditions::SLUG,
                'content' => TermsAndConditions::content(), 'enabled' => 1, 'parent_id' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Do not restore obsolete legal text or discard subsequent administrator edits.
    }
};
