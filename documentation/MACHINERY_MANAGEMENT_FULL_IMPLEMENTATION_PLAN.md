# Machinery Management System - Full Implementation Plan

This plan implements a complete ledger-based machinery management system with financial control, transforming the current operational tracking system into a financial control system with auto-generated ledgers, deduction logic, and payment workflows.

## Overview

**Total Timeline:** 6-9 weeks
**Architecture Change:** Event-based (current) → Ledger-based (target)
**Critical Path:** Ledger creation → Calculation engine → Deduction logic → Payment flow

---

## Phase 1: Critical Foundation (Weeks 1-3)

### Goal
Establish machinery ledger as source of truth and implement calculation engine.

### 1.1 Database Migrations

**Migration 1.1: Add Rate & Compliance Fields to Machineries**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_rate_and_compliance_to_machineries.php
- Add rate_type enum ('hourly', 'daily', 'monthly')
- Add hourly_rate (decimal, nullable)
- Add daily_rate (decimal, nullable)
- Add monthly_rate (decimal, nullable)
- Add billing_rules (json, nullable)
- Add operator_id (foreignId, nullable)
- Add insurance_expiry_date (date, nullable)
- Add puc_expiry_date (date, nullable)
- Add last_service_date (date, nullable)
- Add next_service_date (date, nullable)
```

**Migration 1.2: Add Idle Hours to Daily Progress Reports**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_idle_hours_to_daily_progress_reports.php
- Add idle_hours (decimal, nullable)
- Add gross_amount (decimal, nullable)
- Add billable_hours (decimal, nullable)
```

**Migration 1.3: Create Machinery Ledger Table**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_machinery_ledger_table.php
Table: machinery_ledger
- id (bigIncrements)
- machinery_id (foreignId → machineries)
- entry_type (enum: 'reading_credit', 'diesel_debit', 'maintenance_debit', 'advance_debit', 'payment_credit', 'transfer_debit')
- reference_type (string: 'DailyProgressReport', 'DailyConsumption', 'MaintenanceLog', 'MachineryPayment', 'GeneralTransfer')
- reference_id (unsignedBigInteger)
- amount (decimal, 10, 2)
- running_balance (decimal, 10, 2)
- date (date)
- description (text, nullable)
- metadata (json, nullable)
- created_at, updated_at

Indexes:
- machinery_id
- entry_type
- date
- (machinery_id, date)
- (reference_type, reference_id)
```

### 1.2 Service Classes

**Service 1.1: MachineryLedgerService**
```php
// app/Services/MachineryLedgerService.php
Methods:
- createCreditEntry(machinery_id, amount, reference_type, reference_id, description, date)
- createDebitEntry(machinery_id, amount, reference_type, reference_id, description, date)
- calculateRunningBalance(machinery_id)
- getLedgerBalance(machinery_id, as_of_date = null)
- getLedgerEntries(machinery_id, start_date, end_date)
- backfillHistoricalEntries()
```

**Service 1.2: MachineryCalculationService**
```php
// app/Services/MachineryCalculationService.php
Methods:
- calculateBillableHours(start_reading, end_reading, idle_hours)
- calculateGrossAmount(billable_hours, rate, rate_type)
- calculateRate(machinery, rate_type)
- calculateNetPayable(gross, diesel_deduction, maintenance_deduction, advance_deduction)
```

### 1.3 Model Updates

**Model 1.1: Machinery**
```php
// app/Models/Machinery.php
Add:
- rate_type, hourly_rate, daily_rate, monthly_rate, billing_rules
- operator_id relationship
- compliance dates
- ledger() relationship
- getEffectiveRateAttribute() accessor
- isCompliant() method
```

**Model 1.2: DailyProgressReport**
```php
// app/Models/DailyProgressReport.php
Add:
- idle_hours, gross_amount, billable_hours
- getBillableHoursAttribute() accessor (update logic)
- ledgerEntries() relationship
```

**Model 1.3: MachineryLedger**
```php
// app/Models/MachineryLedger.php (new)
- machinery() relationship
- reference() polymorphic relationship
- scopeCredit(), scopeDebit()
- scopeForMachine(), scopeForPeriod()
```

### 1.4 Controller Updates

**Controller 1.1: DailyProgressReportController**
```php
// Update store() method:
- Calculate billable_hours = (end - start) - idle
- Fetch machinery rate
- Calculate gross_amount = billable_hours × rate
- Save calculated fields
- Call MachineryLedgerService::createCreditEntry()

