<?php
if ($project->type == 'project') {
$name = 'Project';
} else {
$name = 'Project Template';
}
?>
<?php $__env->startSection('page-title'); ?>
<?php echo e(__($name . ' Detail')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('css'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/dropzone.css')); ?>" type="text/css" />
<link rel="stylesheet" href="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/css/custom.css')); ?>" type="text/css" />
<style>

/* Team members container - auto height, no scroll unless needed */
.team-members-wrap {
    max-height: none;
    overflow-x: hidden;
}

.team-members-wrap .row {
    flex-wrap: wrap;
}

/* Team member text truncation */
.team-member-name {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    font-size: 0.875rem;
}

.team-member-email {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    font-size: 0.75rem;
}

/* Fix overflow in parent containers */
.deta-card .card-body,
.col-xxl-12,
.project-detail-wrp {
    overflow-x: hidden;
}

</style>


<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__($name . ' Detail')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-action'); ?>

<div class="d-flex">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->type == 'project'): ?>
    <?php echo $__env->yieldPushContent('addButtonHook'); ?>
    <?php else: ?>
    <?php echo $__env->yieldPushContent('projectConvertButton'); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    <!-- Indent and Purchase Order Buttons -->
    <?php if (app('laratrust')->hasPermission('indent show')) : ?>
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="<?php echo e(route('indent.index', ['site_id' => $project->id])); ?>"
           data-bs-toggle="tooltip" 
           data-bs-original-title="<?php echo e(__('Indents')); ?>">
            <i class="ti ti-file-invoice"></i>
        </a>
    </div>
    <?php endif; // app('laratrust')->permission ?>
    <?php if (app('laratrust')->hasPermission('purchase-order show')) : ?>
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="<?php echo e(route('purchase-order.index', ['site_id' => $project->id])); ?>"
           data-bs-toggle="tooltip" 
           data-bs-original-title="<?php echo e(__('Purchase Orders')); ?>">
            <i class="ti ti-shopping-cart"></i>
        </a>
    </div>
    <?php endif; // app('laratrust')->permission ?>
    <?php if (app('laratrust')->hasPermission('grn show')) : ?>
    <div class="col-sm-auto">
        <a class="btn btn-sm btn-primary me-2" 
           href="<?php echo e(route('grn.index', ['site_id' => $project->id])); ?>"
           data-bs-toggle="tooltip" 
           data-bs-original-title="<?php echo e(__('GRN')); ?>">
            <i class="ti ti-package"></i>
        </a>
    </div>
    <?php endif; // app('laratrust')->permission ?>
    
    
    
    <!--    <div class="col-md-auto  pb-3">
            <a href="#" class="btn btn-sm  align-items-center cp_link bg-primary me-2"
                data-link="<?php echo e(route('project.shared.link', [\Illuminate\Support\Facades\Crypt::encrypt($project->id)])); ?>"
                data-toggle="tooltip" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Copy')); ?>">
                <span class="btn-inner--text text-white">
                    <i class="ti ti-copy"></i></span>
            </a>
        </div>-->
    <?php if (app('laratrust')->hasPermission('project setting')) : ?>
    <?php
    $title =
    module_is_active('ProjectTemplate') && $project->type == 'template'
    ? __('Shared Project Template Settings')
    : __('Shared Project Settings');
    ?>
    <!--        <div class="col-sm-auto">
                <a href="#" class="btn btn-sm me-2 btn-primary" data-title="<?php echo e($title); ?>"
                    data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip"
                    data-bs-original-title="<?php echo e(__('Shared Project Setting')); ?>"
                    data-url="<?php echo e(route('project.setting', [$project->id])); ?>">
                    <i class="ti ti-settings"></i>
                </a>
            </div>-->
    <?php endif; // app('laratrust')->permission ?>
    <?php if (app('laratrust')->hasPermission('task manage')) : ?>
            <!-- <div class="col-sm-auto">
                <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="<?php echo e(route('projects.calendar',[$project->id])); ?>"
                    data-bs-original-title="<?php echo e(__('Calendar')); ?>">
                    <i class="ti ti-calendar"></i>
                </a>
            </div> -->
    <?php endif; // app('laratrust')->permission ?>
    <!--        <div class="col-sm-auto">
                <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="<?php echo e(route('projects.gantt', [$project->id])); ?>"
                    data-bs-original-title="<?php echo e(__('Gantt Chart')); ?>">
                    <i class="ti ti-chart-bar"></i>
                </a>
            </div>-->
    
    

<?php if (app('laratrust')->hasPermission('project finance manage')) : ?>
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="<?php echo e(route('projects.proposal', [$project->id])); ?>"
                data-bs-original-title="<?php echo e(__('Finance')); ?>">
                <i class="ti ti-file-analytics"></i>
            </a>
        </div>-->
<?php endif; // app('laratrust')->permission ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(module_is_active('Procurement')): ?>
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="<?php echo e(route('rfx.index')); ?>"
                data-bs-original-title="<?php echo e(__('RFx')); ?>">
                <i class="ti ti-clipboard"></i>
            </a>
        </div>-->
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (app('laratrust')->hasPermission('bug manage')) : ?>
<!--        <div class="col-sm-auto">
            <a class="btn btn-sm me-2 btn-primary" data-bs-toggle="tooltip" href="<?php echo e(route('projects.bug.report', [$project->id])); ?>"
                data-bs-original-title="<?php echo e(__('Bug Report')); ?>">
                <i class="ti ti-bug"></i>
            </a>
        </div>-->
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('milestone create')) : ?>
        <!-- <div class="col-sm-auto">
            <a class="btn btn-sm btn-primary me-2" data-ajax-popup="true" data-size="lg" data-title="<?php echo e(__('Create Milestone')); ?>"
                data-url="<?php echo e(route('projects.milestone', [$project->id])); ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Create Milestone')); ?>"><i
                    class="ti ti-flag"></i></a>
        </div> -->
