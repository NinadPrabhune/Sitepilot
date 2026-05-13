# User-Employee Data Consistency Fix

## Problem Description

There is a data consistency issue between the `users` table and the `employees` table in the Laravel application. Every employee entry must have a corresponding user record, but mismatches were occurring due to improper deletion handling.

### Issue Symptoms
- Count mismatch between users and employees tables
- Orphan employee records (employees without users)
- Orphan user records (users without employees)

---

## Root Cause Analysis

### Identified Problematic Controllers/Functions

| # | File | Issue | Creates |
|---|------|-------|---------|
| 1 | [`app/Http/Controllers/ProfileController.php:53`](app/Http/Controllers/ProfileController.php) | `$user->delete()` without Employee deletion | Orphan Employees |
| 2 | [`app/Http/Controllers/UserController.php:430`](app/Http/Controllers/UserController.php) | `$user->delete()` without Employee deletion | Orphan Employees |
| 3 | [`app/Http/Controllers/Api/AuthApiController.php:448`](app/Http/Controllers/Api/AuthApiController.php) | `$user->delete()` was **commented out** | Orphan Users |
| 4 | [`app/Http/Controllers/Api/EmployeeApiController.php:1147`](app/Http/Controllers/Api/EmployeeApiController.php) | Query builder misuse: `User::where('id', $id)->delete()` returns query, not user object | User not deleted |
| 5 | [`packages/workdo/Hrm/src/Http/Controllers/EmployeeController.php:695`](packages/workdo/Hrm/src/Http/Controllers/EmployeeController.php) | Employee deleted but User not deleted | Orphan Users |

### Relationship
- **User → Employee**: One-to-one via `user_id` in employees table
- **Defined in**: [`app/Models/User.php:632-634`](app/Models/User.php)

```php
public function employee()
{
    return $this->hasOne(Employee::class, 'user_id');
}
```

---

## Fixes Applied

### 1. ProfileController.php
**File**: `app/Http/Controllers/ProfileController.php`

Added Employee deletion before User deletion:
```php
// Delete related Employee record if exists
$employee = Employee::where('user_id', $user->id)->first();
if ($employee) {
    \Log::info('ProfileController@destroy: Deleting related Employee ID: ' . $employee->id . ' for User ID: ' . $user->id);
    $employee->delete();
}

\Log::info('ProfileController@destroy: Deleting User ID: ' . $user->id);
$user->delete();
```

### 2. UserController.php
**File**: `app/Http/Controllers/UserController.php`

Added Employee deletion before User deletion:
```php
// Delete related Employee record if exists
$employee = Employee::where('user_id', $user->id)->first();
if ($employee) {
    \Log::info('UserController@destroy: Deleting related Employee ID: ' . $employee->id . ' for User ID: ' . $user->id);
    $employee->delete();
}

\Log::info('UserController@destroy: Deleting User ID: ' . $user->id);
$user->delete();
```

### 3. AuthApiController.php
**File**: `app/Http/Controllers/Api/AuthApiController.php`

Uncommented and fixed User deletion:
```php
// Delete related Employee record if exists
$employee = Employee::where('user_id', $user->id)->first();
if ($employee) {
    \Log::info('AuthApiController@deleteAccount: Deleting related Employee ID: ' . $employee->id . ' for User ID: ' . $userId);
    $employee->delete();
}

// ... existing table cleanup code ...

\Log::info('AuthApiController@deleteAccount: Deleting User ID: ' . $userId);
$user->delete();
```

### 4. EmployeeApiController.php
**File**: `app/Http/Controllers/Api/EmployeeApiController.php`

Fixed query builder misuse:
```php
// Before (WRONG):
$user = User::where('id', $id);  // Returns query builder!
$user->delete();  // This doesn't delete anything

// After (CORRECT):
$user = User::find($id);
if ($user) {
    \Log::info('EmployeeApiController@destroy: Deleting related User ID: ' . $id);
    $user->delete();
}
```

### 5. HRM EmployeeController (Web)
**File**: `packages/workdo/Hrm/src/Http/Controllers/EmployeeController.php`