// Update update() method:
- Recalculate billable_hours, gross_amount
- Update ledger entry (delete old, create new)
```

### 1.5 Data Backfill

**Backfill Script 1.1: Historical DPR Calculations**
```php
// database/seeders/BackfillMachineryCalculations.php
- Iterate all existing DailyProgressReport records
- Calculate billable_hours (assuming idle_hours = 0 for historical)
- Calculate gross_amount (using default rate or 0)
- Update records
- Create ledger credit entries
```

**Backfill Script 1.2: Default Machinery Rates**
```php
// database/seeders/BackfillMachineryRates.php
- Set default hourly_rate = 0 for all machines
- Set rate_type = 'hourly' for all
- Admin can update rates later
```

### 1.6 Testing

**Test 1.1: Ledger Service Unit Tests**
```php
// tests/Unit/MachineryLedgerServiceTest.php
- Test credit entry creation
- Test debit entry creation
- Test running balance calculation
- Test ledger balance retrieval
```

**Test 1.2: Calculation Service Unit Tests**
```php
// tests/Unit/MachineryCalculationServiceTest.php
- Test billable hours calculation
- Test gross amount calculation (hourly, daily, monthly)
- Test net payable calculation
```

**Test 1.3: Integration Test**
```php
// tests/Feature/MachineryLedgerIntegrationTest.php
- Test DPR creation → ledger entry
- Test ledger balance accuracy
- Test historical backfill
```

---

## Phase 2: Deduction Logic & Payment Flow (Weeks 4-6)

### Goal
Implement diesel/maintenance deduction logic and ledger-driven payment requests.

### 2.1 Database Migrations

**Migration 2.1: Create Maintenance Log Table**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_maintenance_logs_table.php
Table: maintenance_logs
- id (bigIncrements)
- machinery_id (foreignId → machineries)
- vendor_id (foreignId → suppliers, nullable)
- cost (decimal, 10, 2)
- maintenance_date (date)
- paid_by (enum: 'company', 'supplier')
- description (text, nullable)
- attachment (string, nullable)
- site_id (foreignId → projects)
- workspace_id
- created_by
- status (tinyInteger)
- created_at, updated_at

Indexes:
- machinery_id
- vendor_id
- maintenance_date
```

**Migration 2.2: Add Diesel Fields to Daily Consumption**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_diesel_fields_to_daily_consumption_masters.php
- Add supplier_id (foreignId → suppliers, nullable)
- Add rate (decimal, 10, 2, nullable)
- Add bill_number (string, nullable)
- Add diesel_source (enum: 'company', 'supplier')
```

**Migration 2.3: Add Cost to General Transfer**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_cost_to_general_transfer.php
- Add transport_cost (decimal, 10, 2, nullable)
```

**Migration 2.4: Create Machinery Payment Request Table**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_machinery_payment_requests_table.php
Table: machinery_payment_requests
- id (bigIncrements)
- machinery_id (foreignId → machineries)
- supplier_id (foreignId → suppliers)
- period_start (date)
- period_end (date)
- gross_amount (decimal, 10, 2)
- diesel_deduction (decimal, 10, 2)
- maintenance_deduction (decimal, 10, 2)
- advance_deduction (decimal, 10, 2)
- transport_deduction (decimal, 10, 2)
- net_payable (decimal, 10, 2)
- status (enum: 'pending', 'site_approved', 'pm_approved', 'admin_approved', 'accounts_approved', 'rejected', 'paid')
- requested_by (foreignId → users)
- site_approved_by (foreignId → users, nullable)
- site_approved_at (datetime, nullable)
- pm_approved_by (foreignId → users, nullable)
- pm_approved_at (datetime, nullable)
- admin_approved_by (foreignId → users, nullable)
- admin_approved_at (datetime, nullable)
- accounts_approved_by (foreignId → users, nullable)
- accounts_approved_at (datetime, nullable)
- paid_at (datetime, nullable)
- remarks (text, nullable)
- rejection_reason (text, nullable)
- workspace_id
- created_at, updated_at

