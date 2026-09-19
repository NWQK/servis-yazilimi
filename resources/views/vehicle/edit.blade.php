{{ Form::model($vehicle, array('route' => array('vehicle.update', $vehicle->id), 'method' => 'PUT')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('client', __('Client'),['class'=>'form-label']) }}
            {!! Form::select('client', $clients,null,array('class' => 'form-control select2','required'=>'required')) !!}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('type', __('Type'),['class'=>'form-label']) }}
            {!! Form::select('type', $types,null,array('class' => 'form-control select2','id'=>'type_id','required'=>'required')) !!}
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <input type="hidden" id="edit_brand" value="{{$vehicle->brand}}">
            {{Form::label('brand_id',__('Brand'),array('class'=>'form-label'))}}
            <div class="brand_div">
                <select class="form-control select2 brand" id="brand" name="brand">
                    <option value="">{{__('Select Brand')}}</option>
                </select>
            </div>
        </div>
        <div class="form-group col-md-6">
            {{Form::label('model',__('Model'),array('class'=>'form-label')) }}
            {{Form::text('model',null,array('class'=>'form-control','placeholder'=>__('Enter model'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('color',__('Color'),array('class'=>'form-label')) }}
            {{Form::text('color',null,array('class'=>'form-control','placeholder'=>__('Enter color'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('license_plate',__('License Plate'),array('class'=>'form-label')) }}
            {{Form::text('license_plate',null,array('class'=>'form-control','placeholder'=>__('Enter license plate'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('engine_type',__('Engine Type'),array('class'=>'form-label')) }}
            {{Form::text('engine_type',null,array('class'=>'form-control','placeholder'=>__('Enter engine type'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('engine_no',__('Engine Number'),array('class'=>'form-label')) }}
            {{Form::text('engine_no',null,array('class'=>'form-control','placeholder'=>__('Enter engine number'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('fuel_type',__('Fuel Type'),array('class'=>'form-label')) }}
            {{Form::text('fuel_type',null,array('class'=>'form-control','placeholder'=>__('Enter fuel type'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('chassis_no',__('Chassis Number'),array('class'=>'form-label')) }}
            {{Form::text('chassis_no',null,array('class'=>'form-control','placeholder'=>__('Enter chassis number'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('mileage',__('Mileage'),array('class'=>'form-label')) }}
            {{Form::number('mileage',null,array('class'=>'form-control','placeholder'=>__('Enter mileage'),'required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('last_service_date',__('Last Service Date'),array('class'=>'form-label')) }}
            {{Form::date('last_service_date',null,array('class'=>'form-control','required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('next_service_due_date',__('Next Service Due Date'),array('class'=>'form-label')) }}
            {{Form::date('next_service_due_date',null,array('class'=>'form-control','required'=>'required'))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('insurance_details',__('Insurance Details'),array('class'=>'form-label')) }}
            {{Form::textarea('insurance_details',null,array('class'=>'form-control','placeholder'=>__('Enter insurance details'),'rows'=>2))}}
        </div>
        <div class="form-group col-md-6">
            {{Form::label('notes',__('Notes'),array('class'=>'form-label')) }}
            {{Form::textarea('notes',null,array('class'=>'form-control','placeholder'=>__('Enter notes'),'rows'=>2,'required'=>'required'))}}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{Form::submit(__('Update'),array('class'=>'btn btn-secondary ml-10'))}}
</div>
{{Form::close()}}

<script>
    $('#type_id').on('change', function () {
        "use strict";
        var type_id = $(this).val();
        var url = '{{ route("vehicle.brand", ":id") }}';
        url = url.replace(':id', type_id);
        $.ajax({
            url: url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                type_id: type_id,
            },
            contentType: false,
            processData: false,
            type: 'GET',
            success: function (data) {
                $('.brand').empty();
                var brand = `<select class="form-control select2 brand" id="brand" name="brand"></select>`;
                $('.brand_div').html(brand);

                $.each(data, function (key, value) {
                    var brand_id = $('#edit_brand').val();
                    if (key == brand_id) {
                        $('.brand').append('<option selected value="' + key + '">' + value + '</option>');
                    } else {
                        $('.brand').append('<option value="' + key + '">' + value + '</option>');
                    }
                });
                select2(); // ← replaced manual .select2({ minimumResultsForSearch: -1 })
            },
        });
    });
    $('#type_id').trigger('change');
</script>
