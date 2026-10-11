@props(['step','action','label'=>'Ekle'])
<form method="post" action="{{ route('business-setup.store',['step'=>$step]) }}" class="setup-form">@csrf<input type="hidden" name="action" value="{{ $action }}">{{ $slot }}<button class="btn btn-primary" type="submit">{{ $label }}</button></form>