Indexes:
- machinery_id
- supplier_id
- status
- period_start, period_end
```

### 2.2 Service Classes

**Service 2.1: DieselDeductionService**
```php
// app/Services/DieselDeductionService.php
Methods:
- calculateDieselDeduction(consumption_records)
- applyDieselDeduction(machinery_id, period_start, period_end)
- isCompanyDiesel(consumption_record)
- isSupplierDiesel(consumption_record)
```

**Service 2.2: MaintenanceDeductionService**
```php
// app/Services/MaintenanceDeductionService.php
Methods:
- calculateMaintenanceDeduction(maintenance_records)
- applyMaintenanceDeduction(machinery_id, period_start, period_end)
- isCompanyPaid(maintenance_record)
- isSupplierPaid(maintenance_record)
```

**Service 2.3: MachineryPaymentRequestService**
```php
// app/Services/MachineryPaymentRequestService.php
Methods:
- createFromLedger(machinery_id, supplier_id, period_start, period_end)
- calculateNetPayable(machinery_id, period_start, period_end)
- validateLedgerBalance(machinery_id, as_of_date)
- approveAtSite(request_id, user_id)
- approveAtPM(request_id, user_id)
- approveAtAdmin(request_id, user_id)
- approveAtAccounts(request_id, user_id)
- reject(request_id, reason)
- markAsPaid(request_id)
- syncToSupplierLedger(request_id)
```

**Service 2.4: MachineryLedgerService (Updates)**
```php
// Add methods:
- createDieselDebitEntry(consumption_record)
- createMaintenanceDebitEntry(maintenance_record)
- createAdvanceDebitEntry(machinery_id, amount, reference)
- createPaymentCreditEntry(payment_request)
- createTransferDebitEntry(transfer_record)
```

### 2.3 Model Updates

**Model 2.1: MaintenanceLog (New)**
```php
// app/Models/MaintenanceLog.php
- machinery() relationship
- vendor() relationship
- site() relationship
- creator() relationship
- ledgerEntries() relationship
```

**Model 2.2: DailyConsumptionMaster**
```php
// Update:
- Add supplier_id, rate, bill_number, diesel_source
- supplier() relationship
- isCompanyDiesel() method
- isSupplierDiesel() method
```

**Model 2.3: GeneralTransfer**
```php
// Update:
- Add transport_cost
```

**Model 2.4: MachineryPaymentRequest (New)**
```php
// app/Models/MachineryPaymentRequest.php
- machinery() relationship
- supplier() relationship
- requestedBy() relationship
- approvalWorkflow relationships
- ledgerEntries() relationship
- Status scopes
- Approval methods
```

### 2.4 Controller Updates

**Controller 2.1: DailyConsumptionController**
```php
// Update store() method:
- Handle diesel_source, supplier_id, rate, bill_number
- If diesel_source = 'company', call MachineryLedgerService::createDieselDebitEntry()
```

**Controller 2.2: MaintenanceLogController (New)**
```php
// app/Http/Controllers/MaintenanceLogController.php
- CRUD operations
- On save: if paid_by = 'company', create ledger debit entry
```

**Controller 2.3: GeneralTransferController**
```php
// Update store() method:
- Handle transport_cost
- If cost > 0, create ledger debit entry
- Optionally auto-generate payment request for transport
```

**Controller 2.4: MachineryPaymentRequestController (New)**
```php
// app/Http/Controllers/MachineryPaymentRequestController.php
- index() - list requests
- create() - show creation form (machine, supplier, period selection)
- store() - call MachineryPaymentRequestService::createFromLedger()
- show() - show request details with ledger breakdown
- approveSite() - approve at site level
- approvePM() - approve at PM level
- approveAdmin() - approve at admin level
- approveAccounts() - approve at accounts level
- reject() - reject with reason
- markPaid() - mark as paid, sync to supplier ledger
```

### 2.5 Supplier Ledger Integration

**Integration 2.1: Update Supplier Ledger on Payment**
```php
// In MachineryPaymentRequestService::markAsPaid()
- When status = 'paid':
  - Calculate net amount paid
  - Call existing SupplierLedgerService to create credit entry for supplier
  - Reference: machinery_payment_request_id
```

### 2.6 Data Backfill

**Backfill Script 2.1: Diesel Source Classification**
```php
// database/seeders/BackfillDieselSource.php
- For existing diesel consumption:
  - If supplier_id is set → diesel_source = 'supplier'
  - If supplier_id is null → diesel_source = 'company'
