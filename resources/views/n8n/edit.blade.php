<form action="{{ route('n8n.update', $n8n->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="modal-body">
        <div class="row">

            <!-- MODULE -->
            <div class="form-group col-md-12">
                <label class="form-label">{{ __('Module') }}</label>
                <select name="module" class="form-control hidesearch" required>
                    @foreach ($modules as $key => $value)
                        <option value="{{ $key }}" {{ old('module', $n8n->module) == $key ? 'selected' : '' }}>
                            {{ $value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- METHOD -->
            <div class="form-group col-md-12">
                <label class="form-label">{{ __('Method') }}</label>
                <select name="method" class="form-control hidesearch" required>
                    @foreach ($method as $key => $value)
                        <option value="{{ $key }}" {{ old('method', $n8n->method) == $key ? 'selected' : '' }}>
                            {{ $value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- URL -->
            <div class="form-group col-md-12">
                <label class="form-label">{{ __('Url') }}</label>
                <input type="text" name="url" class="form-control" value="{{ old('url', $n8n->url) }}" required>
            </div>

            <div class="form-group col-md-6">
            {{ Form::label('status', __('Enabled N8n'), ['class' => 'form-label']) }}
            <div class="form-check form-switch">
                <input type="hidden" name="status" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheck" name="status"
                    value="1" {{ $n8n->status == 1 ? 'checked' : '' }}>
                <label class="form-check-label" for="flexSwitchCheck"></label>
            </div>
        </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" class="btn btn-secondary ml-10">
            {{ __('Update') }}
        </button>
    </div>
</form>
