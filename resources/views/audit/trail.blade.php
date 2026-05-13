@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-history"></i> Audit Trail
                    </h3>
                    <div class="d-flex align-items-center">
                        <!-- Filters -->
                        <select class="form-control mr-3" id="entityType" onchange="loadAuditTrail()">
                            <option value="">All Entity Types</option>
                            <option value="machinery_payment_request">Payment Requests</option>
                            <option value="daily_progress_report">Daily Progress Reports</option>
                            <option value="machinery_ledger">Ledger Entries</option>
                            <option value="machinery">Machinery</option>
                        </select>
                        
                        <select class="form-control mr-3" id="actionType" onchange="loadAuditTrail()">
                            <option value="">All Actions</option>
                            <option value="created">Created</option>
                            <option value="updated">Updated</option>
                            <option value="deleted">Deleted</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="paid">Paid</option>
                        </select>
                        
                        <input type="date" class="form-control mr-3" id="fromDate" onchange="loadAuditTrail()">
                        <input type="date" class="form-control mr-3" id="toDate" onchange="loadAuditTrail()">
                        
                        <button type="button" class="btn btn-primary" onclick="loadAuditTrail()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Activities</h6>
                                    <h3 id="totalActivities">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Today's Activities</h6>
                                    <h3 id="todayActivities">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Critical Changes</h6>
                                    <h3 id="criticalChanges">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Active Users</h6>
                                    <h3 id="activeUsers">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline View -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock"></i> Activity Timeline
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="auditTimeline">
                                <!-- Timeline will be populated here -->
                                <div class="text-center py-5">
                                    <div class="spinner-border spinner-border-lg" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading audit trail...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change History Details -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-exchange-alt"></i> Change History Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Entity</th>
                                            <th>Action</th>
                                            <th>Changes</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="changeHistoryTable">
                                        <!-- Will be populated dynamically -->
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

