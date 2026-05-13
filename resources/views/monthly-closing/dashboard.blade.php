@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-lock"></i> Monthly Closing Dashboard
                    </h3>
                    <div class="d-flex align-items-center">
                        <select class="form-control mr-3" id="monthSelector" onchange="loadMonthData()">
                            <option value="">Select Month</option>
                            @for($i = 0; $i < 12; $i++)
                                <?php
                                $date = now()->subMonths($i);
                                $monthYear = $date->format('Y-m');
                                $displayMonth = $date->format('F Y');
                                ?>
                                <option value="{{ $monthYear }}" 
                                        {{ request('month') == $monthYear ? 'selected' : '' }}>
                                    {{ $displayMonth }}
                                </option>
                            @endfor
                        </select>
                        <select class="form-control mr-3" id="siteSelector" onchange="loadMonthData()">
                            <option value="">All Sites</option>
                            @foreach($sites ?? [] as $site)
                                <option value="{{ $site->id }}" 
                                        {{ request('site_id') == $site->id ? 'selected' : '' }}>
                                    {{ $site->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary" onclick="showClosingModal()">
                            <i class="fas fa-lock"></i> Close Month
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Month Selection Info -->
                    <div id="monthInfo" class="alert alert-info mb-4" style="display: none;">
                        <h5><i class="fas fa-calendar"></i> Selected Period</h5>
                        <p class="mb-0">
                            <strong>Month:</strong> <span id="selectedMonth">-</span><br>
                            <strong>Site:</strong> <span id="selectedSite">All Sites</span>
                        </p>
                    </div>
                    
                    <!-- Critical Warnings -->
                    <div id="criticalWarnings" class="mb-4" style="display: none;">
                        <!-- Will be populated dynamically -->
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="row mb-4" id="statusCards" style="display: none;">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Machines</h5>
                                    <h3 id="totalMachines">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Pending DPRs</h5>
                                    <h3 id="pendingDprs">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Diesel</h5>
                                    <h3 id="pendingDiesel">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Payments</h5>
                                    <h3 id="pendingPayments">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed Breakdown -->
                    <div id="detailedBreakdown" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-tachometer-alt"></i> Daily Progress Reports
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="dprBreakdown">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-gas-pump"></i> Diesel Entries
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="dieselBreakdown">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-file-invoice-dollar"></i> Payment Requests
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="paymentBreakdown">
                                            <!-- Will be populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Already Closed Months -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-lock"></i> Closed Months
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Year</th>
                                            <th>Site</th>
                                            <th>Closed By</th>
                                            <th>Closed At</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="closedMonthsTable">
                                        @foreach($closedMonths ?? [] as $closure)
                                        <tr>
                                            <td>{{ $closure['month_name'] }}</td>
                                            <td>{{ $closure['year'] }}</td>
                                            <td>{{ $closure['site_name'] ?? 'All Sites' }}</td>
                                            <td>{{ $closure['closed_by_name'] }}</td>
                                            <td>{{ $closure['closed_at'] }}</td>
                                            <td>{{ $closure['remarks'] ?? '-' }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="showReopenModal({{ $closure['id'] }})">
                                                    <i class="fas fa-unlock"></i> Reopen
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Close Month Modal -->
<div class="modal fade" id="closeMonthModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Close Month - Final Confirmation
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Hard Warnings -->
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> ⚠️ CRITICAL WARNINGS</h6>
                    <p><strong>Closing month will permanently lock:</strong></p>
                    <ul>
                        <li>✅ DPR entries - No modifications allowed</li>
                        <li>✅ Meter readings - Cannot be edited</li>
                        <li>✅ Diesel entries - Immutable after closure</li>
                        <li>✅ Billing recalculations - Frozen calculations</li>
                        <li>✅ Payment requests - Cannot be created for closed period</li>
                    </ul>
                    <p class="mb-0"><strong>This action is irreversible and requires admin approval.</strong></p>
                </div>
                
                <!-- Summary -->
                <div class="row">
                    <div class="col-md-6">
                        <h6>Period Summary</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Month:</strong></td>
                                <td id="summaryMonth">-</td>
                            </tr>
                            <tr>
                                <td><strong>Year:</strong></td>
                                <td id="summaryYear">-</td>
                            </tr>
                            <tr>
                                <td><strong>Site:</strong></td>
                                <td id="summarySite">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Current Status</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Total Machines:</strong></td>
                                <td id="summaryMachines">-</td>
                            </tr>
                            <tr>
                                <td><strong>Pending DPRs:</strong></td>
                                <td id="summaryPendingDprs">-</td>
                            </tr>
                            <tr>
                                <td><strong>Pending Diesel:</strong></td>
                                <td id="summaryPendingDiesel">-</td>
                            </tr>
                            <tr>
                                <td><strong>Pending Payments:</strong></td>
                                <td id="summaryPendingPayments">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Confirmation Checkbox -->
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmClosure">
                        <label class="form-check-label" for="confirmClosure">
                            <strong>I understand that closing this month is irreversible and will lock all financial data.</strong>
                        </label>
                    </div>
                </div>
                
                <!-- Remarks -->
                <div class="form-group">
                    <label for="closureRemarks">Remarks (Optional)</label>
                    <textarea class="form-control" id="closureRemarks" rows="3" 
                              placeholder="Any additional notes about this month closure..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="closeMonth()" id="closeMonthBtn" disabled>
                    <i class="fas fa-lock"></i> Close Month
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reopen Month Modal -->
<div class="modal fade" id="reopenMonthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-unlock"></i> Reopen Month
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Warning</h6>
                    <p>Reopening a closed month will allow modifications to DPRs, meter readings, and diesel entries for this period.</p>
                    <p class="mb-0"><strong>This action should only be performed for critical corrections.</strong></p>
                </div>
                
                <div class="form-group">
                    <label for="reopenReason">Reason for Reopening <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reopenReason" rows="4" 
                              placeholder="Please provide a detailed reason for reopening this month..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="reopenMonth()">
                    <i class="fas fa-unlock"></i> Reopen Month
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentMonth = null;
let currentSite = null;
let currentClosureId = null;

// Load month data
function loadMonthData() {
    const monthSelect = document.getElementById('monthSelector');
    const siteSelect = document.getElementById('siteSelector');
    
    currentMonth = monthSelect.value;
    currentSite = siteSelect.value || null;
    
    if (!currentMonth) {
        hideAllSections();
        return;
    }
    
    // Update month info
    const [year, month] = currentMonth.split('-');
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    document.getElementById('selectedMonth').textContent = monthNames[parseInt(month) - 1] + ' ' + year;
    document.getElementById('selectedSite').textContent = currentSite ? 
        document.querySelector(`#siteSelector option[value="${currentSite}"]`).textContent : 'All Sites';
    
    document.getElementById('monthInfo').style.display = 'block';
    
    // Load status data
    loadMonthStatus();
}

// Load month status
function loadMonthStatus() {
    const params = new URLSearchParams({
        month: currentMonth,
        site_id: currentSite
    });
    
    fetch(`/api/monthly-closing/status?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatusCards(data.status);
                updateDetailedBreakdown(data.breakdown);
                updateCriticalWarnings(data.warnings);
                
                // Show sections
                document.getElementById('statusCards').style.display = 'flex';
                document.getElementById('detailedBreakdown').style.display = 'block';
            } else {
                console.error('Error loading status:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load month status');
        });
}

// Update status cards
function updateStatusCards(status) {
    document.getElementById('totalMachines').textContent = status.total_machines;
    document.getElementById('pendingDprs').textContent = status.pending_dprs;
    document.getElementById('pendingDiesel').textContent = status.pending_diesel;
    document.getElementById('pendingPayments').textContent = status.pending_payment_requests;
    
    // Update summary in modal
    document.getElementById('summaryMonth').textContent = document.getElementById('selectedMonth').textContent;
    document.getElementById('summaryYear').textContent = currentMonth.split('-')[0];
    document.getElementById('summarySite').textContent = document.getElementById('selectedSite').textContent;
    document.getElementById('summaryMachines').textContent = status.total_machines;
    document.getElementById('summaryPendingDprs').textContent = status.pending_dprs;
    document.getElementById('summaryPendingDiesel').textContent = status.pending_diesel;
    document.getElementById('summaryPendingPayments').textContent = status.pending_payment_requests;
}

// Update detailed breakdown
function updateDetailedBreakdown(breakdown) {
    // DPR Breakdown
    let dprHtml = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Status</th><th>Count</th><th>Action</th></tr></thead><tbody>';
    
    Object.entries(breakdown.dprs).forEach(([status, count]) => {
        const badgeClass = status === 'completed' ? 'badge-success' : 'badge-warning';
        dprHtml += `
            <tr>
                <td><span class="badge ${badgeClass}">${status}</span></td>
                <td>${count}</td>
                <td>
                    ${status === 'pending' ? '<button class="btn btn-sm btn-info">View</button>' : '-'}
                </td>
            </tr>
        `;
    });
    
    dprHtml += '</tbody></table></div>';
    document.getElementById('dprBreakdown').innerHTML = dprHtml;
    
    // Diesel Breakdown
    let dieselHtml = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Status</th><th>Count</th><th>Total Liters</th><th>Action</th></tr></thead><tbody>';
    
    Object.entries(breakdown.diesel).forEach(([status, data]) => {
        const badgeClass = status === 'completed' ? 'badge-success' : 'badge-warning';
        dieselHtml += `
            <tr>
                <td><span class="badge ${badgeClass}">${status}</span></td>
                <td>${data.count}</td>
                <td>${data.total_liters || 0} L</td>
                <td>
                    ${status === 'pending' ? '<button class="btn btn-sm btn-info">View</button>' : '-'}
                </td>
            </tr>
        `;
    });
    
    dieselHtml += '</tbody></table></div>';
    document.getElementById('dieselBreakdown').innerHTML = dieselHtml;
    
    // Payment Breakdown
    let paymentHtml = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Status</th><th>Count</th><th>Total Amount</th><th>Action</th></tr></thead><tbody>';
    
    Object.entries(breakdown.payments).forEach(([status, data]) => {
        const badgeClass = status === 'paid' ? 'badge-success' : status === 'approved' ? 'badge-info' : 'badge-warning';
        paymentHtml += `
            <tr>
                <td><span class="badge ${badgeClass}">${status}</span></td>
                <td>${data.count}</td>
                <td>₹${number_format(data.total_amount, 2)}</td>
                <td>
                    <button class="btn btn-sm btn-info">View</button>
                </td>
            </tr>
        `;
    });
    
    paymentHtml += '</tbody></table></div>';
    document.getElementById('paymentBreakdown').innerHTML = paymentHtml;
}

// Update critical warnings
function updateCriticalWarnings(warnings) {
    const warningsContainer = document.getElementById('criticalWarnings');
    
    if (warnings.length === 0) {
        warningsContainer.style.display = 'none';
        return;
    }
    
    let html = '';
    warnings.forEach(warning => {
        const alertClass = warning.severity === 'critical' ? 'alert-danger' : 'alert-warning';
        const icon = warning.severity === 'critical' ? 'exclamation-triangle' : 'exclamation-circle';
        
        html += `
            <div class="alert ${alertClass} alert-dismissible fade show">
                <h6><i class="fas fa-${icon}"></i> ${warning.title}</h6>
                <p class="mb-0">${warning.message}</p>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
    });
    
    warningsContainer.innerHTML = html;
    warningsContainer.style.display = 'block';
}

// Show closing modal
function showClosingModal() {
    if (!currentMonth) {
        alert('Please select a month first.');
        return;
    }
    
    // Check if month is already closed
    fetch(`/api/monthly-closing/check-closed?month=${currentMonth}&site_id=${currentSite}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.is_closed) {
                    alert('This month is already closed.');
                } else {
                    $('#closeMonthModal').modal('show');
                }
            } else {
                alert('Error checking month status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to check month status.');
        });
}

// Close month
function closeMonth() {
    const confirmCheckbox = document.getElementById('confirmClosure');
    const remarks = document.getElementById('closureRemarks').value;
    
    if (!confirmCheckbox.checked) {
        alert('Please confirm that you understand the consequences of closing this month.');
        return;
    }
    
    const params = new URLSearchParams({
        month: currentMonth,
        site_id: currentSite,
        remarks: remarks
    });
    
    fetch(`/api/monthly-closing/close?${params}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#closeMonthModal').modal('hide');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to close month.');
    });
}

// Show reopen modal
function showReopenModal(closureId) {
    currentClosureId = closureId;
    $('#reopenMonthModal').modal('show');
}

// Reopen month
function reopenMonth() {
    const reason = document.getElementById('reopenReason').value.trim();
    
    if (!reason) {
        alert('Please provide a reason for reopening.');
        return;
    }
    
    fetch(`/api/monthly-closing/reopen/${currentClosureId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#reopenMonthModal').modal('hide');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to reopen month.');
    });
}

// Hide all sections
function hideAllSections() {
    document.getElementById('monthInfo').style.display = 'none';
    document.getElementById('criticalWarnings').style.display = 'none';
    document.getElementById('statusCards').style.display = 'none';
    document.getElementById('detailedBreakdown').style.display = 'none';
}

// Enable/disable close button based on confirmation checkbox
document.getElementById('confirmClosure').addEventListener('change', function() {
    document.getElementById('closeMonthBtn').disabled = !this.checked;
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data if month is selected
    if (document.getElementById('monthSelector').value) {
        loadMonthData();
    }
});
</script>
@endsection
