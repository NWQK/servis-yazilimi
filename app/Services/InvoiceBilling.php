<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InvoiceBilling
{
    public static function customer(int $owner, ?int $client): array
    {
        $user = \App\Models\User::where('parent_id', $owner)->find($client);
        if (!$user) return [];
        $profile = \App\Models\Client::where('parent_id', $owner)->where('user_id', $user->id)->first();
        $phone = preg_replace('/\D/', '', $user->phone_number ?? '');
        if (str_starts_with($phone, '90') && strlen($phone) === 12) $phone = substr($phone, 2);
        if (str_starts_with($phone, '0') && strlen($phone) === 11) $phone = substr($phone, 1);
        return ['name' => $user->name, 'email' => $user->email,
            'phone' => preg_match('/^[2-5][0-9]{9}$/', $phone) ? $phone : '',
            'address' => $profile->address ?? '', 'city' => $profile->city ?? '',
            'district' => $profile->state ?? '', 'postcode' => $profile->zip_code ?? ''];
    }

    public static function fields(bool $business = false): array
    {
        $fields = ['name' => $business ? 'İşletme / ticaret unvanı' : 'Ad soyad / unvan',
            'tax_office' => 'Vergi dairesi', 'tax_number' => 'TCKN / VKN',
            'address' => 'Adres', 'city' => 'İl', 'district' => 'İlçe', 'postcode' => 'Posta kodu',
            'email' => 'E-posta', 'phone' => 'Telefon'];
        if ($business) $fields += ['website' => 'Web sitesi', 'mersis' => 'MERSİS numarası', 'trade_registry' => 'Ticaret sicil numarası'];
        return $fields;
    }

    public static function validated(Request $request, bool $business = false): array
    {
        $rules = ['billing' => 'nullable|array'];
        foreach (self::fields($business) as $key => $label) {
            $rule = match ($key) {
                'email' => 'nullable|email|max:254',
                'tax_number' => 'nullable|regex:/^[0-9]{10,11}$/',
                'postcode' => 'nullable|regex:/^[0-9]{5}$/',
                'phone' => 'nullable|regex:/^[2-5][0-9]{9}$/',
                'mersis' => 'nullable|regex:/^[0-9]{16}$/',
                default => 'nullable|string|max:'.($key === 'address' ? '1000' : '255'),
            };
            $rules['billing.'.$key] = $rule;
        }
        $labels = [];
        foreach (self::fields($business) as $key => $label) $labels['billing.'.$key] = $label;
        $validated = $request->validate($rules, [], $labels);
        // Explicit allowlist: nested validation must not persist arbitrary request keys.
        return array_intersect_key($validated['billing'] ?? [], array_flip(array_keys(self::fields($business))));
    }

    public static function business(int $owner): array
    {
        $details = DB::table('invoice_business_profiles')->where('parent_id', $owner)->value('details');
        if ($details !== null) return json_decode($details, true) ?: [];
        $settings = DB::table('settings')->where('parent_id', $owner)
            ->whereIn('name', ['company_name', 'company_address', 'company_phone', 'company_email'])
            ->pluck('value', 'name');
        return ['name' => $settings['company_name'] ?? '', 'address' => $settings['company_address'] ?? '',
            'phone' => preg_replace('/^(\+90|0090|0)/', '', $settings['company_phone'] ?? ''), 'email' => $settings['company_email'] ?? ''];
    }
}
