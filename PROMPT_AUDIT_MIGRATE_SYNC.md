# Audit + Migration + Sync Plan — User/Employee Identity Normalization

## Context: Laravel ERP (SitePilot / Workdo Hrm)

This project has a critical data inconsistency problem between `users` and `employees` tables. The relationship is meant to be one-to-one but has never been enforced at the database or application level. This prompt provides a complete, phased plan covering analysis, rectification, synchronization, enforcement, refactoring, and testing.

---

## Current State

### Schema

| Table | Primary Key | Identity Column |
|---|---|---|
| `users` | `id` (bigIncrements, auto-increment) | `users.id` |
| `employees` | `id` (auto-increment, INDEPENDENT from users) | `users.id` → `employees.user_id` |
| `employees.employee_id` | STRING (`#EMP00001`) | Display code only |

### Employee Model (`packages/workdo/Hrm/src/Entities/Employee.php`)

- `$fillable` includes both `user_id` and `employee_id`
- `user()` relationship: `hasOne(User::class, 'id', 'user_id')` — **uses `hasOne` instead of `belongsTo`**
- `get_net_salary()` and all static helper methods use `$this->id` (employee's own PK) to query sub-tables like allowance, commission, loan, etc.
- `employeeNumber()` at EmployeeController:789 generates string code via `max(employees.id) + 1`

### User Model (`app/Models/User.php`)

- `employee()` relationship: `hasOne(Employee::class, 'user_id')`
- `$not_emp_type` excludes these user types from employee dropdowns: `super admin`, `company`, `client`, `vendor`, `driver`, `salesagent`, `hr`, `gym member`, `gym trainer`, `advocate`, `case initiator`, `parent`
- `scopeEmp()` filters to employee-only users

### Foreign Keys

**Zero foreign key constraints exist across the entire HRM package.** All `integer()` columns (`user_id`, `employee_id`, `created_by`, `branch_id`, `department_id`, `designation_id`) have no `->foreign()`, `->references()`, or `->constrained()` calls.

### Database Integrity Risks

1. **`employees.employee_id`** is a string display code (`#EMP00001`) — it is **NOT** `users.id`. The column name is misleading.
2. **`users.id`** and **`employees.id`** are independent auto-increment sequences — there is no guarantee they match.
3. **12 of 14 `User::create()` calls** never create an Employee record (only EmployeeController and EmployeeApiController do).
4. **The event system is decorative** — ~100+ events are fired but most have zero registered listeners. `CreateEmployee`, `UpdateEmployee`, `DestroyEmployee` events all fire with no listeners.
5. **`UserController@destroy`** does dangerous raw SQL: scans ALL database tables for a `created_by` column and deletes matching rows — no cascade, no business logic.
6. **`EmployeeController@destroy`** deletes the employee AND the user but does NOT clean up related sub-table records (awards, leaves, allowances, etc. are left as orphans).
7. **The `employee_id` column name means 3 different things across the codebase**:
   - **`employees.employee_id`** = string display code (`#EMP00001`)
   - **HRM sub-table `employee_id`** (awards, allowances, etc.) stores `employees.id` (integer)
   - **Form field `$request->employee_id`** sometimes sends `users.id`, sometimes `employees.id`

---

## Phase 1 — Full System Analysis (Execute First, Change Nothing)

### Step 1.1: Inventory All Files

Search every file in the project for:

```
packages/workdo/Hrm/src/
app/
database/
resources/views/hrm/
routes/
```

### Step 1.2: Search Patterns

Grep the entire codebase for each of these patterns (case-insensitive where appropriate):

```bash
# Core identity fields
employee_id
user_id
employees.id
users.id
Auth::id()
created_by
Auth::user()->id

# User creation
User::create
new User
user->save()

# Employee creation
Employee::create
new Employee
employee->save()

# Relationships
hasOne.*Employee
belongsTo.*Employee
hasOne.*User
belongsTo.*User

# Deletion
User::destroy
Employee::destroy
->delete()

# Events/Callbacks
CreateEmployee
UpdateEmployee
DestroyEmployee
::observe
::created
::saved
boot()

# FK references
->foreign(
->references(
->constrained(
onDelete('cascade')
onDelete('set null')
```

### Step 1.3: Document All Locations

Create a detailed report with file paths and line numbers covering:

1. **All places where `User::create()` or `new User` is called** (list every file:line)
2. **All places where `Employee::create()` or `new Employee` is called** (list every file:line)
3. **All code that reads or writes `employee_id`** (list every file:line, noting whether it's the string code or the integer FK)
4. **All code that reads or writes `user_id`** (list every file:line)
5. **All Eloquent relationships** involving User or Employee
6. **All raw SQL queries** that reference `users` or `employees` tables
7. **All foreign key constraints** (or lack thereof)
8. **All observers, events, listeners** for User and Employee models
9. **All validation rules** referencing `employee_id` or `user_id`
10. **All routes/APIs** that create, update, or delete users/employees
11. **All middleware** that checks user or employee identity
12. **All seeders and factories** for User and Employee

### Step 1.4: Risk Assessment

For each location found, classify the risk level if `users.id`, `employees.id`, or `employees.employee_id` were changed:

- **CRITICAL**: Deletion cascades, raw SQL, hardcoded IDs, auth logic
- **HIGH**: Payroll calculations, attendance, salary data
- **MEDIUM**: Display-only references, dropdown lists, reports
- **LOW**: Logs, comments, unused code

### Step 1.5: Generate Orphan Data Report

```sql
-- Users without employees (considering $not_emp_type exclusions)
SELECT u.id, u.name, u.email, u.type
FROM users u
LEFT JOIN employees e ON e.user_id = u.id
WHERE e.id IS NULL
  AND u.type NOT IN ('super admin', 'company', 'client', 'vendor', 'driver', 'salesagent', 'hr', 'gym member', 'gym trainer', 'advocate', 'case initiator', 'parent');

-- Employees without users
SELECT e.id, e.user_id, e.name, e.email
FROM employees e
LEFT JOIN users u ON u.id = e.user_id
WHERE u.id IS NULL;

-- Employees where user_id doesn't match users.id
SELECT e.id, e.user_id, e.employee_id
FROM employees e
LEFT JOIN users u ON u.id = e.user_id
WHERE u.id IS NULL OR e.user_id IS NULL;

-- Duplicate user_id in employees (multiple employees linked to same user)
SELECT user_id, COUNT(*) as cnt
FROM employees
WHERE user_id IS NOT NULL
GROUP BY user_id
HAVING COUNT(*) > 1;
```

---

## Phase 2 — Database Audit Queries (Raw SQL)

Execute these against production BEFORE any changes:

### 2.1: Users Missing Employee Records

```sql
SELECT u.id, u.name, u.email, u.type, u.created_at
FROM users u
LEFT JOIN employees e ON e.user_id = u.id
WHERE e.id IS NULL;
```

### 2.2: Employees Missing User Records

```sql
SELECT e.id, e.user_id, e.name, e.email, e.employee_id
FROM employees e
LEFT JOIN users u ON u.id = e.user_id
WHERE u.id IS NULL;
```

### 2.3: ID Mismatches Between Tables

```sql
-- Employees where user_id doesn't match expected pattern
SELECT e.*
FROM employees e
LEFT JOIN users u ON u.id = e.user_id
WHERE u.id IS NULL OR e.user_id IS NULL;

-- Count: total employees vs total users
SELECT 'Total Users' as metric, COUNT(*) FROM users
UNION ALL
SELECT 'Total Employees', COUNT(*) FROM employees
UNION ALL
SELECT 'Users without Employee', COUNT(*) FROM (
  SELECT u.id FROM users u
  LEFT JOIN employees e ON e.user_id = u.id
  WHERE e.id IS NULL
    AND u.type NOT IN ('super admin','company','client','vendor','driver','salesagent','hr','gym member','gym trainer','advocate','case initiator','parent')
) sub
UNION ALL
SELECT 'Employees without User', COUNT(*) FROM (
  SELECT e.id FROM employees e
  LEFT JOIN users u ON u.id = e.user_id
  WHERE u.id IS NULL
) sub2;
```

### 2.4: Duplicate / Conflicting Mappings

```sql
-- Multiple employees claiming same user_id
SELECT user_id, COUNT(*) as emp_count, GROUP_CONCAT(id) as emp_ids
FROM employees
WHERE user_id IS NOT NULL
GROUP BY user_id
HAVING COUNT(*) > 1;
```

### 2.5: Backup Recommendations

Before any data modification:

```sql
-- Backup employees table
CREATE TABLE employees_backup_YYYYMMDD LIKE employees;
INSERT INTO employees_backup_YYYYMMDD SELECT * FROM employees;

-- Backup users table
CREATE TABLE users_backup_YYYYMMDD LIKE users;
INSERT INTO users_backup_YYYYMMDD SELECT * FROM users;

-- Backup all HRM sub-tables
-- (awards, promotions, transfers, travels, resignations, terminations,
--  leaves, allowances, commissions, loans, overtimes, other_payments,
--  saturation_deductions, company_contributions, pay_slips, attendance,
--  employee_documents)
```

### 2.6: Rollback Plan

```sql
-- Full rollback
DROP TABLE IF EXISTS employees;
RENAME TABLE employees_backup_YYYYMMDD TO employees;

DROP TABLE IF EXISTS users;
RENAME TABLE users_backup_YYYYMMDD TO users;

-- For any migration that adds/changes columns, create a down() method
```

---

## Phase 3 — Old Data Rectification Strategy

### 3.1: Rule Definitions

**Rule 1 — Every User MUST have an Employee record.**

For users in `$not_emp_type` (`super admin`, `company`, `client`, `vendor`, `driver`, `salesagent`, `hr`, `gym member`, `gym trainer`, `advocate`, `case initiator`, `parent`), an employee record is optional. For ALL OTHER user types, if an employee record does not exist, auto-create one copying:
- `name`, `email`, `phone` (from `mobile_no`) from the user
- `created_at`, `updated_at` from the user
- `user_id` = user's id
- `employee_id` = generated string code (but going forward this is replaced)
- Fill nullable fields with defaults

**Rule 2 — Every Employee MUST have a User record.**

If an employee exists without a linked user, auto-create an inactive user:
- `name`, `email` from employee
- Generate a temporary password (hash it)
- `type` = 'staff' (default employee type)
- `active_status` = 0 (inactive)
- Set a flag `must_reset_password = 1` (add this column if needed, or use a custom field)
- `workspace_id` and `created_by` from employee record

**Rule 3 — Normalize IDs.**

Target invariant: `users.id = employees.id = employees.user_id`

The `employees.employee_id` (string code) is renamed/repurposed. Going forward:
- `employees.id` = `users.id` (same value)
- `employees.user_id` = `users.id` (same value, kept for backward compatibility)
- `employees.employee_id` (string) is deprecated for internal use but kept as a display field

**Implementation approach (incremental, not destructive):**

1. First, fix orphan records (create missing employees/users)
2. Then, add new `employee.employee_uuid` or similar stable identifier
3. Finally, add FK constraints once data is clean

### 3.2: Transaction-Safe Rectification Script

Create a migration or command that:

```php
// Pseudocode — implement as Artisan command
DB::transaction(function () {
    // Step 1: Create missing employee records
    $usersWithoutEmployee = User::whereNotIn('type', $notEmpTypes)
        ->whereDoesntHave('employee')
        ->get();

    foreach ($usersWithoutEmployee as $user) {
        Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->mobile_no,
            'employee_id' => Employee::employeeIdFormat(Employee::max('id') + 1),
            // Defaults for required fields
            'gender' => 'other',
            'address' => '',
            'branch_id' => 0,
            'department_id' => 0,
            'designation_id' => 0,
            'workspace' => $user->workspace_id,
            'created_by' => $user->created_by,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    // Step 2: Create missing user records
    $employeesWithoutUser = Employee::whereDoesntHave('user')->get();

    foreach ($employeesWithoutUser as $employee) {
        $tempPassword = Str::random(16);
        $user = User::create([
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => Hash::make($tempPassword),
            'type' => 'staff',
            'active_status' => 0,
            'workspace_id' => $employee->workspace,
            'created_by' => $employee->created_by,
            'lang' => 'en',
        ]);
        // Store temp password reference
        // $user->must_reset_password = 1;
        Log::info("Created missing user for employee {$employee->id}: user_id={$user->id}, temp_password={$tempPassword}");

        // Update employee with correct user_id
        $employee->user_id = $user->id;
        $employee->save();
    }

    // Step 3: Handle duplicate user_id entries
    $duplicates = Employee::select('user_id')
        ->groupBy('user_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    foreach ($duplicates as $dup) {
        $employees = Employee::where('user_id', $dup->user_id)
            ->orderBy('id')
            ->get();
        $keep = $employees->shift(); // Keep first
        foreach ($employees as $extra) {
            // Reassign sub-table records to kept employee
            // Then delete or archive the extra employee
            $extra->delete(); // Or mark as merged
        }
    }

    // NOTE: Do NOT change existing primary keys yet
    // That requires full FK dependency analysis
});
```

### 3.3: ID Normalization Migration Script

After orphan rectification, normalize IDs in a separate, carefully planned migration:

```sql
-- Step 1: Create a mapping of old employee.id -> new employee.id (= users.id)
-- but first we need to ensure every employee has a user_id that matches a users.id

-- For each employee where user_id differs from id, we need to:
-- 1. Update all FK references in sub-tables to point to the new employee ID
-- 2. Update the employee's id
-- 3. Then add FK constraints

-- This is complex. Alternative: use employees.user_id as the source of truth
-- and change the primary key to match.

-- SAFER APPROACH: Don't change primary keys. Instead:
-- 1. Add FK constraint: employees.user_id -> users.id
-- 2. Add unique constraint: employees.user_id
-- 3. Enforce at the application layer that employees.user_id == employees.id
-- 4. In new code, use $employee->user_id instead of $employee->id for all lookups
```

---

## Phase 4 — Permanent Architecture Fix (Synchronization System)

### 4.1: Observer Pattern (Preferred Over Events)

Create `app/Observers/UserObserver.php`:

```php
class UserObserver
{
    public function created(User $user): void
    {
        // Skip for non-employee types
        if (in_array($user->type, $user->not_emp_type)) {
            return;
        }

        DB::transaction(function () use ($user) {
            // Check if employee already exists (import/seed scenarios)
            $existing = Employee::where('user_id', $user->id)->first();
            if ($existing) {
                return;
            }

            Employee::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->mobile_no,
                'employee_id' => Employee::employeeIdFormat(Employee::max('id') + 1),
                'gender' => 'other',
                'address' => '',
                'branch_id' => 0,
                'department_id' => 0,
                'designation_id' => 0,
                'workspace' => $user->workspace_id,
                'created_by' => $user->created_by,
            ]);
        });
    }

    public function deleted(User $user): void
    {
        // Cascade: when user is deleted, also delete employee and related records
        if (!in_array($user->type, $user->not_emp_type)) {
            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee) {
                // Clean up sub-table records
                // $employee->delete(); — but use the service layer
            }
        }
    }
}
```

Create `app/Observers/EmployeeObserver.php`:

```php
class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            // Check if user already exists
            $existing = User::find($employee->user_id);
            if ($existing) {
                return;
            }

            $tempPassword = Str::random(16);
            $user = User::create([
                'name' => $employee->name,
                'email' => $employee->email,
                'password' => Hash::make($tempPassword),
                'type' => 'staff',
                'active_status' => 0,
                'workspace_id' => $employee->workspace,
                'created_by' => $employee->created_by,
                'lang' => 'en',
            ]);

            // Update employee with correct user_id if different
            if ($employee->user_id !== $user->id) {
                $employee->user_id = $user->id;
                $employee->save();
            }

            Log::info("EmployeeObserver: Created user {$user->id} for employee {$employee->id}");
        });
    }
}
```

Register observers in `AppServiceProvider`:

```php
public function boot(): void
{
    User::observe(UserObserver::class);
    Employee::observe(EmployeeObserver::class);
}
```

### 4.2: Service Layer

Create `app/Services/EmployeeSyncService.php`:

```php
class EmployeeSyncService
{
    public function syncUserWithEmployee(User $user): Employee
    {
        return DB::transaction(function () use ($user) {
            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee) {
                return $employee;
            }

            // Prevent infinite loop with observer
            Employee::withoutEvents(function () use ($user) {
                return Employee::create([...]);
            });
        });
    }

    public function deleteUserWithCascade(User $user): void
    {
        DB::transaction(function () use ($user) {
            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee) {
                $this->deleteEmployeeSubRecords($employee);
                $employee->delete();
            }
            $user->delete();
        });
    }

    public function ensureConsistency(): array
    {
        // Batch fix all orphans
        $results = ['users_fixed' => 0, 'employees_fixed' => 0];
        // ... implementation
        return $results;
    }

    private function deleteEmployeeSubRecords(Employee $employee): void
    {
        // Delete all sub-table records
        Award::where('employee_id', $employee->id)->delete();
        Allowance::where('employee_id', $employee->id)->delete();
        // ... all other sub-tables
    }
}
```

### 4.3: Deduplication Logic

Include a method to ensure no duplicate employee records are created:

```php
// Before creating employee, always check
if (Employee::where('user_id', $user->id)->exists()) {
    throw new DuplicateEmployeeException("User {$user->id} already has an employee record");
}
```

---

## Phase 5 — Enforce Integrity (Database Constraints)

### 5.1: New Migration

```php
// database/migrations/YYYY_MM_DD_HHMMSS_enforce_user_employee_integrity.php
Schema::table('employees', function (Blueprint $table) {
    // Add FK constraint: employees.user_id -> users.id
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade');

    // Ensure unique user_id (one-to-one enforcement)
    $table->unique('user_id', 'employees_user_id_unique');
});
```

For ALL HRM sub-tables that reference employees:

```php
// Pattern for each sub-table
Schema::table('awards', function (Blueprint $table) {
    $table->foreign('employee_id')
          ->references('id')
          ->on('employees')
          ->onDelete('cascade');
});

// Repeat for: promotions, transfers, travels, resignations, terminations,
// leaves, allowances, commissions, loans, overtimes, other_payments,
// saturation_deductions, company_contributions, pay_slips, attendance,
// employee_documents
```

### 5.2: Application Layer Enforcement

Add validation in `app/Http/Requests/`:

```php
// StoreEmployeeRequest
'user_id' => [
    'required',
    'integer',
    'exists:users,id',
    Rule::unique('employees', 'user_id'), // No duplicate user_id
],

// StoreUserRequest
'type' => [
    'required',
    Rule::in(['staff', 'hr', ...]), // Only employee types allowed
],
```

Add a global scope or trait for consistency checks:

```php
trait HasConsistentIdentity
{
    public static function bootHasConsistentIdentity(): void
    {
        static::saving(function ($model) {
            // Ensure employee.id == employee.user_id if they're meant to match
        });
    }
}
```

---

## Phase 6 — Refactor Relationships

### 6.1: Fix Employee Model

**Current (WRONG):**
```php
public function user()
{
    return $this->hasOne(User::class, 'id', 'user_id');
}
```

**Fixed:**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id', 'id');
}
```

An employee BELONGS TO a user (the employee has a foreign key `user_id` pointing to `users.id`).

### 6.2: Create Backward-Compatible Accessors

To ensure old code using `$employee->id` continues working for sub-table lookups:

```php
// In Employee model
public function getEmployeeId(): int
{
    return $this->user_id; // Use user_id as the canonical employee identifier
}
```

Or better, keep `$employee->id` as the primary key but ensure it always equals `$employee->user_id`.

### 6.3: Audit All Relationship Usage

Search for these patterns and refactor:

| Old Pattern | New Pattern |
|---|---|
| `$employee->id` (for sub-table FK lookups) | Keep if id == user_id; otherwise use `$employee->user_id` |
| `Employee::find($id)` where `$id` comes from form | Determine if `$id` is `users.id` or `employees.id` |
| `$request->employee_id` | Add explicit documentation: expects `employees.id` or `users.id` |
| `Auth::id()` used as employee lookup | `Employee::where('user_id', Auth::id())->first()` |

### 6.4: Update ALL Sub-Table Controllers

Review every controller that stores `employee_id` (AwardController, AllowanceController, etc.) to verify they store the correct identifier. The current code has **inconsistent patterns** — some resolve `$request->employee_id` through `Employee::where('user_id', $request->employee_id)->first()` and store `$employee->id`, while others store `$request->employee_id` directly.

---

## Phase 7 — Testing

### 7.1: Test Cases Required

#### Unit Tests

| # | Test | Expected |
|---|---|---|
| 1 | UserObserver creates Employee on User creation | Employee exists with same user_id |
| 2 | EmployeeObserver creates User on Employee creation | User exists with matching id |
| 3 | UserObserver skips employee creation for non-emp types | No Employee created for super admin, company, etc. |
| 4 | EmployeeObserver skips user creation if user already exists | Uses existing user, no duplicate |
| 5 | Service deduplication prevents duplicate employee | Throws exception |
| 6 | FK constraint prevents orphan employee | Cannot delete user without deleting employee |
| 7 | FK cascade deletes sub-table records | Awards, leaves, etc. deleted with employee |

#### Feature Tests

| # | Test | Endpoint |
|---|---|---|
| 8 | Create user via registration → employee auto-created | POST /register |
| 9 | Create employee via HRM form → user auto-created | POST /hrm/employee |
| 10 | Delete employee → user also deleted (or vice versa) | DELETE /hrm/employee/{id} |
| 11 | Import employees → users created | POST /hrm/employee/import |
| 12 | API create employee → user auto-created | API endpoint |
| 13 | Create user with existing employee_id → prevented | POST /hrm/employee |

#### Migration/Data Tests

| # | Test | Expected |
|---|---|---|
| 14 | Rectification script fixes all orphans | Zero orphans after run |
| 15 | Rectification is idempotent | Running twice produces same result |
| 16 | Rollback restores original data | Data matches backup |
| 17 | FK addition works on existing data | No constraint violations |
| 18 | Unique constraint prevents duplicates | Cannot insert duplicate user_id |

#### Edge Cases

| # | Test | Expected |
|---|---|---|
| 19 | Concurrent user+employee creation | No duplicates (handle race conditions) |
| 20 | Soft-delete user | Employee not deleted (if using soft deletes) |
| 21 | Restore soft-deleted user | Auto-restore employee if soft-deleted |
| 22 | User type changes from staff to company | Employee not deleted, but becomes optional |
| 23 | Bulk import with existing users | Skips existing, creates missing |

### 7.2: Test Implementation Example

```php
// tests/Unit/Observers/UserObserverTest.php
class UserObserverTest extends TestCase
{
    /** @test */
    public function it_creates_employee_when_user_is_created()
    {
        $user = User::factory()->create(['type' => 'staff']);

        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /** @test */
    public function it_skips_employee_for_non_emp_types()
    {
        $user = User::factory()->create(['type' => 'super admin']);

        $this->assertDatabaseMissing('employees', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_handles_duplicate_prevention_in_observer()
    {
        $user = User::factory()->create(['type' => 'staff']);

        // Second save should not create duplicate
        $user->save();

        $this->assertEquals(1, Employee::where('user_id', $user->id)->count());
    }
}
```

---

## Implementation Order

```
Phase 1: Full Analysis (read-only) — Do NOT change any code
         ↓
Phase 2: Audit Queries against production
         ↓
Phase 3: Back up all tables
         ↓
Phase 4: Write & test rectification script on staging
         ↓
Phase 5: Deploy rectification to production (transaction-safe)
         ↓
Phase 6: Write & deploy observers + service layer
         ↓
Phase 7: Write & deploy FK migrations
         ↓
Phase 8: Refactor relationships in codebase
         ↓
Phase 9: Write & run full test suite
         ↓
Phase 10: Deploy to production
```

---

## Critical Rules

1. **DO NOT** blindly modify production data — always backup first
2. **ALWAYS** use transactions for any data modification
3. **NEVER** change primary keys without full FK dependency analysis
4. **Maintain backward compatibility** — old code using `$employee->id` must continue working
5. **Prefer incremental migration** over destructive migration
6. **Show all risky operations** before execution
7. **Produce step-by-step implementation plan** before writing any code
8. **Log everything** — every fix, every creation, every change
9. **The employee_id column name is overloaded** — treat each occurrence contextually
10. **Test on staging first** — production data is sacred
