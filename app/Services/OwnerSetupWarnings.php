<?php

namespace App\Services;

use App\Models\{AppointmentProfile, Item, Service, User, VehicleBrand, VehicleType};
use Illuminate\Support\Facades\{DB, Schema};

class OwnerSetupWarnings
{
    public function forUser(User $user, bool $respectPermissions = true): array
    {
        if ($user->type !== 'owner') return [];
        $warnings = [];
        $can = fn($permission) => !$respectPermissions || $user->can($permission);
        if ($can('manage service') && $can('create service') && !Service::where('parent_id', $user->id)->exists()) {
            $warnings[] = [
                'key' => 'services', 'title' => 'İlk servis kaydınızı oluşturun',
                'description' => 'Müşteri ve aracını kaydedin, çalışanınızı ve servis türlerini hazırlayın; ardından yapılacak işlemleri girin. Başlangıç ve bitiş tarihleri isteğe bağlıdır.',
                'steps' => ['Müşteri ve araç', 'Çalışan ve servis türü', 'Servis', 'Fatura'],
                'actions' => [['label' => 'Servis oluşturma rehberini aç', 'url' => route('service.setup')]],
            ];
        }
        if ($can('manage item') && $can('create item') && !Item::where('parent_id', $user->id)->exists()) {
            $warnings[] = [
                'key' => 'products', 'title' => 'İlk ürününüzü ekleyin',
                'description' => 'Ürün listeniz boş. Vergi ve birim ayarlarınızı kontrol edin, dilerseniz kategori oluşturun; ardından ürününüzü stok miktarı ve fiyatlarıyla kaydedin. Hazır vergi ve birim ayarlarını kullanabilirsiniz.',
                'steps' => ['Vergi', 'Birim', 'Kategori (isteğe bağlı)', 'Ürün ve stok'],
                'actions' => [['label' => 'Ürün ekleme rehberini aç', 'url' => route('inventory.setup')]],
            ];
        }
        if ($can('manage account settings') && $can('manage general settings') &&
            trim((string) DB::table('settings')->where('parent_id', $user->id)->where('name', 'invoice_logo')->value('value')) === '') {
            $warnings[] = [
                'key' => 'invoice_logo', 'title' => 'Fatura logonuzu yükleyin',
                'description' => 'Faturalarınız için ayrı bir logo seçmediniz. İşletmenizin fatura logosunu yükleyin; sistem görseli uygun boyuta sığdırır.',
                'actions' => [['label' => 'Fatura logosunu ayarla', 'url' => route('setting.index', ['tab' => 'user_profile_settings']).'#business-invoice-logo']],
            ];
        }
        if ($can('manage company settings') && Schema::hasTable('invoice_business_profiles')) {
            $details = InvoiceBilling::business($user->id);
            $missing = [];
            foreach (['name' => 'İşletme adı', 'address' => 'Adres', 'phone' => 'Telefon'] as $field => $label) {
                if (trim($details[$field] ?? '') === '') $missing[] = $label;
            }
            if ($missing) $warnings[] = [
                'key' => 'business', 'title' => 'İşletme bilgilerinizi tamamlayın',
                'description' => 'Faturalarda bazı işletme bilgileriniz boş görünecek. Lütfen doldurun. Eksik alanlar: '.implode(', ', $missing).'.',
                'actions' => [['label' => 'İşletme bilgilerini doldur', 'url' => route('setting.index', ['tab' => 'invoice_business'])]],
            ];
        }
        if ($can('create vehicle type') && $can('create vehicle brand') &&
            (!VehicleType::where('parent_id', $user->id)->exists() || !VehicleBrand::where('parent_id', $user->id)->exists())) {
            $actions = [];
            foreach (VehicleCatalog::CATEGORIES as $key => $label) $actions[] = [
                'label' => $label.' marka ve modellerini yükle',
                'url' => route('vehicle-catalog.create', ['category' => $key]), 'modal' => true,
            ];
            $warnings[] = [
                'key' => 'catalog', 'title' => 'Marka ve model listeniz boş',
                'description' => 'Araç kaydını kolaylaştırmak için ihtiyacınız olan hazır listeyi yükleyebilirsiniz. Mevcut kayıtlarınız korunur.',
                'actions' => $actions,
            ];
        }
        if (Schema::hasTable('appointment_profiles')) {
            $profile = AppointmentProfile::where('owner_id', $user->id)->first();
            $hasHours = $profile && collect($profile->weekly_hours ?? [])->contains(fn ($hours) => is_array($hours) && count($hours) > 0);
            if (!$profile || !$profile->is_active || !$hasHours || trim($profile->display_name ?? '') === '') $warnings[] = [
                'key' => 'appointments', 'title' => 'Randevu ayarlarınızı tamamlayın',
                'description' => 'Müşterilerinizin randevu alabilmesi için işletme adınızı, çalışma günlerinizi ve saatlerinizi belirleyin; randevu alımını etkinleştirin.',
                'actions' => [['label' => 'Randevu sistemini yapılandır', 'url' => route('appointments.settings')]],
            ];
        }
        return $warnings;
    }
}
