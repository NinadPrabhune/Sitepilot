<?php
use App\Services\ProjectDashboardService;
$dashboardService = new App\Services\ProjectDashboardService($project);
$dashboardData = $dashboardService->getDashboardData(false);
$alerts = $dashboardService->getAlerts();

$currency = company_setting('defult_currancy') ?? '₹';
$spentPercent = $dashboardData['project']['spent_percent'] ?? 0;
$budgetStatus = $dashboardData['project']['budget_status'] ?? 'within_budget';
$healthScore = $dashboardData['project']['health_score'] ?? 0;
$riskLevel = $dashboardData['project']['risk_level'] ?? 'low';
$scheduleStatus = $dashboardData['project']['schedule_status'] ?? 'on_track';
$activityProgress = $dashboardData['project']['activity_progress'] ?? 0;
$timeProgress = $dashboardData['project']['time_progress'] ?? 0;
$overallProgress = $dashboardData['project']['overall_progress'] ?? 0;


?>

<!-- ============================================
     ENTERPRISE ERP PROJECT DASHBOARD
     ============================================ -->

<!-- 1. TOP KPI CARDS -->
<div class="row g-4 mb-4">
    <!-- Budget Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="p-3 rounded-3" style="background: #EEF2FF;">
                        <i class="ti ti-wallet fs-4" style="color: #4F46E5;"></i>
                    </div>
                    <span class="badge bg-light text-dark bg-opacity-10">Budget</span>
                </div>
                <h2 class="fw-bold mb-1 counter" data-target="<?php echo e($dashboardData['project']['budget']); ?>" style="color: #0F172A;">
                    <?php echo e(currency_format_with_sym($dashboardData['project']['budget'])); ?>



                </h2>
                <p class="text-muted mb-0 small">Total Project Budget</p>
                <div class="mt-3">
                    <div class="progress" style="height: 6px; background: #E5E7EB; border-radius: 10px;">
                        <div class="progress-bar" role="progressbar" style="width: 100%; background: #4F46E5; border-radius: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    <!-- Spent Card -->
    <div class="col-xl-3 col-md-6">
        <a href="<?php echo e(route('spent.index')); ?>" class="text-decoration-none">
            <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="p-3 rounded-3" style="background: #FEF3C7;">
                            <i class="ti ti-credit-card fs-4" style="color: #F59E0B;"></i>
                        </div>
                        <span class="badge bg-light text-dark bg-opacity-10">Spent</span>
                    </div>
                    <h2 class="fw-bold mb-1 counter" data-target="<?php echo e($dashboardData['project']['total_spent']); ?>" style="color: #0F172A;">
                        <?php echo e(currency_format_with_sym($dashboardData['project']['total_spent'])); ?>

                    </h2>
                    <p class="text-muted mb-0 small"><?php echo e($spentPercent); ?>% of budget used</p>
                    <div class="mt-3">
                        <div class="progress" style="height: 6px; background: #E5E7EB; border-radius: 10px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo e(min($spentPercent, 100)); ?>%; background: #F59E0B; border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Remaining Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="p-3 rounded-3" style="background: #DCFCE7;">
                        <i class="ti ti-chart-pie fs-4" style="color: #22C55E;"></i>
                    </div>
                    <span class="badge bg-light text-dark bg-opacity-10">Remaining</span>
                </div>
                <h2 class="fw-bold mb-1 counter" data-target="<?php echo e($dashboardData['project']['remaining_budget']); ?>" style="color: #0F172A;">
                    <?php echo e(currency_format_with_sym($dashboardData['project']['remaining_budget'])); ?>

                </h2>
                <p class="text-muted mb-0 small">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dashboardData['project']['remaining_budget'] < 0): ?>
                    <span class="text-danger">Over Budget</span>
                    <?php else: ?>
                    <span class="text-success">Under Budget</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Days Left Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="p-3 rounded-3" style="background: #F3E8FF;">
                        <i class="ti ti-calendar fs-4" style="color: #8B5CF6;"></i>
                    </div>
                    <span class="badge bg-light text-dark bg-opacity-10">Timeline</span>
                </div>
                <h2 class="fw-bold mb-1" style="color: #0F172A;"><?php echo e($dashboardData['project']['days_left']); ?></h2>
                <p class="text-muted mb-0 small">Days Remaining</p>
                <p class="text-muted mb-0 small mt-2">Due: <?php echo e(company_date_formate($dashboardData['project']['end_date'])); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- HEALTH & STATUS INDICATORS -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="position-relative d-inline-block" style="width: 80px; height: 80px;">
                            <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                <circle cx="50" cy="50" r="38" fill="none" stroke="#E5E7EB" stroke-width="8"/>
                                <circle cx="50" cy="50" r="38" fill="none" stroke="<?php echo e($healthScore >= 70 ? '#22C55E' : ($healthScore >= 40 ? '#F59E0B' : '#EF4444')); ?>" stroke-width="8"
                                    stroke-dasharray="<?php echo e($healthScore * 2.38); ?> 238"
                                    stroke-linecap="round"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <h5 class="mb-0 fw-bold" style="color: #0F172A;"><?php echo e($healthScore); ?></h5>
                                <small class="text-muted" style="font-size: 10px;">SCORE</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-10">
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <div class="text-center p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                                    <i class="ti ti-shield-check fs-4 mb-1" style="color: <?php echo e($riskLevel == 'low' ? '#22C55E' : ($riskLevel == 'medium' ? '#F59E0B' : '#EF4444')); ?>;"></i>
                                    <h6 class="mb-1 fw-semibold" style="color: #0F172A;"><?php echo e(ucfirst($riskLevel)); ?> Risk</h6>
                                    <small class="text-muted">Risk Level</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="text-center p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                                    <i class="ti ti-calendar-check fs-4 mb-1" style="color: <?php echo e($scheduleStatus == 'on_track' ? '#22C55E' : ($scheduleStatus == 'ahead' ? '#4F46E5' : '#EF4444')); ?>;"></i>
                                    <h6 class="mb-1 fw-semibold" style="color: #0F172A;"><?php echo e(str_replace('_', ' ', ucwords($scheduleStatus, '_'))); ?></h6>
                                    <small class="text-muted">Schedule</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="text-center p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                                    <i class="ti ti-wallet fs-4 mb-1" style="color: <?php echo e($budgetStatus == 'within_budget' ? '#22C55E' : ($budgetStatus == 'near_limit' ? '#F59E0B' : '#EF4444')); ?>;"></i>
                                    <h6 class="mb-1 fw-semibold" style="color: #0F172A;"><?php echo e(str_replace('_', ' ', ucwords($budgetStatus, '_'))); ?></h6>
                                    <small class="text-muted">Budget</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="text-center p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                                    <i class="ti ti-checklist fs-4 mb-1" style="color: #4F46E5;"></i>
                                    <h6 class="mb-1 fw-semibold" style="color: #0F172A;"><?php echo e($activityProgress); ?>%</h6>
                                    <small class="text-muted">Activity</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- PROCUREMENT QUICK NAVIGATION (KPI CARDS) -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <h5 class="fw-bold mb-3" style="color: #0F172A;">
            <i class="ti ti-navigation me-2" style="color: #4F46E5;"></i>Procurement Quick Navigation
        </h5>
    </div>
    
    <!-- Indent -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
        <a href="<?php echo e(route('indent.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
            <div class="procurement-card text-center">
                <i class="ti ti-file-text fs-32 text-primary"></i>
                <h6 class="mt-2 fw-semibold">Indent</h6>
                <div class="mt-3">
                    <p class="mb-1 text-muted small">All</p>
                    <h5 class="fw-bold"><?php echo e($dashboardData['procurement']['indent']['total_indent'] ?? 0); ?></h5>
                    <p class="mb-1 text-muted small">Today</p>
                    <h6 class="fw-semibold text-primary"><?php echo e($dashboardData['procurement']['indent']['today_indent'] ?? 0); ?></h6>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Purchase Order -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
        <a href="<?php echo e(route('purchase-order.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
            <div class="procurement-card text-center">
                <i class="ti ti-shopping-cart fs-32" style="color: #F59E0B;"></i>
                <h6 class="mt-2 fw-semibold">Purchase Order</h6>
                <div class="mt-3">
                    <p class="mb-1 text-muted small">All</p>
                    <h5 class="fw-bold"><?php echo e($dashboardData['procurement']['po']['total_po'] ?? 0); ?></h5>
                    <p class="mb-1 text-muted small">Today</p>
                    <h6 class="fw-semibold text-primary"><?php echo e($dashboardData['procurement']['po']['today_po'] ?? 0); ?></h6>
                </div>
            </div>
        </a>
    </div>
    
    <!-- GRN -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
        <a href="<?php echo e(route('grn.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
            <div class="procurement-card text-center">
                <i class="ti ti-truck-delivery fs-32" style="color: #22C55E;"></i>
                <h6 class="mt-2 fw-semibold">GRN</h6>
                <div class="mt-3">
                    <p class="mb-1 text-muted small">All</p>
                    <h5 class="fw-bold"><?php echo e($dashboardData['procurement']['grn']['total_grn'] ?? 0); ?></h5>
                    <p class="mb-1 text-muted small">Today</p>
                    <h6 class="fw-semibold text-primary"><?php echo e($dashboardData['procurement']['grn']['today_grn'] ?? 0); ?></h6>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Invoice -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
        <a href="<?php echo e(route('purchase-invoice.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
            <div class="procurement-card text-center">
                <i class="ti ti-receipt fs-32" style="color: #EF4444;"></i>
                <h6 class="mt-2 fw-semibold">Invoice</h6>
                <div class="mt-3">
                    <p class="mb-1 text-muted small">All</p>
                    <h5 class="fw-bold"><?php echo e($dashboardData['procurement']['invoice']['total_invoice'] ?? 0); ?></h5>
                    <p class="mb-1 text-muted small">Today</p>
                    <h6 class="fw-semibold text-primary"><?php echo e($dashboardData['procurement']['invoice']['today_invoice'] ?? 0); ?></h6>
                </div>
            </div>
        </a>
    </div>