- Set default rate = 0
```

**Backfill Script 2.2: Maintenance Data Migration**
```php
// database/seeders/BackfillMaintenanceData.php
- Extract maintenance notes from DailyProgressReport.maintenance_notes
- Create MaintenanceLog records
- Parse cost if mentioned in notes (manual review needed)
- Set paid_by = 'company' by default
```

### 2.7 Testing

**Test 2.1: Deduction Logic Tests**
```php
// tests/Unit/DieselDeductionServiceTest.php
- Test company diesel deduction
- Test supplier diesel (no deduction)
- Test mixed period calculation

// tests/Unit/MaintenanceDeductionServiceTest.php
- Test company-paid maintenance deduction
- Test supplier-paid (no deduction)
```

**Test 2.2: Payment Request Tests**
```php
// tests/Feature/MachineryPaymentRequestTest.php
- Test creation from ledger
- Test auto-calculation of deductions
- Test approval workflow
- Test supplier ledger sync on payment
```

---

## Phase 3: Enhancements & UI (Weeks 7-9)

### Goal
Complete UI, reports, alerts, and compliance features.

### 3.1 Database Migrations

**Migration 3.1: Create Machine Types Table**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_machine_types_table.php
Table: machine_types
- id (bigIncrements)
- name (string)
- description (text, nullable)
- default_hourly_rate (decimal, nullable)
- default_daily_rate (decimal, nullable)
- default_monthly_rate (decimal, nullable)
- status (tinyInteger)
- workspace_id
- created_by
- created_at, updated_at
```

**Migration 3.2: Add Machine Type to Machineries**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_machine_type_to_machineries.php
- Add machine_type_id (foreignId → machine_types)
```

**Migration 3.3: Create Alerts Table**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_machinery_alerts_table.php
Table: machinery_alerts
- id (bigIncrements)
- machinery_id (foreignId → machineries)
- alert_type (enum: 'insurance_expiry', 'puc_expiry', 'service_due')
- alert_date (date)
- is_resolved (boolean, default false)
- resolved_at (datetime, nullable)
- resolved_by (foreignId → users, nullable)
- notes (text, nullable)
- created_at, updated_at
```

### 3.2 Service Classes

**Service 3.1: MachineryAlertService**
```php
// app/Services/MachineryAlertService.php
Methods:
- checkInsuranceExpiry() - check 30 days before expiry
- checkPUCExpiry() - check 30 days before expiry
- checkServiceDue() - check based on last_service_date + interval
- createAlert(machinery_id, alert_type, alert_date)
- resolveAlert(alert_id, user_id, notes)
- getActiveAlerts(machinery_id)
- sendAlertNotifications()
```

**Service 3.2: MachineryReportService**
```php
// app/Services/MachineryReportService.php
Methods:
- generateCostReport(machinery_id, start_date, end_date)
- generateUsageReport(machinery_id, start_date, end_date)
- generateProductivityReport(machinery_id, start_date, end_date)
- generateOutstandingReport(as_of_date)
- generateSupplierWiseReport(supplier_id, start_date, end_date)
```

### 3.3 Model Updates

**Model 3.1: MachineType (New)**
```php
// app/Models/MachineType.php
- machineries() relationship
```

**Model 3.2: Machinery**
```php
// Update:
- Add machine_type_id relationship
- alerts() relationship
```

**Model 3.3: MachineryAlert (New)**
```php
// app/Models/MachineryAlert.php
- machinery() relationship
- resolver() relationship
```

### 3.4 Controller Updates

**Controller 3.1: MachineTypeController (New)**
```php
// app/Http/Controllers/MachineTypeController.php
- CRUD operations
```

**Controller 3.2: MachineryAlertController (New)**
```php
// app/Http/Controllers/MachineryAlertController.php
- index() - list alerts
- resolve() - mark alert as resolved
- sendTestNotification() - test alert system
```

**Controller 3.3: MachineryReportController (New)**
```php
// app/Http/Controllers/MachineryReportController.php
- costReport()
- usageReport()
- productivityReport()
- outstandingReport()
- exportReport()
```

**Controller 3.4: MachineryController (Enhancements)**
```php
// Update show() method:
- Display as control center with tabs:
  - Overview
  - Daily Readings (with ledger impact)
  - Diesel Log
  - Maintenance Log
  - Ledger (real-time balance)
  - Transfers
  - Compliance (alerts)
  - Payment Requests
```

### 3.5 UI Updates

