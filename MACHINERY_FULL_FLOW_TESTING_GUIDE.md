# 🧠 Machinery Management Full Flow Validation Guide

## 🎯 Overview

This guide provides comprehensive testing strategy for the machinery management system using **"FULL FLOW VALIDATION WITH INTENT"** approach. The system is tested across 3 critical dimensions:

1. **Operational Flow** (DPR, Diesel, Operators)
2. **Financial Flow** (Owned vs Rental separation)  
3. **Behavioral + Validation Flow** (warnings, overrides, lifecycle)

## 🏗️ Test Phases

### Phase 0: Master Data Setup (CRITICAL FOUNDATION)
**Goal**: Ensure system logic behaves differently for owned vs rental machinery

#### Test Data Creation:
```php
// 🏢 Owned Machinery
$ownedMachinery = Machinery::create([
    'name' => 'Excavator A',
    'owned_by' => 'owned',
    'rate' => 1500,
    'supplier_id' => null, // ❌ NULL - must be null for owned
]);

// 🚚 Rental Machinery  
$rentalMachinery = Machinery::create([
    'name' => 'Excavator B',
    'owned_by' => 'rental',
    'rate' => 1200,
    'minimum_billing_hours' => 8,
    'supplier_id' => $supplier->id, // ✅ REQUIRED - must have supplier
]);
```

#### ✅ Validation Checkpoints:
- ❌ Owned with supplier → MUST FAIL
- ❌ Rental without supplier → MUST FAIL
- ✅ Owned without supplier → SUCCESS
- ✅ Rental with supplier → SUCCESS

**If this fails → your entire financial flow is compromised**

---

### Phase 1: DPR Creation (CORE ENGINE Test)
**Goal**: Test BOTH machinery types SAME DAY with proper calculations

#### 🏢 OWNED DPR Test:
```php
$ownedDpr = DailyProgressReport::create([
    'date' => now()->toDateString(),
    'machinery_id' => $ownedMachinery->id,
    'machine_start_reading' => 100,
    'machine_end_reading' => 106, // 6 hrs
    'machine_idle_reading' => 1, // 1 hr idle
    'number_of_operators' => 2,
    'operator_names' => 'John, Mike',
]);

// Expected Results:
// Working = 6 hrs
// Billable = 5 hrs (6 - 1 idle)
// Amount = 5 × 1500 = ₹7500
```

#### 🚚 RENTAL DPR Test:
```php
$rentalDpr = DailyProgressReport::create([
    'date' => now()->toDateString(),
    'machinery_id' => $rentalMachinery->id,
    'machine_start_reading' => 200,
    'machine_end_reading' => 205, // 5 hrs
    'machine_idle_reading' => 1, // 1 hr idle = 4 hrs actual
    'minimum_billing_hours' => 8, // Applied automatically
]);

// Expected Results:
// Billable = 8 (NOT 4) - minimum billing applied
// Amount = 8 × 1200 = ₹9600
```

#### 🔍 Verification Points:
- **Owned DPR**: Ledger Type = `internal_cost`, Payment request = NOT ALLOWED
- **Rental DPR**: Ledger Type = `payable`, `minimum_billing_applied` = ✅

#### 🚨 Break Tests:
- End < Start → ❌ BLOCK
- Idle > Working → ⚠️ WARN (allow with override)
- Duplicate DPR → ❌ BLOCK

---

### Phase 2: Diesel Management Test
**Goal**: Validate separation from DPR and expense tracking

#### Test Data:
```php
// Owned Machine Diesel
$ownedDiesel = DailyConsumptionMaster::create([
    'date' => now()->toDateString(),
    'daily_progress_report_id' => $ownedDpr->id,
    'diesel_consumed_liters' => 50,
    'diesel_rate_per_liter' => 100,
    'total_diesel_cost' => 5000,
]);

// Rental Machine Diesel
$rentalDiesel = DailyConsumptionMaster::create([
    'date' => now()->toDateString(),
    'daily_progress_report_id' => $rentalDpr->id,
    'diesel_consumed_liters' => 40,
    'diesel_rate_per_liter' => 100,
    'total_diesel_cost' => 4000,
]);
```

