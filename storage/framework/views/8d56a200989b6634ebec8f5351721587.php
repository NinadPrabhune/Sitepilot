<?php $__env->startSection('page-title'); ?>
    Monthly Attendance Report
<?php $__env->stopSection(); ?>

<?php $__env->startPush('css'); ?>
<?php echo $__env->make('components.image-preview-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

 



<?php $__env->startSection('page-breadcrumb'); ?>
    HRM / Monthly Attendance Report
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <div class="col-xl-12">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Filters</strong>
            </div>
            <div class="card-body">
                <form method="GET" action="<?php echo e(route('attendance.monthly-report-new')); ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="month">Month</label>
                            <input type="month" class="form-control" id="month" name="month" value="<?php echo e($month ?? date('Y-m')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="employee_id">Employee</label>
                            <select class="form-select" id="employee_id" name="employee_id">
                                <option value="all"
                                    <?php echo e(request('employee_id', 'all') == 'all' ? 'selected' : ''); ?>>
                                    All Employees
                                </option>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($employee['employee_id']); ?>"
                                        <?php echo e((string) request('employee_id') === (string) $employee['employee_id'] ? 'selected' : ''); ?>>
                                        <?php echo e($employee['name']); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($attendanceData) && !empty($attendanceData)): ?>
            <!-- Attendance Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 h-100 " style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total Employees</h6>
                            <h3 class="display-6"><?php echo e(count($attendanceData)); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 h-100 " style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Working Days</h6>
                            <h3 class="display-6"><?php echo e($workingDays ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 h-100 " style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Average Attendance</h6>
                            <h3 class="display-6 text-success"><?php echo e($averageAttendance ?? 0); ?>%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 h-100 " style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Report Period</h6>
                            <h5 class="display-6"><?php echo e(date('F Y', strtotime($month ?? date('Y-m')))); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="card" id="attendanceDetailsCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Attendance Details</strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                            <i class="ti ti-download"></i> Export to Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="printAttendanceDetails()">
                            <i class="ti ti-printer"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Legend -->
                    <div class="mb-3">
                        <strong>Status Legend:</strong>
                        <span class="badge bg-success ms-2">P - Present</span>
                        <span class="badge bg-warning ms-1">L - Leave</span>
                        <span class="badge bg-danger ms-1">A - Absent</span>
                        <span class="text-muted ms-2">(Blank = Future Date)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="attendanceTable">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" class="align-middle">Employee Name</th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dates)): ?>
                                        <th colspan="<?php echo e(count($dates)); ?>" class="text-center"><?php echo e(date('F Y', strtotime($month ?? date('Y-m')))); ?></th>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <th rowspan="2" class="align-middle text-center">Summary</th>
                                </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dates)): ?>
                                    <tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <th class="text-center"><?php echo e($date); ?></th>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($attendanceData)): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $attendanceData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employeeId => $employeeData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr>
                                            <td><strong style="color: #4C259E;font-weight: 800;"><?php echo e($employeeData['name']); ?></strong></td>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dates)): ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <td class="text-center">
                                                        <?php
                                                            $attendance = $employeeData['attendance'][$date] ?? '';
                                                            if ($attendance == 'P') {
                                                                $badgeClass = 'bg-success';
                                                            } elseif ($attendance == 'L') {
                                                                $badgeClass = 'bg-warning';
                                                            } elseif ($attendance == 'A') {
                                                                $badgeClass = 'bg-danger';
                                                            } else {
                                                                $badgeClass = '';
                                                                $attendance = '';
                                                            }
                                                        ?>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attendance): ?>
                                                            <span class="badge <?php echo e($badgeClass); ?> cursor-pointer"
                                                                  data-employee-id="<?php echo e($employeeId); ?>"
                                                                  data-date="<?php echo e($month); ?>-<?php echo e($date); ?>"
                                                                  data-status="<?php echo e($attendance); ?>"
                                                                  onclick="showAttendanceDetails(this)">
                                                                <?php echo e($attendance); ?>

                                                            </span>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </td>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <td class="text-center">
                                                <strong>
                                                    <?php
                                                        $presentDays = $employeeData['present_days'] ?? 0;
                                                        $totalDays = $workingDays ?? count($dates ?? []);
                                                    ?>
                                                    <span class="badge bg-primary "><?php echo e($presentDays); ?>/<?php echo e($totalDays); ?></span>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <th><strong>Daily Summary</strong></th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($dates)): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $presentCount = 0;
                                                $totalEmployees = count($attendanceData);
                                                foreach($attendanceData as $employeeData) {
                                                    $status = $employeeData['attendance'][$date] ?? '';
                                                    if($status == 'P') {
                                                        $presentCount++;
                                                    }
                                                }
                                            ?>
                                            <td class="text-center">
                                                <small class="text-white"><?php echo e($presentCount); ?>/<?php echo e($totalEmployees); ?></small>
                                            </td>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <td class="text-center table-secondary text-white">
                                        <strong >Present/Total</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-calendar-event" style="font-size: 4rem; color: #ccc;"></i>
                    <h5 class="mt-3">No Attendance Data Available</h5>
                    <p class="text-muted">Please select month and employee to generate the attendance report.</p>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>

