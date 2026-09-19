    @extends('layouts.app')
    @section('page-title')
        {{ __('WO Request Status') }}
    @endsection
    @section('breadcrumb')
        <ul class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
            </li>
            <li class="breadcrumb-item active">
                {{ __('WO Request Status') }}
            </li>
        </ul>
    @endsection
    @php
        $pipelines = [];
        foreach ($stages as $stage) {
            $pipelines[] = 'applicant-' . $stage->id;
        }
    @endphp
    @section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane show active" id="kanban-1" role="tabpanel" aria-labelledby="kanban-tab-1">
                                <div class="pc-kanban-wrapper" data-plugin="dragula"
                                    data-containers='{!! json_encode($pipelines) !!}'>
                                    @foreach ($stages as $stage)
                                        <div class="pc-kanban-column kanban-column">
                                            <div class="pc-kanban-header">
                                                <h4>
                                                    {{ $stage->title }}
                                                    <span class="countTodo counts">
                                                        ({{ $stage->requests->count() }})
                                                    </span>
                                                </h4>
                                            </div>
                                            <div class="pc-kanban-body">
                                                <div class="pc-kanban-cards secondary" id="applicant-{{ $stage->id }}"
                                                    data-stageId="{{ $stage->id }}">
                                                    @foreach ($stage->requests as $request)
                                                        <div class="card border" data-applicantId="{{ $request->id }}">
                                                            <div class="card-body px-3 py-3">
                                                                <div class="float-end">
                                                                    @if (Gate::check('edit wo request') || Gate::check('delete wo request'))
                                                                        <div class="dropdown">
                                                                            <a class="avtar avtar-xs btn-link-secondary dropdown-toggle arrow-none"
                                                                                href="#" data-bs-toggle="dropdown"
                                                                                aria-haspopup="true" aria-expanded="false">
                                                                                <i class="ti ti-dots f-18"></i>
                                                                            </a>
                                                                            <div
                                                                                class="dropdown-menu dropdown-menu-end drop-kanban">
                                                                                <a class="dropdown-item customModal"
                                                                                    href="#" data-bs-toggle="tooltip"
                                                                                    data-size="lg"
                                                                                    data-bs-original-title="{{ __('Details') }}"
                                                                                    data-url="{{ route('wo-request.show', \Crypt::encrypt($request->id)) }}"
                                                                                    data-title="{{ __('WO Request Detail') }}">
                                                                                    <i class="ti ti-eye"></i>
                                                                                    {{ __('View') }}
                                                                                </a>

                                                                                <a class="dropdown-item customModal"
                                                                                    href="#" data-bs-toggle="tooltip"
                                                                                    data-size="lg"
                                                                                    data-bs-original-title="{{ __('Edit') }}"
                                                                                    data-url="{{ route('wo-request.edit', \Crypt::encrypt($request->id)) }}"
                                                                                    data-title="{{ __('WO Request Edit') }}">
                                                                                    <i class="ti ti-pencil"></i>
                                                                                    {{ __('Edit') }}
                                                                                </a>

                                                                                {!! Form::open([
                                                                                    'method' => 'DELETE',
                                                                                    'route' => ['wo-request.destroy', \Crypt::encrypt($request->id)],
                                                                                    'id' => 'wo-request-' . $request->id,
                                                                                ]) !!}

                                                                                <a class="dropdown-item confirm_dialog"
                                                                                    href="#">
                                                                                    <i class="ti ti-trash"></i>
                                                                                    {{ __('Delete') }}
                                                                                </a>

                                                                                {!! Form::close() !!}

                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="link-secondary text-sm mt-1">
                                                                    {{ $request->request_detail }}
                                                                    </h5>

                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span>{{ __('Client') }} :</span>
                                                                        {{ optional($request->clients)->name }}
                                                                    </div>

                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span>{{ __('Asset') }} :</span>
                                                                        {{ optional($request->assets)->name }}
                                                                    </div>
                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span>{{ __('Assign') }} :</span>
                                                                        {{ optional($request->assigned)->name }}
                                                                    </div>
                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span>{{ __('Due Date') }} :</span>
                                                                        {{ DateFormat($request->due_date) }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endsection

    @push('script-page')
        <script src="{{ asset('assets/js/plugins/dragula.min.js') }}"></script>
        <script>
            var tc = document.querySelectorAll('.pc-kanban-body');
            for (var t = 0; t < tc.length; t++) {
                new SimpleBar(tc[t]);
            };
            ! function(a) {
                "use strict";
                var t = function() {
                    this.$body = a("body")
                };
                t.prototype.init = function() {
                    a('[data-plugin="dragula"]').each(function() {
                        var t = a(this).data("containers"),
                            n = [];
                        if (t)
                            for (var i = 0; i < t.length; i++) n.push(a("#" + t[i])[0]);
                        else n = [a(this)[0]];
                        var r = a(this).data("handleclass");
                        r ? dragula(n, {
                                moves: function(a, t, n) {
                                    return n.classList.contains(r)
                                }
                            }) :
                            dragula(n).on('drop', function(el, targetResult, sourceResult, sibling) {
                                var applicantOrder = [];
                                $("#" + targetResult.id + " > div").each(function() {
                                    applicantOrder[$(this).index()] = $(this).attr(
                                        'data-applicantId');
                                });

                                var applicantId = $(el).attr('data-applicantId');
                                var stageId = $(targetResult).attr('data-stageId');

                                let sourceCount = $("#" + sourceResult.id + " > div").length;
                                $("#" + sourceResult.id).closest('.kanban-column').find('.countTodo').text(
                                    "(" + sourceCount + ")");

                                let targetCount = $("#" + targetResult.id + " > div").length;
                                $("#" + targetResult.id).closest('.kanban-column').find('.countTodo').text(
                                    "(" + targetCount + ")");

                                $.ajax({
                                    url: '{{ route('service.change.status') }}',
                                    type: 'POST',
                                    data: {
                                        applicantId: applicantId,
                                        stageId: stageId,
                                        applicantOrder: applicantOrder,
                                        "_token": $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(data) {
                                        toastrs("Success!",
                                            "Work Request updated successfully.",
                                            "success");
                                    }
                                });
                            });

                    })
                }, a.Dragula = new t, a.Dragula.Constructor = t
            }(window.jQuery),
            function(a) {
                "use strict";
                a.Dragula.init()
            }(window.jQuery);
        </script>
    @endpush
