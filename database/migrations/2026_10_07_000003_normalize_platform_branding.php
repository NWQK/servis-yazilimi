<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    private function normalize(?string $value): ?string
    {
        if ($value === null) return null;
        $value = str_replace([
            'Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops. It provides an integrated solution to effectively manage day-to-day operations, enhance productivity, and improve customer satisfaction.',
            'Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops.',
            'Service Hub - Vehicle Repair Center Management',
            'About Service Hub',
            'ATAXNET - Araç Servis Platformu',
        ], [
            'sanayirandevu.com; araç servislerinin randevu, müşteri, araç, servis, stok ve fatura işlemlerini tek yerden yönetmesini sağlar.',
            'Araç servisleri ve atölyeler için sanayirandevu.com yönetim platformu.',
            'sanayirandevu.com - Araç Servis ve Randevu Yönetimi',
            'sanayirandevu.com hakkında',
            'sanayirandevu.com - Araç Servis ve Randevu Yönetimi',
        ], $value);
        return preg_replace('/\b(?:service[ _-]*hub(?:[ _-]*saas)?|ataxnet)\b/i', 'sanayirandevu.com', $value);
    }

    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('home_pages')) {
                DB::table('home_pages')->orderBy('id')->chunkById(100, function ($pages) {
                    foreach ($pages as $page) {
                        $changes = [];
                        foreach (['title', 'content', 'content_value'] as $field) {
                            $value = $field === 'content_value' ? $this->normalizeJson($page->{$field}) : $this->normalize($page->{$field});
                            if ($value !== $page->{$field}) $changes[$field] = $value;
                        }
                        if ($changes) DB::table('home_pages')->where('id', $page->id)->update($changes);
                    }
                });
            }
            if (Schema::hasTable('settings')) {
                DB::table('settings')->whereIn('name', ['app_name', 'copyright'])->orderBy('id')->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $value = $this->normalize($row->value);
                        if ($row->name === 'app_name' && trim((string) $value) === 'Laravel') $value = 'sanayirandevu.com';
                        if ($value !== $row->value) DB::table('settings')->where('id', $row->id)->update(['value' => $value]);
                    }
                });
            }
            if (Schema::hasTable('appointment_sms_settings') && Schema::hasColumn('appointment_sms_settings', 'brand')) {
                foreach (DB::table('appointment_sms_settings')->get(['id', 'brand']) as $row) {
                    $value = $this->normalize($row->brand);
                    if ($value !== $row->brand) DB::table('appointment_sms_settings')->where('id', $row->id)->update(['brand' => $value]);
                }
            }
        });
    }

    private function normalizeJson(?string $value): ?string
    {
        if ($value === null) return null;
        $decoded = json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) return $this->normalize($value);
        $walk = function ($entry) use (&$walk) {
            if (is_string($entry)) return $this->normalize($entry);
            if (is_array($entry)) return array_map($walk, $entry);
            if (is_object($entry)) {
                $result = clone $entry;
                foreach ($entry as $key => $child) $result->{$key} = $walk($child);
                return $result;
            }
            return $entry;
        };
        $normalized = $walk($decoded);
        return $normalized == $decoded ? $value : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function down(): void
    {
        // Content branding is intentionally kept when rolling back; no schema is changed.
    }
};
