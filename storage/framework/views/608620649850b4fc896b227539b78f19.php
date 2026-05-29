<?php $__env->startSection('page-title'); ?>
    <?php echo e(__('Manage Projects')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-breadcrumb'); ?>
    <?php echo e(__('Projects')); ?>

<?php $__env->stopSection(); ?>
<?php $__env->startPush('css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/css/custom.css')); ?>" type="text/css" />
<?php $__env->stopPush(); ?>
<?php $__env->startSection('page-action'); ?>
    <div class="d-flex">
        <?php echo $__env->yieldPushContent('project_template_button'); ?>
        <?php if (app('laratrust')->hasPermission('project import')) : ?>
<!--            <a href="javascript:void(0)" class="btn btn-sm btn-primary me-2" data-ajax-popup="true"
                data-title="<?php echo e(__('Project Import')); ?>" data-url="<?php echo e(route('project.file.import')); ?>" data-bs-toggle="tooltip"
                title="<?php echo e(__('Import')); ?>"><i class="ti ti-file-import"></i>
            </a>-->
        <?php endif; // app('laratrust')->permission ?>
        <a href="<?php echo e(route('projects.list')); ?>" class="btn btn-sm btn-primary me-2" data-bs-toggle="tooltip"
            title="<?php echo e(__('List View')); ?>">
            <i class="ti ti-list text-white"></i>
        </a>
        <?php if (app('laratrust')->hasPermission('project create')) : ?>
            <a class="btn btn-sm btn-primary me-2" data-ajax-popup="true" data-size="md"
                data-title="<?php echo e(__('Create Project')); ?>" data-url="<?php echo e(route('projects.create')); ?>" data-bs-toggle="tooltip"
                title="<?php echo e(__('Create')); ?>">
                <i class="ti ti-plus"></i>
            </a>
        <?php endif; // app('laratrust')->permission ?>
    </div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
    <section class="section">
        <div class="row ">
            <div class="col-xl-12 col-lg-12 col-md-12 d-flex align-items-center justify-content-end">
                <div class="text-sm-right status-filter">
                    <div class="btn-group mb-3">
                        <button type="button" class="btn btn-light  text-white btn_tab  bg-primary active"
                            data-filter="All" data-status="All"><?php echo e(__('All')); ?></button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Ongoing"><?php echo e(__('Ongoing')); ?></button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Finished"><?php echo e(__('Finished')); ?></button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="OnHold"><?php echo e(__('OnHold')); ?></button>
                    </div>
                </div>
            </div><!-- end col-->
        </div>

        <div id="multiCollapseExample1" class="d-none">
            <div class="card">
                <div class="card-body">
                    <?php echo e(Form::open(['route' => ['projects.index'], 'method' => 'GET', 'id' => 'project_submit'])); ?>

                    <div class="row d-flex align-items-center justify-content-end">
                        <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                            <div class="btn-box">
                                <?php echo e(Form::label('start_date', __('Start Date'), ['class' => 'form-label'])); ?>

                                <?php echo e(Form::date('start_date', isset($_GET['start_date']) ? $_GET['start_date'] : null, ['class' => 'form-control ', 'placeholder' => 'Select Date'])); ?>


                            </div>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                            <div class="btn-box">
                                <?php echo e(Form::label('end_date', __('End Date'), ['class' => 'form-label'])); ?>

                                <?php echo e(Form::date('end_date', isset($_GET['end_date']) ? $_GET['end_date'] : null, ['class' => 'form-control ', 'placeholder' => 'Select Date'])); ?>


                            </div>
                        </div>
                        <div class="col-auto float-end mt-4 d-flex">

                            <a href="javascript:void(0)" class="btn btn-sm btn-primary me-2"
                                onclick="document.getElementById('project_submit').submit(); return false;"
                                data-bs-toggle="tooltip" title="<?php echo e(__('Apply')); ?>"
                                data-original-title="<?php echo e(__('apply')); ?>">
                                <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                            </a>
                            <a href="<?php echo e(route('projects.index')); ?>" class="btn btn-sm btn-danger" data-toggle="tooltip"
                                data-original-title="<?php echo e(__('Reset')); ?>">
                                <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off"></i></span>
                            </a>
                        </div>
                    </div>
                    <?php echo e(Form::close()); ?>

                </div>
            </div>
        </div>

        <div class="filters-content">
            <div class="row mb-4 project-wrp d-flex">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($projects)): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-xxl-3 col-xl-4 col-md-6 col-12 All <?php echo e($project->status); ?>">
                            <div class="project-card">
                                <div class="project-card-inner">
                                    <div class="project-card-header d-flex justify-content-between">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->status == 'Finished'): ?>
                                            <p class="badge bg-success mb-0 d-flex align-items-center"><?php echo e(__('Finished')); ?></p>
                                        <?php elseif($project->status == 'Ongoing'): ?>
                                            <p class="badge bg-secondary mb-0 d-flex align-items-center"><?php echo e(__('Ongoing')); ?>

                                            </p>
                                        <?php else: ?>
                                            <p class="badge bg-warning mb-0 d-flex align-items-center"><?php echo e(__('OnHold')); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->is_active): ?>
                                            <button type="button"
                                                class="btn btn-light dropdown-toggle d-flex align-items-center justify-content-center"
                                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="ti ti-dots-vertical text-black"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end pointer">
                                                <?php if (app('laratrust')->hasPermission('project invite user')) : ?>
