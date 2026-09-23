{{ Form::model($category, ['route' => $category->exists ? ['item-category.update', $category->id] : 'item-category.store', 'method' => $category->exists ? 'PUT' : 'POST']) }}
<div class="modal-body">
    <div class="form-group">
        {{ Form::label('name', 'Kategori Adı', ['class' => 'form-label']) }}
        {{ Form::text('name', null, ['class' => 'form-control', 'required' => true, 'maxlength' => 150, 'placeholder' => 'Örn. Motor Yağları']) }}
        @error('name') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-secondary">Kaydet</button>
</div>
{{ Form::close() }}
