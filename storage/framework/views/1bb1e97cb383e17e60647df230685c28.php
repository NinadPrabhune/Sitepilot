<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="40%">Employee Name</th>
                <td><?php echo e($leave->EmployeeName->name ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Leave Type</th>
                <td><?php echo e($leave->leaveType->title ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Applied On</th>
                <td><?php echo e(date('d M Y', strtotime($leave->applied_on))); ?></td>
            </tr>
            <tr>
                <th>Start Date</th>
                <td><?php echo e(date('d M Y', strtotime($leave->start_date))); ?></td>
            </tr>
            <tr>
                <th>End Date</th>
                <td><?php echo e(date('d M Y', strtotime($leave->end_date))); ?></td>
            </tr>
            <tr>
                <th>Total Leave Days</th>
                <td><?php echo e($leave->total_leave_days); ?></td>
            </tr>
            <tr>
                <th>Approved Days</th>
                <td><?php echo e($leave->approved_days ?? '0'); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($leave->status == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                    <?php elseif($leave->status == 'Partially Approved'): ?>
                        <span class="badge bg-info">Partially Approved</span>
                    <?php elseif($leave->status == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                    <?php elseif($leave->status == 'Reject'): ?>
                        <span class="badge bg-danger">Rejected</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?php echo e($leave->status); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Leave Reason</th>
                <td><?php echo e($leave->leave_reason ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Remark</th>
                <td><?php echo e($leave->remark ?? 'N/A'); ?></td>
            </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($leave->status_reason): ?>
            <tr>
                <th>Status Reason</th>
                <td><?php echo e($leave->status_reason); ?></td>
            </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Hrm\src\Providers/../Resources/views/report/leave-details-modal.blade.php ENDPATH**/ ?>