<!--                                                    <a href="#!" data-ajax-popup="true" data-size="md"
                                                        data-title="<?php echo e(__('Invite Users')); ?>"
                                                        data-url="<?php echo e(route('projects.invite.popup', [$project->id])); ?>"
                                                        class="dropdown-item" tabindex="0"><i
                                                            class="ti ti-user-plus me-1"></i><span><?php echo e(__('Invite Users')); ?></span></a>-->
                                                <?php endif; // app('laratrust')->permission ?>

                                                <?php if (app('laratrust')->hasPermission('project manage')) : ?>
<!--                                                    <a class="dropdown-item" data-ajax-popup="true" data-size="md"
                                                        data-title="<?php echo e(__('Share to Clients')); ?>"
                                                        data-url="<?php echo e(route('projects.share.popup', [$project->id])); ?>">
                                                        <i class="ti ti-share me-1"></i> <span><?php echo e(__('Share to Clients')); ?></span>
                                                    </a>-->
                                                <?php endif; // app('laratrust')->permission ?>
                                                <?php if (app('laratrust')->hasPermission('project create')) : ?>
<!--                                                    <a class="dropdown-item" data-ajax-popup="true" data-size="md"
                                                        data-title="<?php echo e(__('Duplicate Project')); ?>"
                                                        data-url="<?php echo e(route('project.copy', [$project->id])); ?>">
                                                        <i class="ti ti-copy me-1"></i> <span><?php echo e(__('Duplicate')); ?></span>
                                                    </a>-->
                                                <?php endif; // app('laratrust')->permission ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(module_is_active('ProjectTemplate')): ?>
                                                    <?php if (app('laratrust')->hasPermission('project template create')) : ?>
<!--                                                        <a class="dropdown-item" data-ajax-popup="true" data-size="md"
                                                            data-title="<?php echo e(__('Save As Template')); ?>"
                                                            data-url="<?php echo e(route('project-template.create', ['project_id' => $project->id, 'type' => 'template'])); ?>">
                                                            <i class="ti ti-bookmark me-1"></i>
                                                            <span><?php echo e(__('Save as template')); ?></span>
                                                        </a>-->
                                                    <?php endif; // app('laratrust')->permission ?>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if (app('laratrust')->hasPermission('project edit')) : ?>
                                                    <a class="dropdown-item" data-ajax-popup="true" data-size="lg"
                                                        data-title="<?php echo e(__('Edit Project')); ?>"
                                                        data-url="<?php echo e(route('projects.edit', [$project->id])); ?>">
                                                        <i class="ti ti-pencil me-1"></i> <span><?php echo e(__('Edit')); ?></span>
                                                    </a>
                                                <?php endif; // app('laratrust')->permission ?>
                                                <?php if (app('laratrust')->hasPermission('project delete')) : ?>
                                                    <form id="delete-form-<?php echo e($project->id); ?>"
                                                        action="<?php echo e(route('projects.destroy', [$project->id])); ?>" method="POST">
                                                        <?php echo csrf_field(); ?>
