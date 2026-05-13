{{ Form::open(['url' => 'leave/changeaction', 'method' => 'post', 'id' => 'leaveActionForm']) }}
<div class="modal-body">
    <div class="table-responsive">
        <table class="table table-bordered ">
            <tr>
                <th>{{ __('Employee') }}</th>
                <td>{{ $employee->name ?? '' }}</td>
            </tr>
            <tr>
                <th>{{ __('Leave Type') }}</th>
                <td>{{ $leavetype->title ?? '' }}</td>
            </tr>
            <tr>
                <th>{{ __('Applied On') }}</th>
                <td>{{ company_date_formate($leave->applied_on) }}</td>
            </tr>
            <tr>
                <th>{{ __('Start Date') }}</th>
                <td>{{ company_date_formate($leave->start_date) }}</td>
            </tr>
            <tr>
                <th>{{ __('End Date') }}</th>
                <td>{{ company_date_formate($leave->end_date) }}</td>
            </tr>
            <tr>
                <th>{{ __('Total Leave Days') }}</th>
                <td>
                    @php
                        $totalDays = \Carbon\Carbon::parse($leave->start_date)
                            ->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1;
                    @endphp
                    {{ $totalDays }}
                </td>
            </tr>
            <tr>
                <th>{{ __('Leave Reason') }}</th>
                <td class="text-wrap text-break">{{ $leave->leave_reason ?? '' }}</td>
            </tr>
            <tr>
                <th>{{ __('Status') }}</th>
                <td>{{ $leave->status ?? '' }}</td>
            </tr>
            
            <tr>
                <th>{{ __('Leave Summary') }}</th>
                <td>
                    <ul class="list-unstyled mb-0">
                         <li><strong>Total:</strong> {{ $leave->days }}</li>
                        <li><strong>Sundays Worked:</strong> {{ $leave->sundays_worked }}</li>
                       
                        <li><strong>Used:</strong> {{ $leave->used }}</li>
                        <li><strong>Remaining:</strong> {{ $leave->remaining_days }}</li>
                    </ul>
                </td>

            </tr>

            
            <tr @if($totalDays == 1) style="display:none;" @endif>
                <th>{{ __('Days Approved') }}</th>
                <td><input type="number" name="approved_days" id="approved_days" class="form-control "
                           placeholder="Days Approved" min="1" max="{{ $totalDays }}" value="{{ $totalDays }}">
                @error('approved_days')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
                </td>
            </tr>
            
            <tr>
                <th>{{ __('Admin Reason') }}</th>
                <td>
                    <textarea name="status_reason" id="status_reason" class="form-control"
                              placeholder="{{ __('Enter reason for approval/rejection') }}" rows="3">{{ $leave->status_reason ?? '' }}</textarea>
                    @error('status_reason')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </td>
            </tr>

            
            <input type="hidden" value="{{ $leave->id }}" name="leave_id">
        </table>
    </div>
</div>

@if ($leave->status == 'Pending')
    <div class="modal-footer">
        <!-- All buttons are type="button" -->
        <input type="button" value="{{ __('Approved') }}" class="btn btn-primary" id="approveBtn">
        <input type="button" value="{{ __('Reject') }}" class="btn btn-danger" id="rejectBtn">
        <input type="button" value="{{ __('Partially Approved') }}" class="btn btn-warning me-2" @if($totalDays == 1) style="display:none;" @endif id="partialBtn">
        <input type="hidden" name="status" id="statusField">
    </div>
@endif

{{ Form::close() }}

<script>
$(document).ready(function () {
    const $form = $('#leaveActionForm');
    const $statusField = $('#statusField');
    const $approvedDaysInput = $('#approved_days');
    const totalDays = parseInt($approvedDaysInput.attr('max'), 10); // from Blade

    // Approved button
    $('#approveBtn').on('click', function () {
        $statusField.val('Approved');
        $approvedDaysInput.val(totalDays); // full approval = total days
        $form.submit();
    });

    // Reject button
    $('#rejectBtn').on('click', function () {
        $statusField.val('Reject');
        $approvedDaysInput.val(0);
        $form.submit();
    });

    // Partially Approved button
    $('#partialBtn').on('click', function () {
        const val = parseInt($approvedDaysInput.val(), 10);

        // 🚫 Block partial approval if total days = 1
        if (totalDays === 1) {
            alert('Partially Approved is not allowed when total leave days is 1.');
            return;
        }

        if (!val || val <= 0) {
            alert('Please enter a valid number of approved days greater than 0 for partial approval.');
            $approvedDaysInput.focus();
        } else if (val > totalDays) {
            alert('Approved days cannot exceed total leave days.');
            $approvedDaysInput.focus();
        } else {
            $statusField.val('Partially Approved');
            $form.submit();
        }
    });
});
</script>