<?php endif; // app('laratrust')->permission ?>





<?php if (app('laratrust')->hasPermission('purchase-invoice manage')) : ?>
<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="<?php echo e(route('purchase-invoice.index')); ?>"
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Purchase Invoice')); ?>">
        <i class="ti ti-file-invoice"></i>
    </a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('activity manage')) : ?>
<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="<?php echo e(route('activities.index')); ?>"
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Site Activity')); ?>">
        <i class="ti ti-activity"></i>
    </a>
</div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('task manage')) : ?>
        <div class="col-sm-auto">
            <a class="btn btn-sm btn-primary me-2" data-bs-toggle="tooltip" href="<?php echo e(route('projects.task.board', [$project->id])); ?>"
                data-bs-original-title="<?php echo e(__('Task Board')); ?>">
                <i class="ti ti-layout-kanban"></i>
            </a>
        </div>
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('manpower manage')) : ?>
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="<?php echo e(route('manpower.index')); ?>"
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Man-Power')); ?>">
        <i class="ti ti-users"></i>
    </a>
</div>-->
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('consumption-log manage')) : ?>
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="<?php echo e(route('daily-consumption.index')); ?>"
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Consumption Log')); ?>">
        <i class="ti ti-notebook"></i>
    </a>
</div>-->
<?php endif; // app('laratrust')->permission ?>

<?php if (app('laratrust')->hasPermission('material-transfer manage')) : ?>
<!--<div class="col-sm-auto">
    <a class="btn btn-sm btn-primary me-2" 
       href="<?php echo e(route('material-transfer.index')); ?>"
       data-bs-toggle="tooltip" 
       data-bs-original-title="<?php echo e(__('Material Transfer')); ?>">
        <i class="ti ti-transfer"></i>
    </a>
</div>-->
<?php endif; // app('laratrust')->permission ?>




