@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>
                    <i class="fas fa-chart-line"></i> Machinery Financial Dashboard
                </h3>
                <div class="d-flex align-items-center">
                    <!-- Period Selector -->
                    <select class="form-control mr-3" id="periodSelector" onchange="loadDashboard()">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="365">Last Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    
                    <!-- Custom Date Range (hidden by default) -->
                    <div id="customDateRange" style="display: none;">
                        <input type="date" class="form-control mr-2" id="customFromDate">
                        <input type="date" class="form-control mr-2" id="customToDate">
                    </div>
                    
                    <!-- Site Filter -->
                    <select class="form-control mr-3" id="siteFilter" onchange="loadDashboard()">
                        <option value="">All Sites</option>
                        @foreach($sites ?? [] as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                    
                    <button type="button" class="btn btn-primary" onclick="loadDashboard()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="totalMachineryExpense">₹0</h4>
                            <p class="mb-0">Total Machinery Expense</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small id="expenseTrend">
                            <i class="fas fa-arrow-up"></i> 0% from last period
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="pendingPayments">₹0</h4>
                            <p class="mb-0">Pending Payments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small id="pendingCount">0 payment requests</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="dieselRecovery">₹0</h4>
                            <p class="mb-0">Diesel Recovery</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-gas-pump fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small id="dieselLiters">0 liters recovered</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0" id="monthlyUtilization">0%</h4>
                            <p class="mb-0">Monthly Utilization</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-pie fa-2x opacity-75"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small id="activeMachines">0 of 0 machines active</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line"></i> Monthly Billing Trend
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="billingTrendChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie"></i> Expense Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="expenseBreakdownChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-gas-pump"></i> Diesel Consumption Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="dieselConsumptionChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tachometer-alt"></i> Machine Utilization
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="utilizationChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Performers & Issues -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy"></i> Top Machines by Revenue
                    </h5>
                </div>
                <div class="card-body">
                    <div id="topMachinesList">
                        <!-- Will be populated dynamically -->
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Issues & Alerts
                    </h5>
                </div>
                <div class="card-body">
                    <div id="issuesList">
                        <!-- Will be populated dynamically -->
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Supplier Outstanding -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-tie"></i> Supplier Outstanding
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Total Billed</th>
                                    <th>Diesel Recovery</th>
                                    <th>Payments Received</th>
                                    <th>Outstanding</th>
                                    <th>Aging</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="supplierOutstandingTable">
                                <!-- Will be populated dynamically -->
                                <tr>
                                    <td colspan="7" class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let dashboardData = {};
let charts = {};

// Load dashboard data
function loadDashboard() {
    const period = document.getElementById('periodSelector').value;
    const siteId = document.getElementById('siteFilter').value;
    
    let fromDate, toDate;
    
    if (period === 'custom') {
        fromDate = document.getElementById('customFromDate').value;
        toDate = document.getElementById('customToDate').value;
        
        if (!fromDate || !toDate) {
            alert('Please select custom date range');
            return;
        }
    } else {
        const days = parseInt(period);
        toDate = new Date();
        fromDate = new Date(toDate.getTime() - days * 24 * 60 * 60 * 1000);
    }
    
    const params = new URLSearchParams({
        from_date: fromDate.toISOString().split('T')[0],
        to_date: toDate.toISOString().split('T')[0],
        site_id: siteId
    });
    
    fetch(`/api/machinery/financial-dashboard?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dashboardData = data.data;
                updateKPIs(data.data.kpis);
                renderCharts(data.data.charts);
                renderTopMachines(data.data.top_machines);
                renderIssues(data.data.issues);
                renderSupplierOutstanding(data.data.supplier_outstanding);
            } else {
                showError('Failed to load dashboard: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load dashboard data.');
        });
}

// Update KPI cards
function updateKPIs(kpis) {
    // Total Machinery Expense
    document.getElementById('totalMachineryExpense').textContent = 
        `₹${number_format(kpis.total_machinery_expense, 2)}`;
    
    const expenseTrend = kpis.expense_trend || 0;
    const expenseTrendEl = document.getElementById('expenseTrend');
    expenseTrendEl.innerHTML = `
        <i class="fas fa-arrow-${expenseTrend >= 0 ? 'up' : 'down'}"></i> 
        ${Math.abs(expenseTrend)}% from last period
    `;
    expenseTrendEl.className = expenseTrend >= 0 ? 'text-warning' : 'text-success';
    
    // Pending Payments
    document.getElementById('pendingPayments').textContent = 
        `₹${number_format(kpis.pending_payments, 2)}`;
    document.getElementById('pendingCount').textContent = 
        `${kpis.pending_payment_count} payment requests`;
    
    // Diesel Recovery
    document.getElementById('dieselRecovery').textContent = 
        `₹${number_format(kpis.diesel_recovery, 2)}`;
    document.getElementById('dieselLiters').textContent = 
        `${kpis.diesel_liters} liters recovered`;
    
    // Monthly Utilization
    document.getElementById('monthlyUtilization').textContent = 
        `${kpis.monthly_utilization}%`;
    document.getElementById('activeMachines').textContent = 
        `${kpis.active_machines} of ${kpis.total_machines} machines active`;
}

// Render charts
function renderCharts(chartData) {
    // Billing Trend Chart
    if (charts.billingTrend) {
        charts.billingTrend.destroy();
    }
    
    const billingCtx = document.getElementById('billingTrendChart').getContext('2d');
    charts.billingTrend = new Chart(billingCtx, {
        type: 'line',
        data: {
            labels: chartData.billing_trend.labels,
            datasets: [{
                label: 'Work Charges',
                data: chartData.billing_trend.work_charges,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true
            }, {
                label: 'Diesel Recovery',
                data: chartData.billing_trend.diesel_recovery,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Expense Breakdown Chart
    if (charts.expenseBreakdown) {
        charts.expenseBreakdown.destroy();
    }
    
    const expenseCtx = document.getElementById('expenseBreakdownChart').getContext('2d');
    charts.expenseBreakdown = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: chartData.expense_breakdown.labels,
            datasets: [{
                data: chartData.expense_breakdown.values,
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = '₹' + context.parsed.toLocaleString();
                            const percentage = Math.round((context.parsed / context.dataset.data.reduce((a, b) => a + b, 0)) * 100);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Diesel Consumption Chart
    if (charts.dieselConsumption) {
        charts.dieselConsumption.destroy();
    }
    
    const dieselCtx = document.getElementById('dieselConsumptionChart').getContext('2d');
    charts.dieselConsumption = new Chart(dieselCtx, {
        type: 'bar',
        data: {
            labels: chartData.diesel_consumption.labels,
            datasets: [{
                label: 'Diesel Consumed (Liters)',
                data: chartData.diesel_consumption.values,
                backgroundColor: '#17a2b8'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Utilization Chart
    if (charts.utilization) {
        charts.utilization.destroy();
    }
    
    const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
    charts.utilization = new Chart(utilizationCtx, {
        type: 'bar',
        data: {
            labels: chartData.utilization.labels,
            datasets: [{
                label: 'Utilization %',
                data: chartData.utilization.values,
                backgroundColor: chartData.utilization.values.map(v => v > 80 ? '#28a745' : v > 50 ? '#ffc107' : '#dc3545')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

// Render top machines
function renderTopMachines(machines) {
    const container = document.getElementById('topMachinesList');
    
    if (machines.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No data available</p>';
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    machines.forEach((machine, index) => {
        const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${medal} ${machine.name}</strong><br>
                    <small class="text-muted">${machine.machine_id}</small>
                </div>
                <div class="text-right">
                    <strong>₹${number_format(machine.revenue, 2)}</strong><br>
                    <small class="text-muted">${machine.days} days</small>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Render issues
function renderIssues(issues) {
    const container = document.getElementById('issuesList');
    
    if (issues.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No issues detected</p>';
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    issues.forEach(issue => {
        const severityClass = issue.severity === 'critical' ? 'danger' : 
                              issue.severity === 'high' ? 'warning' : 'info';
        const icon = issue.severity === 'critical' ? 'exclamation-triangle' : 
                    issue.severity === 'high' ? 'exclamation-circle' : 'info-circle';
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">
                            <i class="fas fa-${icon} text-${severityClass}"></i>
                            ${issue.title}
                        </h6>
                        <p class="mb-1">${issue.description}</p>
                        <small class="text-muted">${issue.date}</small>
                    </div>
                    <span class="badge badge-${severityClass}">${issue.severity}</span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Render supplier outstanding
function renderSupplierOutstanding(suppliers) {
    const tbody = document.getElementById('supplierOutstandingTable');
    
    if (suppliers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No outstanding payments</td></tr>';
        return;
    }
    
    tbody.innerHTML = suppliers.map(supplier => `
        <tr>
            <td><strong>${supplier.name}</strong></td>
            <td>₹${number_format(supplier.total_billed, 2)}</td>
            <td>₹${number_format(supplier.diesel_recovery, 2)}</td>
            <td>₹${number_format(supplier.payments_received, 2)}</td>
            <td class="font-weight-bold ${supplier.outstanding > 0 ? 'text-danger' : 'text-success'}">
                ₹${number_format(supplier.outstanding, 2)}
            </td>
            <td>
                <span class="badge ${supplier.aging_days > 60 ? 'badge-danger' : 
                              supplier.aging_days > 30 ? 'badge-warning' : 'badge-info'}">
                    ${supplier.aging_days} days
                </span>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        onclick="viewSupplierStatement(${supplier.id})">
                    <i class="fas fa-file-invoice"></i> Statement
                </button>
            </td>
        </tr>
    `).join('');
}

// View supplier statement
function viewSupplierStatement(supplierId) {
    window.open(`/supplier/statement?supplier_id=${supplierId}`, '_blank');
}

// Handle period selector change
document.getElementById('periodSelector').addEventListener('change', function() {
    const customDateRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customDateRange.style.display = 'flex';
        
        // Set default dates
        const today = new Date();
        const lastMonth = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
        
        document.getElementById('customFromDate').value = lastMonth.toISOString().split('T')[0];
        document.getElementById('customToDate').value = today.toISOString().split('T')[0];
    } else {
        customDateRange.style.display = 'none';
    }
});

// Helper function for number formatting
function number_format(number, decimals) {
    return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Show error
function showError(message) {
    console.error(message);
    // You could implement a toast notification here
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial dashboard data
    loadDashboard();
});
</script>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection
