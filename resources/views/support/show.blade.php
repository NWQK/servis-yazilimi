@extends('layouts.app')
@section('page-title','Destek bileti #'.$ticket->id)
@section('content')
@include('support.style')
<div class="card"><div class="card-header"><a href="{{ route('support.index') }}" class="d-inline-block mb-3">← Destek biletlerine dön</a><h5 class="support-ticket-title">#{{ $ticket->id }} · {{ $ticket->subject }}</h5><div class="support-meta"><span class="badge bg-primary">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</span><span>{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] }} öncelik</span>@if($isAdmin)<span>{{ $ticket->owner?->name ?? 'Silinmiş işletme' }} · {{ $ticket->owner?->email }}</span>@endif</div></div>
<div class="card-body">
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@foreach($messages->getCollection()->reverse() as $message)
<div class="support-message {{ $message->is_admin ? 'support-message-admin' : '' }}"><div class="support-meta justify-content-between"><strong>{{ $message->is_admin ? 'Sanayi Randevu Destek' : ($message->author?->name ?? 'İşletme') }}</strong><span class="text-muted small">{{ $message->created_at->timezone('Europe/Istanbul')->translatedFormat('d M Y H:i') }}</span></div><div class="support-message-body">{{ $message->body }}</div></div>
@endforeach
@if($messages->hasPages())<p class="text-muted small">İlk sayfada en yeni mesajlar gösterilir. Önceki mesajlar için sonraki sayfalara geçin.</p>{{ $messages->links() }}@endif
@if($ticket->status!=='closed')
<form method="POST" action="{{ route('support.reply',$ticket->id) }}">@csrf<label class="form-label" for="support-reply">{{ $isAdmin ? 'İşletmeye yanıtınız' : 'Yeni mesajınız' }}</label><textarea class="form-control support-reply" id="support-reply" name="body" rows="5" maxlength="10000" required>{{ old('body') }}</textarea><button class="btn btn-primary mt-3">Mesaj gönder</button></form>
@else<div class="alert alert-secondary">Bu bilet kapatıldı. Yeni mesaj göndermek için bileti yeniden açabilirsiniz.</div>@endif
<hr class="my-4">
<form method="POST" action="{{ route('support.status',$ticket->id) }}">@csrf
@if($isAdmin)<div class="row g-3"><div class="col-12 col-md-5"><label class="form-label" for="support-change-status">Bilet durumu</label><select id="support-change-status" name="status" class="form-select">@foreach(\App\Models\SupportTicket::STATUSES as $value=>$label)<option value="{{ $value }}" @selected($ticket->status===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-12 col-md-4"><label class="form-label" for="support-change-priority">Öncelik</label><select id="support-change-priority" name="priority" class="form-select">@foreach(\App\Models\SupportTicket::PRIORITIES as $value=>$label)<option value="{{ $value }}" @selected($ticket->priority===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-12 col-md-3 align-self-end"><button class="btn btn-secondary">Bileti güncelle</button></div></div>
@else<input type="hidden" name="status" value="{{ $ticket->status==='closed' ? 'waiting_support' : 'closed' }}"><button class="btn btn-outline-secondary">{{ $ticket->status==='closed' ? 'Bileti yeniden aç' : 'Sorun çözüldü · bileti kapat' }}</button>@endif
</form>
</div></div>
@endsection