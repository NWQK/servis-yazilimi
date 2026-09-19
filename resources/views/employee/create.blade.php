{{ Form::open(['url' => 'employee', 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}
            {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Enter Name'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
            {{ Form::text('email', null, ['class' => 'form-control', 'placeholder' => __('Enter Email'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('phone_number', __('Phone Number'), ['class' => 'form-label']) }}
            {{ Form::text('phone_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Phone Number'), 'required' => 'required']) }}
            <small class="form-text text-muted">
                {{ __('Please enter the number with country code. e.g., +91XXXXXXXXXX') }}
            </small>

        </div>

        <div class="form-group col-md-6">
            {{ Form::label('password', __('Password'), ['class' => 'form-label']) }}
            {{ Form::password('password', ['class' => 'form-control', 'placeholder' => __('Enter password'), 'required' => 'required', 'minlength' => '6']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('gender', __('Gender'), ['class' => 'form-label']) }}
            {!! Form::select('gender', $gender, null, ['class' => 'form-control select2 ', 'required' => 'required']) !!}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('Age', __('age'), ['class' => 'form-label']) }}
            {{ Form::number('age', null, ['class' => 'form-control', 'placeholder' => __('Enter age'), 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('joining_date', __('Joining Date'), ['class' => 'form-label']) }}
            {{ Form::date('joining_date', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('document', __('Document'), ['class' => 'form-label']) }}
            {{ Form::file('document', ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('reference', __('Reference'), ['class' => 'form-label']) }}
            {{ Form::text('reference', null, ['class' => 'form-control', 'placeholder' => __('Enter reference')]) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('address', __('Address'), ['class' => 'form-label']) }}
            {{ Form::textarea('address', null, ['class' => 'form-control', 'placeholder' => __('Enter address'), 'rows' => 2, 'required' => 'required']) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => 2]) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    {{ Form::submit(__('Create'), ['class' => 'btn btn-secondary ml-10']) }}
</div>
{{ Form::close() }}
