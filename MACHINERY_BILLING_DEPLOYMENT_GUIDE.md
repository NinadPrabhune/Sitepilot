# Machinery Billing System - Production Deployment Guide

This guide covers the deployment of the enhanced machinery billing system with daily/monthly rate calculations, diesel deduction workflows, and simplified 3-step approval process.

## 🚀 Pre-Deployment Checklist

### Database Preparation
- [ ] **Database Backup**: Create full backup before migration
- [ ] **Migration Testing**: Test all migrations in staging environment
- [ ] **Performance Benchmarks**: Establish baseline performance metrics

### Code Validation
- [ ] **Unit Tests**: All calculation service tests passing
- [ ] **Integration Tests**: End-to-end payment workflow tests
- [ ] **API Tests**: All API endpoints responding correctly
- [ ] **Security Review**: Permission checks and validation rules verified

### Business Logic Verification
- [ ] **Calculation Accuracy**: Compare old vs new calculation results
- [ ] **Financial Integrity**: Ledger balance reconciliation verified
- [ **Audit Trail**: Complete audit logging functional

## 📦 Deployment Steps

### Step 1: Database Migrations
```bash
# Run migrations in order
php artisan migrate --path=database/migrations/2026_05_11_120000_enhance_machinery_payment_requests.php
php artisan migrate --path=database/migrations/2026_05_11_120100_enhance_diesel_tracking.php

# Verify migration success
php artisan migrate:status
```

### Step 2: Data Migration
```bash
# Migrate to 3-step workflow
php artisan db:seed --class=MigratePaymentRequestsTo3Step

# Backfill breakdown data
php artisan db:seed --class=BackfillPaymentRequestBreakdowns

# Validate migration results
php artisan tinker
>>> App\Domain\Machinery\Models\MachineryPaymentRequest::whereNotNull('gross_amount')->count()
>>> App\Domain\Machinery\Models\MachineryPaymentRequest::whereIn('status', ['verified', 'locked'])->count()
```

### Step 3: Cache Clearing
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Warm up caches
php artisan config:cache
php artisan route:cache
```

### Step 4: Test Suite Execution
```bash
# Run calculation service tests
php artisan test tests/Unit/MachineryBillingCalculatorServiceTest.php
php artisan test tests/Unit/MachineryDieselAdjustmentServiceTest.php
php artisan test tests/Unit/MeterReadingValidationServiceTest.php

# Run integration tests
php artisan test tests/Feature/MachineryPaymentRequestTest.php
```

## 🔍 Post-Deployment Validation

### Financial Integrity Checks
```sql
-- Verify calculation consistency
SELECT 
    COUNT(*) as total_requests,
    COUNT(CASE WHEN ABS((gross_amount - diesel_deduction) - net_payable) > 0.01 THEN 1 END) as inconsistent_count,
    COUNT(CASE WHEN gross_amount IS NULL THEN 1 END) as missing_breakdown_count
FROM machinery_payment_requests;

-- Verify ledger balance integrity
SELECT 
    machinery_id,
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE 0 END) as total_credits,
    SUM(CASE WHEN entry_direction = 'debit' THEN amount ELSE 0 END) as total_debits,
    SUM(amount) as net_balance
FROM machinery_ledgers
WHERE is_reversal = false
GROUP BY machinery_id
HAVING ABS(SUM(amount)) > 0.01;
```

### API Endpoint Testing
```bash
# Test billing calculation preview
curl -X POST "http://your-domain/api/machinery-payment-requests/preview-calculation" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "machinery_id": 1,
    "period_start": "2026-05-01",
    "period_end": "2026-05-31"
  }'

# Test payment request creation
curl -X POST "http://your-domain/api/machinery-payment-requests" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "machinery_id": 1,
    "supplier_id": 1,
    "period_start": "2026-05-01",
    "period_end": "2026-05-31"
  }'
