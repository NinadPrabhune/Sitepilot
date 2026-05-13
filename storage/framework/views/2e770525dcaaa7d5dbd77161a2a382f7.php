<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Edit Activity')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('script-page'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('Activities')); ?> / <?php echo e(__('Edit')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <a href="<?php echo e(route('activities.index')); ?>" class="btn btn-sm btn-light border me-2">
        <i class="ti ti-arrow-left"></i> <?php echo e(__('Back')); ?>

    </a>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <!-- [ sample-page ] start -->
    <div class="col-sm-12">
        <div class="row">
            <div class="col-xl-3">
                <div class="card sticky-top" style="top:30px">
                    <div class="list-group list-group-flush" id="useradd-sidenav">

                        <a href="#activity-section"
                           class="list-group-item list-group-item-action border-0
                           <?php if(!session('highlight_last_completion')): ?> active <?php endif; ?>"
                           data-tab="activity-section">
                            <?php echo e(__('Activity')); ?>

                            <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                        </a>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activity->completeds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $completedIndex => $completed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="#completion-<?php echo e($completed->id); ?>"
                           class="list-group-item list-group-item-action border-0
                           <?php if(session('highlight_last_completion') && $loop->last): ?> active <?php endif; ?>"
                           data-tab="completion-<?php echo e($completed->id); ?>">
                            <?php echo e(__('Completion #')); ?><?php echo e($completedIndex + 1); ?> 
                            (<?php echo e($completed->completed_quantity); ?> <?php echo e($activity->unit); ?> - 
                            <?php echo e(\Carbon\Carbon::parse($completed->completed_date)->format('d M Y')); ?>)
                            <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                        </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>
                </div>
            </div>





            <div class="col-xl-9">
                <div id="activity-section">


                    <div class="card">
                        <div class="card-body">
                            <div class="form-container">
                                <form action="<?php echo e(route('activities.update', $activity->id)); ?>" method="POST" id="activityForm" class="needs-validation" novalidate enctype="multipart/form-data">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>

                                    <div class="row">
                                        
                                        <div class="form-group col-md-6">
                                            <?php echo e(Form::label('assign_to', __('Assign To'), ['class' => 'form-label'])); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
                                            <select class="multi-select choices" id="assign_to" name="assign_to[]" multiple="multiple" data-placeholder="<?php echo e(__('Select Users ...')); ?>" required>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($id); ?>" <?php if(!empty($activity) && in_array($id, explode(',', $activity->assign_to))): ?> selected <?php endif; ?>>
                                                    <?php echo e($name); ?>

                                                </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </select>
                                            <p class="text-danger d-none" id="user_validation"><?php echo e(__('Assign To field is required.')); ?></p>
                                        </div>

                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="title" class="form-label"><?php echo e(__('Title')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                                                <input type="text" name="title" class="form-control" placeholder="Enter Title" value="<?php echo e(old('title', $activity->title)); ?>" required>
                                            </div>
                                        </div>

                                        
                                        <div class="form-group col-md-6">
                                            <label class="form-label"><?php echo e(__('Duration')); ?></label><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
                                            <div class='input-group'>
                                                <input type='text' class="form-control form-control-light" id="duration" name="duration" required autocomplete="off" placeholder="Select date range"
                                                       value="<?php echo e(old('duration', \Carbon\Carbon::parse($activity->start_date)->format('M d, Y h:i A') . ' - ' . \Carbon\Carbon::parse($activity->due_date)->format('M d, Y h:i A'))); ?>">
                                                <input type="hidden" name="start_date" value="<?php echo e(old('start_date', $activity->start_date)); ?>">
                                                <input type="hidden" name="due_date" value="<?php echo e(old('due_date', $activity->due_date)); ?>">
                                                <span class="input-group-text"><i class="feather icon-calendar"></i></span>
                                            </div>
                                            <small class="text-danger d-none" id="duration_validation"><?php echo e(__('Due date must be after Start date.')); ?></small>
                                        </div>


                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="priority" class="form-label"><?php echo e(__('Priority')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                                                <select name="priority" class="form-control" required>
                                                    <option value="low" <?php echo e(old('priority', $activity->priority) == 'low' ? 'selected' : ''); ?>><?php echo e(__('Low')); ?></option>
                                                    <option value="medium" <?php echo e(old('priority', $activity->priority) == 'medium' ? 'selected' : ''); ?>><?php echo e(__('Medium')); ?></option>
                                                    <option value="high" <?php echo e(old('priority', $activity->priority) == 'high' ? 'selected' : ''); ?>><?php echo e(__('High')); ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="reference_file" class="form-label"><?php echo e(__('Reference File')); ?></label>
                                                <input type="file" name="reference_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activity->reference_file): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo e(asset('storage/' . $activity->reference_file)); ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="ti ti-file"></i> <?php echo e(__('View Current File')); ?>

                                                        </a>
                                                    </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <small class="text-muted"><?php echo e(__('Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG, XLS, XLSX (Max: 20MB)')); ?></small>
                                            </div>
                                        </div>




                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="scope" class="form-label"><?php echo e(__('Scope')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                                                <input type="text" name="scope" class="form-control" placeholder="Enter Scope" value="<?php echo e(old('scope', $activity->scope)); ?>" required>
                                            </div>
                                        </div>

                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="quantity" class="form-label"><?php echo e(__('Total Quantity')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                                                <input type="number" name="quantity" class="form-control" placeholder="Enter Quantity" value="<?php echo e(old('quantity', $activity->quantity)); ?>" min="0" required>
                                            </div>
                                        </div>

                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="unit" class="form-label"><?php echo e(__('Unit')); ?></label>
                                                <input type="text" name="unit" class="form-control" placeholder="Enter Unit" value="<?php echo e(old('unit', $activity->unit)); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="card border-0 shadow-sm mt-4">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-2"><?php echo e(__('Completed Quantity & Date')); ?></h5>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="addCompletedRow">
                                                <i class="ti ti-plus"></i> <?php echo e(__('Add Row')); ?>

                                            </button>
                                        </div>




                                        <div class="card-body">
                                            <div id="completedQuantityContainer">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $activity->completeds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $completed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <div class="row g-2 align-items-center mb-2 completed-row">

                                                    <div class="col-md-1">
                                                        <input type="hidden" name="activities_completed[<?php echo e($index); ?>][id]" value="<?php echo e($completed->id); ?>">
                                                        <span class="badge bg-secondary">#<?php echo e($completed->id); ?></span>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <input type="number" name="activities_completed[<?php echo e($index); ?>][completed_quantity]" class="form-control"
                                                               value="<?php echo e(old('completed_quantity.' . $index, $completed->completed_quantity)); ?>"
                                                               min="0" required>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <input type="date" name="activities_completed[<?php echo e($index); ?>][completed_date]" class="form-control"
                                                               value="<?php echo e(old('completed_date.' . $index, $completed->completed_date)); ?>"
                                                               required>
                                                    </div>

                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completed->completed_reference_file): ?>
                                                    <div class="col-md-3">
                                                        <input type="file" name="activities_completed[<?php echo e($index); ?>][completed_reference_file]" class="form-control"
                                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                        
                                                            
                                                    </div>
                                                    <div class="col-md-1">
                                                    <a href="<?php echo e(asset($completed->completed_reference_file)); ?>" target="_blank" class="btn btn-link btn-sm p-0">View/Download</a>
                                                    
                                                    </div>

                                                    <?php else: ?>
                                                    <div class="col-md-4">
                                                        <input type="file" name="activities_completed[<?php echo e($index); ?>][completed_reference_file]" class="form-control"
                                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">                                                        
                                                    </div>

                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    

                                                    <div class="col-md-1">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(
                                                        $completed->manpowers->count() == 0 &&
                                                        $completed->dailyProgressReports->count() == 0 &&
                                                        $completed->allConsumptions->count() == 0
                                                        ): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <button type="button"
                                                                class="btn btn-outline-secondary btn-sm w-100 locked-row"
                                                                data-manpower="<?php echo e($completed->manpowers->count()); ?>"
                                                                data-dpr="<?php echo e($completed->dailyProgressReports->count()); ?>"
                                                                data-consumption="<?php echo e($completed->allConsumptions->count()); ?>">
                                                            <i class="ti ti-lock"></i>
                                                        </button>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </div>

                                                </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>

                                                
                                                <div class="row g-2 align-items-center mb-2 completed-row">
                                                    <div class="col-md-1">
                                                        <span class="badge bg-primary">NEW</span>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <input type="number" name="activities_completed[new_0][completed_quantity]" class="form-control"
                                                               value="0" min="0" required>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <input type="date" name="activities_completed[new_0][completed_date]" class="form-control"
                                                               value="<?php echo e(now()->format('Y-m-d')); ?>" required>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <input type="file" name="activities_completed[new_0][completed_reference_file]" class="form-control"
                                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                    </div>

                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="text-end mt-4">
                                        <a href="<?php echo e(route('activities.index')); ?>" class="btn btn-light"><?php echo e(__('Cancel')); ?></a>
                                        <button type="submit" class="btn btn-primary"><?php echo e(__('Update Activity')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>


                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activity->completeds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $completedIndex => $completed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div id="completion-<?php echo e($completed->id); ?>" style="display:none;">
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-primary">
                            <h5 class="mb-0 text-white">
                                <?php echo e(__('Completion Entry #')); ?><?php echo e($completedIndex + 1); ?> 
                                <span class="badge bg-light text-dark ms-2">
                                    <?php echo e($completed->completed_quantity); ?> <?php echo e($activity->unit); ?>

                                </span>
                                <small class="text-white ms-2">
                                    (<?php echo e(\Carbon\Carbon::parse($completed->completed_date)->format('d M Y')); ?>)
                                </small>
                            </h5>
                        </div>
                        <div class="card-body">

                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo e(__('Man Power')); ?></h6>
                                    <a href="#"
                                       class="btn btn-sm btn-primary"
                                       data-url="<?php echo e(route('manpower.create', ['activity_completed_id' => $completed->id ?? '', 'activity_id' => $activity->id ?? '' ])); ?>"
                                       data-ajax-popup="true"
                                       data-size="lg"
                                       data-title="<?php echo e(__('Add Man Power to Completion')); ?>">
                                        <i class="ti ti-plus"></i> <?php echo e(__('Add Man Power')); ?>

                                    </a>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completed->manpowers->count() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th><?php echo e(__('Work Date')); ?></th>
                                                <th><?php echo e(__('Supplier')); ?></th>
                                                <th><?php echo e(__('Manpower Types')); ?></th>
                                                <th><?php echo e(__('Total Count')); ?></th>
                                                <th><?php echo e(__('Action')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completed->manpowers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $manpower): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr>
                                                <td><?php echo e(\Carbon\Carbon::parse($manpower->work_date)->format('d M Y')); ?></td>
                                                <td><?php echo e($manpower->supplier->name ?? '-'); ?></td>
                                                <td>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $manpower->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span class="badge bg-primary me-1">
                                                        <?php echo e($detail->type->name ?? 'N/A'); ?>: <?php echo e($detail->count); ?>

                                                    </span>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </td>
                                                <td><?php echo e($manpower->total_count); ?></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="#" data-url="<?php echo e(route('manpower.edit', $manpower->id)); ?>" data-ajax-popup="true" data-size="lg" data-title="<?php echo e(__('Edit Man Power')); ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <?php echo Form::open(['route' => ['manpower.destroy', $manpower->id], 'method' => 'DELETE', 'class' => 'd-inline', 'id' => 'delete-form-' . $manpower->id]); ?>

                                                        <a href="#" class="btn btn-sm btn-outline-danger show_confirm" data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>" onclick="document.getElementById('delete-form-<?php echo e($manpower->id); ?>').submit()">
                                                            <i class="ti ti-trash"></i>
                                                        </a>
                                                        <?php echo Form::close(); ?>

                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted"><?php echo e(__('No man power records for this completion.')); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo e(__('Daily Progress Report')); ?></h6>
                                    <a href="#"
                                       class="btn btn-sm btn-primary"
                                       data-url="<?php echo e(route('daily-progress-reports-new.createdpr', ['activity_completed_id' => $completed->id ?? '', 'activity_id' => $activity->id ?? '' ])); ?>"
                                       data-ajax-popup="true"
                                       data-size="xl"
                                       data-title="<?php echo e(__('Add DPR to Completion')); ?>">
                                        <i class="ti ti-plus"></i> <?php echo e(__('Add DPR')); ?>

                                    </a>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completed->dailyProgressReports->count() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th><?php echo e(__('Date')); ?></th>
                                                <th><?php echo e(__('Machinery')); ?></th>
                                                <th><?php echo e(__('Machine Start')); ?></th>
                                                <th><?php echo e(__('Machine End')); ?></th>
                                                <th><?php echo e(__('Actions')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completed->dailyProgressReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dpr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr>
                                                <td><?php echo e(\Carbon\Carbon::parse($dpr->date)->format('d M Y')); ?></td>
                                                <td><?php echo e($dpr->machinery->name ?? 'N/A'); ?></td>
                                                <td><?php echo e($dpr->machine_start_reading ?? '-'); ?></td>
                                                <td><?php echo e($dpr->machine_end_reading ?? '-'); ?></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a class="btn btn-sm btn-outline-info" data-url="<?php echo e(route('daily-progress-reports.edit', $dpr->id)); ?>" data-ajax-popup="true" data-size="xl" data-title="<?php echo e(__('Edit Daily Report')); ?>">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <?php echo Form::open(['method' => 'DELETE', 'route' => ['daily-progress-reports.destroy', $dpr->id], 'id' => 'delete-dpr-' . $dpr->id]); ?>

                                                        <a href="#" class="btn btn-sm btn-outline-danger show_confirm" data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>" onclick="document.getElementById('delete-dpr-<?php echo e($dpr->id); ?>').submit()">
                                                            <i class="ti ti-trash"></i>
                                                        </a>
                                                        <?php echo Form::close(); ?>

                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted"><?php echo e(__('No DPR records for this completion.')); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo e(__('Material Consumption')); ?></h6>
                                    <a href="#"
                                       class="btn btn-sm btn-primary"
                                       data-url="<?php echo e(route('daily-consumption.create', ['activity_completed_id' => $completed->id ?? '', 'activity_id' => $activity->id ?? '' ])); ?>"
                                       data-ajax-popup="true"
                                       data-size="xl"
                                       data-title="<?php echo e(__('Add Consumption to Completion')); ?>">
                                        <i class="ti ti-plus"></i> <?php echo e(__('Add Consumption')); ?>

                                    </a>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completed->allConsumptions->count() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th><?php echo e(__('Date')); ?></th>
                                                <th><?php echo e(__('Number')); ?></th>
                                                <th><?php echo e(__('Type')); ?></th>
                                                <th><?php echo e(__('Items')); ?></th>
                                                <th><?php echo e(__('Actions')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completed->allConsumptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr>
                                                <td><?php echo e(\Carbon\Carbon::parse($consumption->consumption_date)->format('d M Y')); ?></td>
                                                <td><?php echo e($consumption->consumption_number); ?></td>
                                                <td><?php echo e(ucfirst($consumption->consumption_type)); ?></td>
                                                <td><?php echo e($consumption->details->count()); ?></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a class="btn btn-sm btn-outline-info" data-url="<?php echo e(route('daily-consumption.edit', $consumption->id)); ?>" data-ajax-popup="true" data-size="xl" data-title="<?php echo e(__('Edit Consumption')); ?>">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                        <?php echo Form::open(['method' => 'DELETE', 'route' => ['daily-consumption.destroy', $consumption->id], 'id' => 'delete-consumption-' . $consumption->id]); ?>

                                                        <a href="#" class="btn btn-sm btn-outline-danger show_confirm" data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>" onclick="document.getElementById('delete-consumption-<?php echo e($consumption->id); ?>').submit()">
                                                            <i class="ti ti-trash"></i>
                                                        </a>
                                                        <?php echo Form::close(); ?>

                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted"><?php echo e(__('No consumption records for this completion.')); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                <!--                <div id="manpower-section" style="display:none;">
                                    
                                    <div class="row mt-4">
                                        <div class="col-xl-12">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h5 class="mb-0"><?php echo e(__('Man Power Details')); ?></h5>
                                                            
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <?php $allManpowers = $activity->completeds->flatMap->manpowers ?? collect(); ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allManpowers->count() > 0): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-hover">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th><?php echo e(__('Work Date')); ?></th>
                                                                    <th><?php echo e(__('Supplier')); ?></th>
                                                                    <th><?php echo e(__('Manpower Types')); ?></th>
                                                                    <th><?php echo e(__('Total Count')); ?></th>
                                                                    <th><?php echo e(__('Action')); ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allManpowers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $manpower): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <tr>
                                                                    <td><?php echo e(\Carbon\Carbon::parse($manpower->work_date)->format('d M Y')); ?></td>
                                                                    <td><?php echo e($manpower->supplier->name ?? '-'); ?></td>
                                                                    <td>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $manpower->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <span class="badge bg-primary me-1">
                                                                            <?php echo e($detail->type->name ?? 'N/A'); ?>: <?php echo e($detail->count); ?>

                                                                        </span>
                                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </td>
                                                                    <td><?php echo e($manpower->total_count); ?></td>
                                                                    <td>
                                                                        <div class="d-flex gap-2">
                                                                            <a href="#" data-url="<?php echo e(route('manpower.edit', $manpower->id)); ?>" data-ajax-popup="true" data-size="lg" data-title="<?php echo e(__('Edit Man Power')); ?>" class="btn btn-sm btn-outline-info">
                                                                                <i class="ti ti-pencil"></i>
                                                                            </a>
                                                                            <?php echo Form::open(['route' => ['manpower.destroy', $manpower->id], 'method' => 'DELETE', 'class' => 'd-inline', 'id' => 'delete-form-' . $manpower->id]); ?>

                                                                            <a href="#" class="btn btn-sm btn-outline-danger show_confirm" data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>" onclick="document.getElementById('delete-form-<?php echo e($manpower->id); ?>').submit()">
                                                                                <i class="ti ti-trash"></i>
                                                                            </a>
                                                                            <?php echo Form::close(); ?>

                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <?php else: ?>
                                                    <p class="text-muted"><?php echo e(__('No man power records found for this activity.')); ?></p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>-->

                <!--                <div id="machinery-section" style="display:none;"> 
                                    <div class="row mt-4">
                                        <div class="col-xl-12">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h5 class="mb-0"><?php echo e(__('Machinery DPR Details')); ?></h5>
                                                        <?php if (app('laratrust')->hasPermission('machinery-dpr create')) : ?>
                                                            <a href="<?php echo e(route('daily-progress-reports-new.createdpr', $activity->id)); ?>" class="btn btn-sm btn-primary">
                                                                <i class="ti ti-plus"></i> <?php echo e(__('Add DPR')); ?>

                                                            </a>
                                                        
                                                        
                                                        <?php endif; // app('laratrust')->permission ?>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dprList) && $dprList->count() > 0): ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php echo e(__('Date')); ?></th>
                                                                        <th><?php echo e(__('Machinery')); ?></th>
                                                                        <th><?php echo e(__('Machine Start')); ?></th>
                                                                        <th><?php echo e(__('Machine End')); ?></th>
                                                                       
                                                                        <th><?php echo e(__('Actions')); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dprList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dpr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <tr>
                                                                            <td><?php echo e(\Carbon\Carbon::parse($dpr->date)->format('d M Y')); ?></td>
                                                                           
                                                                            <td><?php echo e($dpr->machinery->name ?? 'N/A'); ?></td>
                                                                            <td><?php echo e($dpr->machine_start_reading ?? '-'); ?></td>
                                                                            <td><?php echo e($dpr->machine_end_reading ?? '-'); ?></td>
                                                                           
                                                                            <td>
                                                                             <?php if (app('laratrust')->hasPermission('machinery-dpr edit')) : ?>
                                                                                
                                                                                <div class="action-btn me-2">
                                                                                    <a class="mx-3 btn btn-sm align-items-center bg-info"
                                                                                       data-url="<?php echo e(route('daily-progress-reports.edit', $dpr->id)); ?>"
                                                                                       data-ajax-popup="true"
                                                                                       data-size="xl"
                                                                                       data-bs-toggle="tooltip"
                                                                                       title="<?php echo e(__('Edit Daily Report')); ?>"
                                                                                       data-title="<?php echo e(__('Edit Daily Progress Report')); ?>">
                                                                                        <i class="ti ti-pencil text-white"></i>
                                                                                    </a>
                                                                                </div>
                                                                                <?php endif; // app('laratrust')->permission ?>
                                                                                <?php if (app('laratrust')->hasPermission('machinery-dpr delete')) : ?>
                                                                                <div class="action-btn">
                                                                                    <?php echo Form::open([
                                                                                    'method' => 'DELETE',
                                                                                    'route' => ['daily-progress-reports.destroy', $dpr->id],
                                                                                    'id' => 'delete-form-' . $dpr->id,
                                                                                    ]); ?>

                                                                                    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
                                                                                       data-bs-toggle="tooltip"
                                                                                       title="<?php echo e(__('Delete Daily Report')); ?>">
                                                                                        <i class="ti ti-trash text-white"></i>
                                                                                    </a>
                                                                                    <?php echo Form::close(); ?>

                                                                                </div>
                                                                                <?php endif; // app('laratrust')->permission ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted"><?php echo e(__('No DPR linked to this activity.')); ?></p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div></div>-->

                <!--                <div id="material-section" style="display:none;"> 
                                    <div class="row mt-4">
                                        <div class="col-xl-12">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h5 class="mb-0"><?php echo e(__('Material Details')); ?></h5>
                                                        <?php if (app('laratrust')->hasPermission('consumption-log create')) : ?>
                                                            <a data-url="<?php echo e(route('daily-consumption.create', ['activity_id' => $activity->id])); ?>" data-size="xl" data-ajax-popup="true" data-bs-toggle="tooltip" title="<?php echo e(__('Create')); ?>" data-title="<?php echo e(__('Create Consumption Log')); ?>"  class="btn btn-sm btn-primary">
                                                                <i class="ti ti-plus"></i> <?php echo e(__('Add Consumption')); ?>

                                                            </a>
                                                        
                                                        
                                                <button type="button" data-size="xl"
                                                        data-url="<?php echo e(route('daily-consumption.create', ['activity_completed_id' => $completed->id ?? '', 'activity_id' => $activity->id ?? '' ])); ?>"
                                                        data-ajax-popup="true"
                                                        data-bs-toggle="tooltip"
                                                        data-toggle="tooltip"
                                                        title="<?php echo e(__('Create')); ?>"
                                                        data-title="<?php echo e(__('Create Consumption Log')); ?>" class="btn btn-sm btn-outline-primary" >
                                                    <i class="ti ti-plus"></i> <?php echo e(__('Add Consumption')); ?>

                                                </button>
                                                      
                                                        <?php endif; // app('laratrust')->permission ?>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($consumptionList) && $consumptionList->count() > 0): ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php echo e(__('Date')); ?></th>
                                                                        <th><?php echo e(__('Consumption Number')); ?></th>
                                                                        <th><?php echo e(__('Site')); ?></th>
                                                                        <th><?php echo e(__('Type')); ?></th>
                                                                        <th><?php echo e(__('Items')); ?></th>
                                                                        <th><?php echo e(__('Actions')); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $consumptionList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <tr>
                                                                            <td><?php echo e(\Carbon\Carbon::parse($consumption->consumption_date)->format('d M Y')); ?></td>                                                           
                                                                            <td><?php echo e($consumption->consumption_number); ?></td>
                                                                            <td><?php echo e($consumption->site->name ?? 'N/A'); ?></td>
                                                                            <td><?php echo e(ucfirst($consumption->consumption_type)); ?></td>
                                                                            <td><?php echo e($consumption->details->count()); ?></td>
                                                                            <td>
                                                                               
                                                                                <?php if (app('laratrust')->hasPermission('consumption-log edit')) : ?>
                                                                                <div class="action-btn me-2">
                                                                                    <a class="mx-3 btn btn-sm align-items-center bg-info"
                                                                                       data-url="<?php echo e(route('daily-consumption.edit', $consumption->id)); ?>"
                                                                                       data-ajax-popup="true" data-size="xl"
                                                                                       data-bs-toggle="tooltip" title="<?php echo e(__('Edit')); ?>"
                                                                                       data-title="<?php echo e(__('Edit Daily Consumption')); ?>">
                                                                                        <i class="ti ti-pencil text-white"></i>
                                                                                    </a>
                                                                                </div>
                                                                                <?php endif; // app('laratrust')->permission ?>
                                                                                
                                                                                <?php if (app('laratrust')->hasPermission('consumption-log delete')) : ?>
                                                                                <div class="action-btn">
                                                                                    <?php echo Form::open([
                                                                                        'method' => 'DELETE',
                                                                                        'route' => ['daily-consumption.destroy', $consumption->id],
                                                                                        'id' => 'delete-form-' . $consumption->id,
                                                                                    ]); ?>

                                                                                    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
                                                                                       data-bs-toggle="tooltip" title="<?php echo e(__('Delete')); ?>">
                                                                                        <i class="ti ti-trash text-white"></i>
                                                                                    </a>
                                                                                    <?php echo Form::close(); ?>

                                                                                </div>
                                                                                <?php endif; // app('laratrust')->permission ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted"><?php echo e(__('No consumption records linked to this activity.')); ?></p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                        
                                </div>-->



            </div>
        </div>






        
        <div class="modal fade" id="addManPowerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title"><?php echo e(__('Add Man Power to Activity')); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?php echo e(route('manpower.store')); ?>" method="POST" id="manpowerForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="site_id" value="<?php echo e($activity->site_id); ?>">

                        <div class="modal-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <?php echo e(Form::label('activity_completed_id', __('Completion Entry'), ['class' => 'form-label'])); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
                                    <?php echo e(Form::select('activity_completed_id', $activity->completeds->pluck('completed_quantity', 'id')->map(fn($qty, $id) => "Completion #$id ($qty {$activity->unit})"), null, ['class' => 'form-control select', 'required' => true, 'placeholder' => __('Select Completion')])); ?>

                                </div>
                                <div class="form-group col-md-6">
                                    <?php echo e(Form::label('work_date', __('Work Date'), ['class' => 'form-label'])); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
                                    <?php echo e(Form::date('work_date', \Carbon\Carbon::now(), ['class' => 'form-control', 'required' => true])); ?>

                                </div>

                                <div class="form-group col-md-6">
                                    <?php echo e(Form::label('supplier_id', __('Supplier'), ['class' => 'form-label'])); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
                                    <?php echo e(Form::select('supplier_id', $manpowerSuppliers ?? [], null, ['class' => 'form-control select', 'required' => true, 'placeholder' => __('Select Supplier')])); ?>

                                </div>
                            </div>

                            <hr>

                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo e(__('Manpower Counts')); ?></h5>

                                    <button type="button"
                                            id="add-manpower-add-row"
                                            class="btn btn-sm btn-outline-secondary">
                                        <i class="ti ti-plus"></i> <?php echo e(__('Add Row')); ?>

                                    </button>
                                </div>

                                <div class="card-body">

                                    <div id="add-manpower-rows-container">

                                        <div class="row manpower-row mb-3">

                                            <div class="col-md-5">
                                                <?php echo e(Form::label('manpower_type_id', __('Manpower Type'), ['class' => 'form-label'])); ?>

                                                <?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>

                                                <?php echo e(Form::select(
                        'details[0][manpower_type_id]',
                        $manpowerTypes ?? [],
                        null,
                        [
                            'class' => 'form-control select manpower-type-select',
                            'required' => true,
                            'placeholder' => __('Select Manpower Type')
                        ]
                    )); ?>

                                            </div>

                                            <div class="col-md-5">
                                                <?php echo e(Form::label('count', __('Count'), ['class' => 'form-label'])); ?>

                                                <?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>

                                                <?php echo e(Form::number(
                        'details[0][count]',
                        0,
                        [
                            'class' => 'form-control count-input',
                            'min' => 0,
                            'required' => true
                        ]
                    )); ?>

                                            </div>

                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button"
                                                        class="btn btn-danger w-100 remove-row">
                                                    Remove
                                                </button>
                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>



                            <div class="form-group col-md-12 mt-3">
                                <?php echo e(Form::label('total_count', __('Total Count'), ['class' => 'form-label'])); ?>

                                <?php echo e(Form::number('total_count', 0, ['class' => 'form-control', 'readonly' => true, 'id' => 'manpower_total_count'])); ?>

                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo e(__('Cancel')); ?></button>
                            <button type="submit" class="btn btn-primary"><?php echo e(__('Create Man Power')); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function () {
            // Container for Add Man Power modal
            const $container = $('#add-manpower-rows-container');
            let rowIndex = $container.find('.manpower-row').length;
            // Manpower types for dropdown
            const manpowerTypes = <?php echo json_encode($manpowerTypes ?? [], 15, 512) ?>;
            // Function to update total count
            function updateTotalCount() {
            let total = 0;
            $container.find('.count-input').each(function () {
            total += parseInt($(this).val()) || 0;
            });
            $('#manpower_total_count').val(total);
            }

            // Re-index row names
            function reindexRows() {
            rowIndex = 0;
            $container.find('.manpower-row').each(function () {
            $(this).find('.manpower-type-select').attr('name', `details[${rowIndex}][manpower_type_id]`);
            $(this).find('.count-input').attr('name', `details[${rowIndex}][count]`);
            rowIndex++;
            });
            }

            // Add Row Button
            $('#add-manpower-add-row').on('click', function () {
            rowIndex = $container.find('.manpower-row').length;
            // Build options for select
            let options = `<option value="">Select Manpower Type</option>`;
            for (const [id, name] of Object.entries(manpowerTypes)) {
            options += `<option value="${id}">${name}</option>`;
            }

            const newRow = `
                    <div class="row manpower-row mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Manpower Type <?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                            <select name="details[${rowIndex}][manpower_type_id]" class="form-control select manpower-type-select" required>
                                ${options}
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Count <?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                            <input type="number" name="details[${rowIndex}][count]" class="form-control count-input" value="0" min="0" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-row">Remove</button>
                        </div>
                    </div>
                `;
            $container.append(newRow);
            // Initialize select2 if used
            if ($().select2) {
            $('.manpower-type-select').select2({ allowClear: true });
            }

            updateTotalCount();
            });
            // Remove row
            $container.on('click', '.remove-row', function () {
            if ($container.find('.manpower-row').length > 1) {
            $(this).closest('.manpower-row').remove();
            reindexRows();
            updateTotalCount();
            } else {
            alert('At least one row is required.');
            }
            });
            // Update total count on input change
            $container.on('input', '.count-input', updateTotalCount);
            // Initialize total count on page load
            updateTotalCount();
            });
            // Handle modal shown - initialize scripts for dynamically loaded content
            $(document).on('shown.bs.modal', '.modal', function () {
            // Initialize Add Row functionality for dynamically loaded modals
            initManpowerEditModalScripts();
            });
            // Function to initialize modal scripts
            function initManpowerEditModalScripts() {
            // Only initialize if there's an Add Row button in the modal
            const addBtn = $('#edit-manpower-add-row');
            if (addBtn.length === 0) return;
            let rowIndex = 0;
            const $container = $('#edit-manpower-rows-container');
            // Get manpower types from the page
            let manpowerTypes = <?php echo json_encode($manpowerTypes ?? [], 15, 512) ?>;
            function updateTotalCount() {
            let total = 0;
            $container.find('.count-input').each(function () {
            const val = parseInt($(this).val()) || 0;
            total += val;
            });
            $('#manpower_edit_total_count').val(total);
            }

            function reindexRows() {
            rowIndex = 0;
            $container.find('.manpower-row').each(function() {
            const $row = $(this);
            $row.find('.manpower-type-select').attr('name', 'details[' + rowIndex + '][manpower_type_id]');
            $row.find('.count-input').attr('name', 'details[' + rowIndex + '][count]');
            rowIndex++;
            });
            }

            // Add new row handler - use event delegation
            $(document).off('click', '#edit-manpower-add-row').on('click', '#edit-manpower-add-row', function () {
            rowIndex = $container.find('.manpower-row').length;
            let options = '<option value=""><?php echo e(__("Select Manpower Type")); ?></option>';
            for (const [id, name] of Object.entries(manpowerTypes)) {
            options += `<option value="${id}">${name}</option>`;
            }

            const newRow = `
                    <div class="row g-2 align-items-center mb-2 manpower-row">
                        <div class="col-md-5">
                            <label class="form-label"><?php echo e(__('Manpower Type')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                            <select name="details[${rowIndex}][manpower_type_id]" class="form-control select manpower-type-select" required>
                                ${options}
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label"><?php echo e(__('Count')); ?><?php if (isset($component)) { $__componentOriginalbba606fec37ea04333bc269e3e165587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbba606fec37ea04333bc269e3e165587 = $attributes; } ?>
<?php $component = App\View\Components\Required::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Required::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $attributes = $__attributesOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__attributesOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbba606fec37ea04333bc269e3e165587)): ?>
<?php $component = $__componentOriginalbba606fec37ea04333bc269e3e165587; ?>
<?php unset($__componentOriginalbba606fec37ea04333bc269e3e165587); ?>
<?php endif; ?></label>
                            <input type="number" name="details[${rowIndex}][count]" class="form-control count-input" value="0" min="0" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger remove-row"><?php echo e(__('Remove')); ?></button>
                        </div>
                    </div>
                `;
            $container.append(newRow);
            if ($().select2) {
            $('.manpower-type-select').select2({ allowClear: true });
            }

            updateTotalCount();
            });
            // Remove row handler - use event delegation
            $(document).off('click', '#edit-manpower-rows-container .remove-row').on('click', '#edit-manpower-rows-container .remove-row', function () {
            if ($container.find('.manpower-row').length > 1) {
            $(this).closest('.manpower-row').remove();
            reindexRows();
            updateTotalCount();
            } else {
            alert('<?php echo e(__("At least one row is required.")); ?>');
            }
            });
            // Count input handler - use event delegation
            $(document).off('input', '#edit-manpower-rows-container .count-input').on('input', '#edit-manpower-rows-container .count-input', updateTotalCount);
            }

            // Handle modal close - refresh page to show updated data
            $(document).on('hidden.bs.modal', '.modal', function () {
            // Reload the page to refresh Man Power table
            location.reload();
            });
            // Handle delete form submission
            $(document).on('submit', 'form[id^="delete-form-"]', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this record?')) {
            return false;
            }

            var form = $(this);
            $.ajax({
            url: form.attr('action'),
                    method: 'DELETE',
                    data: form.serialize(),
                    success: function(response) {
                    location.reload();
                    },
                    error: function(xhr) {
                    alert('Error deleting record');
                    }
            });
            });
            // Handle modal form success - redirect to activity edit page
            $(document).on('ajax:success', '.modal form', function(e) {
            const response = e.detail[0];
            if (response.success || response.redirect) {
            $('.modal').modal('hide');
            window.location.href = response.redirect || '<?php echo e(route("activities.edit", $activity->id)); ?>';
            }
            });
            // Also handle regular form submission success
            $(document).ready(function() {
            $('#ManpowerForm').on('submit', function(e) {
            var formData = new FormData(this);
            $.ajax({
            url: $(this).attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                    $('.modal').modal('hide');
                    window.location.href = '<?php echo e(route("activities.edit", $activity->id)); ?>';
                    },
                    error: function(xhr) {
                    // Let the form handle errors normally
                    }
            });
            e.preventDefault();
            });
            });</script>
        <link rel="stylesheet" href="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/libs/bootstrap-daterangepicker/daterangepicker.css')); ?> ">
        <script src="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/libs/moment/min/moment.min.js')); ?>"></script>
        <script src="<?php echo e(asset('packages/workdo/Taskly/src/Resources/assets/libs/bootstrap-daterangepicker/daterangepicker.js')); ?>"></script>

        <script>
            $(function () {
            var start = moment("<?php echo e($activity->start_date); ?>", 'YYYY-MM-DD HH:mm:ss');
            var end = moment("<?php echo e($activity->due_date); ?>", 'YYYY-MM-DD HH:mm:ss');
            function cb(start, end) {
            $("#duration").val(start.format('MMM D, YY hh:mm A') + ' - ' + end.format('MMM D, YY hh:mm A'));
            $('input[name="start_date"]').val(start.format('YYYY-MM-DD HH:mm:ss'));
            $('input[name="due_date"]').val(end.format('YYYY-MM-DD HH:mm:ss'));
            // Real-time validation: due date must be after start date
            if (end.isBefore(start)) {
            $('#duration_validation').removeClass('d-none');
            } else {
            $('#duration_validation').addClass('d-none');
            }
            }

            $('#duration').daterangepicker({
            autoApply: true,
                    timePicker: true,
                    autoUpdateInput: false,
                    startDate: start,
                    endDate: end,
                    locale: {
                    format: 'MMMM D, YYYY hh:mm A',
                            applyLabel: "<?php echo e(__('Apply')); ?>",
                            cancelLabel: "<?php echo e(__('Cancel')); ?>",
                            fromLabel: "<?php echo e(__('From')); ?>",
                            toLabel: "<?php echo e(__('To')); ?>",
                            daysOfWeek: [
                                    "<?php echo e(__('Sun')); ?>", "<?php echo e(__('Mon')); ?>", "<?php echo e(__('Tue')); ?>", "<?php echo e(__('Wed')); ?>",
                                    "<?php echo e(__('Thu')); ?>", "<?php echo e(__('Fri')); ?>", "<?php echo e(__('Sat')); ?>"
                            ],
                            monthNames: [
                                    "<?php echo e(__('January')); ?>", "<?php echo e(__('February')); ?>", "<?php echo e(__('March')); ?>", "<?php echo e(__('April')); ?>",
                                    "<?php echo e(__('May')); ?>", "<?php echo e(__('June')); ?>", "<?php echo e(__('July')); ?>", "<?php echo e(__('August')); ?>",
                                    "<?php echo e(__('September')); ?>", "<?php echo e(__('October')); ?>", "<?php echo e(__('November')); ?>", "<?php echo e(__('December')); ?>"
                            ],
                    }
            }, cb);
            cb(start, end);
            });</script>
        <script>
            $(document).on('click', '#addCompletedRow', function () {

            const container = $('#completedQuantityContainer');
            let rows = container.find('input[name^="activities_completed"]');
            let rowCount = rows.length > 0 ? Math.floor(rows.length / 2) + 1 : 1;
            let lastQtyInput = container.find('input[name$="[completed_quantity]"]').last();
            let lastVal = parseInt(lastQtyInput.val()) || 0;
            // Rule 1: Only allow add if last value > 0
            if (lastVal <= 0) {
            alert("You must enter a value greater than 0 in the previous row before adding a new one.");
            return;
            }

            // Rule 2: Prevent sum exceeding main quantity
            let totalCompleted = 0;
            container.find('input[name$="[completed_quantity]"]').each(function () {
            totalCompleted += parseInt($(this).val()) || 0;
            });
            let maxQuantity = parseInt($('input[name="quantity"]').val()) || 0;
            if (maxQuantity > 0 && totalCompleted >= maxQuantity) {
            alert("Total completed quantity cannot exceed the main Quantity.");
            return;
            }

            let today = new Date().toISOString().split('T')[0];
            let newRow = `
                <div class="row g-2 align-items-center mb-2 completed-row">
                    <div class="col-md-1">
                        <span class="badge bg-primary">NEW</span>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="activities_completed[new_${rowCount}][completed_quantity]" class="form-control" placeholder="<?php echo e(__('Completed Quantity')); ?>" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="activities_completed[new_${rowCount}][completed_date]" class="form-control" value="${today}" required>
                    </div>
                    <div class="col-md-2">
                        <input type="file" name="activities_completed[new_${rowCount}][completed_reference_file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(newRow);
            });
            // Remove row (event delegation) - use container-specific selector
            $(document).on('click', '#completedQuantityContainer .remove-row', function () {
            const container = $('#completedQuantityContainer');
            if (container.find('.completed-row').length > 1) {
            $(this).closest('.completed-row').remove();
            } else {
            alert("At least one row must remain.");
            }
            });
        </script>


        <script>
            $(document).ready(function () {

            // Click handler for sidebar links
            $('.list-group-item-action').on('click', function (e) {
            e.preventDefault();
            $('.list-group-item-action').removeClass('active');
            $(this).addClass('active');
            // Hide all sections
            $('#activity-section, #manpower-section, #machinery-section, #material-section, [id^="completion-"]').hide();
            // Show target section
            const target = $(this).data('tab');
            $('#' + target).fadeIn(200);
            });
            // --- Auto-activate last completion after update ---
            <?php if(session('highlight_last_completion')): ?>
                    // Trigger click on the last completion link
                    $('#useradd-sidenav a[data-tab^="completion-"]:last').trigger('click');
            <?php else: ?>
                    // Show activity section by default
                    $('#activity-section').show();
            <?php endif; ?>

                    });
            </script>

        <script>
            $(document).on('click', '.locked-row', function () {

            let manpower = $(this).data('manpower');
            let dpr = $(this).data('dpr');
            let consumption = $(this).data('consumption');
            let reasons = [];
            if (manpower > 0) {
            reasons.push("• Manpower records exist");
            }
            if (dpr > 0) {
            reasons.push("• Daily Progress Reports exist");
            }
            if (consumption > 0) {
            reasons.push("• Daily Consumptions exist");
            }

            Swal.fire({
            icon: 'error',
                    title: 'Cannot Delete',
                    html: `
                    <div style="text-align:left">
                        This completed entry cannot be deleted because:
                        <br><br>
                        ${reasons.join('<br>')}
                    </div>
                `,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
            });
            });
        </script>

        
        <script>
            $(document).ready(function() {
                // Function to validate completed_quantity against main quantity
                function validateCompletedQuantity(input) {
                    var completedQty = parseInt($(input).val()) || 0;
                    var mainQuantity = parseInt($('input[name="quantity"]').val()) || 0;
                    var $row = $(input).closest('.completed-row');
                    var $errorSpan = $row.find('.completed-qty-error');
                    
                    // Remove existing error message
                    $errorSpan.remove();
                    
                    // Validate: completed_quantity should not exceed main quantity
                    if (mainQuantity > 0 && completedQty > mainQuantity) {
                        // Show error message
                        var errorMsg = '<span class="completed-qty-error text-danger" style="font-size: 0.875em;">Completed quantity cannot exceed ' + mainQuantity + '</span>';
                        $(input).after(errorMsg);
                        $(input).addClass('is-invalid');
                        return false;
                    } else {
                        $(input).removeClass('is-invalid');
                        return true;
                    }
                }
                
                // Function to validate total completed quantity against main quantity
                function validateTotalCompletedQuantity() {
                    var mainQuantity = parseInt($('input[name="quantity"]').val()) || 0;
                    var totalCompleted = 0;
                    
                    // Sum all completed_quantity values
                    $('input[name$="[completed_quantity]"]').each(function() {
                        totalCompleted += parseInt($(this).val()) || 0;
                    });
                    
                    // Remove existing total error message
                    $('.total-completed-qty-error').remove();
                    
                    // Validate: total completed_quantity should not exceed main quantity
                    if (mainQuantity > 0 && totalCompleted > mainQuantity) {
                        // Show error message after the last completed_quantity field
                        var errorMsg = '<span class="total-completed-qty-error text-danger" style="font-size: 0.875em; display: block; margin-top: 5px;">Total completed quantity (' + totalCompleted + ') cannot exceed main quantity (' + mainQuantity + ')</span>';
                        $('#completedQuantityContainer').after(errorMsg);
                        return false;
                    }
                    return true;
                }
                
                // Validate on input change for existing rows
                $(document).on('input', 'input[name$="[completed_quantity]"]', function() {
                    validateCompletedQuantity(this);
                    validateTotalCompletedQuantity();
                });
                
                // Validate on main quantity change
                $(document).on('input', 'input[name="quantity"]', function() {
                    // Re-validate all completed_quantity fields
                    $('input[name$="[completed_quantity]"]').each(function() {
                        validateCompletedQuantity(this);
                    });
                    validateTotalCompletedQuantity();
                });
                
                // Validate on form submit
                $('#activityForm').on('submit', function(e) {
                    var isValid = true;
                    var mainQuantity = parseInt($('input[name="quantity"]').val()) || 0;
                    var totalCompleted = 0;
                    
                    // Validate each completed_quantity field and calculate total
                    $('input[name$="[completed_quantity]"]').each(function() {
                        var completedQty = parseInt($(this).val()) || 0;
                        totalCompleted += completedQty;
                        if (mainQuantity > 0 && completedQty > mainQuantity) {
                            isValid = false;
                            validateCompletedQuantity(this);
                        }
                    });
                    
                    // Validate total completed quantity
                    if (mainQuantity > 0 && totalCompleted > mainQuantity) {
                        isValid = false;
                        validateTotalCompletedQuantity();
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Total completed quantity (' + totalCompleted + ') cannot exceed the main quantity (' + mainQuantity + ').',
                            confirmButtonColor: '#d33'
                        });
                        return false;
                    }
                });
            });
        </script
        <?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/activities/edit.blade.php ENDPATH**/ ?>