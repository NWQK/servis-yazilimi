<form method="post" action="{{ route('vehicle-catalog.store') }}">
    @csrf
    <div class="modal-body">
        <p>Hangi hazır marka-model listesini yüklemek istersiniz?</p>
        <p class="text-muted small">Listeler yalnızca bu işletmeye eklenir. Mevcut marka ve modeller korunur; aynı kayıt tekrar oluşturulmaz. Model yılı, motor ve donanım seçenekleri içermez.</p>
        <fieldset>
            <legend class="visually-hidden">Araç kategorisi</legend>
            <div class="row g-3">
                @foreach($categories as $key=>$label)
                    <div class="col-12 col-md-4">
                        <label class="border rounded p-3 d-block h-100" style="cursor:pointer">
                            <input class="form-check-input me-2" type="radio" name="category" value="{{ $key }}" required @checked($selectedCategory === $key && !in_array($key,$loaded)) {{ in_array($key,$loaded) ? 'disabled' : '' }}>
                            <strong>{{ $label }}</strong>
                            <span class="d-block small text-muted mt-2">{{ $counts[$key]['brands'] }} marka · {{ $counts[$key]['models'] }} model</span>
                            @if(in_array($key,$loaded))<span class="badge bg-light-success mt-2">Yüklendi</span>@endif
                        </label>
                    </div>
                @endforeach
            </div>
        </fieldset>
        <div class="alert alert-light mt-4 mb-0">
            <p class="mb-2"><strong>Hazır listeyi yüklemek istediğinizden emin misiniz?</strong></p>
            <label><input type="checkbox" class="form-check-input me-2" name="confirm" value="1" required> Seçtiğim marka ve modellerin bu işletmeye eklenmesini onaylıyorum.</label>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Vazgeç</button>
        <button class="btn btn-secondary" type="submit" {{ count($loaded) === count($categories) ? 'disabled' : '' }}>Evet, yükle</button>
    </div>
</form>
