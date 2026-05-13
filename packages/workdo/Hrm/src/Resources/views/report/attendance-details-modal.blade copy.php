<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <th width="40%">Employee Name</th>
                <td>{{ $attendance->employees->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Date</th>
                <td>{{ date('d M Y', strtotime($attendance->date)) }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    @if($attendance->status == 'Present')
                        <span class="badge bg-success">Present</span>
                    @elseif($attendance->status == 'Leave')
                        <span class="badge bg-warning">Leave</span>
                    @else
                        <span class="badge bg-danger">{{ $attendance->status }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Clock In</th>
                <td>{{ $attendance->clock_in ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Clock Out</th>
                <td>{{ $attendance->clock_out ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Late</th>
                <td>{{ $attendance->late ?? '0' }}</td>
            </tr>
            <tr>
                <th>Early Leaving</th>
                <td>{{ $attendance->early_leaving ?? '0' }}</td>
            </tr>
            <tr>
                <th>Overtime</th>
                <td>{{ $attendance->overtime ?? '0' }}</td>
            </tr>
            <tr>
                <th>Total Rest</th>
                <td>{{ $attendance->total_rest ?? '0' }}</td>
            </tr>
            <tr>
                <th>Clock In Latitude</th>
                <td>{{ $attendance->clock_in_latitude ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Clock In Longitude</th>
                <td>{{ $attendance->clock_in_longitude ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Clock Out Latitude</th>
                <td>{{ $attendance->clock_out_latitude ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Clock Out Longitude</th>
                <td>{{ $attendance->clock_out_longitude ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Clock In Image</th>
                <td>
                    @if($attendance->clock_in_image)
                        <img src="{{ asset('uploads/attendance/' . $attendance->clock_in_image) }}" alt="Clock In" style="max-width: 150px;">
                    @else
                        <span class="text-muted">No image</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Clock Out Image</th>
                <td>
                    @if($attendance->clock_out_image)
                        <img src="{{ asset('uploads/attendance/' . $attendance->clock_out_image) }}" alt="Clock Out" style="max-width: 150px;">
                    @else
                        <span class="text-muted">No image</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Workspace</th>
                <td>
                    @if(is_object($attendance->workspaceRelation) && $attendance->workspaceRelation->name)
                        {{ $attendance->workspaceRelation->name }}
                    @elseif(is_numeric($attendance->workspaceRelation))
                        {{ $attendance->workspaceRelation }} (ID)
                    @else
                        {{ $attendance->workspace_id ?? 'N/A' }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Site</th>
                <td>{{ $attendance->site->name ?? $attendance->site_id ?? 'N/A' }}</td>
            </tr>
        </tbody>
    </table>
</div>