**View 3.1: Machinery Detail (Control Center)**
```php
// resources/views/machineries/show.blade.php
- Tab-based layout
- Real-time ledger balance display
- Chart for usage trends
- Alert notifications
- Quick actions (create DPR, create maintenance, create payment request)
```

**View 3.2: DPR Create/Edit (Enhanced)**
```php
// resources/views/daily-progress-reports/create.blade.php
- Auto-calculate billable hours on input change
- Display machinery rate
- Show calculated gross amount
- Idle hours input
- Ledger impact preview
```

**View 3.3: Diesel Consumption Create/Edit**
```php
// resources/views/daily-consumption/create.blade.php
- Supplier selection
- Rate input
- Bill number input
- Diesel source selection (company/supplier)
- Show deduction impact
```

**View 3.4: Maintenance Log Create/Edit**
```php
// resources/views/maintenance-logs/create.blade.php
- Vendor selection
- Cost input
- Paid by selection (company/supplier)
- File upload for bills
- Show deduction impact
```

**View 3.5: Machinery Payment Request**
```php
// resources/views/machinery-payment-requests/
- create.blade.php - machine, supplier, period selection
- show.blade.php - ledger breakdown, approval workflow
- approval.blade.php - approval interface
```

**View 3.6: Reports**
```php
// resources/views/machinery-reports/
- cost.blade.php
- usage.blade.php
- productivity.blade.php
- outstanding.blade.php
- Export buttons (PDF, Excel)
```

### 3.6 Alert System

**Scheduled Task 3.1: Daily Alert Check**
```php
// app/Console/Commands/CheckMachineryAlerts.php
- Run daily via scheduler
- Check insurance, PUC, service due
- Create alert records
- Send notifications to relevant users
```

**Scheduler Entry:**
```php
// app/Console/Kernel.php
$schedule->command('machinery:check-alerts')->daily();
```

### 3.7 Routes

**Route Group 3.1: Machinery Payment Requests**
```php
// routes/web.php
Route::resource('machinery-payment-requests', MachineryPaymentRequestController::class);
Route::post('machinery-payment-requests/{id}/approve-site', [MachineryPaymentRequestController::class, 'approveSite']);
Route::post('machinery-payment-requests/{id}/approve-pm', [MachineryPaymentRequestController::class, 'approvePM']);
Route::post('machinery-payment-requests/{id}/approve-admin', [MachineryPaymentRequestController::class, 'approveAdmin']);
Route::post('machinery-payment-requests/{id}/approve-accounts', [MachineryPaymentRequestController::class, 'approveAccounts']);
Route::post('machinery-payment-requests/{id}/reject', [MachineryPaymentRequestController::class, 'reject']);
Route::post('machinery-payment-requests/{id}/mark-paid', [MachineryPaymentRequestController::class, 'markPaid']);
```

**Route Group 3.2: Maintenance Logs**
```php
Route::resource('maintenance-logs', MaintenanceLogController::class);
```

**Route Group 3.3: Machine Types**
```php
Route::resource('machine-types', MachineTypeController::class);
```

**Route Group 3.4: Alerts**
```php
Route::get('machinery-alerts', [MachineryAlertController::class, 'index']);
Route::post('machinery-alerts/{id}/resolve', [MachineryAlertController::class, 'resolve']);
```

**Route Group 3.5: Reports**
```php
Route::get('machinery-reports/cost', [MachineryReportController::class, 'costReport']);
Route::get('machinery-reports/usage', [MachineryReportController::class, 'usageReport']);
Route::get('machinery-reports/productivity', [MachineryReportController::class, 'productivityReport']);
Route::get('machinery-reports/outstanding', [MachineryReportController::class, 'outstandingReport']);
```

### 3.8 Permissions

**Permission Seeding 3.1: Add New Permissions**
```php
// database/seeders/PermissionTableSeeder.php
Add:
- 'machinery-payment-request manage'
- 'machinery-payment-request create'
- 'machinery-payment-request approve-site'
- 'machinery-payment-request approve-pm'
- 'machinery-payment-request approve-admin'
- 'machinery-payment-request approve-accounts'
- 'maintenance-log manage'
- 'machine-type manage'
- 'machinery-alert manage'
- 'machinery-report view'
```

### 3.9 Testing

**Test 3.1: Alert System Tests**
```php
// tests/Feature/MachineryAlertTest.php
- Test insurance expiry alert creation
- Test PUC expiry alert creation
- Test service due alert creation
- Test alert resolution
- Test notification sending
```