#### 🔍 Verification:
- **Ledger Type** = `expense` for both
- **Cost Category** = `diesel` for both
- **Entry Direction** = `debit` for both

#### 🚨 Break Tests:
- Duplicate diesel same day → ⚠️ WARN (allow with override)
- Diesel without DPR → ⚠️ WARN (allowed)
- Excessive diesel (100L for 2 hrs) → 🚨 FLAG

---

### Phase 3: Operator Entry Test
**Goal**: Validate behavioral + data integrity

#### Test Data:
```php
$operatorDpr = DailyProgressReport::create([
    'date' => now()->toDateString(),
    'machinery_id' => $machinery->id,
    'machine_start_reading' => 100,
    'machine_end_reading' => 105,
    'number_of_operators' => 2,
    'operator_names' => 'John, Mike', // Properly formatted
]);
```

#### 🚨 Break Test:
- Count = 2, names = 1 → ⚠️ WARN + reason required

---

### Phase 4: Payment Flow Test (CRITICAL)
**Goal**: 🚚 RENTAL ONLY payment processing

#### Test Flow:
```php
// 1. Create Payment Request for Rental DPR
$paymentRequest = MachineryPaymentRequest::create([
    'machinery_id' => $rentalMachinery->id,
    'daily_progress_report_id' => $rentalDpr->id,
    'amount' => $rentalDpr->calculated_amount, // ₹9600
    'status' => 'pending',
]);

// 2. Approve Payment
$paymentRequest->update([
    'status' => 'approved',
    'approved_by' => $admin->id,
    'approved_at' => now(),
]);
```

#### 🔍 Verification:
- DPR → `locked = true` (via status)
- Ledger entry created and linked to payment
- Payment linked to DPR

#### 🚨 Break Tests:
- Try payment for owned → ❌ BLOCK
- Try edit DPR after payment → ❌ BLOCK

---

### Phase 5: Reversal Test (AUDIT TEST)
**Goal**: Never delete, only reverse with audit trail

#### Test Flow:
```php
// Reverse Payment
$reversalLedger = MachineryLedgerService::reverseEntry(
    $paymentRequest->ledgerEntry->id,
    'Incorrect amount calculation'
);
```

#### 🔍 Verification:
- Reversal entry created with `is_reversal = true`
- Original DPR unchanged
- Ledger balanced (opposite entry direction)

---

### Phase 6: Machine Work Report Test
**Goal**: Validate aggregation logic and financial separation

#### Expected Aggregation:
```php
// Owned Machinery Costs
$ownedInternalCost = $ownedLedgerEntries->where('ledger_type', 'internal_cost')->sum();
$ownedExpense = $ownedLedgerEntries->where('ledger_type', 'expense')->sum();

// Rental Machinery Costs  
$rentalPayable = $rentalLedgerEntries->where('ledger_type', 'payable')->sum();
$rentalExpense = $rentalLedgerEntries->where('ledger_type', 'expense')->sum();

// 🚨 CRITICAL CHECK:
Project Cost = internal_cost + expense  
Payables = payable  

// 👉 These MUST NEVER mix
```

#### 🚨 Critical Validation:
- Owned machinery should NOT have `payable` entries
- Rental machinery should NOT have `internal_cost` entries

---

### Phase 7: Behavioral Test
**Goal**: Validate warning system and override functionality

#### Test Override Flow:
```php
// Enter wrong idle → override with reason
$overrideDpr = DailyProgressReport::create([
    'date' => now()->toDateString(),
    'machinery_id' => $machinery->id,
    'machine_start_reading' => 100,
    'machine_end_reading' => 105,
    'machine_idle_reading' => 3, // High idle time
    'override_reason' => 'Machine stuck in mud - justified',
    'override_by' => $operator->id,
    'override_at' => now(),
]);
```

#### 🔍 Verification:
- Override reason stored
- Warning count tracked
- Escalation triggered (>60% warning rate)

---

### Phase 8: Report + Warning Visibility
**Goal**: Validate transparency and quality metrics

