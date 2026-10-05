<div class="form-group">
    {{ Form::label('vehicle_limit', 'Kayıtlı araç kapasitesi', ['class' => 'form-label']) }}
    {{ Form::select('vehicle_limit', [50 => 'Demo — 50 araç', 500 => '500 araç', 1000 => '1.000 araç', 2000 => '2.000 araç', 3000 => '3.000 araç'], null, ['class' => 'form-control', 'placeholder' => isset($subscription) && $subscription->vehicle_limit === null ? 'Eski paket — araç limiti tanımlanmamış' : 'Kapasite seçin', 'required' => !isset($subscription)]) }}
    <small>İşletmenin sistemde kayıtlı toplam araç sayısı esas alınır. Bu paketlerde müşteri, personel ve kullanıcı sayısı sınırlanmaz.</small>
</div>
<div class="form-group">
    {{ Form::label('vehicle_block_amount', 'Her 500 ek araç için ücret (₺)', ['class' => 'form-label']) }}
    {{ Form::number('vehicle_block_amount', null, ['class' => 'form-control', 'min' => '0.01', 'step' => '0.01', 'placeholder' => 'Ek kapasite ücreti']) }}
    <small>Yalnızca 3.000 araçlık pakette kullanılır. Boş bırakırsanız ek kapasite talebi kapalı olur. Esnafın talebi ödeme kontrolünden sonra süper admin tarafından onaylanır.</small>
</div>
