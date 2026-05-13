@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice"></i> Supplier Ledger Statement
                    </h3>
                    <div class="d-flex align-items-center">
                        <!-- Export Buttons -->
                        <div class="btn-group mr-3">
                            <button type="button" class="btn btn-success" onclick="exportStatement('pdf')">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportStatement('excel')">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="printStatement()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        
                        <a href="{{ route('supplier.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplierSelect">Supplier</label>
                                <select class="form-control" id="supplierSelect" onchange="loadStatement()">
                                    <option value="">Select Supplier</option>
                                    @foreach($suppliers ?? [] as $supplier)
                                        <option value="{{ $supplier->id }}" 
                                                {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="fromDate">From Date</label>
                                <input type="date" class="form-control" id="fromDate" 
                                       value="{{ request('from_date') ?? now()->subMonth()->toDateString() }}" 
                                       onchange="loadStatement()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="toDate">To Date</label>
                                <input type="date" class="form-control" id="toDate" 
                                       value="{{ request('to_date') ?? now()->toDateString() }}" 
                                       onchange="loadStatement()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="siteSelect">Site</label>
                                <select class="form-control" id="siteSelect" onchange="loadStatement()">
                                    <option value="">All Sites</option>
                                    @foreach($sites ?? [] as $site)
                                        <option value="{{ $site->id }}" 
                                                {{ request('site_id') == $site->id ? 'selected' : '' }}>
                                            {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="machineSelect">Machine</label>
                                <select class="form-control" id="machineSelect" onchange="loadStatement()">
                                    <option value="">All Machines</option>
                                    @foreach($machines ?? [] as $machine)
                                        <option value="{{ $machine->id }}" 
                                                {{ request('machine_id') == $machine->id ? 'selected' : '' }}>
                                            {{ $machine->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-block" onclick="loadStatement()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statement Content -->
                    <div id="statementContent" style="display: none;">
                        <!-- Header Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Supplier Information</h5>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td id="supplierName">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Code:</strong></td>
                                        <td id="supplierCode">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Contact:</strong></td>
                                        <td id="supplierContact">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Period Information</h5>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Period:</strong></td>
                                        <td id="periodInfo">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Generated:</strong></td>
                                        <td>{{ now()->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Site:</strong></td>
                                        <td id="siteInfo">All Sites</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Balance Summary -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Balance Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h6>Opening Balance</h6>
                                            <h4 class="text-primary" id="openingBalance">₹0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h6>Total Work Charges</h6>
                                            <h4 class="text-success" id="totalWorkCharges">₹0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h6>Total Deductions</h6>
                                            <h4 class="text-danger" id="totalDeductions">₹0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h6>Closing Balance</h6>
                                            <h4 class="font-weight-bold" id="closingBalance">₹0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Transaction Details Tabs -->
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#workChargesTab">
                                            Work Charges ({{ $statement['work_charges']['count'] ?? 0 }})
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#dieselRecoveryTab">
                                            Diesel Recovery ({{ $statement['diesel_recovery']['count'] ?? 0 }})
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#paymentsTab">
                                            Payments ({{ $statement['payments']['count'] ?? 0 }})
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Work Charges Tab -->
                                    <div class="tab-pane fade show active" id="workChargesTab">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Reference</th>
                                                        <th>Machine</th>
                                                        <th>Period</th>
                                                        <th class="text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="workChargesTable">
                                                    <!-- Will be populated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Diesel Recovery Tab -->
                                    <div class="tab-pane fade" id="dieselRecoveryTab">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Reference</th>
                                                        <th>Machine</th>
                                                        <th>Liters</th>
                                                        <th>Rate</th>
                                                        <th class="text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dieselRecoveryTable">
                                                    <!-- Will be populated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Payments Tab -->
                                    <div class="tab-pane fade" id="paymentsTab">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Reference</th>
                                                        <th>Method</th>
                                                        <th class="text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="paymentsTable">
                                                    <!-- Will be populated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Summary Statistics -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0">Summary Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Total Transactions</label>
                                            <input type="text" class="form-control" id="totalTransactions" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Net Change</label>
                                            <input type="text" class="form-control" id="netChange" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Average Monthly Charges</label>
                                            <input type="text" class="form-control" id="avgMonthlyCharges" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Diesel Recovery Rate</label>
                                            <input type="text" class="form-control" id="dieselRecoveryRate" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border spinner-border-lg" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading statement data...</p>
                    </div>
                    
                    <!-- Empty State -->
                    <div id="emptyState" class="text-center py-5" style="display: none;">
                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                        <h5>No Data Available</h5>
                        <p class="text-muted">Please select a supplier and date range to generate the statement.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentStatement = null;

// Load statement data
function loadStatement() {
    const supplierId = document.getElementById('supplierSelect').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const siteId = document.getElementById('siteSelect').value;
    const machineId = document.getElementById('machineSelect').value;
    
    if (!supplierId || !fromDate || !toDate) {
        showEmptyState();
        return;
    }
    
    showLoadingState();
    
    const params = new URLSearchParams({
        supplier_id: supplierId,
        from_date: fromDate,
        to_date: toDate,
        site_id: siteId,
        machine_id: machineId
    });
    
    fetch(`/api/supplier/statement?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentStatement = data.statement;
                displayStatement(data.statement);
            } else {
                showError('Failed to load statement: ' + data.message);
                showEmptyState();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load statement data.');
            showEmptyState();
        });
}

// Display statement
function displayStatement(statement) {
    // Hide loading, show content
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('statementContent').style.display = 'block';
    
    // Update header information
    document.getElementById('supplierName').textContent = statement.supplier.name;
    document.getElementById('supplierCode').textContent = statement.supplier.code || '-';
    document.getElementById('supplierContact').textContent = statement.supplier.contact || '-';
    
    document.getElementById('periodInfo').textContent = `${statement.period.from} to ${statement.period.to}`;
    document.getElementById('siteInfo').textContent = 'All Sites'; // Would need to fetch site name
    
    // Update balance summary
    document.getElementById('openingBalance').textContent = `₹${number_format(statement.balances.opening_balance, 2)}`;
    document.getElementById('totalWorkCharges').textContent = `₹${number_format(statement.balances.total_work_charges, 2)}`;
    document.getElementById('totalDeductions').textContent = `₹${number_format(statement.balances.total_diesel_recovery + statement.balances.total_payments, 2)}`;
    document.getElementById('closingBalance').textContent = `₹${number_format(statement.balances.closing_balance, 2)}`;
    
    // Update summary statistics
    document.getElementById('totalTransactions').value = statement.summary.total_transactions;
    document.getElementById('netChange').value = `₹${number_format(statement.summary.net_change, 2)}`;
    document.getElementById('avgMonthlyCharges').value = `₹${number_format(statement.summary.average_monthly_charges, 2)}`;
    document.getElementById('dieselRecoveryRate').textContent = `${statement.balances.total_work_charges > 0 ? 
        number_format((statement.balances.total_diesel_recovery / statement.balances.total_work_charges) * 100, 1) : 0}%`;
    
    // Populate transaction tables
    populateWorkChargesTable(statement.work_charges.transactions);
    populateDieselRecoveryTable(statement.diesel_recovery.transactions);
    populatePaymentsTable(statement.payments.transactions);
}

// Populate work charges table
function populateWorkChargesTable(transactions) {
    const tbody = document.getElementById('workChargesTable');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No work charges found</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(transaction => `
        <tr>
            <td>${transaction.date}</td>
            <td>${transaction.description}</td>
            <td>${transaction.reference}</td>
            <td>${transaction.machinery_name}</td>
            <td>${transaction.period_start} to ${transaction.period_end}</td>
            <td class="text-right text-success font-weight-bold">₹${number_format(transaction.amount, 2)}</td>
        </tr>
    `).join('');
}

// Populate diesel recovery table
function populateDieselRecoveryTable(transactions) {
    const tbody = document.getElementById('dieselRecoveryTable');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No diesel recovery entries found</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(transaction => `
        <tr>
            <td>${transaction.date}</td>
            <td>${transaction.description}</td>
            <td>${transaction.reference}</td>
            <td>${transaction.machinery_name}</td>
            <td>${transaction.diesel_liters || 0}</td>
            <td>₹${number_format(transaction.diesel_rate || 0, 2)}</td>
            <td class="text-right text-danger font-weight-bold">-₹${number_format(transaction.amount, 2)}</td>
        </tr>
    `).join('');
}

// Populate payments table
function populatePaymentsTable(transactions) {
    const tbody = document.getElementById('paymentsTable');
    
    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payments found</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactions.map(transaction => `
        <tr>
            <td>${transaction.date}</td>
            <td>${transaction.description}</td>
            <td>${transaction.reference}</td>
            <td>${transaction.payment_method || 'Bank Transfer'}</td>
            <td class="text-right text-danger font-weight-bold">-₹${number_format(transaction.amount, 2)}</td>
        </tr>
    `).join('');
}

// Export statement
function exportStatement(format) {
    if (!currentStatement) {
        alert('Please load a statement first.');
        return;
    }
    
    const supplierId = document.getElementById('supplierSelect').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const siteId = document.getElementById('siteSelect').value;
    const machineId = document.getElementById('machineSelect').value;
    
    const params = new URLSearchParams({
        supplier_id: supplierId,
        from_date: fromDate,
        to_date: toDate,
        site_id: siteId,
        machine_id: machineId,
        format: format
    });
    
    window.open(`/api/supplier/statement/export?${params}`, '_blank');
}

// Print statement
function printStatement() {
    if (!currentStatement) {
        alert('Please load a statement first.');
        return;
    }
    
    window.print();
}

// Show loading state
function showLoadingState() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('statementContent').style.display = 'none';
}

// Show empty state
function showEmptyState() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
    document.getElementById('statementContent').style.display = 'none';
}

// Show error
function showError(message) {
    // You could implement a toast notification here
    console.error(message);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load statement if parameters are present
    if (document.getElementById('supplierSelect').value && 
        document.getElementById('fromDate').value && 
        document.getElementById('toDate').value) {
        loadStatement();
    } else {
        showEmptyState();
    }
});

// Helper function for number formatting
function number_format(number, decimals) {
    return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>

<style>
@media print {
    .card-header .btn-group,
    .card-header .btn,
    .nav-tabs,
    .no-print {
        display: none !important;
    }
    
    .tab-content .tab-pane {
        display: block !important;
    }
    
    .card {
        page-break-inside: avoid;
    }
}
</style>
@endsection