```

## 📊 Monitoring Setup

### Performance Monitoring
```php
// Add to app/Providers/AppServiceProvider.php
public function boot()
{
    // Log slow calculation operations
    DB::listen(function ($query) {
        if ($query->time > 1000) { // More than 1 second
            Log::warning('Slow database query detected', [
                'sql' => $query->sql,
                'time' => $query->time,
                'bindings' => $query->bindings
            ]);
        }
    });
}
```

### Financial Integrity Monitoring
```php
// Create app/Console/Commands/CheckMachineryBillingIntegrity.php
class CheckMachineryBillingIntegrity extends Command
{
    public function handle()
    {
        // Check calculation consistency
        $inconsistent = DB::select("
            SELECT COUNT(*) as count
            FROM machinery_payment_requests
            WHERE ABS((gross_amount - diesel_deduction) - net_payable) > 0.01
        ")[0]->count;
        
        if ($inconsistent > 0) {
            Log::critical("Found {$inconsistent} inconsistent payment request calculations");
            $this->error("Financial integrity check failed: {$inconsistent} inconsistent calculations");
        }
        
        // Check ledger balance integrity
        $balanceIssues = DB::select("
            SELECT machinery_id, SUM(amount) as balance
            FROM machinery_ledgers
            WHERE is_reversal = false
            GROUP BY machinery_id
            HAVING ABS(SUM(amount)) > 0.01
        ");
        
        if (!empty($balanceIssues)) {
            Log::warning('Ledger balance issues detected', ['issues' => $balanceIssues]);
        }
        
        $this->info('Financial integrity check completed');
    }
}
```

### Schedule Automated Checks
```php
// Add to app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Daily financial integrity check
    $schedule->command('machinery:check-billing-integrity')
        ->dailyAt('02:00')
        ->onFailure(function () {
            Log::critical('Daily billing integrity check failed');
        });
    
    // Weekly performance report
    $schedule->command('machinery:performance-report')
        ->weeklyOn(DayOfWeek::MONDAY, '09:00');
}
```

## 🚨 Alert Configuration

### Critical Alerts
- **Financial Integrity Failure**: Calculation inconsistencies detected
- **Ledger Balance Issues**: Unbalanced ledger entries
- **Payment Request Errors**: Failed payment request processing
- **Database Performance**: Slow query execution (>2 seconds)

### Warning Alerts
- **High Diesel Consumption**: Unusual diesel usage patterns
- **Meter Reading Anomalies**: Backward readings or large jumps
- **Payment Processing Delays**: Requests taking >5 minutes to process

## 🔄 Rollback Procedures

### Database Rollback
```bash
# Rollback migrations
php artisan migrate:rollback --step=2

# Restore data from backup
mysql -u username -p database_name < backup_before_migration.sql

# Re-run old data seeder if needed
php artisan db:seed --class=OriginalPaymentRequestSeeder
```

### Code Rollback
```bash
# Switch to previous Git branch/tag
git checkout previous-stable-tag

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## 📈 Performance Benchmarks

### Expected Performance Metrics
- **Payment Request Creation**: <3 seconds
- **Billing Calculation**: <1 second
- **API Response Time**: <200ms for standard operations
- **Database Queries**: <100ms for indexed queries

### Load Testing
```bash
# Test payment request creation under load
ab -n 100 -c 10 -H "Authorization: Bearer TOKEN" \
   http://your-domain/api/machinery-payment-requests

# Test billing calculation performance
ab -n 50 -c 5 -H "Authorization: Bearer TOKEN" \
   http://your-domain/api/machinery-payment-requests/preview-calculation
```

## 📋 User Acceptance Testing

### Test Scenarios
1. **Daily Rate Calculation**: Verify any usage counts as full day
2. **Monthly Rate Calculation**: Verify prorated monthly billing
3. **Diesel Deduction**: Verify diesel recovery from supplier payments
4. **3-Step Workflow**: Test Draft → Submitted → Approved → Paid flow
5. **Meter Reading Validation**: Test backward reading prevention
6. **Audit Trail**: Verify complete audit logging

### User Training Checklist
- [ ] New calculation methods explained
- [ ] Simplified workflow demonstrated
- [ ] Diesel deduction process explained
- [ ] Error handling procedures documented
- [ ] Support contact information provided

## ✅ Success Criteria

### Technical Success
- [ ] All migrations applied successfully
- [ ] Zero calculation errors in production
- [ ] Performance benchmarks met
- [ ] All automated tests passing

### Business Success
- [ ] Supplier billing accuracy 100%
- [ ] Payment processing time reduced by 40%
- [ ] User acceptance >95% satisfaction
- [ ] Zero financial discrepancies

### Operational Success
- [ ] Monitoring systems active
- [ ] Alert configuration verified
- [ ] Rollback procedures tested
- [ ] Support team trained

## 📞 Support Information

### Emergency Contacts
- **Technical Lead**: [Contact Information]
- **Database Administrator**: [Contact Information]
- **Business Analyst**: [Contact Information]

### Troubleshooting Guide
1. **Calculation Errors**: Check machinery rate configuration
2. **Payment Issues**: Verify ledger entry integrity
3. **Performance Issues**: Check database indexes and query performance
4. **API Errors**: Review validation rules and permissions

This deployment guide ensures a smooth transition to the enhanced machinery billing system with proper validation, monitoring, and rollback procedures.
