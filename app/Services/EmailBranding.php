<?php

namespace App\Services;

class EmailBranding
{
    public const NAME = 'sanayirandevu.com';

    public static function text(?string $value): ?string
    {
        if ($value === null) return null;
        // Never rewrite destinations, addresses or template variables as brand text.
        $parts = preg_split('~((?:https?://|mailto:)[^\s<>]+|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\{[a-z_][a-z0-9_]*\})~iu', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = preg_replace('/\b(?:service[ _-]*hub(?:[ _-]*saas)?|ataxnet)\b/iu', self::NAME, $part);
            }
        }
        return implode('', $parts);
    }

    public static function html(?string $value): ?string
    {
        if ($value === null) return null;
        $parts = preg_split('/(<[^>]*>)/s', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) $parts[$index] = self::text($part);
        }
        return implode('', $parts);
    }

    public static function logo(): string
    {
        return rtrim(config('app.url'), '/').'/images/brand/sanayirandevu-email-logo.png';
    }
}
