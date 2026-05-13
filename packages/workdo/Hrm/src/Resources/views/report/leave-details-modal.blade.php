<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="40%">Employee Name</th>
                <td>{{ $leave->EmployeeName->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Leave Type</th>
                <td>{{ $leave->leaveType->title ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Applied On</th>
                <td>{{ date('d M Y', strtotime($leave->applied_on)) }}</td>
            </tr>
            <tr>
                <th>Start Date</th>
                <td>{{ date('d M Y', strtotime($leave->start_date)) }}</td>
            </tr>
            <tr>
                <th>End Date</th>
                <td>{{ date('d M Y', strtotime($leave->end_date)) }}</td>
            </tr>
            <tr>
                <th>Total Leave Days</th>
                <td>{{ $leave->total_leave_days }}</td>
            </tr>
            <tr>
                <th>Approved Days</th>
                <td>{{ $leave->approved_days ?? '0' }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    @if($leave->status == 'Approved')
                        <span class="badge bg-success">Approved</span>
                    @elseif($leave->status == 'Partially Approved')
                        <span class="badge bg-info">Partially Approved</span>
                    @elseif($leave->status == 'Pending')
                        <span class="badge bg-warning">Pending</span>
                    @elseif($leave->status == 'Reject')
                        <span class="badge bg-danger">Rejected</span>
                    @else
                        <span class="badge bg-secondary">{{ $leave->status }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Leave Reason</th>
                <td>{{ $leave->leave_reason ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Remark</th>
                <td>{{ $leave->remark ?? 'N/A' }}</td>
            </tr>
            @if($leave->status_reason)
            <tr>
                <th>Status Reason</th>
                <td>{{ $leave->status_reason }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