**Test 3.2: Report Generation Tests**
```php
// tests/Feature/MachineryReportTest.php
- Test cost report generation
- Test usage report generation
- Test productivity report generation
- Test outstanding report generation
- Test export functionality
```

**Test 3.3: End-to-End Integration Test**
```php
// tests/Feature/MachineryE2ETest.php
- Test full workflow:
  1. Create machine with rate
  2. Create DPR → ledger credit
  3. Add diesel (company) → ledger debit
  4. Add maintenance (company-paid) → ledger debit
  5. Create payment request → auto-calculate
  6. Approve workflow
  7. Mark paid → supplier ledger sync
  8. Verify all balances
```

---

## Implementation Order & Dependencies

### Critical Path
1. **Phase 1.1-1.3** (Migrations) → Must be first
2. **Phase 1.4** (Services) → Depends on migrations
3. **Phase 1.5** (Models) → Depends on migrations
4. **Phase 1.6** (Controller updates) → Depends on services/models
5. **Phase 1.7** (Backfill) → After controller updates
6. **Phase 2** → Depends on Phase 1 completion
7. **Phase 3** → Depends on Phase 2 completion

### Parallel Work Opportunities
- Phase 1.6 (Testing) can run parallel to Phase 1.7 (Backfill)
- Phase 3.1-3.3 (Migrations) can be done before Phase 2 completes
- UI development (Phase 3.5) can start in parallel with Phase 2 services

---

## Risk Mitigation

### Risk 1: Data Loss During Backfill
**Mitigation:** 
- Create backup before backfill scripts
- Run backfill in transaction batches
- Validate backfill results before committing
- Keep rollback scripts ready

### Risk 2: Ledger Balance Drift
**Mitigation:**
- Implement ledger integrity check command
- Run weekly balance reconciliation
- Add audit trail for all ledger entries
- Prevent manual ledger edits (only through services)

### Risk 3: Performance Impact
**Mitigation:**
- Add proper indexes on ledger table
- Implement pagination for ledger views
- Cache running balance calculations
- Use database aggregates where possible

### Risk 4: User Adoption
**Mitigation:**
- Provide training documentation
- Create user guides for new workflows
- Implement gradual rollout (pilot with 1-2 machines)
- Keep old DPR form as fallback during transition

---

## Success Criteria

### Phase 1 Success
- [ ] Machinery ledger table created and operational
- [ ] All historical DPRs have ledger entries
- [ ] Billable hours and gross amount calculated correctly
- [ ] Ledger balance matches manual calculations for test data
- [ ] Unit tests pass with >80% coverage

### Phase 2 Success
- [ ] Diesel deduction logic working correctly
- [ ] Maintenance deduction logic working correctly
- [ ] Payment requests created from ledger automatically
- [ ] Approval workflow functional
- [ ] Supplier ledger syncs on payment completion
- [ ] Integration tests pass

### Phase 3 Success
- [ ] All UI views implemented and functional
- [ ] Alert system generating and sending notifications
- [ ] Reports generating accurate data
- [ ] Compliance tracking operational
- [ ] End-to-end workflow test passes
- [ ] User acceptance testing complete

---

## Rollback Plan

If critical issues arise:

### Phase 1 Rollback
- Disable ledger entry creation in controllers
- Keep ledger table but don't use it
- Revert DPR controller to original logic
- Use backup data if backfill corrupted

### Phase 2 Rollback
- Disable payment request creation
- Keep deduction logic but don't apply to payments
- Revert to manual payment requests
- Keep maintenance table but don't link to ledger

### Phase 3 Rollback
- Disable alert system
- Keep new UI views but hide from navigation
- Revert to old machinery show view
- Keep report controllers but disable routes

---

## Post-Implementation Tasks

1. **Performance Optimization**
   - Add caching for ledger balance queries
   - Optimize report generation with queues
   - Add database query monitoring

2. **Documentation**
   - Update API documentation
   - Create user manuals
   - Create admin guides
   - Document troubleshooting procedures

3. **Monitoring**
   - Add logging for ledger operations
   - Set up alerts for ledger balance anomalies
   - Monitor payment request approval times
   - Track alert resolution rates

4. **Maintenance**
   - Schedule regular ledger integrity checks
   - Plan for rate updates (seasonal changes)
   - Plan for compliance date updates
   - Archive old ledger entries periodically
