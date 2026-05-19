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

            
            <!-- Date-wise Approval Section -->
            <tr>
                <th>{{ __('Date-wise Approval') }}</th>
                <td>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="dateApprovalTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Day') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Remarks') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $startDate = \Carbon\Carbon::parse($leave->start_date);
                                    $endDate = \Carbon\Carbon::parse($leave->end_date);
                                    $currentDate = $startDate;
                                @endphp
                                @while($currentDate <= $endDate)
                                    <tr data-date="{{ $currentDate->format('Y-m-d') }}">
                                        <td>{{ company_date_formate($currentDate->format('Y-m-d')) }}</td>
                                        <td>{{ $currentDate->format('l') }}</td>
                                        <td>
                                            <select name="approved_dates[{{ $currentDate->format('Y-m-d') }}][status]" 
                                                    class="form-select form-select-sm date-status-select">
                                                <option value="approved" {{ ($existingDates[$currentDate->format('Y-m-d')] ?? null) === 'approved' ? 'selected' : '' }}>{{ __('Approve') }}</option>
                                                <option value="rejected" {{ ($existingDates[$currentDate->format('Y-m-d')] ?? null) === 'rejected' ? 'selected' : '' }}>{{ __('Reject') }}</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="approved_dates[{{ $currentDate->format('Y-m-d') }}][remarks]" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="{{ __('Optional remarks') }}">
                                        </td>
                                    </tr>
                                    @php($currentDate->addDay())
                                @endwhile
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-success" id="approveAllBtn">
                            {{ __('Approve All') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="rejectAllBtn">
                            {{ __('Reject All') }}
                        </button>
                    </div>
                    
                    <!-- Summary -->
                    <div class="mt-2 p-2 bg-light rounded">
                        <strong>{{ __('Approval Summary') }}:</strong>
                        <span id="approvedCount">0</span> {{ __('approved') }},
                        <span id="rejectedCount">{{ $totalDays }}</span> {{ __('rejected') }}
                    </div>
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


    <div class="modal-footer">
        <!-- All buttons are type="button" -->
        <input type="button" value="{{ __('Approved') }}" class="btn btn-primary" id="approveBtn">
        <input type="button" value="{{ __('Reject') }}" class="btn btn-danger" id="rejectBtn">
        <input type="button" value="{{ __('Partially Approved') }}" class="btn btn-warning me-2" @if($totalDays == 1 || !($allow_partial ?? true)) style="display:none;" @endif id="partialBtn">
        <input type="hidden" name="status" id="statusField">
    </div>


{{ Form::close() }}

<script>
$(document).ready(function () {
    const $form = $('#leaveActionForm');
    const $statusField = $('#statusField');
    const $dateSelects = $('.date-status-select');
    const totalDays = {{ $totalDays }};
    
    // Update summary
    function updateSummary() {
        let approved = 0;
        let rejected = 0;
        
        $dateSelects.each(function() {
            if ($(this).val() === 'approved') approved++;
            if ($(this).val() === 'rejected') rejected++;
        });
        
        $('#approvedCount').text(approved);
        $('#rejectedCount').text(rejected);
    }
    
    $dateSelects.on('change', updateSummary);
    
    // Approve all
    $('#approveAllBtn').on('click', function() {
        $dateSelects.val('approved').trigger('change');
    });
    
    // Reject all
    $('#rejectAllBtn').on('click', function() {
        $dateSelects.val('rejected').trigger('change');
    });
    
    // Full approve
    $('#approveBtn').on('click', function() {
        $dateSelects.val('approved').trigger('change');
        $statusField.val('Approved');
        $form.submit();
    });
    
    // Full reject
    $('#rejectBtn').on('click', function() {
        $dateSelects.val('rejected').trigger('change');
        $statusField.val('Reject');
        $form.submit();
    });
    
    // Partial approval
    $('#partialBtn').on('click', function() {
        const approvedCount = parseInt($('#approvedCount').text());
        
        if (approvedCount === 0) {
            alert('Please select at least one date to approve for partial approval.');
            return;
        }
        
        if (approvedCount === totalDays) {
            if (!confirm('All dates are approved. This will be a full approval. Continue?')) {
                return;
            }
            $statusField.val('Approved');
        } else {
            $statusField.val('Partially Approved');
        }
        
        $form.submit();
    });
    
    // Initialize summary
    updateSummary();
});
</script>