<!-- Change Details Modal -->
<div class="modal fade" id="changeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Change Details
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="changeDetailsContent">
                    <!-- Will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
    max-height: 600px;
    overflow-y: auto;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-left: 20px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-marker.created { background-color: #28a745; }
.timeline-marker.updated { background-color: #007bff; }
.timeline-marker.deleted { background-color: #dc3545; }
.timeline-marker.approved { background-color: #28a745; }
.timeline-marker.rejected { background-color: #dc3545; }
.timeline-marker.paid { background-color: #ffc107; }
.timeline-marker.critical { background-color: #dc3545; }

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
    position: relative;
}

.timeline-content.critical {
    border-left-color: #dc3545;
    background: #f8d7da;
}

.timeline-time {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.timeline-user {
    font-weight: bold;
    color: #495057;
}

.timeline-action {
    color: #007bff;
    font-weight: 500;
}

.timeline-entity {
    color: #6c757d;
    font-size: 0.875rem;
}

.timeline-changes {
    margin-top: 10px;
    font-size: 0.875rem;
}

.change-item {
    background: #e9ecef;
    padding: 5px 8px;
    border-radius: 3px;
    margin-bottom: 3px;
}

.change-old {
    text-decoration: line-through;
    color: #dc3545;
}

.change-new {
    color: #28a745;
    font-weight: bold;
}
</style>

<script>
let auditData = [];

// Load audit trail
function loadAuditTrail() {
    const entityType = document.getElementById('entityType').value;
    const actionType = document.getElementById('actionType').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    const params = new URLSearchParams({
        entity_type: entityType,
        action_type: actionType,
        from_date: fromDate,
        to_date: toDate
    });
    
    fetch(`/api/audit/trail?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                auditData = data.audit_trail;
                updateStatistics(data.statistics);
                renderTimeline(auditData);
                renderChangeHistory(auditData);
            } else {
                showError('Failed to load audit trail: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load audit trail data.');
        });
}

// Update statistics
function updateStatistics(stats) {
    document.getElementById('totalActivities').textContent = stats.total_activities;
    document.getElementById('todayActivities').textContent = stats.today_activities;
    document.getElementById('criticalChanges').textContent = stats.critical_changes;
    document.getElementById('activeUsers').textContent = stats.active_users;
}

// Render timeline
function renderTimeline(auditTrail) {
    const timeline = document.getElementById('auditTimeline');
    
    if (auditTrail.length === 0) {
        timeline.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5>No audit records found</h5>
                <p class="text-muted">Try adjusting your filters or date range.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    auditTrail.forEach(item => {
        const isCritical = item.action === 'deleted' || item.action === 'rejected' || item.severity === 'critical';
        const markerClass = isCritical ? 'critical' : item.action;
        
        html += `
            <div class="timeline-item">
                <div class="timeline-marker ${markerClass}"></div>
                <div class="timeline-content ${isCritical ? 'critical' : ''}">
                    <div class="timeline-time">${formatDateTime(item.created_at)}</div>
                    <div class="timeline-user">${item.user_name}</div>
                    <div class="timeline-action">${getActionDisplay(item.action)}</div>
                    <div class="timeline-entity">${getEntityDisplay(item.entity_type)} #${item.entity_id}</div>
                    
                    ${item.changes ? `
                        <div class="timeline-changes">
                            ${renderChangesSummary(item.changes)}
                        </div>
                    ` : ''}
                    
                    ${item.remarks ? `
                        <div class="mt-2">
                            <small class="text-muted"><strong>Remarks:</strong> ${item.remarks}</small>
                        </div>
                    ` : ''}
                    
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="showChangeDetails(${auditTrail.indexOf(item)})">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    timeline.innerHTML = html;
}

// Render change history table
function renderChangeHistory(auditTrail) {
    const tbody = document.getElementById('changeHistoryTable');
    
    if (auditTrail.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No audit records found</td></tr>';
        return;
    }
    
    tbody.innerHTML = auditTrail.map(item => `
        <tr>
            <td>${formatDateTime(item.created_at)}</td>
            <td>${item.user_name}</td>
            <td>${getEntityDisplay(item.entity_type)} #${item.entity_id}</td>
            <td>
                <span class="badge ${getActionBadgeClass(item.action)}">
                    ${getActionDisplay(item.action)}
                </span>
            </td>
            <td>
                ${item.changes ? `${Object.keys(item.changes).length} changes` : '-'}
            </td>
            <td>${item.ip_address || '-'}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-info" 
                        onclick="showChangeDetails(${auditTrail.indexOf(item)})">
                    <i class="fas fa-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');
}

// Render changes summary
function renderChangesSummary(changes) {
    const changeCount = Object.keys(changes).length;
    if (changeCount === 0) return '';
    
    let html = '<div class="change-item">';
    if (changeCount <= 3) {
        Object.keys(changes).slice(0, 3).forEach(key => {
            html += `<strong>${key}:</strong> ${changes[key].old} → ${changes[key].new}<br>`;
        });
    } else {
        html += `${changeCount} fields changed`;
    }
    html += '</div>';
    
    return html;
}

// Show change details
function showChangeDetails(index) {
    const item = auditData[index];
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Timestamp:</strong></td>
                        <td>${formatDateTime(item.created_at)}</td>
                    </tr>
                    <tr>
                        <td><strong>User:</strong></td>
                        <td>${item.user_name}</td>
                    </tr>
                    <tr>
                        <td><strong>Action:</strong></td>
                        <td><span class="badge ${getActionBadgeClass(item.action)}">${getActionDisplay(item.action)}</span></td>
                    </tr>
                    <tr>
                        <td><strong>Entity:</strong></td>
                        <td>${getEntityDisplay(item.entity_type)} #${item.entity_id}</td>
                    </tr>
                    <tr>
                        <td><strong>IP Address:</strong></td>
                        <td>${item.ip_address || '-'}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>System Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>User Agent:</strong></td>
                        <td>${item.user_agent || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>Session ID:</strong></td>
                        <td>${item.session_id || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>Request ID:</strong></td>
                        <td>${item.request_id || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td>${item.duration ? item.duration + 'ms' : '-'}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
    
    if (item.changes) {
        html += `
            <h6 class="mt-4">Field Changes</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        Object.entries(item.changes).forEach(([field, change]) => {
            html += `
                <tr>
                    <td><strong>${field}</strong></td>
                    <td class="change-old">${change.old || '-'}</td>
                    <td class="change-new">${change.new || '-'}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    if (item.remarks) {
        html += `
            <h6 class="mt-4">Remarks</h6>
            <div class="alert alert-info">
                ${item.remarks}
            </div>
        `;
    }
    
    if (item.metadata) {
        html += `
            <h6 class="mt-4">Additional Metadata</h6>
            <pre class="bg-light p-3 rounded">${JSON.stringify(item.metadata, null, 2)}</pre>
        `;
    }
    
    document.getElementById('changeDetailsContent').innerHTML = html;
    $('#changeDetailsModal').modal('show');
}

// Helper functions
function formatDateTime(dateTime) {
    const date = new Date(dateTime);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getActionDisplay(action) {
    const actions = {
        'created': 'Created',
        'updated': 'Updated',
        'deleted': 'Deleted',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'paid': 'Paid',
        'submitted': 'Submitted'
    };
    return actions[action] || action;
}

function getActionBadgeClass(action) {
    const classes = {
        'created': 'badge-success',
        'updated': 'badge-info',
        'deleted': 'badge-danger',
        'approved': 'badge-success',
        'rejected': 'badge-danger',
        'paid': 'badge-warning',
        'submitted': 'badge-primary'
    };
    return classes[action] || 'badge-secondary';
}

function getEntityDisplay(entityType) {
    const entities = {
        'machinery_payment_request': 'Payment Request',
        'daily_progress_report': 'DPR',
        'machinery_ledger': 'Ledger Entry',
        'machinery': 'Machinery'
    };
    return entities[entityType] || entityType;
}

function showError(message) {
    // You could implement a toast notification here
    console.error(message);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 7 days)
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    document.getElementById('toDate').value = today.toISOString().split('T')[0];
    document.getElementById('fromDate').value = weekAgo.toISOString().split('T')[0];
    
    // Load initial data
    loadAuditTrail();
});
</script>
@endsection
