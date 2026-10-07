<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_legacy_brand_migration_preserves_other_content_and_is_idempotent()
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => !str_contains($path, 'version_1_7_filled')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        $id = DB::table('home_pages')->insertGetId(['title' => 'About Service Hub', 'content' => '<h1>Service Hub SaaS</h1>',
            'content_value' => json_encode(['title' => 'ATAXNET - Araç Servis Platformu', 'custom' => 'Özel açıklama', 'image' => 'upload/homepage/test.png']), 'enabled' => 0, 'parent_id' => 8]);
        DB::table('settings')->insert([
            ['name' => 'app_name', 'value' => 'Service Hub SaaS', 'parent_id' => 8],
            ['name' => 'copyright', 'value' => '© ATAXNET', 'parent_id' => 8],
            ['name' => 'company_name', 'value' => 'Özel işletme', 'parent_id' => 8],
            ['name' => 'smtp_password', 'value' => 'Service Hub', 'parent_id' => 8],
        ]);
        $migration = require database_path('migrations/2026_10_07_000003_normalize_platform_branding.php');
        $migration->up();
        $page = DB::table('home_pages')->find($id);
        $this->assertSame('sanayirandevu.com hakkında', $page->title);
        $this->assertSame('<h1>sanayirandevu.com</h1>', $page->content);
        $details = json_decode($page->content_value, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('sanayirandevu.com - Araç Servis ve Randevu Yönetimi', $details['title']);
        $this->assertSame('Özel açıklama', $details['custom']);
        $this->assertSame('upload/homepage/test.png', $details['image']);
        $this->assertEquals(0, $page->enabled);
        $this->assertSame('sanayirandevu.com', DB::table('settings')->where('name', 'app_name')->value('value'));
        $this->assertSame('© sanayirandevu.com', DB::table('settings')->where('name', 'copyright')->value('value'));
        $this->assertSame('Özel işletme', DB::table('settings')->where('name', 'company_name')->value('value'));
        $this->assertSame('Service Hub', DB::table('settings')->where('name', 'smtp_password')->value('value'));
        $migration->up();
        $this->assertEquals($page, DB::table('home_pages')->find($id));
    }

    public function test_translation_json_and_homepage_defaults_use_current_brand()
    {
        foreach (glob(resource_path('lang/*.json')) as $file) {
            $translations = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($translations);
            $this->assertDoesNotMatchRegularExpression('/service[ _-]*hub|ataxnet/i', file_get_contents($file));
        }
        $this->assertStringContainsString('sanayirandevu.com', file_get_contents(base_path('.env.example')));
        $this->assertDoesNotMatchRegularExpression('/service[ _-]*hub|ataxnet/i', file_get_contents(app_path('Helper/helper.php')));
        $this->assertDoesNotMatchRegularExpression('/service[ _-]*hub|ataxnet/i', file_get_contents(resource_path('views/layouts/landing.blade.php')));
    }
}
