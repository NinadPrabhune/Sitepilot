# Machinery Management ERP Testing Guide

## 🚀 Complete Testing Setup Ready

Your comprehensive Machinery Management ERP test environment is now fully configured with controlled test data, validation tools, and automated test suites.

## 📋 What's Been Created

### ✅ **Test Plan & Strategy**
- **Location:** `C:\Users\ninad\.windsurf\plans\machinery-management-erp-test-plan-8b8bb1.md`
- **Coverage:** 7-day controlled test cycle with Thinker-level system validation
- **Focus:** Rental vs Owned calculations, payment workflows, edge cases

### ✅ **SQL Audit Pack**
- **Location:** `database/audit_queries/machinery_management_audit.sql`
- **Features:** 50+ validation queries for financial integrity
- **Checks:** Duplicates, amount mismatches, orphan records, period locks

### ✅ **Controlled Test Data**
- **Location:** `database/seeders/TestDataSetupScript.php`
- **Machinery:** 4 machines (2 owned, 2 rental with complex configurations)
- **Duration:** 7-day test cycle with realistic scenarios

### ✅ **Postman API Collection**
- **Location:** `tests/postman/Machinery_Management_ERP_Test_Collection.postman_collection.json`
- **Coverage:** Full API workflow from DPR creation to payment approval
- **Validation:** Automated response testing and error handling

### ✅ **PHPUnit Test Suite**
- **Feature Tests:** `tests/Feature/MachineryManagementTest.php`
- **Unit Tests:** `tests/Unit/MachineryCalculationTest.php`
- **Coverage:** Business logic, financial integrity, edge cases

## 🎯 Test Configuration Summary

### **Machinery Setup**
| Machine | Type | Rate/Hr | Special Features |
|---------|------|---------|-----------------|
| OWN-001 | Owned | ₹1000 | Standard owned machine |
| OWN-002 | Owned | ₹800 | Edge case testing |
| RENT-001 | Rental | ₹1500 | Standard rental |
| RENT-002 | Rental | ₹1200 | **Complex**: 8hr min, diesel+operator by supplier |

### **Test Scenarios**
- **Normal Operations:** Standard working hours and consumption
- **Edge Cases:** Zero hours, minimum billing violations, data entry errors
- **Financial Workflows:** DPR → Ledger → Payment Request → Approval
- **Error Conditions:** Duplicate prevention, period overlap, validation failures

## 🚀 Quick Start Execution

### **Step 1: Setup Test Data**
```bash
php artisan db:seed --class=TestDataSetupScript
```

This creates:
- 4 test machines with realistic configurations
- 28 Daily Progress Reports (7 days × 4 machines)
- Diesel consumption entries
- Payment requests with workflow processing
- Complete ledger with financial entries

### **Step 2: Run SQL Audit Validation**
```sql
-- Run the complete audit pack
SOURCE database/audit_queries/machinery_management_audit.sql;
```

**Critical Checks:**
- Duplicate detection (should be 0)
- Amount mismatches (should be 0)
- Period lock integrity (all approved periods locked)
- Ledger balance reconciliation

### **Step 3: Test API Workflows**
1. Import Postman collection: `tests/postman/Machinery_Management_ERP_Test_Collection.postman_collection.json`
2. Set environment variables:
   - `base_url`: `http://localhost:8000`
   - `test_email`: Your test user email
   - `test_password`: Your test user password
3. Run collection in order: Authentication → Setup → DPR Flow → Payment Flow → Validation

### **Step 4: Run Automated Tests**
```bash
# Feature tests (business workflows)
php artisan test tests/Feature/MachineryManagementTest.php

# Unit tests (calculations)
php artisan test tests/Unit/MachineryCalculationTest.php

# All machinery tests
php artisan test --filter=Machinery
```

## 🔍 Key Validation Points

### **Financial Integrity**
- ✅ No duplicate ledger entries
- ✅ Correct credit/debit calculations
- ✅ Proper period locking after approval
- ✅ Accurate reversal handling

### **Business Logic**
- ✅ Minimum billing enforcement (RENT-002: 8hr minimum)
- ✅ Diesel responsibility allocation
- ✅ Operator cost handling
- ✅ Rental vs owned calculation differences

### **System Robustness**
- ✅ Concurrent request handling
- ✅ Edge case validation
- ✅ Error recovery mechanisms
- ✅ Audit trail completeness

## 📊 Expected Test Results

### **Daily Progress Reports**
- **Total DPRs:** 28 (4 machines × 7 days)
- **Status:** All approved with ledger entries
- **Scenarios:** Mix of normal, extended, partial, zero hours

### **Payment Requests**
- **Owned Machines:** 2 requests (internal cost tracking)
- **Rental Machines:** 2 requests (supplier payments)
- **Status:** All processed through approval workflow

### **Ledger Entries**
- **Credits:** DPR earnings (machine hours × rate)
- **Debits:** Diesel consumption costs
- **Balance:** Proper running balance calculation

## 🚨 Critical Test Cases

### **Minimum Billing Test**
**Machine:** RENT-002 (Complex Rental)
**Scenario:** 6 hours usage (below 8hr minimum)
**Expected:** Billed at 8 hours × ₹1200 = ₹9600

### **Diesel Responsibility Test**
**Machine:** RENT-001 vs RENT-002
**Scenario:** 40L diesel consumption
**Expected:** 
- RENT-001: Supplier charged (diesel_by_company = false)
- RENT-002: Company bears cost (diesel_by_company = true)

### **Period Overlap Test**
**Scenario:** Try creating payment request for overlapping dates
**Expected:** System blocks with overlap error

### **Duplicate Prevention Test**
**Scenario:** Try creating DPR for same machine/date
**Expected:** System blocks with duplicate error

## 🔧 Troubleshooting

### **Common Issues**
1. **Missing Materials:** Ensure diesel material exists in materials table
2. **Permission Errors:** Check user has required permissions
3. **Foreign Key Issues:** Run cleanup script if needed

### **Data Cleanup**
```bash
php artisan db:seed --class=TestDataSetupScript --cleanup
```

### **Audit Query Failures**
- Check table names match your schema
- Verify date formats in queries
- Ensure test data exists before running audits

## 📈 Performance Validation

### **Ledger Query Performance**
```sql
-- Check ledger query performance
EXPLAIN SELECT * FROM machinery_ledgers 
WHERE machinery_id = 1 AND date BETWEEN '2026-04-25' AND '2026-05-01'
ORDER BY date, id;
```

### **Payment Calculation Speed**
```sql
-- Time payment request generation
SET @start = NOW();
SELECT * FROM machinery_payment_requests;
SELECT TIMESTAMPDIFF(MICROSECOND, @start, NOW()) as microseconds;
```

## 🎯 Success Criteria

### ✅ **Financial Integrity**
- No duplicate ledger entries
- Correct credit/debit calculations
- Proper period locking
- Accurate reversal handling

### ✅ **Business Logic**
- Minimum billing enforcement
- Diesel responsibility allocation
- Operator cost handling
- Rental vs owned differences

### ✅ **System Robustness**
- Concurrent request handling
- Edge case validation
- Error recovery
- Complete audit trail

## 📞 Next Steps

1. **Execute Test Data Setup:** Run the seeder script
2. **Validate with SQL:** Run audit queries
3. **Test APIs:** Use Postman collection
4. **Run Automated Tests:** Execute PHPUnit suite
5. **Document Results:** Record findings and issues

Your Machinery Management ERP system is now ready for comprehensive validation with real-world scenarios and edge case testing! 🚀