<style>
    /* Fix border consistency for attendance table */
    #attendanceTable {
        border-collapse: collapse;
        border: 1px solid #dee2e6;
    }
    
    #attendanceTable th,
    #attendanceTable td {
        border: 1px solid #dee2e6;
        min-width: 40px;
        font-size: 0.85rem;
        padding: 0.5rem;
        text-align: center;
        vertical-align: middle;
    }
    
    #attendanceTable th {
        background-color: #6c757d;
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    #attendanceTable th.table-dark {
        border-color: #5a6268 !important;
    }
    
    #attendanceTable td {
        background-color: white;
    }
    
    #attendanceTable tbody tr:hover td {
        background-color: #f8f9fa;
    }
    
    #attendanceTable tfoot th,
    #attendanceTable tfoot td {
        background-color: #6c757d;
        color: white;
        font-weight: 600;
        border-color: #5a6268;
    }
    
    #attendanceTable .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.4rem;
        min-width: 28px;
        display: inline-block;
    }
    
    /* Fix table responsive wrapper border issues */
    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        overflow-x: auto;
        overflow-y: hidden;
        max-height: none;
    }
    
    /* Add horizontal scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 12px;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 6px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 6px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* Employee name column styling */
    #attendanceTable td:first-child {
        text-align: left;
        font-weight: 500;
        min-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Summary column styling */
    #attendanceTable td:last-child {
        font-weight: 600;
        /* background-color: #f8f9fa !important; */
        border-left: 2px solid #dee2e6;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        #attendanceTable th, 
        #attendanceTable td {
            font-size: 0.7rem;
            padding: 0.2rem;
            border: 1px solid #000 !important;
        }
        #attendanceTable th {
            background-color: #f0f0f0 !important;
            color: #000 !important;
        }
        .table-responsive {
            border: 1px solid #000;
            overflow: visible;
        }
    }
    
    /* Fix for small screens */
    @media (max-width: 768px) {
        #attendanceTable th,
        #attendanceTable td {
            min-width: 35px;
            padding: 0.3rem;
            font-size: 0.75rem;
        }
        #attendanceTable td:first-child {
            min-width: 120px;
        }
    }
