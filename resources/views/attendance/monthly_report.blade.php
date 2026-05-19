@extends('layouts.main')

@section('page-title')
    Monthly Attendance Report
@endsection

@section('page-breadcrumb')
    Attendance / Monthly Attendance Report
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
                                <option value="all" {{ ($employeeId ?? '') === 'all' ? 'selected' : '' }}>All Employees</option>
                                @if(isset($employees))
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ ($employeeId ?? '') == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
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
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Attendance Details</strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                            <i class="ti ti-download"></i> Export to Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="window.print()">
                            <i class="ti ti-printer"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
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
                                            <th class="text-center">{{ date('d', strtotime($date)) }}</th>
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
                                                            $attendance = $employeeData['attendance'][$date] ?? null;
                                                            $status = $attendance ? ($attendance == 'P' ? 'P' : 'A') : 'A';
                                                            $badgeClass = $status == 'P' ? 'bg-success' : 'bg-danger';
                                                        @endphp
                                                        <span class="badge {{ $badgeClass }}">{{ $status }}</span>
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

<script>
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
