<?php $__env->startSection('page-title'); ?>
<?php echo e(__('Notifications')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-breadcrumb'); ?>
<?php echo e(__('Notifications')); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-action'); ?>
<div class="d-flex">
    <form action="<?php echo e(route('notifications.markAllAsRead')); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm btn-secondary">
            <i class="ti ti-check"></i> <?php echo e(__('Mark All as Read')); ?>

        </button>
    </form>
</div>
<?php $__env->stopSection(); ?>
<?php use Illuminate\Support\Str; ?>
<?php $__env->startSection('content'); ?>
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo e(__('Sr. No.')); ?></th>
                                <th><?php echo e(__('Title')); ?></th>
                                <th><?php echo e(__('Message')); ?></th>
                                <th><?php echo e(__('Type')); ?></th>
                                <th><?php echo e(__('Created At')); ?></th>
                                <th><?php echo e(__('Status')); ?></th>
                                <th><?php echo e(__('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php








                            $isRead = !is_null($nu->read_at);

                            ?>
                            <tr class="<?php echo e($isRead ? '' : 'table-primary'); ?>">
                                
                                <td><?php echo e($loop->iteration + ($notifications->currentPage() - 1) * $notifications->perPage()); ?></td>

                                <td><a href="<?php echo e($nu->notification->full_action_url ?? '#'); ?>" onclick="event.stopPropagation();"><?php echo e($nu->notification->title); ?></a></td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                        <?php echo Str::limit(strip_tags($nu->notification->message), 50); ?>

                                    </span>

                                    <button type="button"
                                            class="btn btn-sm btn-link"
                                            data-bs-toggle="modal"
                                            data-bs-target="#notifModal<?php echo e($nu->id); ?>">
                                        <?php echo e(__('View Details')); ?>

                                    </button>

                                    
                                    <div class="modal fade" id="notifModal<?php echo e($nu->id); ?>" tabindex="-1" aria-labelledby="notifModalLabel<?php echo e($nu->id); ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="notifModalLabel<?php echo e($nu->id); ?>">
                                                        <?php echo e($nu->notification->title); ?>

                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo e(__('Close')); ?>"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php echo $nu->notification->message; ?>

                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        <?php echo e(__('Close')); ?>

                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                               <td>
                                   

                                    <span class="badge bg-primary">
                                        <?php echo e(Str::of($nu->notification->type ?? '')->replace('_', ' ')->title()); ?>

                                    </span>
                                </td>
                                <td><?php echo e($nu->notification->created_at->format('d M Y, h:i A')); ?></td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isRead): ?>
                                    <span class="badge bg-success"><?php echo e(__('Read')); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-warning"><?php echo e(__('Unread')); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isRead)): ?>
                                    <form action="<?php echo e(route('notifications.markAsRead', $nu->id)); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <?php echo e(__('Mark as Read')); ?>

                                        </button>
                                    </form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="text-center"><?php echo e(__('No notifications found.')); ?></td>
                            </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <?php echo e($notifications->links('pagination::bootstrap-5')); ?>

                </div>

            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\resources\views/notifications/index.blade.php ENDPATH**/ ?>