{!! Form::open(['id' => 'aitemplate']) !!}
@csrf

<div id="aiMessage" class="mb-2"></div>

<div class="row">

    {{-- Content What --}}
    <div class="col-12">
        <div class="form-group">
            {!! Form::label('', __('Content What'), ['class' => 'col-form-label']) !!}<br>

            @foreach ($title as $key => $value)
                <div class="form-check form-check-inline">
                    {!! Form::radio('title', $value->id, $key === 0, [
                        'class' => 'form-check-input template_title',
                        'id' => 'title_' . $value->id,
                        'data-name' => $value->title,
                    ]) !!}
                    {!! Form::label('title_' . $value->id, ucwords(str_replace('_', ' ', $value->title)), [
                        'class' => 'form-check-label',
                    ]) !!}
                </div>
            @endforeach
        </div>
    </div>

    {{-- Writing Style --}}
    <div class="col-6 mode">
        <div class="form-group">
            {!! Form::label('', __('Writing Style'), ['class' => 'col-form-label']) !!}
            {!! Form::select(
                'mode',
                [
                    'professional' => 'Profesyonel',
                    'casual' => 'Gündelik',
                    'friendly' => 'Samimi',
                    'funny' => 'Eğlenceli',
                    'excited' => 'Coşkulu',
                    'witty' => 'Esprili',
                    'sarcastic' => 'İğneleyici',
                    'bold' => 'İddialı',
                    'dramatic' => 'Dramatik',
                    'secretive' => 'Gizemli',
                ],
                null,
                ['class' => 'form-select select2'],
            ) !!}
        </div>
    </div>

    {{-- Creativity --}}
    <div class="col-6">
        <div class="form-group">
            {!! Form::label('', __('Creativity Intensity'), ['class' => 'col-form-label']) !!}
            {!! Form::select(
                'creativity_level',
                [
                    '1' => __('High (More Creative)'),
                    '0.5' => __('Medium (Balanced)'),
                    '0' => __('Low (More Precise)'),
                ],
                '1',
                ['class' => 'form-select select2'],
            ) !!}
        </div>
    </div>

    {{-- Number of Outputs --}}
    <div class="col-6">
        <div class="form-group">
            {!! Form::label('', __('Number of Outputs'), ['class' => 'col-form-label']) !!}
            {!! Form::select('num_of_outputs', array_combine(range(1, 10), range(1, 10)), 1, [
                'class' => 'form-select select2',
            ]) !!}
        </div>
    </div>

    {{-- Word Limit --}}
    <div class="col-6">
        <div class="form-group">
            {!! Form::label('', __('Maximum Word Limit'), ['class' => 'col-form-label']) !!}
            {!! Form::number('maximum_word_limit', 20, ['class' => 'form-control', 'min' => 10]) !!}
        </div>
    </div>

    {{-- Dynamic Keywords --}}
    <div class="col-12" id="promptKeywords"></div>

</div>
{!! Form::close() !!}

<div class="response mt-3 d-none">
    <a href="#!" class="btn btn-primary btn-sm" id="generate_content">
        <i class="ti ti-sparkles"></i> {{ __('Generate Content') }}
    </a>

    <div class="form-group mt-3">
        {!! Form::textarea('ai_generated_content', null, [
            'class' => 'form-control',
            'rows' => 6,
            'id' => 'ai_generated_content',
            'placeholder' => __('AI generated content will appear here...'),
        ]) !!}
    </div>

    <div class="d-flex gap-2">
        <a href="#!" onclick="copyContent()" class="btn btn-primary btn-sm">
            {{ __('Copy All Content') }}
        </a>
        <a href="#!" onclick="copySelectedContent()" class="btn btn-primary btn-sm">
            {{ __('Copy Selected Content') }}
        </a>
    </div>
</div>

<script>
    function copyContent() {
        const generatedText = $('#ai_generated_content').val();

        if (!generatedText) {
            showAiMessage('error', 'Kopyalanacak metin yok.', 'error');
            return;
        }

        navigator.clipboard.writeText(generatedText).then(() => {
            toastrs('success', 'Result text copied successfully', 'success');
            $('#aiModal').modal('hide');
        }).catch(() => {
            showAiMessage('error', 'Metin kopyalanamadı.', 'error');
        });
    }

    function copySelectedContent() {
        const selectedText = window.getSelection().toString();

        if (!selectedText) {
            showAiMessage('error', 'Önce bir metin seçin.', 'error');
            return;
        }

        navigator.clipboard.writeText(selectedText).then(() => {
            toastrs('success', 'Seçilen metin kopyalandı.', 'success');
            $('#aiModal').modal('hide');
        }).catch(() => {
            showAiMessage('error', 'Metin kopyalanamadı.', 'error');
        });
    }

    function loadKeywords(templateId) {
        if (!templateId) return;

        $.ajax({
            type: 'POST',
            url: '{{ route('generate.template.keywords', 'placeholder') }}'.replace('placeholder', templateId),
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                template_id: templateId,
            },
            success: function(data) {
                if (data.active == 1) {
                    $('.mode').removeClass('d-none');
                    $('.response').removeClass('d-none');
                    $('.mode select').attr('name', 'mode');
                } else {
                    $('.mode').addClass('d-none');
                    $('.mode select').removeAttr('name');
                }

                $('#promptKeywords').html(data.template || '');

                setTimeout(() => {
                    $('#promptKeywords .form-control').first().focus();
                }, 100);
            },
            error: function(xhr) {
                console.error('Keywords load error:', xhr.responseText);
                $('#promptKeywords').html('<div class="alert alert-danger">Alanlar yüklenemedi.</div>');
                $('.mode').addClass('d-none');
            }
        });
    }

    $(document).ready(function() {
        const $firstRadio = $('#aitemplate .template_title:first');

        if ($firstRadio.length) {
            $firstRadio.prop('checked', true);
            loadKeywords($firstRadio.val());
        }
    });


    $(document).on('change', '.template_title', function() {
        loadKeywords($(this).val());
    });

    $(document).on('click', '#generate_content', function(e) {
        e.preventDefault();

        const $btn = $(this);

        if (!$('input[name="title"]:checked').length) {
            showAiMessage('error', 'Please select a Content Type', 'error');
            return;
        }

        $.ajax({
            type: 'POST',
            url: '{{ route('generate.prompt.response') }}',
            data: $('#aitemplate').serialize(),
            dataType: 'json',
            beforeSend: function() {
                $btn.html('<span class="spinner-grow spinner-grow-sm"></span> Generating...');
                $btn.prop('disabled', true);
            },
            success: function(response) {
                $('.response').removeClass('d-none');
                $btn.html('Re-Generate');

                if (response.message) {
                    showAiMessage('error', response.message, 'error');
                    $('#ai_generated_content').val('');
                } else {
                    $('#ai_generated_content').val(response.content);
                }
            },
            error: function() {
                $btn.html('Generate');
                showAiMessage('error', 'Something went wrong. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
</script>
