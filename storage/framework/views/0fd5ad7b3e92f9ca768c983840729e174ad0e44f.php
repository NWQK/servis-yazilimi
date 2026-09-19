<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Dashboard')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item" aria-current="page"><?php echo e(__('Dashboard')); ?></li>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('css-page'); ?>
    <style>
        #calendar .fc-toolbar-chunk button {
            display: none
        }

        #calendar table {
            font-size: 11px;
            width: 100%;
        }

        /* Calendar compact - cell height ઘટાડો */
        #calendar .fc-daygrid-day {
            height: 55px !important;
            max-height: 55px !important;
        }

        #calendar .fc-daygrid-day-frame {
            min-height: 55px !important;
            max-height: 55px !important;
        }

        #calendar .fc-col-header-cell {
            padding: 3px 0 !important;
            font-size: 10px !important;
        }

        #calendar .fc-daygrid-day-number {
            font-size: 10px !important;
            padding: 2px 3px !important;
        }

        #calendar .fc-toolbar-title {
            font-size: 13px !important;
        }

        #calendar .fc-toolbar.fc-header-toolbar {
            margin-bottom: 5px !important;
        }

        #calendar .fc-event {
            font-size: 9px !important;
            padding: 0px 2px !important;
            line-height: 1.2 !important;
        }

        #calendar .fc-daygrid-body {
            width: 100% !important;
        }

        .card .fc-view-harness {
            height: auto !important;
        }
    </style>
<?php $__env->stopPush(); ?>
<?php $__env->startPush('script-page'); ?>
    <script>
        var eventData = <?php echo json_encode($eventData); ?>;
    </script>
    <script src="<?php echo e(asset('assets/js/plugins/index.global.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/pages/calendar.js')); ?>"></script>
    <script>
        var options = {
            chart: {
                type: 'area',
                height: 450,
                toolbar: {
                    show: false
                }
            },
            colors: ['#2ca58d', '#0a2342'],
            dataLabels: {
                enabled: false
            },
            legend: {
                show: true,
                position: 'top'
            },
            markers: {
                size: 1,
                colors: ['#fff', '#fff', '#fff'],
                strokeColors: ['#2ca58d', '#0a2342'],
                strokeWidth: 1,
                shape: 'circle',
                hover: {
                    size: 4
                }
            },
            stroke: {
                width: 2,
                curve: 'smooth'
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    type: 'vertical',
                    inverseColors: false,
                    opacityFrom: 0.5,
                    opacityTo: 0
                }
            },
            grid: {
                show: false
            },
            series: [{
                    name: "<?php echo e(__('Total Income')); ?>",
                    data: <?php echo json_encode($result['incomeExpenseByMonth']['income']); ?>,
                    // data: [50,20,50,70,55,80,70,60,40,50,30,60],
                },
                {
                    name: "<?php echo e(__('Total Expense')); ?>",
                    data: <?php echo json_encode($result['incomeExpenseByMonth']['expense']); ?>,
                    // data: [40,10,40,60,50,70,60,50,30,40,20,50],
                }
            ],
            xaxis: {
                categories: <?php echo json_encode($result['incomeExpenseByMonth']['label']); ?>,
                tooltip: {
                    enabled: false
                },
                labels: {
                    hideOverlappingLabels: true
                },
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                }
            }
        };
        var chart = new ApexCharts(document.querySelector('#incomeExpenseByMonth'), options);
        chart.render();
    </script>

    <script>
        var serviceStatusData = <?php echo json_encode(array_values($result['serviceStatusChart']), 15, 512) ?>;

        var options = {
            chart: {
                type: 'pie',
                height: 350
            },
            series: serviceStatusData,
            labels: [
                'Scheduled',
                'In Progress',
                'Completed',
                'Pending Parts',
                'On Hold',
                'Cancelled'
            ],
            legend: {
                position: 'bottom'
            },
            dataLabels: {
                enabled: true
            },
            colors: [
                '#0d6efd',
                '#6c757d',
                '#198754',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ]
        };

        var chart = new ApexCharts(
            document.querySelector("#serviceStatusChart"),
            options
        );

        chart.render();
    </script>
<?php $__env->stopPush(); ?>