</div>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('css'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/dropzone.min.css')); ?>">

<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="row row-gap mb-4 ">
    <div class="col-xxl-12 col-12">
        <div class="dashboard-card project-detail-card">
            <div class="card-inner">
                <div class="card-content">
                    <h2><?php echo e($project->name); ?></h2>
                    <p><?php echo e($project->description); ?></p>
                    <div class="btn-wrp d-flex gap-3">
                        <?php if (app('laratrust')->hasPermission('project edit')) : ?>
                        <a href="#" class="btn btn-primary " tabindex="0" data-size="lg" data-url="<?php echo e(route('projects.edit',$project->id)); ?>" data-ajax-popup="true" data-title="<?php echo e(__('Edit ') . $name); ?>" data-bs-toggle="tooltip"  title="<?php echo e(__('Edit')); ?>" data-original-title="<?php echo e(__('Edit')); ?>">
                            <i class="ti ti-pencil text-success"></i>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>
                        <?php if (app('laratrust')->hasPermission('project delete')) : ?>
                        <?php echo e(Form::open(['route' => ['projects.destroy', $project->id], 'class' => 'm-0'])); ?>

                        <?php echo method_field('DELETE'); ?>
                        <!--                                    <a href="#" class="btn btn-light show_confirm" tabindex="0" data-bs-toggle="tooltip" title=""
                                                            data-bs-original-title="<?php echo e(__('Delete')); ?>" aria-label="<?php echo e(__('Delete')); ?>"
                                                            data-confirm-yes="delete-form-<?php echo e($project->id); ?>">
                                                                <i class="ti ti-trash text-danger"></i>
                                                            </a>-->
                        <?php echo e(Form::close()); ?>

                        <?php endif; // app('laratrust')->permission ?>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->type == 'project'): ?>
                <div class="status-info">
                    <div class="status-wrp">
                        <span class="d-block"><?php echo e(__('Start Date')); ?>:</span>
                        <p class="mb-0"><?php echo e(company_date_formate($project->start_date)); ?></p>
                    </div>
                    <div class="status-wrp">
                        <span class="d-block"><?php echo e(__('Due Date')); ?>:</span>
                        <p class="mb-0"><?php echo e(company_date_formate($project->end_date)); ?></p>
                    </div>
                    <div class="status-wrp">
                        <span class="d-block"><?php echo e(__('Total Members')); ?>:</span>
                        <p class="mb-0"><?php echo e((int) $project->users->count() + (int) $project->clients->count()); ?></p>
                    </div>
                    <div class="status-wrp ">
                        <span class="d-block">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->status == 'Finished'): ?>
                            <div class="badge bg-success p-2 f-12 text-capitalize"> <?php echo e(__('Finished')); ?>

                            </div>
                            <?php elseif($project->status == 'Ongoing'): ?>
                            <div class="badge bg-secondary p-2 f-12 text-capitalize"><?php echo e(__('Ongoing')); ?>

                            </div>
                            <?php else: ?>
                            <div class="badge bg-warning p-2 f-12 text-capitalize"><?php echo e(__('OnHold')); ?>

                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
    <!-- <div class="col-xxl-6 col-12">
        <div class="row dashboard-wrp">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->type == 'project'): ?>
            <div class="col-sm-6 col-12">
                <div class="dashboard-project-card">
                    <div class="card-inner  d-flex justify-content-between">
                        <div class="card-content">
                            <div class="theme-avtar bg-white">
                                <i class="fas fas fa-calendar-day text-danger"></i>
                            </div>
                            <h3 class="mt-3 mb-0 text-danger"><?php echo e(__('Days left')); ?></h3>
                        </div>
                        <h3 class="mb-0"><?php echo e($daysleft); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-12">
                <div class="dashboard-project-card">
                    <div class="card-inner  d-flex justify-content-between">
                        <div class="card-content">
                            <div class="theme-avtar bg-white">
                                <i class="fas fa-money-bill-alt"></i>
                            </div>
                            <h3 class="mt-3 mb-0"><?php echo e(__('Budget')); ?></h3>
                        </div>
                        <h3 class="mb-0"><?php echo e(company_setting('defult_currancy')); ?>

                            <?php echo e(number_format($project->budget)); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php
            $class = $project->type == 'template' ? 'col-lg-6 col-6 mt-3' : 'col-lg-3 col-6 mt-3';
            ?>
                            <div class="col-sm-6 col-12">
                                <div class="dashboard-project-card">
                                    <div class="card-inner  d-flex justify-content-between">
                                        <div class="card-content">
                                            <div class="theme-avtar bg-white">
                                                <i class="ti ti-file-invoice text-danger"></i>
                                            </div>
                                        <h3 class="mt-3 mb-0"><?php echo e(__('Total Task')); ?></h3>
                                        </div>
                                        <h3 class="mb-0"><?php echo e($project->countTask()); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-12">
                                <div class="dashboard-project-card">
                                    <div class="card-inner d-flex justify-content-between">
                                        <div class="card-content">
                                            <div class="theme-avtar bg-white">
                                                <i class="ti ti-message-circle-2"></i>
                                            </div>
                                            <h3 class="mt-3 mb-0"><?php echo e(__('Comment')); ?></h3>
                                        </div>
                                        <h3 class="mb-0"><?php echo e($project->countTaskComments()); ?></h3>
                                    </div>
                                </div>
                            </div>
        </div>
    </div> -->