<!--                                                        <a href="javascript:void(0)"
                                                            class="dropdown-item text-danger delete-popup bs-pass-para show_confirm"
                                                            data-confirm="<?php echo e(__('Are You Sure?')); ?>"
                                                            data-text="<?php echo e(__('This action can not be undone. Do you want to continue?')); ?>"
                                                            data-confirm-yes="delete-form-<?php echo e($project->id); ?>">
                                                            <i class="ti ti-trash me-1"></i> <span><?php echo e(__('Delete')); ?></span>
                                                        </a>-->
                                                        <?php echo method_field('DELETE'); ?>
                                                    </form>
                                                <?php endif; // app('laratrust')->permission ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="btn">
                                                <i class="ti ti-lock"></i>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="project-card-content">
                                        <div class="project-content-top">
                                            <div class="user-info  d-flex align-items-center">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->is_active): ?>
                                                    <a href="<?php if (app('laratrust')->hasPermission('project manage')) : ?> <?php echo e(route('projects.show', [$project->id])); ?> <?php endif; // app('laratrust')->permission ?>"
                                                        class="wid-30 me-2 border-1 border border-primary rounded-circle"
                                                        tabindex="0">
                                                        <img alt="<?php echo e($project->name); ?>" class="img-fluid  fix_img"
                                                            avatar="<?php echo e($project->name); ?>">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="javascript:void(0)"
                                                        class="wid-30 me-2 border-1 border border-primary rounded-circle"
                                                        tabindex="0">
                                                        <img alt="<?php echo e($project->name); ?>" class="img-fluid  fix_img"
                                                            avatar="<?php echo e($project->name); ?>">
                                                    </a>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <h2 class="h5 mb-0">
                                                    <a href="<?php if (app('laratrust')->hasPermission('project manage')) : ?> <?php echo e(route('projects.show', [$project->id])); ?> <?php endif; // app('laratrust')->permission ?>"
                                                        tabindex="0" title="<?php echo e($project->name); ?>"
                                                        class=""><?php echo e($project->name); ?></a>
                                                </h2>
                                            </div>
                                            <p><?php echo e($project->description); ?></p>
                                            <div class="d-flex gap-2 align-items-center justify-content-between">
                                                <p class="mb-0"><b><?php echo e(__('Due Date')); ?> : </b><?php echo e($project->end_date); ?></p>
                                                <div class="view-btn d-flex gap-2 align-items-center">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->is_active): ?>
                                                        <a class="btn btn-warning" data-bs-toggle="tooltip"
                                                            href="<?php echo e(route('projects.show', [$project->id])); ?>"
                                                            data-bs-original-title="<?php echo e(__('View')); ?>">
                                                            <i class="ti ti-eye text-white"></i>
                                                        </a>
<!--                                                        <a class="btn btn-primary" data-bs-toggle="tooltip"
                                                            href="<?php echo e(route('projects.task.board', [$project->id])); ?>"
                                                            data-bs-original-title="<?php echo e(__('Task Board')); ?>">
                                                            <i class="ti ti-list-check"></i>
                                                        </a>-->
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="project-content-bottom d-flex align-items-center justify-content-between gap-2">
                                            <div class="user-image">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $project->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->pivot->is_active): ?>
                                                        <img alt="<?php echo e($user->name); ?>" 
                                                             width="28" height="28"
                                                             data-bs-toggle="tooltip" 
                                                             data-bs-placement="top"
                                                             title="<?php echo e($user->name); ?>"
                                                             src="<?php echo e(check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png')); ?>"
                                                             class="rounded-circle border border-white me-1">
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <span class="ms-1"><?php echo e(__('Members')); ?></span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard('web')->check()): ?>
                    <?php if (app('laratrust')->hasPermission('project create')) : ?>
                        <div class="col-xxl-3 col-xl-4 col-md-6 col-12 All Ongoing Finished OnHold">
                            <div class="project-card-inner">
                                <a href="javascript:void(0)" class="btn-addnew-project " data-ajax-popup="true" data-size="md"
                                    data-title="<?php echo e(__('Create New Project')); ?>" data-url="<?php echo e(route('projects.create')); ?>">
                                    <div class="bg-primary proj-add-icon">
                                        <i class="ti ti-plus"></i>
                                    </div>
                                    <h6 class="mt-4 mb-2"><?php echo e(__('Add Project')); ?></h6>
                                    <p class="text-muted text-center mb-0"><?php echo e(__('Click here to add New Project')); ?></p>
                                </a>
                            </div>
                        </div>
                    <?php endif; // app('laratrust')->permission ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php echo $projects->links('vendor.pagination.global-pagination'); ?>

        </div>
    </section>
<?php $__env->stopSection(); ?>



<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/js/isotope.pkgd.min.js')); ?>"></script>

    <script src="<?php echo e(asset('js/letter.avatar.js')); ?>"></script>

    <script>
        $(document).ready(function() {

            $('.status-filter button').click(function() {
                $('.status-filter button').removeClass('active');
                $(this).addClass('active');
                var classAttr = $(this).data('filter');
                if (classAttr === 'All') {
                    // $('.All').removeClass('d-none');
                    $('.All').removeClass('d-none').css('opacity', 1);
                    $('.All').each(function() {
                        $(this).css('transform', 'translateX(0)');
                    });
                } else {
                    // $('.All').addClass('d-none');
                    // $('.' + classAttr).removeClass('d-none');
                    $('.All').addClass('d-none').css('opacity', 0).css('transform', 'translateX(-20px)');
                    $('.' + classAttr).removeClass('d-none').css('opacity', 1).css('transform', 'translateX(0)');
                }

            });

            // Check if the direction is RTL, then set right based on a repeating pattern
            if ($('html').attr('dir') === 'rtl') {
                var $allItems = $('.filters-content .All');
                $allItems.each(function(index) {
                    // Set right property based on a repeating pattern
                    $(this).css('right', (index % 4) * 25 + '%');
                });
            }
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Taskly\src\Providers/../Resources/views/projects/index.blade.php ENDPATH**/ ?>