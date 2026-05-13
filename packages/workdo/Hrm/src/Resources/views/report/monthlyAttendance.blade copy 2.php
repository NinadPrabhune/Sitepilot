@extends('layouts.main')

@section('page-title')
    Monthly Attendance Report
@endsection
<!-- 
{{-- DEBUG: Dump all arrays --}}
@if(isset($attendanceData))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - attendanceData:</h5>
        <pre>{{ print_r($attendanceData, true) }}</pre>
    </div>
@endif

@if(isset($employees))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - employees:</h5>
        <pre>{{ print_r($employees, true) }}</pre>
    </div>
@endif

@if(isset($branch))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - branch:</h5>
        <pre>{{ print_r($branch, true) }}</pre>
    </div>
@endif

@if(isset($department))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - department:</h5>
        <pre>{{ print_r($department, true) }}</pre>
    </div>
@endif

@if(isset($dates))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - dates:</h5>
        <pre>{{ print_r($dates, true) }}</pre>
    </div>
@endif

@if(isset($data))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - data:</h5>
        <pre>{{ print_r($data, true) }}</pre>
    </div>
@endif

@if(isset($workingDays))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - workingDays:</h5>
        <pre>{{ print_r($workingDays, true) }}</pre>
    </div>
@endif

@if(isset($averageAttendance))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - averageAttendance:</h5>
        <pre>{{ print_r($averageAttendance, true) }}</pre>
    </div>
@endif

@if(isset($month))
    <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">
        <h5>DEBUG - month:</h5>
        <pre>{{ print_r($month, true) }}</pre>
    </div>
@endif -->

{{-- END DEBUG --}}

@section('page-breadcrumb')
    HRM / Monthly Attendance Report
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Filters</strong>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.monthly-report-new') }}">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="month">Month</label>
                            <input type="month" class="form-control" id="month" name="month" value="{{ $month ?? date('Y-m') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="employee_id">Employee</label>
                            <select class="form-select" id="employee_id" name="employee_id">
                                <option value="">All Employees</option>
                                @if(isset($employees))
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee['employee_id'] }}" {{ ($employeeId ?? '') == $employee['employee_id'] ? 'selected' : '' }}>{{ $employee['name'] }}</option>
                                    @endforeach
                                @endif
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

        @if(isset($attendanceData) && !empty($attendanceData))
            <!-- Attendance Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total Employees</h6>
                            <h3 class="display-6">{{ count($attendanceData) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Working Days</h6>
                            <h3 class="display-6">{{ $workingDays ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Average Attendance</h6>
                            <h3 class="display-6 text-success">{{ $averageAttendance ?? 0 }}%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Report Period</h6>
                            <h5 class="display-6">{{ date('F Y', strtotime($month ?? date('Y-m'))) }}</h5>
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
                        <table class="table table-striped table-bordered" id="attendanceTable">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" class="align-middle">Employee Name</th>
                                    @if(isset($dates))
                                        <th colspan="{{ count($dates) }}" class="text-center">{{ date('F Y', strtotime($month ?? date('Y-m'))) }}</th>
                                    @endif
                                    <th rowspan="2" class="align-middle text-center">Summary</th>
                                </tr>
                                @if(isset($dates))
                                    <tr>
                                        @foreach($dates as $date)
                                            <th class="text-center">{{ $date }}</th>
                                        @endforeach
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @if(isset($attendanceData))
                                    @foreach($attendanceData as $employeeId => $employeeData)
                                        <tr>
                                            <td><strong>{{ $employeeData['name'] }}</strong></td>
                                            @if(isset($dates))
                                                @foreach($dates as $date)
                                                    <td class="text-center">
                                                        @php
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
                                                        @endphp
                                                        @if($attendance)
                                                            <span class="badge {{ $badgeClass }} cursor-pointer"
                                                                  data-employee-id="{{ $employeeId }}"
                                                                  data-date="{{ $month }}-{{ $date }}"
                                                                  data-status="{{ $attendance }}"
                                                                  onclick="showAttendanceDetails(this)">
                                                                {{ $attendance }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @endif
                                            <td class="text-center">
                                                <strong>
                                                    @php
                                                        $presentDays = $employeeData['present_days'] ?? 0;
                                                        $totalDays = $workingDays ?? count($dates ?? []);
                                                    @endphp
                                                    <span class="badge bg-info">{{ $presentDays }}/{{ $totalDays }}</span>
                                                </strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <th><strong>Daily Summary</strong></th>
                                    @if(isset($dates))
                                        @foreach($dates as $date)
                                            @php
                                                $presentCount = 0;
                                                foreach($attendanceData as $employeeData) {
                                                    if(($employeeData['attendance'][$date] ?? 'A') == 'P') {
                                                        $presentCount++;
                                                    }
                                                }
                                            @endphp
                                            <td class="text-center">
                                                <small class="text-muted">{{ $presentCount }}/{{ count($attendanceData) }}</small>
                                            </td>
                                        @endforeach
                                    @endif
                                    <td class="text-center">
                                        <strong>Present/Total</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center">
                    <i class="ti ti-calendar-event" style="font-size: 4rem; color: #ccc;"></i>
                    <h5 class="mt-3">No Attendance Data Available</h5>
                    <p class="text-muted">Please select month and employee to generate the attendance report.</p>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    .table th {
        min-width: 40px;
        font-size: 0.85rem;
        padding: 0.5rem;
        text-align: center;
    }
    .table td {
        font-size: 0.85rem;
        padding: 0.4rem;
        text-align: center;
    }
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.4rem;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        .table th, .table td {
            font-size: 0.7rem;
            padding: 0.2rem;
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
        fetch(`{{ route('attendance.details') }}?employee_id=${employeeId}&date=${date}`)
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
        fetch(`{{ route('leave.details') }}?employee_id=${employeeId}&date=${date}`)
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
            <h2 style="text-align: center; margin-bottom: 20px;">Monthly Attendance Report - {{ date('F Y', strtotime($month ?? date('Y-m'))) }}</h2>
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

function exportToExcel() {
    let table = document.getElementById('attendanceTable');
    let rows = table.getElementsByTagName('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Remove HTML tags and get text content
            let text = cols[j].innerText || cols[j].textContent;
            text = text.replace(/"/g, '""'); // Escape quotes
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'attendance_report_{{ $month ?? date('Y-m') }}.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
@endsection