</div>

<?php if (app('laratrust')->hasPermission('project dashboard manage')) : ?>

<?php echo $__env->make('taskly::projects.dashboard-modern', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="row project-detail-wrp">
    <div class="col-xxl-12 col-md-12">
        <div class="card deta-card">
            <div class="card-header p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><?php echo e(__('Team Members')); ?>

                            (<?php echo e(count($project->users)); ?>)
                        </h5>
                    </div>
                    <div class="text-end">
                        <p class="text-muted d-sm-flex align-items-center mb-0">

                                                      <a href="#" class="btn btn-sm btn-primary"
                                                           data-ajax-popup="true" data-title="<?php echo e(__('Invite')); ?>"
                                                           data-bs-toggle="tooltip" data-bs-title="<?php echo e(__('Invite')); ?>"
                                                           data-url="<?php echo e(route('projects.invite.popup', [$project->id])); ?>"><i
                                                                class="ti ti-brand-telegram"></i></a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body p-3 team-members-wrap">
                <div class="row g-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $project->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <div class="list-group-item p-2">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <img alt="image"
                                             data-bs-toggle="tooltip"
                                             data-bs-placement="top"
                                             title="<?php echo e($user->name); ?>"
                                             <?php if($user->avatar): ?> src="<?php echo e(get_file($user->avatar)); ?>" <?php else: ?> src="<?php echo e(get_file('avatar.png')); ?>" <?php endif; ?>
                                             class="rounded border border-primary"
                                             width="32" height="32">
                                    </div>
                                    <div class="flex-grow-1 ms-2 overflow-hidden">
                                        <h6 class="m-0 team-member-name"><?php echo e($user->name); ?></h6>
                                        <p class="text-muted mb-0 team-member-email"><?php echo e($user->email); ?></p>
                                        <span class="text-primary" style="font-size: 0.7rem;">
                                            <?php echo e((int) count($project->user_done_tasks($user->id))); ?>/<?php echo e((int) count($project->user_tasks($user->id))); ?>

                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<div class="col-xxl-12 col-12">
    <div class="card invoice-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?php echo e(__('Last 10 Purchase Invoices')); ?></h5>
                </div>
                <div class="text-end">
                    <a href="<?php echo e(route('purchase-invoice.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Invoice No')); ?></th>
                            <th><?php echo e(__('Invoice Date')); ?></th>
                            <th><?php echo e(__('Invoice Type')); ?></th>
                            <th><?php echo e(__('Supplier')); ?></th>

                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Total Amount')); ?></th>
                            <th><?php echo e(__('Payment Status')); ?></th>
                            <th><?php echo e(__('Invoice File')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($invoice->invoice_number); ?></td>
                            <td><?php echo e(\Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')); ?></td>
                            <td>
                                <?php
                                $map = [
                                'minor_misc_service' => ['label' => 'Minor/Misc Service', 'class' => 'badge bg-warning text-dark'],
                                'general_po' => ['label' => 'General PO', 'class' => 'badge bg-primary'],
                                ];
                                $type = $invoice->invoice_type;
                                $label = $map[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
                                $class = $map[$type]['class'] ?? 'badge bg-secondary';
                                ?>
                                <span class="<?php echo e($class); ?>"><?php echo e($label); ?></span>
                            </td>
                            <td><?php echo e(optional($invoice->supplier)->name ?? '—'); ?></td>

                            <td><?php echo e(optional($invoice->creator)->name ?? '—'); ?></td>
                            <td><?php echo e(currency_format_with_sym($invoice->total_amount)); ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                'unpaid' => ['label' => 'Unpaid', 'class' => 'badge bg-secondary'],
                                'paid' => ['label' => 'Paid', 'class' => 'badge bg-success'],
                                'overpaid' => ['label' => 'Overpaid', 'class' => 'badge bg-info text-dark'],
                                'partially paid' => ['label' => 'Partially Paid', 'class' => 'badge bg-warning text-dark'],
                                ];
                                $status = strtolower($invoice->payment_status);
                                $label = $statusMap[$status]['label'] ?? ucfirst($status);
                                $class = $statusMap[$status]['class'] ?? 'badge bg-secondary';
                                ?>
                                <span class="<?php echo e($class); ?>"><?php echo e($label); ?></span>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->invoice_file): ?>
                                <a href="<?php echo e(asset('storage/' . ltrim($invoice->invoice_file, '/'))); ?>" target="_blank"><?php echo e(__('Download')); ?></a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="col-xxl-12 col-12">
    <div class="card activity-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?php echo e(__('Last 10 Activities')); ?></h5>
                </div>
                <div class="text-end">
                    <a href="<?php echo e(route('activities.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Title')); ?></th>
                            <th><?php echo e(__('Date')); ?></th>
                            <th><?php echo e(__('Scope')); ?></th>
                            <th><?php echo e(__('Quantity')); ?></th>
                            <th><?php echo e(__('Completed Quantity')); ?></th>
                            <th><?php echo e(__('Unit')); ?></th>
                            <th><?php echo e(__('Priority')); ?></th>
                            <th><?php echo e(__('Progress')); ?></th>
                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Created At')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                        $completed = $activity->completeds->sum('completed_quantity');
                        $total = $activity->quantity;
                        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td><?php echo e($activity->title); ?></td>
                            <td><?php echo e(\Carbon\Carbon::parse($activity->date)->format('d-m-Y')); ?></td>
                            <td><?php echo e($activity->scope); ?></td>
                            <td><?php echo e($activity->quantity); ?></td>
                            <td><?php echo e($completed); ?></td>
                            <td><?php echo e($activity->unit); ?></td>
                            <td><?php echo e(ucfirst($activity->priority)); ?></td>
                            <td>
                                <div class="progress_wrapper">
                                    <div class="progress" style="width: 120px">
                                        <div class="progress-bar bg-success" role="progressbar"
                                             style="width: <?php echo e($percentage); ?>%;"
                                             aria-valuenow="<?php echo e($percentage); ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="progress_labels">
                                        <div class="total_progress">
                                            <strong><?php echo e($percentage); ?>%</strong>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo e(optional($activity->creator)->name ?? '—'); ?></td>
                            <td><?php echo e($activity->created_at->format('d-m-Y, h:i A')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card manpower-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?php echo e(__('Last 10 Manpower Records')); ?></h5>
                </div>
                <div class="text-end">
                    <a href="<?php echo e(route('manpower.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Work Date')); ?></th>
                            <th><?php echo e(__('Supplier')); ?></th>
                            <th><?php echo e(__('Site')); ?></th>
                            <th><?php echo e(__('Total Count')); ?></th>
                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Created At')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $manpowers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e(\Carbon\Carbon::parse($row->work_date)->format('d-m-Y')); ?></td>
                            <td><?php echo e(optional($row->supplier)->name ?? '—'); ?></td>
                            <td><?php echo e(optional($row->site)->name ?? '—'); ?></td>
                            <td><?php echo e($row->total_count); ?></td>
                            <td><?php echo e(optional($row->creator)->name ?? '—'); ?></td>
                            <td><?php echo e($row->created_at->format('d-m-Y, h:i A')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card consumption-card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?php echo e(__('Last 10 Daily Consumptions')); ?></h5>
                </div>
                <div class="text-end">
                    <a href="<?php echo e(route('daily-consumption.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Consumption No')); ?></th>
                            <th><?php echo e(__('Consumption Date')); ?></th>
                            <th><?php echo e(__('Consumption Type')); ?></th>
                            <th><?php echo e(__('Site')); ?></th>
                            <th><?php echo e(__('Consumption File')); ?></th>
                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Created At')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $consumptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $master): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($master->consumption_number); ?></td>
                            <td><?php echo e(\Carbon\Carbon::parse($master->consumption_date)->format('d-m-Y')); ?></td>
                            <td><?php echo e(ucfirst($master->consumption_type)); ?></td>
                            <td><?php echo e(optional($master->site)->name ?? '—'); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($master->consumption_file): ?>
                                <a href="<?php echo e(asset('storage/' . ltrim($master->consumption_file, '/'))); ?>" target="_blank"><?php echo e(__('Download')); ?></a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e(optional($master->creator)->name ?? '—'); ?></td>
                            <td><?php echo e($master->created_at->format('d-m-Y, h:i A')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12">
    <div class="card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Materials Transferred (Materials Out)')); ?></h5>
                <div class="text-end">
                    <a href="<?php echo e(route('material-transfer.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Record No')); ?></th>
                            <th><?php echo e(__('Record Date')); ?></th>
                            <th><?php echo e(__('From Site')); ?></th>
                            <th><?php echo e(__('To Site')); ?></th>
                            <th><?php echo e(__('Total Amount')); ?></th>
                            <th><?php echo e(__('Record File')); ?></th>
                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Created At')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transferredFrom; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transfer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($transfer->record_number); ?></td>
                            <td><?php echo e(\Carbon\Carbon::parse($transfer->record_date)->format('d-m-Y')); ?></td>
                            <td><?php echo e(optional($transfer->fromSite)->name ?? '—'); ?></td>
                            <td><?php echo e(optional($transfer->toSite)->name ?? '—'); ?></td>
                            <td><?php echo e(currency_format_with_sym($transfer->total_amount)); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transfer->record_file): ?>
                                <a href="<?php echo e(asset('storage/' . ltrim($transfer->record_file, '/'))); ?>" target="_blank"><?php echo e(__('Download')); ?></a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e(optional($transfer->creator)->name ?? '—'); ?></td>
                            <td><?php echo e($transfer->created_at->format('d M Y, h:i A')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-12 col-12 mt-4">
    <div class="card">
        <div class="card-header p-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e(__('Materials Transferred (Materials In)')); ?></h5>
                <div class="text-end">
                    <a href="<?php echo e(route('material-transfer.index', ['site_id' => $project->id])); ?>" class="btn btn-sm btn-primary">
                        <?php echo e(__('View All')); ?>

                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3 top-10-scroll">
            <div class="table-responsive">
                <table class="table table-bordered px-2">
                    <thead>
                        <tr>
                            <th><?php echo e(__('Record No')); ?></th>
                            <th><?php echo e(__('Record Date')); ?></th>
                            <th><?php echo e(__('From Site')); ?></th>
                            <th><?php echo e(__('To Site')); ?></th>
                            <th><?php echo e(__('Total Amount')); ?></th>
                            <th><?php echo e(__('Record File')); ?></th>
                            <th><?php echo e(__('Created By')); ?></th>
                            <th><?php echo e(__('Created At')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transferredTo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transfer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($transfer->record_number); ?></td>
                            <td><?php echo e(\Carbon\Carbon::parse($transfer->record_date)->format('d-m-Y')); ?></td>
                            <td><?php echo e(optional($transfer->fromSite)->name ?? '—'); ?></td>
                            <td><?php echo e(optional($transfer->toSite)->name ?? '—'); ?></td>
                            <td><?php echo e(currency_format_with_sym($transfer->total_amount)); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transfer->record_file): ?>
                                <a href="<?php echo e(asset('storage/' . ltrim($transfer->record_file, '/'))); ?>" target="_blank"><?php echo e(__('Download')); ?></a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e(optional($transfer->creator)->name ?? '—'); ?></td>
                            <td><?php echo e($transfer->created_at->format('d M Y, h:i A')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php endif; // app('laratrust')->permission ?>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>

<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Taskly\src\Providers/../Resources/views/projects/show.blade.php ENDPATH**/ ?>