</style>

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="attendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceModalLabel">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attendanceModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveModalLabel">Leave Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="leaveModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showAttendanceDetails(element) {
    const employeeId = element.getAttribute('data-employee-id');
    const date = element.getAttribute('data-date');
    const status = element.getAttribute('data-status');

    if (status === 'P') {
        // Show attendance details modal
        const attendanceModal = new bootstrap.Modal(document.getElementById('attendanceModal'));
        document.getElementById('attendanceModalLabel').textContent = 'Attendance Details - ' + date;
        document.getElementById('attendanceModalBody').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        attendanceModal.show();

        // Fetch attendance details via AJAX
        fetch(`<?php echo e(route('attendance.details')); ?>?employee_id=${employeeId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('attendanceModalBody').innerHTML = data.html;
                } else {
                    document.getElementById('attendanceModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            ${data.message || 'Error loading attendance details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('attendanceModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        Error loading attendance details: ${error.message}
                    </div>
                `;
            });
    } else if (status === 'L') {
        // Show leave details modal
        const leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
        document.getElementById('leaveModalLabel').textContent = 'Leave Details - ' + date;
        document.getElementById('leaveModalBody').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        leaveModal.show();

        // Fetch leave details via AJAX
        fetch(`<?php echo e(route('leave.details')); ?>?employee_id=${employeeId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('leaveModalBody').innerHTML = data.html;
                } else {
                    document.getElementById('leaveModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            ${data.message || 'Error loading leave details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('leaveModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        Error loading leave details: ${error.message}
                    </div>
                `;
            });
    }
}

function printAttendanceDetails() {
    let printContent = document.getElementById('attendanceDetailsCard').innerHTML;
    let originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding: 20px;">
            <h2 style="text-align: center; margin-bottom: 20px;">Monthly Attendance Report - <?php echo e(date('F Y', strtotime($month ?? date('Y-m')))); ?></h2>
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

function exportToExcel() {
    console.log('Export function - Excel with auto-fit columns');
    
    // Load SheetJS library if not already loaded
    if (typeof XLSX === 'undefined') {
        // Load SheetJS from CDN
        let script = document.createElement('script');
        script.src = 'https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js';
        script.onload = function() {
            performExcelExport();
        };
        document.head.appendChild(script);
    } else {
        performExcelExport();
    }
}

function performExcelExport() {
    let table = document.getElementById('attendanceTable');
    let rows = table.getElementsByTagName('tr');
    let data = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [];
        let cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cell = cols[j];
            let text = '';
            let forceText = false;
            
            // Check if this is a Daily Summary footer row
            if (cell.closest('tfoot')) {
                let smallWhite = cell.querySelector('small.text-white');
                if (smallWhite) {
                    text = smallWhite.textContent || smallWhite.innerText || '';
                    forceText = true;
                } else {
                    text = cell.textContent || cell.innerText || '';
                }
            } else {
                // Force badge extraction for summary column
                let badge = cell.querySelector('.badge.bg-primary');
                if (badge) {
                    text = badge.textContent || badge.innerText || '';
                    forceText = true;
                } else if (cell.querySelector('.badge')) {
                    text = cell.querySelector('.badge').textContent || cell.querySelector('.badge').innerText || '';
                } else {
                    text = cell.textContent || cell.innerText || '';
                }
            }
            
            // Clean up the text
            text = text.toString();
            text = text.replace(/[A-Za-z]{3}-\d+/g, '');
            text = text.replace(/\d{4}-\d{2}/g, '');
            text = text.trim();
            text = text.replace(/\s+/g, ' ');
            text = text.replace(/[\r\n\t]/g, '');
            
            if (text === '' || text === null || text === 'null') {
                text = '';
            }
            
            row.push(text);
        }
        data.push(row);
    }
    
    // Create workbook
    let ws = XLSX.utils.aoa_to_sheet(data);
    
    // Set column widths to auto-fit
    let colWidths = [];
    let maxCols = data.reduce((max, row) => Math.max(max, row.length), 0);
    
    for (let col = 0; col < maxCols; col++) {
        let maxWidth = 10; // minimum width
        for (let row = 0; row < data.length; row++) {
            if (data[row][col]) {
                let cellLength = data[row][col].toString().length;
                maxWidth = Math.max(maxWidth, cellLength);
            }
        }
        colWidths.push({ width: Math.min(maxWidth + 2, 50) }); // Add padding and cap at 50
    }
    
    ws['!cols'] = colWidths;
    
    // Create workbook and add worksheet
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Attendance Report");
    
    // Export to Excel file
    let fileName = 'attendance_report_<?php echo e($month ?? date('Y-m')); ?>.xlsx';
    XLSX.writeFile(wb, fileName);
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Hrm\src\Providers/../Resources/views/report/monthlyAttendance.blade.php ENDPATH**/ ?>