@php
    $historyNumber=$historyVehicle?->chassis_no;
@endphp
<label for="chassis_no" class="form-label">Şasi numarası {{ !$historyVehicle || $historyNumber ? '*' : '(eski kayıt — isteğe bağlı)' }}</label>
<input id="chassis_no" name="chassis_no" class="form-control" type="text" value="{{ old('chassis_no',$historyNumber) }}" maxlength="64" autocomplete="off" spellcheck="false" placeholder="Ruhsattaki şasi numarası" @required(!$historyVehicle || $historyNumber) aria-describedby="chassis-help">
<small id="chassis-help" class="text-muted d-block mt-1">Ruhsattan kontrol edin. Boşluklar ve tireler kaldırılır, harfler büyütülür. Standart VIN 17 hanedir; eski araçların farklı uzunluktaki numaraları da kabul edilir.</small>
@if($historyVehicle && $historyNumber)
<div class="form-check my-2"><input id="chassis-change-confirm" name="chassis_change_confirm" value="1" type="checkbox" class="form-check-input"><label class="form-check-label" for="chassis-change-confirm">Şasi numarasını değiştirdiysem ruhsattan kontrol ettim ve geçmiş eşleşmesinin değişmesini onaylıyorum.</label></div>
@endif
<p class="text-muted small mt-3">Geçerli şasi numarasına bağlı tamamlanmış servis özetleri otomatik sorgulanabilir. İşletme, marka/model, plakanın yalnız son üç karakteri, servis tarihi, işlem türü ve servis kilometresi gösterilir. Yayımlanan geçmiş kayıt değişikliği veya silmeyle kaldırılmaz. Görünürlüğü yalnız süper admin yönetir; müşteri ve mali bilgiler paylaşılmaz.</p>
