<div class="modal-body">
    @if (auth()->user()->type !== 'client' && auth()->user()->can('create vehicle'))
        <div class="mb-3 p-3 border rounded">
            @if ($vehicle->qrCode)
                <strong>QR etiketi: {{ $vehicle->qrCode->label }}</strong>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('vehicle-qr.print', ['ids' => [$vehicle->qrCode->id]]) }}">Tekrar yazdır</a>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="{{ $vehicle->qrCode->publicUrl() }}">Müşteri görünümü</a>
            @elseif (auth()->user()->can('edit vehicle'))
                <form method="post" action="{{ route('vehicle-qr.assign', $vehicle) }}">
                    @csrf
                    <label for="existing_qr" class="form-label">Araca QR etiketi ata</label>
                    <select id="existing_qr" name="qr_code_id" class="form-control mb-2" required>
                        <option value="">Teslim edeceğiniz etiketin numarasını seçin</option>
                        @foreach ($qrCodes as $qr)<option value="{{ $qr->id }}">{{ $qr->label }}</option>@endforeach
                    </select>
                    <button class="btn btn-secondary" @disabled($qrCodes->isEmpty())>QR ata</button>
                    <a href="{{ route('vehicle-qr.index') }}">Etiketleri yönet</a>
                </form>
            @endif
        </div>
    @endif
    <div class="product-card">
        <div class="row">
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Vehicle ID')}}</h6>
                    <p class="mb-20 text-muted">{{ vehiclePrefix().$vehicle->vehicle_id}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Client')}}</h6>
                    <p class="mb-20 text-muted">{{!empty($vehicle->clients)?$vehicle->clients->name:'-'}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Vehicle Type')}}</h6>
                    <p class="mb-20 text-muted">{{!empty($vehicle->types)?$vehicle->types->type:'-'}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Vehicle Brand')}}</h6>
                    <p class="mb-20 text-muted">{{ !empty($vehicle->brands)?$vehicle->brands->name:'-' }}</p>
                </div>
            </div>

            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Vehicle Model')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->model}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Color')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->color}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('License Plate')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->license_plate}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Engine Type')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->engine_type}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Engine Number')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->engine_no}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Fuel Type')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->fuel_type}}</p>
                </div>
            </div>

            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Chassis Number')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->chassis_no}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Mileage')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->mileage}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Last Service Date')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->last_service_date}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Next Service Due Date')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->next_service_due_date}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('Insurance Details')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->insurance_details}}</p>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-group">
                    <h6>{{__('notes')}}</h6>
                    <p class="mb-20 text-muted">{{$vehicle->notes}}</p>
                </div>
            </div>
        </div>
    </div>
</div>