Added User deletion after Employee deletion with safeguards:
```php
// Check if employee has attendance records
$hasAttendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $employee->id)->exists();
if ($hasAttendance) {
    return redirect()->back()->with('error', __('Cannot delete employee. Attendance records exist for this employee.'));
}

event(new DestroyEmployee($employee));
$employee->delete();

// Delete the related User record (skip for super admin and company users)
$user = User::find($id);
if ($user && !in_array($user->type, ['super admin', 'company'])) {
    \Log::info('EmployeeController@destroy: Deleting related User ID: ' . $id);
    $user->delete();
} else if ($user && in_array($user->type, ['super admin', 'company'])) {
    \Log::info('EmployeeController@destroy: Skipped deleting User ID: ' . $id . ' (type: ' . $user->type + ')');
}
```

---

## Audit & Logging

All fixed controllers now include logging to track:
- When a user/employee is deleted
- From which controller (Web/API)
- Related deletion status

### Log Format
```
INFO: ProfileController@destroy: Deleting related Employee ID: {id} for User ID: {id}
INFO: UserController@destroy: Deleting User ID: {id}
INFO: EmployeeApiController@destroy: Deleting related User ID: {id}
```

---

## Mismatch Detection Command

### Check Consistency (Read-only)
```bash
php artisan users:check-consistency
```

### Auto-fix Orphan Employees
```bash
php artisan users:check-consistency --fix
```

### Output Example
```
=== CONSISTENCY CHECK RESULTS ===

Found X orphan Employee(s) (Employees without User):
+----+------+-------+----------+-----------+
| ID | Name | User ID | Workspace | Created By |
+----+------+-------+----------+-----------+

Found X orphan User(s) (Users without Employee):
+----+------+------------------+-----------+

=== SUMMARY ===
Total Users: X
Total Employees: X
Orphan Employees: X
Orphan Users: X
```

---

## SQL Queries for Manual Check

### Find Employees without Users
```sql
SELECT * FROM employees 
WHERE user_id NOT IN (SELECT id FROM users);
```

### Find Users without Employees
```sql
SELECT * FROM users 
WHERE id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL)
AND type != 'super admin';
```

---

---

## Safeguards Implemented

### 1. User Type Protection
When deleting an Employee, the related User is NOT deleted if:
- `users.type` = 'super admin'
- `users.type` = 'company'

### 2. Attendance Check
Deletion is blocked if the Employee has attendance records in the `hrm_attendances` table.

### 3. Updated Controllers with Safeguards

All 5 controllers now include:
- Attendance check before deletion
- User type check (for Employee deletion flow)
- Comprehensive logging

---

## Preventive Measures

### 1. Database-Level Cascade (Recommended)
Add foreign key constraint with cascade delete:

```php
// In migration
Schema::table('employees', function (Blueprint $table) {
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade');
});
```

### 2. Eloquent Model Events (Alternative)
Add events to User model:

```php
// In app/Models/User.php
protected static function boot()
{
    parent::boot();

    static::deleting(function ($user) {
        if ($user->employee) {
            $user->employee->delete();
        }
    });
}
```

### 3. Code Review Checklist
When implementing user/employee deletion:
- [ ] Always delete in consistent order (Employee first, then User OR User first, then Employee)
- [ ] Use proper Eloquent methods (`find()`, not query builder)
- [ ] Add logging for audit trail
- [ ] Test both Web and API endpoints

---

## Files Modified

| File | Change Type |
|------|-------------|
| `app/Http/Controllers/ProfileController.php` | Added Employee deletion + logging |
| `app/Http/Controllers/UserController.php` | Added Employee deletion + logging |
| `app/Http/Controllers/Api/AuthApiController.php` | Enabled User deletion + logging |
| `app/Http/Controllers/Api/EmployeeApiController.php` | Fixed query builder + logging |
| `packages/workdo/Hrm/src/Http/Controllers/EmployeeController.php` | Added User deletion + logging |
| `app/Console/Commands/CheckUserEmployeeConsistency.php` | New command created |

---

## Testing Checklist

- [ ] Delete user from Profile page → Employee should be deleted
- [ ] Delete user from User management → Employee should be deleted
- [ ] Delete account from API → Both User and Employee deleted
- [ ] Delete employee from HRM module → User should be deleted
- [ ] Run `php artisan users:check-consistency` → Should show 0 mismatches