#### Expected Report Content:
```
Total Cost: ₹XXXX
⚠ 2 warnings overridden
Quality Score: 78%
```

#### Test Components:
- Total cost calculation
- Warning count display
- Quality score calculation
- Report generation

---

## 🔒 Final Chaos Test (MOST IMPORTANT)

### Goal: Try to break the system intentionally

#### Test Scenarios:

1. **Change rate after DPR** → Old DPR must NOT change
2. **Edit locked DPR** → ❌ BLOCK
3. **Create duplicate entries** → ❌ BLOCK  
4. **Force mismatch ledger** → ❌ BLOCK
5. **Mix cost & payable** → ❌ BLOCK

### Success Criteria:
- All chaos tests must pass (system properly blocks invalid operations)
- Data integrity maintained
- Audit trail preserved

---

## 🧠 Final Thinker Checklist

If ALL tests pass, you have:

✅ **Deterministic calculations** - Same inputs always produce same outputs  
✅ **Correct financial classification** - Owned vs rental properly separated  
✅ **Behavioral accountability** - All actions tracked with audit trail  
✅ **Audit-safe flows** - Reversals only, no deletions  

## 🏁 Final Verdict

This is not just testing—it's **System certification through controlled stress simulation**

---

## 🚀 Running the Tests

### Using Artisan Command:
```bash
# Run all phases
php artisan machinery:test-full-flow

# Run specific phase
php artisan machinery:test-full-flow --phase=1

# Run only chaos tests
php artisan machinery:test-full-flow --chaos

# Run with cleanup
php artisan machinery:test-full-flow --cleanup

# Verbose output
php artisan machinery:test-full-flow --verbose
```

### Using PHPUnit:
```bash
# Run full test suite
php artisan test tests/Feature/MachineryFullFlowValidationTest.php

# Run specific test method
php artisan test --filter test_master_data_validation_owned_vs_rental
```

---

## 📊 Test Results Interpretation

### Score Ranges:
- **90-100%**: 🏆 EXCELLENT - System is production ready!
- **80-89%**: ✅ GOOD - System meets most requirements  
- **70-79%**: ⚠️ FAIR - System needs some improvements
- **Below 70%**: ❌ POOR - System requires significant fixes

### Phase Status:
- **PASS**: All validations working correctly
- **FAIL**: Critical issues found, must fix before production
- **ERROR**: Test execution failed, check environment

### Chaos Test Results:
- **80-100%**: System properly handles edge cases
- **Below 80%**: System vulnerable to data integrity issues

---

## 🔧 Troubleshooting

### Common Issues:

1. **Foreign Key Constraints**: Ensure test data cleanup in proper order
2. **Missing Test Data**: Run Phase 0 first to setup master data
3. **Permission Issues**: Test user needs appropriate roles
4. **Database State**: Use `--cleanup` option between test runs

### Debug Tips:
- Use `--verbose` flag for detailed output
- Check Laravel logs for detailed error messages
- Verify database state after each phase
- Test in isolation before running full suite

---

## 📝 Test Documentation

### Generated Reports:
- Test execution logs
- Performance metrics (execution time)
- Validation scores
- Warning statistics
- Audit trail verification

### Continuous Integration:
- Integrate with CI/CD pipeline
- Run chaos tests before deployment
- Monitor test scores over time
- Alert on score degradation

---

## 🎯 Production Readiness Checklist

Before deploying to production, ensure:

- [ ] All phases pass with ≥80% score
- [ ] Chaos tests pass with ≥80% score  
- [ ] No test data in production database
- [ ] Audit logging enabled
- [ ] Backup procedures tested
- [ ] Rollback plan documented
- [ ] Team trained on override procedures

---

## 🔄 Maintenance

### Regular Testing:
- Run full flow validation weekly
- After major system changes
- Before database migrations
- After security updates

### Monitoring:
- Track test score trends
- Monitor warning rates
- Audit override usage
- Review reversal patterns

---

**This comprehensive testing strategy ensures your machinery management system is robust, audit-safe, and production-ready!** 🚀