<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-secondary">
                                <i class="ti ti-users f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1"><?php echo e(__('Total Client')); ?></p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0"><?php echo e($result['totalClient']); ?></h4>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-warning">
                                <i class="ti ti-package f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1"><?php echo e(__('Today Service')); ?></p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0"><?php echo e($result['todayService']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-primary">
                                <i class="ti ti-history f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1"><?php echo e(__('Curent Month Income')); ?></p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0"><?php echo e($result['curentMonthIncome']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-danger">
                                <i class="ti ti-credit-card f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1"><?php echo e(__('Curent Month Expense')); ?></p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0"><?php echo e($result['curentMonthExpense']); ?>

                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo e(__('Today Services')); ?></h5>
                </div>
                <div class="card-body pt-0 table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo e(__('ID')); ?></th>
                                <th><?php echo e(__('Vehicle')); ?></th>
                                <th><?php echo e(__('Client')); ?></th>
                                <th><?php echo e(__('Service Time')); ?></th>
                                <th><?php echo e(__('Status')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $result['incomingServices']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo e(route('service.show', \Crypt::encrypt($service->service_id))); ?>">
                                            <?php echo e(servicePrefix() . $service->service_id); ?>

                                        </a>
                                    </td>

                                    <td>
                                        <?php echo e(vehiclePrefix()); ?><?php echo e($service->vehicles->vehicle_id ?? '-'); ?>

                                    </td>

                                    <td>
                                        <?php echo e($service->clients->name ?? '-'); ?>

                                    </td>

                                    <td>
                                        <?php echo e(timeFormat($service->service_time)); ?>

                                    </td>

                                    <td>
                                        <?php if($service->status == 'scheduled'): ?>
                                            <span class="badge bg-light-primary">
                                                <?php echo e(__('Scheduled')); ?>

                                            </span>
                                        <?php elseif($service->status == 'in_progress'): ?>
                                            <span class="badge bg-light-secondary">
                                                <?php echo e(__('In Progress')); ?>

                                            </span>
                                        <?php elseif($service->status == 'completed'): ?>
                                            <span class="badge bg-light-success">
                                                <?php echo e(__('Completed')); ?>

                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light-warning">
                                                <?php echo e(ucfirst(str_replace('_', ' ', $service->status))); ?>

                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <?php echo e(__('No Services Today')); ?>

                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo e(__('Today Payments')); ?></h5>
                </div>

                <div class="card-body pt-0 table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo e(__('Invoice')); ?></th>
                                <th><?php echo e(__('Vehicle')); ?></th>
                                <th><?php echo e(__('Payment Time')); ?></th>
                                <th><?php echo e(__('Amount')); ?></th>
                                <th><?php echo e(__('Status')); ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $result['monthPayments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td>
                                        <a
                                            href="<?php echo e(route('invoice.show', \Crypt::encrypt($payment->invoice->invoice_id ?? '-'))); ?>">
                                            <?php echo e(invoicePrefix()); ?>

                                            <?php echo e($payment->invoice->invoice_id ?? '-'); ?>

                                        </a>
                                    </td>

                                    <td>
                                        <a class="avtar avtar-xs customModal" data-size="lg" data-bs-toggle="tooltip"
                                            data-bs-original-title="<?php echo e(__('Show')); ?>" href="#"
                                            data-url="<?php echo e(route('vehicle.show', $payment->invoice->services->vehicles->vehicle_id ?? '-')); ?>"
                                            data-title="<?php echo e(__('Details')); ?>">
                                            <?php echo e(vehiclePrefix()); ?>

                                            <?php echo e($payment->invoice->services->vehicles->vehicle_id ?? '-'); ?>

                                        </a>
                                    </td>

                                    <td>
                                        <?php echo e(dateFormat($payment->payment_date)); ?>

                                    </td>

                                    <td>
                                        <?php echo e(priceFormat($payment->amount)); ?>

                                    </td>

                                    <td>
                                        <?php if($payment->payment_status == 'success'): ?>
                                            <span class="badge bg-light-success">
                                                <?php echo e(__('Success')); ?>

                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light-danger">
                                                <?php echo e(__('Failed')); ?>

                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <?php echo e(__('No Payments Today')); ?>

                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- Pie Chart -->
        <div class="col-lg-6 d-flex">
            <div class="card w-100 h-100">
                <div class="card-header">
                    <h5><?php echo e(__('Service Status')); ?></h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div id="serviceStatusChart"></div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="col-lg-6 d-flex">
            <div class="card w-100 h-100">
                <div class="card-header">
                    <h5><?php echo e(__('Current Month Calendar')); ?></h5>
                </div>
                <div class="card-body">
                    <div id="calendar" class="calendar"></div>
                </div>
            </div>
        </div>

        <!-- Analysis Report -->
        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h5 class="mb-1"><?php echo e(__('Analysis Report')); ?></h5>
                            <p class="text-muted mb-2"><?php echo e(__('Income and Expense Overview')); ?></p>
                        </div>
                    </div>
                    <div id="incomeExpenseByMonth"></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="calendar-modal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header justify-content-between align-items-center">
                        <h3 class="calendar-modal-title f-w-600 text-truncate"><?php echo e(__('Modal title')); ?></h3>
                        <a href="#" class="avtar avtar-s btn-link-danger btn-pc-default" data-bs-dismiss="modal">
                            <i class="ti ti-x f-20"></i>
                        </a>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-secondary">
                                    <i class="ti ti-tools f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b><?php echo e(__('Service Name')); ?></b></h5>
                                <p class="pc-event-title text-muted"></p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-warning">
                                    <i class="ti ti-user f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b><?php echo e(__('Assign By')); ?></b></h5>
                                <p class="pc-event-assign text-muted"></p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-info">
                                    <i class="ti ti-calendar-event f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b><?php echo e(__('Scheduled Date')); ?></b></h5>
                                <p class="pc-event-date text-muted"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item align-bottom">
                                <a href="#" id="pc_event_show"
                                    class="avtar avtar-s btn-link-warning btn-pc-default text-warning bg-light-warning"
                                    data-bs-toggle="tooltip" title="Edit">
                                    <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="flex-grow-1 text-end">
                            <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal"><?php echo e(__('Close')); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\main_file\resources\views/dashboard/index.blade.php ENDPATH**/ ?>