@php
    $historyNumber=$historyVehicle?->chassis_no;
    $historyShared=$historyVehicle ? app(\App\Services\PublicVehicleHistory::class)->isPublic($historyVehicle) : false;
@endphp
<label for="chassis_no" class="form-label">Şasi numarası {{ !$historyVehicle || $historyNumber ? '*' : '(eski kayıt — isteğe bağlı)' }}</label>
<input id="chassis_no" name="chassis_no" class="form-control" type="text" value="{{ old('chassis_no',$historyNumber) }}" maxlength="64" autocomplete="off" spellcheck="false" placeholder="Ruhsattaki şasi numarası" @required(!$historyVehicle || $historyNumber) aria-describedby="chassis-help">
<small id="chassis-help" class="text-muted d-block mt-1">Ruhsattan kontrol edin. Boşluklar ve tireler kaldırılır, harfler büyütülür. Standart VIN 17 hanedir; eski araçların farklı uzunluktaki numaraları da kabul edilir.</small>
@if($historyVehicle && $historyNumber)
<div class="form-check my-2"><input id="chassis-change-confirm" name="chassis_change_confirm" value="1" type="checkbox" class="form-check-input"><label class="form-check-label" for="chassis-change-confirm">Şasi numarasını değiştirdiysem ruhsattan kontrol ettim ve geçmiş eşleşmesinin değişmesini onaylıyorum.</label></div>
@endif
<input type="hidden" name="history_public" value="0">
<div class="form-check mt-3"><input id="history-public" type="checkbox" class="form-check-input" name="history_public" value="1" @checked(old('history_public',$historyShared))><label class="form-check-label" for="history-public">Bu aracın tamamlanmış servis özetlerini herkese açık şasi sorgusunda paylaş.</label></div>
<small class="text-muted d-block mt-1">İşletme adı, servis tarihi, işlem türü ve kayıtlı servis kilometresi gösterilir. Müşteri, fiyat, borç ve özel notlar paylaşılmaz. Bu seçenek geçmiş ve sonraki tamamlanmış servisleri kapsar. Şasi değiştiğinde paylaşım kapanır; tekrar açmak için yeni numarayı kaydedip formu yeniden açın.</small>
