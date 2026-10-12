{{ Form::model($supplier, ['route'=>$supplier->exists ? ['supplier.update', $supplier->id] : 'supplier.store', 'method'=>$supplier->exists ? 'PUT' : 'POST']) }}
<div class="modal-body"><div class="row g-3">
    <div class="col-12"><label for="company_name" class="form-label">Firma adı</label>{{ Form::text('company_name', null, ['class'=>'form-control', 'required'=>true, 'maxlength'=>150]) }}</div>
    <div class="col-12"><label for="contact_name" class="form-label">Firma personeli adı soyadı</label>{{ Form::text('contact_name', null, ['class'=>'form-control', 'maxlength'=>150]) }}</div>
    <div class="col-12 col-md-6"><label for="phone" class="form-label">Telefon</label><x-tr-phone name="phone" :value="$supplier->phone" :landline="true" /></div>
    <div class="col-12 col-md-6"><label for="email" class="form-label">E-posta</label>{{ Form::email('email', null, ['class'=>'form-control', 'maxlength'=>254]) }}</div>
    <div class="col-12"><small class="text-muted">Firma adı zorunludur. İletişim bilgileri isteğe bağlıdır; cep telefonu girilirse WhatsApp bağlantısı açılır. Tedarikçi kaydı ödeme veya borç oluşturmaz.</small></div>
</div></div>
<div class="modal-footer"><button class="btn btn-secondary" type="submit">Kaydet</button></div>
{{ Form::close() }}
