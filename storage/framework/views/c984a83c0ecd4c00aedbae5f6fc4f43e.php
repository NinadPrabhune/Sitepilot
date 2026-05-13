<!-- Include Image Preview Modal Component -->
<?php echo $__env->make('components.image-preview-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="40%">Employee Name</th>
                <td><?php echo e($attendance->employees->name ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><?php echo e(date('d M Y', strtotime($attendance->date))); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attendance->status == 'Present'): ?>
                        <span class="badge bg-success">Present</span>
                    <?php elseif($attendance->status == 'Leave'): ?>
                        <span class="badge bg-warning">Leave</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><?php echo e($attendance->status); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Clock In</th>
                <td><?php echo e($attendance->clock_in ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Clock Out</th>
                <td><?php echo e($attendance->clock_out ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Late</th>
                <td><?php echo e($attendance->late ?? '0'); ?></td>
            </tr>
            <tr>
                <th>Early Leaving</th>
                <td><?php echo e($attendance->early_leaving ?? '0'); ?></td>
            </tr>
            <tr>
                <th>Overtime</th>
                <td><?php echo e($attendance->overtime ?? '0'); ?></td>
            </tr>
            <tr>
                <th>Total Rest</th>
                <td><?php echo e($attendance->total_rest ?? '0'); ?></td>
            </tr>
            <tr>
                <th>Clock In Latitude</th>
                <td><?php echo e($attendance->clock_in_latitude ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Clock In Longitude</th>
                <td><?php echo e($attendance->clock_in_longitude ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Clock Out Latitude</th>
                <td><?php echo e($attendance->clock_out_latitude ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Clock Out Longitude</th>
                <td><?php echo e($attendance->clock_out_longitude ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Clock In Image</th>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attendance->clock_in_image): ?>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?php echo e(asset($attendance->clock_in_image)); ?>" alt="Clock In" style="max-width: 150px; cursor: pointer;" onclick="openImagePreview({images: ['<?php echo e(asset($attendance->clock_in_image)); ?>']})">
                            <?php echo $__env->make('components.image-preview-button', ['src' => asset($attendance->clock_in_image), 'text' => 'Preview', 'class' => 'btn btn-sm btn-info'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">No image</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Clock Out Image</th>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attendance->clock_out_image): ?>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?php echo e(asset($attendance->clock_out_image)); ?>" alt="Clock Out" style="max-width: 150px; cursor: pointer;" onclick="openImagePreview({images: ['<?php echo e(asset($attendance->clock_out_image)); ?>']})">
                            <?php echo $__env->make('components.image-preview-button', ['src' => asset($attendance->clock_out_image), 'text' => 'Preview', 'class' => 'btn btn-sm btn-info'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">No image</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Workspace</th>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_object($attendance->workspaceRelation) && $attendance->workspaceRelation->name): ?>
                        <?php echo e($attendance->workspaceRelation->name); ?>

                    <?php elseif(is_numeric($attendance->workspaceRelation)): ?>
                        <?php echo e($attendance->workspaceRelation); ?> (ID)
                    <?php else: ?>
                        <?php echo e($attendance->workspace_id ?? 'N/A'); ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Site</th>
                <td><?php echo e($attendance->site->name ?? $attendance->site_id ?? 'N/A'); ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Hrm\src\Providers/../Resources/views/report/attendance-details-modal.blade.php ENDPATH**/ ?>