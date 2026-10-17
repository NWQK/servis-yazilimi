<div class="d-flex flex-wrap gap-2 mb-3">
@if(auth()->user()->type==='branch_manager')<a class="btn btn-sm btn-outline-primary" href="{{ route('branches.portal') }}">Şubelerim</a>@endif
@if(in_array(auth()->user()->type,['branch_manager','super admin'],true))
<a class="btn btn-sm btn-outline-primary" href="{{ route('branches.staff',['group'=>$group->id]) }}">Personel erişimi</a>
<a class="btn btn-sm btn-outline-primary" href="{{ route('branches.report',['group'=>$group->id]) }}">Karşılaştırmalı rapor</a>
<a class="btn btn-sm btn-outline-primary" href="{{ route('branches.history',['group'=>$group->id]) }}">İşlem geçmişi</a>
@endif
<a class="btn btn-sm btn-outline-primary" href="{{ route('branch-transfers.index',['group'=>$group->id]) }}">Stok transferleri</a>
@if(in_array(auth()->user()->type,['owner','branch_manager','super admin'],true))<a class="btn btn-sm btn-outline-secondary" href="{{ route('branch-referrals.index',['group'=>$group->id]) }}">Araç yönlendirmeleri</a>@endif
</div>