</div>

<style>
.procurement-card{
    border:1px solid #E5E7EB;
    border-radius:16px;
    padding:24px;
    background:#fff;
    height:100%;
    transition:all .25s ease;
    cursor:pointer;
}
.procurement-card:hover{
    border-color:var(--bs-primary);
    transform:translateY(-6px);
    box-shadow:0 20px 40px rgba(0,0,0,0.08);
}
</style>

<!-- 1B. OVERALL PROGRESS CARD -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-1" style="color: #0F172A;">
                            <i class="ti ti-chart-ring me-2" style="color: #4F46E5;"></i>Overall Project Progress
                        </h5>
                        <p class="text-muted mb-2">Weighted: Activity (60%) + Time (20%) + Budget (20%)</p>
                        <div class="d-flex gap-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary">Activity: <?php echo e($activityProgress); ?>%</span>
                            <span class="badge bg-info bg-opacity-10 text-info">Time: <?php echo e($timeProgress); ?>%</span>
                            <span class="badge bg-warning bg-opacity-10 text-warning">Budget: <?php echo e($spentPercent); ?>%</span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="position-relative d-inline-block" style="width: 100px; height: 100px;">
                            <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#E5E7EB" stroke-width="10"/>
                                <circle cx="50" cy="50" r="42" fill="none" stroke="<?php echo e($overallProgress >= 70 ? '#22C55E' : ($overallProgress >= 40 ? '#F59E0B' : '#EF4444')); ?>" stroke-width="10"
                                    stroke-dasharray="<?php echo e($overallProgress * 2.64); ?> 264"
                                    stroke-linecap="round"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <h4 class="mb-0 fw-bold" style="color: #0F172A;"><?php echo e($overallProgress); ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="progress" style="height: 12px; background: #E5E7EB; border-radius: 10px;">
                        <div class="progress-bar" role="progressbar" 
                            style="width: <?php echo e($overallProgress); ?>%; background: linear-gradient(90deg, <?php echo e($overallProgress >= 70 ? '#22C55E' : ($overallProgress >= 40 ? '#F59E0B' : '#EF4444')); ?>, <?php echo e($overallProgress >= 70 ? '#16A34A' : ($overallProgress >= 40 ? '#D97706' : '#DC2626')); ?>); border-radius: 10px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. PROJECT PROGRESS -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-1" style="color: #0F172A;">
                            <i class="ti ti-progress-check me-2" style="color: #4F46E5;"></i>Activities Progress
                        </h5>
                        <p class="text-muted mb-0"><?php echo e($dashboardData['activities']['completed_activities']); ?> of <?php echo e($dashboardData['activities']['total_activities']); ?> activities completed (<?php echo e($activityProgress); ?>%)</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="position-relative d-inline-block" style="width: 80px; height: 80px;">
                            <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                <circle cx="50" cy="50" r="38" fill="none" stroke="#E5E7EB" stroke-width="8"/>
                                <circle cx="50" cy="50" r="38" fill="none" stroke="#4F46E5" stroke-width="8"
                                    stroke-dasharray="<?php echo e($activityProgress * 2.38); ?> 238"
                                    stroke-linecap="round"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <h5 class="mb-0 fw-bold" style="color: #0F172A;"><?php echo e($activityProgress); ?>%</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="progress" style="height: 10px; background: #E5E7EB; border-radius: 10px;">
                        <div class="progress-bar" role="progressbar" 
                            style="width: <?php echo e($activityProgress); ?>%; background: linear-gradient(90deg, #4F46E5, #6366F1); border-radius: 10px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. PROCUREMENT PIPELINE -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-flow-merge me-2" style="color: #4F46E5;"></i>Procurement Pipeline
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3 align-items-center">
                    <!-- Indent -->
                    <div class="col-xl-2 col-md-6">
                        <a href="<?php echo e(route('indent.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
                            <div class="pipeline-card p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="p-2 rounded me-2" style="background: #EEF2FF;">
                                        <i class="ti ti-file-invoice" style="color: #4F46E5;"></i>
                                    </div>
                                    <span class="fw-semibold" style="color: #0F172A;">Indent</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Open</span>
                                    <span class="fw-bold" style="color: #F59E0B;"><?php echo e($dashboardData['procurement']['indent']['open_indent']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Closed</span>
                                    <span class="fw-bold" style="color: #22C55E;"><?php echo e($dashboardData['procurement']['indent']['closed_indent']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Today</span>
                                    <span class="fw-bold" style="color: #4F46E5;"><?php echo e($dashboardData['procurement']['indent']['today_indent']); ?></span>
                                </div>
                                <hr class="my-2" style="border-color: #E5E7EB;">
                                <div class="text-center">
                                    <span class="badge rounded-pill px-3 py-2" style="background: #4F46E5; color: white;"><?php echo e($dashboardData['procurement']['indent']['total_indent']); ?> Total</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Arrow -->
                    <div class="col-xl-1 col-12 text-center d-none d-xl-block">
                        <i class="ti ti-arrow-right fs-4" style="color: #64748B;"></i>
                    </div>

                    <!-- PO -->
                    <div class="col-xl-2 col-md-6">
                        <a href="<?php echo e(route('purchase-order.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
                            <div class="pipeline-card p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="p-2 rounded me-2" style="background: #FEF3C7;">
                                        <i class="ti ti-shopping-cart" style="color: #F59E0B;"></i>
                                    </div>
                                    <span class="fw-semibold" style="color: #0F172A;">Purchase Order</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Draft</span>
                                    <span class="fw-bold" style="color: #64748B;"><?php echo e($dashboardData['procurement']['po']['draft_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Pending</span>
                                    <span class="fw-bold" style="color: #F59E0B;"><?php echo e($dashboardData['procurement']['po']['pending_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Approved</span>
                                    <span class="fw-bold" style="color: #22C55E;"><?php echo e($dashboardData['procurement']['po']['approved_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Partial Rec.</span>
                                    <span class="fw-bold" style="color: #8B5CF6;"><?php echo e($dashboardData['procurement']['po']['partial_received_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Completed</span>
                                    <span class="fw-bold" style="color: #4F46E5;"><?php echo e($dashboardData['procurement']['po']['completed_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Rejected</span>
                                    <span class="fw-bold" style="color: #EF4444;"><?php echo e($dashboardData['procurement']['po']['rejected_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Flagged</span>
                                    <span class="fw-bold" style="color: #F97316;"><?php echo e($dashboardData['procurement']['po']['flagged_po']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Short Closed</span>
                                    <span class="fw-bold" style="color: #6B7280;"><?php echo e($dashboardData['procurement']['po']['short_closed_po']); ?></span>
                                </div>
                                <hr class="my-2" style="border-color: #E5E7EB;">
                                <div class="text-center">
                                    <span class="badge rounded-pill px-3 py-2" style="background: #F59E0B; color: white;"><?php echo e($dashboardData['procurement']['po']['total_po']); ?> Total</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Arrow -->
                    <div class="col-xl-1 col-12 text-center d-none d-xl-block">
                        <i class="ti ti-arrow-right fs-4" style="color: #64748B;"></i>
                    </div>

                    <!-- GRN -->
                    <div class="col-xl-2 col-md-6">
                        <a href="<?php echo e(route('grn.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
                            <div class="pipeline-card p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="p-2 rounded me-2" style="background: #DCFCE7;">
                                        <i class="ti ti-package" style="color: #22C55E;"></i>
                                    </div>
                                    <span class="fw-semibold" style="color: #0F172A;">GRN</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Pending</span>
                                    <span class="fw-bold" style="color: #F59E0B;"><?php echo e($dashboardData['procurement']['grn']['pending_grn']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Partial</span>
                                    <span class="fw-bold" style="color: #8B5CF6;"><?php echo e($dashboardData['procurement']['grn']['partial_grn']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Completed</span>
                                    <span class="fw-bold" style="color: #22C55E;"><?php echo e($dashboardData['procurement']['grn']['completed_grn']); ?></span>
                                </div>
                                <hr class="my-2" style="border-color: #E5E7EB;">
                                <div class="text-center">
                                    <span class="badge rounded-pill px-3 py-2" style="background: #22C55E; color: white;"><?php echo e($dashboardData['procurement']['grn']['total_grn']); ?> Total</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Arrow -->
                    <div class="col-xl-1 col-12 text-center d-none d-xl-block">
                        <i class="ti ti-arrow-right fs-4" style="color: #64748B;"></i>
                    </div>

                    <!-- Invoice -->
                    <div class="col-xl-2 col-md-6">
                        <a href="<?php echo e(route('purchase-invoice.index', ['site_id' => $project->id])); ?>" class="text-decoration-none">
                            <div class="pipeline-card p-3 rounded-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="p-2 rounded me-2" style="background: #FEE2E2;">
                                        <i class="ti ti-receipt" style="color: #EF4444;"></i>
                                    </div>
                                    <span class="fw-semibold" style="color: #0F172A;">Invoice</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Paid</span>
                                    <span class="fw-bold" style="color: #22C55E;"><?php echo e($dashboardData['procurement']['invoice']['paid_invoice']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Partially Paid</span>
                                    <span class="fw-bold" style="color: #8B5CF6;"><?php echo e($dashboardData['procurement']['invoice']['partially_paid_invoice']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Unpaid</span>
                                    <span class="fw-bold" style="color: #EF4444;"><?php echo e($dashboardData['procurement']['invoice']['unpaid_invoice']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Overdue</span>
                                    <span class="fw-bold" style="color: #F59E0B;"><?php echo e($dashboardData['procurement']['invoice']['overdue_invoice']); ?></span>
                                </div>
                                <hr class="my-2" style="border-color: #E5E7EB;">
                                <div class="text-center">
                                    <span class="badge rounded-pill px-3 py-2" style="background: #EF4444; color: white;"><?php echo e($dashboardData['procurement']['invoice']['total_invoice']); ?> Total</span>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted"><?php echo e(currency_format_with_sym($dashboardData['procurement']['invoice']['total_invoice_amount'])); ?></small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 4. CHARTS ROW -->
<div class="row g-4 mb-4">
    <!-- Monthly Spending -->
    <div class="col-xl-8">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-chart-bar me-2" style="color: #4F46E5;"></i>Monthly Spending vs Budget
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="monthlySpendingChart" style="height: 320px;"></div>
            </div>
        </div>
    </div>

    <!-- Budget Donut -->
    <div class="col-xl-4">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-chart-pie me-2" style="color: #4F46E5;"></i>Budget Consumption
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="budgetDonutChart" style="height: 320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 5. WORKFORCE & MATERIAL -->
<div class="row g-4 mb-4">
    <!-- Workforce -->
    <div class="col-xl-6">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-users me-2" style="color: #4F46E5;"></i>Workforce Analytics
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                            <i class="ti ti-users fs-3 mb-2" style="color: #4F46E5;"></i>
                            <h4 class="mb-0 fw-bold counter" data-target="<?php echo e($dashboardData['manpower']['total_workers_today']); ?>" style="color: #0F172A;"><?php echo e($dashboardData['manpower']['total_workers_today']); ?></h4>
                            <small class="text-muted">Workers Today</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                            <i class="ti ti-file-text fs-3 mb-2" style="color: #22C55E;"></i>
                            <h4 class="mb-0 fw-bold" style="color: #0F172A;"><?php echo e($dashboardData['manpower']['manpower_records_today']); ?></h4>
                            <small class="text-muted">Records Today</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                            <i class="ti ti-user-check fs-3 mb-2" style="color: #F59E0B;"></i>
                            <h4 class="mb-0 fw-bold" style="color: #0F172A;"><?php echo e($dashboardData['manpower']['total_contractors']); ?></h4>
                            <small class="text-muted">Contractors</small>
                        </div>
                    </div>
                </div>
                <div id="manpowerTrendChart" style="height: 180px;"></div>
            </div>
        </div>
    </div>

    <!-- Material -->
    <div class="col-xl-6">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-packages me-2" style="color: #4F46E5;"></i>Material Consumption
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="text-center mb-3">
                    <span class="badge rounded-pill px-4 py-2" style="background: #4F46E5; color: white; font-size: 14px;">
                        Today: <?php echo e(currency_format_with_sym($dashboardData['consumption']['total_consumption_today'])); ?>

                    </span>
                </div>
                <div id="materialConsumptionChart" style="height: 250px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 6. TRANSFERS & ALERTS -->
<div class="row g-4 mb-4">
    <!-- Transfers -->
    <div class="col-xl-4">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-transfer me-2" style="color: #4F46E5;"></i>Material Transfers
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-4 rounded-3 text-center" style="background: #FEF3C7; border: 1px solid #FCD34D;">
                            <i class="ti ti-arrow-left fs-2 mb-2" style="color: #F59E0B;"></i>
                            <h3 class="mb-0 fw-bold counter" data-target="<?php echo e($dashboardData['transfers']['materials_transferred_out']); ?>" style="color: #0F172A;"><?php echo e($dashboardData['transfers']['materials_transferred_out']); ?></h3>
                            <small class="text-muted">Transferred Out</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 rounded-3 text-center" style="background: #DBEAFE; border: 1px solid #93C5FD;">
                            <i class="ti ti-arrow-right fs-2 mb-2" style="color: #3B82F6;"></i>
                            <h3 class="mb-0 fw-bold counter" data-target="<?php echo e($dashboardData['transfers']['materials_transferred_in']); ?>" style="color: #0F172A;"><?php echo e($dashboardData['transfers']['materials_transferred_in']); ?></h3>
                            <small class="text-muted">Transferred In</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($alerts) > 0): ?>
    <div class="col-xl-8">
        <div class="card border-0 h-100" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-alert-triangle me-2" style="color: #F59E0B;"></i>Alerts & Warnings
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $alerts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 d-flex align-items-center" style="background: <?php echo e($alert['type'] == 'danger' ? '#FEF2F2' : ($alert['type'] == 'warning' ? '#FFFBEB' : '#EFF6FF')); ?>; border: 1px solid <?php echo e($alert['type'] == 'danger' ? '#FECACA' : ($alert['type'] == 'warning' ? '#FDE68A' : '#BFDBFE')); ?>;">
                            <div class="p-2 rounded me-3" style="background: white;">
                                <i class="ti <?php echo e($alert['icon']); ?> fs-5" style="color: <?php echo e($alert['type'] == 'danger' ? '#EF4444' : ($alert['type'] == 'warning' ? '#F59E0B' : '#3B82F6')); ?>;"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-semibold" style="color: #0F172A;"><?php echo e($alert['title']); ?></h6>
                                <small class="text-muted"><?php echo e($alert['message']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<!-- 7. RECENT ACTIVITY TIMELINE -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0" style="border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
            <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #0F172A;">
                    <i class="ti ti-history me-2" style="color: #4F46E5;"></i>Recent Activity Timeline
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="activity-timeline">
                    <?php
                    $allRecent = collect()
                        ->concat($dashboardData['recent']['recent_invoices']->take(3)->map(function($i) { return is_array($i) ? array_merge($i, ['type' => 'invoice']) : array_merge($i->toArray(), ['type' => 'invoice']); }))
                        ->concat($dashboardData['recent']['recent_activities']->take(3)->map(function($i) { return is_array($i) ? array_merge($i, ['type' => 'activity']) : array_merge($i->toArray(), ['type' => 'activity']); }))
                        ->concat($dashboardData['recent']['recent_manpower']->take(3)->map(function($i) { return is_array($i) ? array_merge($i, ['type' => 'manpower']) : array_merge($i->toArray(), ['type' => 'manpower']); }))
                        ->sortByDesc('created_at')
                        ->take(8);
                    ?>
                    <div class="row">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $allRecent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="d-flex align-items-start p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E5E7EB;">
                                <div class="p-2 rounded me-3" style="background: white;">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($item['type'] ?? '') === 'invoice'): ?>
                                    <i class="ti ti-receipt" style="color: #22C55E;"></i>
                                    <?php elseif(($item['type'] ?? '') === 'activity'): ?>
                                    <i class="ti ti-activity" style="color: #4F46E5;"></i>
                                    <?php else: ?>
                                    <i class="ti ti-users" style="color: #F59E0B;"></i>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($item['type'] ?? '') === 'invoice'): ?>
                                    <h6 class="mb-0 fw-semibold" style="color: #0F172A;"><?php echo e($item['invoice_number'] ?? ''); ?></h6>
                                    <span class="badge bg-<?php echo e($item['payment_status'] === 'paid' ? 'success' : 'secondary'); ?>"><?php echo e(ucfirst($item['payment_status'] ?? '')); ?></span>
                                    <?php elseif(($item['type'] ?? '') === 'activity'): ?>
                                    <h6 class="mb-0 fw-semibold" style="color: #0F172A;"><?php echo e($item['title'] ?? ''); ?></h6>
                                    <span class="badge bg-<?php echo e($item['status'] === 'completed' ? 'success' : 'warning'); ?>"><?php echo e(ucfirst($item['status'] ?? '')); ?></span>
                                    <?php else: ?>
                                    <h6 class="mb-0 fw-semibold" style="color: #0F172A;"><?php echo e($item['total_count'] ?? 0); ?> Workers</h6>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <small class="text-muted d-block"><?php echo e(isset($item['created_at']) ? \Carbon\Carbon::parse($item['created_at'])->diffForHumans() : ''); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="col-12 text-center py-4">
                            <i class="ti ti-inbox fs-1" style="color: #64748B;"></i>
                            <p class="text-muted mb-0">No recent activity</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     STYLES & SCRIPTS
     ============================================ -->
<?php $__env->startPush('css'); ?>
<style>
    :root {
        --primary: #4F46E5;
        --secondary: #6366F1;
        --success: #22C55E;
        --warning: #F59E0B;
        --danger: #EF4444;
        --bg: #F8FAFC;
        --card-bg: #FFFFFF;
        --border: #E5E7EB;
        --text-primary: #0F172A;
        --text-secondary: #64748B;
    }
    body { background: #F8FAFC; }

    /* Pipeline Card Hover Effects */
    .pipeline-card {
        background: #F8FAFC;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        transition: all 0.25s ease;
        cursor: pointer;
    }

    .pipeline-card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    .pipeline-card .badge {
        font-weight: 600;
    }

    /* Procurement Quick Navigation Card */
    .procurement-card {
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        padding: 24px;
        background: #fff;
        height: 100%;
        transition: all 0.25s ease;
    }

    .procurement-card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    }

    .procurement-card .fs-32 {
        font-size: 32px;
    }

    .procurement-card .btn {
        border-radius: 8px;
        font-weight: 500;
    } }
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #E5E7EB !important;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
    }
    h1, h2, h3, h4, h5, h6 { color: #0F172A; }
    .text-muted { color: #64748B !important; }
    /* Make small text more readable */
    small, .small {
        font-size: 0.875rem !important;
        line-height: 1.4;
    }
    /* Smooth progress bar */
    .progress-bar {
        transition: width 1s ease-in-out;
    }
    /* Counter animation */
    @keyframes countUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .counter {
        animation: countUp 0.5s ease forwards;
    }
    /* Mobile responsive */
    @media (max-width: 768px) {
        .card-body { padding: 1rem !important; }
        h2 { font-size: 1.5rem !important; }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="<?php echo e(asset('assets/js/plugins/apexcharts.min.js')); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Counter animation with currency symbol support
    document.querySelectorAll('.counter').forEach(function(el) {
        const target = parseFloat(el.dataset.target) || 0;
        const text = el.textContent.trim();
        // Extract currency symbol from text if present
        const match = text.match(/^([^0-9]*)\s*/);
        const prefix = match ? match[1] : '<?php echo e(company_setting("defult_currancy")); ?> ';
        
        if(target > 0) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(function() {
                current += increment;
                if(current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = prefix + Math.floor(current).toLocaleString('en-IN');
            }, 40);
        }
    });

    // Chart Colors
    const colors = {
        primary: '#4F46E5',
        secondary: '#6366F1',
        success: '#22C55E',
        warning: '#F59E0B',
        danger: '#EF4444',
        info: '#3B82F6',
        gray: '#64748B'
    };

    // Monthly Spending Chart
    const monthlyData = <?php echo json_encode($dashboardData['charts']['monthly_spending'], 15, 512) ?>;
    const monthlyBudget = <?php echo e($dashboardData['charts']['monthly_budget']); ?>;
    
    new ApexCharts(document.querySelector("#monthlySpendingChart"), {
        series: [
            { name: 'Spent', data: monthlyData.map(i => parseFloat(i.total)) },
            { name: 'Budget', data: Array(monthlyData.length).fill(monthlyBudget) }
        ],
        chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif' },
        plotOptions: { bar: { borderRadius: 8, columnWidth: '55%', dataLabels: { position: 'top' } } },
        colors: [colors.primary, colors.success],
        dataLabels: { enabled: false },
        xaxis: { categories: monthlyData.map(i => i.month), labels: { style: { colors: '#64748B' } } },
        yaxis: { labels: { style: { colors: '#64748B' }, formatter: (val) => '₹ ' + val.toLocaleString('en-IN') } },
        legend: { position: 'top', horizontalAlign: 'right' },
        grid: { borderColor: '#E5E7EB' },
        stroke: { curve: 'smooth', width: 2 }
    }).render();

    // Budget Donut Chart
    const budgetSpent = <?php echo e($dashboardData['project']['total_spent']); ?>;
    const budgetRemain = Math.max(0, <?php echo e($dashboardData['project']['remaining_budget']); ?>);
    
    new ApexCharts(document.querySelector("#budgetDonutChart"), {
        series: [budgetSpent, budgetRemain],
        labels: ['Spent', 'Remaining'],
        chart: { type: 'donut', height: 320, fontFamily: 'Inter, system-ui, sans-serif' },
        colors: [colors.primary, '#E5E7EB'],
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: { show: true }, value: { show: true, formatter: (val) => '₹ ' + parseInt(val).toLocaleString('en-IN') } } } } },
        legend: { position: 'bottom' }
    }).render();

    // Manpower Trend Chart
    const manpowerData = <?php echo json_encode($dashboardData['manpower']['manpower_trend'], 15, 512) ?>;
    
    new ApexCharts(document.querySelector("#manpowerTrendChart"), {
        series: [{ name: 'Workers', data: manpowerData.map(i => i.total_workers) }],
        chart: { type: 'area', height: 180, toolbar: { show: false }, sparkline: { enabled: false }, fontFamily: 'Inter, system-ui, sans-serif' },
        stroke: { curve: 'smooth', width: 2, colors: [colors.primary] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
        colors: [colors.primary],
        xaxis: { categories: manpowerData.map(i => i.work_date), labels: { style: { colors: '#64748B' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { show: true, style: { colors: '#64748B' } } },
        grid: { borderColor: '#E5E7EB' }
    }).render();

    // Material Consumption Chart
    const consumptionData = <?php echo json_encode($dashboardData['consumption']['top_consumed_materials'], 15, 512) ?>;
    const matNames = consumptionData.map(i => i.material ? i.material.name : 'Unknown');
    const matQuantities = consumptionData.map(i => parseFloat(i.total_quantity));
    
    new ApexCharts(document.querySelector("#materialConsumptionChart"), {
        series: matQuantities.length > 0 ? matQuantities : [1],
        labels: matNames.length > 0 ? matNames : ['No Data'],
        chart: { type: 'donut', height: 250, fontFamily: 'Inter, system-ui, sans-serif' },
        colors: [colors.primary, colors.secondary, colors.success, colors.warning, colors.danger],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, name: { show: true, fontSize: '14px' }, value: { show: true, fontSize: '16px', fontWeight: 600 } } } } }
    }).render();
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\wamp64\www\SitePilot\packages\workdo\Taskly\src\Providers/../Resources/views/projects/dashboard-modern.blade.php ENDPATH**/ ?>