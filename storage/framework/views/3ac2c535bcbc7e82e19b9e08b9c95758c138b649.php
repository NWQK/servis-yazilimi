    
    <?php $__env->startSection('page-title'); ?>
        <?php echo e(__('WO Request Status')); ?>

    <?php $__env->stopSection(); ?>
    <?php $__env->startSection('breadcrumb'); ?>
        <ul class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('Dashboard')); ?></a>
            </li>
            <li class="breadcrumb-item active">
                <?php echo e(__('WO Request Status')); ?>

            </li>
        </ul>
    <?php $__env->stopSection(); ?>
    <?php
        $pipelines = [];
        foreach ($stages as $stage) {
            $pipelines[] = 'applicant-' . $stage->id;
        }
    ?>
    <?php $__env->startSection('content'); ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane show active" id="kanban-1" role="tabpanel" aria-labelledby="kanban-tab-1">
                                <div class="pc-kanban-wrapper" data-plugin="dragula"
                                    data-containers='<?php echo json_encode($pipelines); ?>'>
                                    <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="pc-kanban-column kanban-column">
                                            <div class="pc-kanban-header">
                                                <h4>
                                                    <?php echo e($stage->title); ?>

                                                    <span class="countTodo counts">
                                                        (<?php echo e($stage->requests->count()); ?>)
                                                    </span>
                                                </h4>
                                            </div>
                                            <div class="pc-kanban-body">
                                                <div class="pc-kanban-cards secondary" id="applicant-<?php echo e($stage->id); ?>"
                                                    data-stageId="<?php echo e($stage->id); ?>">
                                                    <?php $__currentLoopData = $stage->requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <div class="card border" data-applicantId="<?php echo e($request->id); ?>">
                                                            <div class="card-body px-3 py-3">
                                                                <div class="float-end">
                                                                    <?php if(Gate::check('edit wo request') || Gate::check('delete wo request')): ?>
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
                                                                                    data-bs-original-title="<?php echo e(__('Details')); ?>"
                                                                                    data-url="<?php echo e(route('wo-request.show', \Crypt::encrypt($request->id))); ?>"
                                                                                    data-title="<?php echo e(__('WO Request Detail')); ?>">
                                                                                    <i class="ti ti-eye"></i>
                                                                                    <?php echo e(__('View')); ?>

                                                                                </a>

                                                                                <a class="dropdown-item customModal"
                                                                                    href="#" data-bs-toggle="tooltip"
                                                                                    data-size="lg"
                                                                                    data-bs-original-title="<?php echo e(__('Edit')); ?>"
                                                                                    data-url="<?php echo e(route('wo-request.edit', \Crypt::encrypt($request->id))); ?>"
                                                                                    data-title="<?php echo e(__('WO Request Edit')); ?>">
                                                                                    <i class="ti ti-pencil"></i>
                                                                                    <?php echo e(__('Edit')); ?>

                                                                                </a>

                                                                                <?php echo Form::open([
                                                                                    'method' => 'DELETE',
                                                                                    'route' => ['wo-request.destroy', \Crypt::encrypt($request->id)],
                                                                                    'id' => 'wo-request-' . $request->id,
                                                                                ]); ?>


                                                                                <a class="dropdown-item confirm_dialog"
                                                                                    href="#">
                                                                                    <i class="ti ti-trash"></i>
                                                                                    <?php echo e(__('Delete')); ?>

                                                                                </a>

                                                                                <?php echo Form::close(); ?>


                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="link-secondary text-sm mt-1">
                                                                    <?php echo e($request->request_detail); ?>

                                                                    </h5>

                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span><?php echo e(__('Client')); ?> :</span>
                                                                        <?php echo e(optional($request->clients)->name); ?>

                                                                    </div>

                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span><?php echo e(__('Asset')); ?> :</span>
                                                                        <?php echo e(optional($request->assets)->name); ?>

                                                                    </div>
                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span><?php echo e(__('Assign')); ?> :</span>
                                                                        <?php echo e(optional($request->assigned)->name); ?>

                                                                    </div>
                                                                    <div class="link-secondary text-sm mt-1">
                                                                        <span><?php echo e(__('Due Date')); ?> :</span>
                                                                        <?php echo e(DateFormat($request->due_date)); ?>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php $__env->stopSection(); ?>

    <?php $__env->startPush('script-page'); ?>
        <script src="<?php echo e(asset('assets/js/plugins/dragula.min.js')); ?>"></script>
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
                                    url: '<?php echo e(route('service.change.status')); ?>',
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
    <?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/service/kanban.blade.php ENDPATH**/ ?>