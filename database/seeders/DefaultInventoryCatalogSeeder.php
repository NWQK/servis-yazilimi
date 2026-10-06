<?php
namespace Database\Seeders;

use App\Models\User;
use App\Services\DefaultInventoryCatalog;
use Illuminate\Database\Seeder;

class DefaultInventoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        User::where('type','owner')->select('id')->chunkById(100, function ($owners) {
            foreach ($owners as $owner) { app(DefaultInventoryCatalog::class)->seed($owner->id); }
        });
    }
}
