<?php

namespace App\Services;

class TermsAndConditions
{
    public const SLUG = 'terms_conditions';
    public const TITLE = 'Şartlar ve koşullar';
    public const VERSION = '20 Eki 2026';

    public static function content(): string
    {
        return file_get_contents(resource_path('legal/terms.html'));
    }

    public static function isLegacy(string $content): bool
    {
        return str_contains($content, '[Your Company Name]') && str_contains($content, 'Acceptance of Terms');
    }
}
