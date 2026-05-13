# 🚀 Integration Guide - Step by Step

## Prerequisites Check
Before starting, verify:
- ✅ Laravel 11 installed
- ✅ Database connection working
- ✅ JWT authentication configured
- ✅ User model has JWTSubject interface
- ✅ Storage directory writable

---

## STEP 1: Copy Model File
**What**: Copy the ProjectFile model to your app

**File to copy**:
```
FROM: (provided earlier)
TO:   app/Models/ProjectFile.php
```

**Action**:
1. Create new file: `app/Models/ProjectFile.php`
2. Add ProjectFile model code from documentation
3. Save the file

**Verify**:
```bash
ls app/Models/ProjectFile.php
```
Should show the file exists ✓

---

## STEP 2: Copy Policy File
**What**: Copy authorization policy for file management

**File to copy**:
```
FROM: (provided earlier)
TO:   app/Policies/ProjectFilePolicy.php
```

**Action**:
1. Create directory: `app/Policies/` (if not exists)
2. Create new file: `app/Policies/ProjectFilePolicy.php`
3. Add ProjectFilePolicy code
4. Save

**Verify**:
```bash
ls app/Policies/ProjectFilePolicy.php
```
Should show the file exists ✓

---

## STEP 3: Copy Service File
**What**: Business logic for file operations

**File to copy**:
```
FROM: (provided earlier)
TO:   app/Services/ProjectFileService.php
```

**Action**:
1. Create directory: `app/Services/` (if not exists)
2. Create new file: `app/Services/ProjectFileService.php`
3. Add ProjectFileService code
4. Save

**Verify**:
```bash
ls app/Services/ProjectFileService.php
```
Should show the file exists ✓

---

## STEP 4: Copy Web Controller
**What**: Handle web UI for file manager

**File to copy**:
```
FROM: (provided earlier)
TO:   app/Http/Controllers/FileManagerController.php
```

**Action**:
1. Create new file: `app/Http/Controllers/FileManagerController.php`
2. Add FileManagerController code
3. Save

**Verify**:
```bash
ls app/Http/Controllers/FileManagerController.php
```
Should show the file exists ✓

---

## STEP 5: Copy API Controller
**What**: REST API endpoints for mobile apps

**File to copy**:
```
FROM: (provided earlier)
TO:   app/Http/Controllers/Api/ProjectFileApiController.php
```

**Action**:
1. Create directory: `app/Http/Controllers/Api/` (if not exists)
2. Create new file: `app/Http/Controllers/Api/ProjectFileApiController.php`
3. Add ProjectFileApiController code
4. Save

**Verify**:
```bash
ls app/Http/Controllers/Api/ProjectFileApiController.php
```
Should show the file exists ✓

---

## STEP 6: Copy Database Migration
**What**: Create database table for project files

**File to copy**:
```
FROM: (provided earlier)
TO:   database/migrations/2026_01_21_000001_create_project_files_table.php
```

**Action**:
1. Create new file with exact migration name
2. Add migration code
3. Save

**Verify**:
```bash
ls database/migrations/2026_01_21_000001_create_project_files_table.php
```
Should show the file exists ✓

---

## STEP 7: Copy View Files
**What**: HTML/Blade templates for file manager dashboard

**Files to copy**:
```
FROM: (provided earlier)
TO:   resources/views/file-manager/index.blade.php
TO:   resources/views/file-manager/folder-tree.blade.php
```

**Action**:
1. Create directory: `resources/views/file-manager/`
2. Create new file: `index.blade.php`
3. Add view code
4. Create new file: `folder-tree.blade.php`
5. Add tree view code
6. Save both files

**Verify**:
```bash
ls resources/views/file-manager/index.blade.php
ls resources/views/file-manager/folder-tree.blade.php
```
Both should exist ✓

---

## STEP 8: Update Routes - Web Routes
**What**: Add web routes for file manager UI

**File to edit**: `routes/web.php`

**What to add** (add this to the end of the file):
```php
// File Manager Routes
Route::middleware('auth')->group(function () {
    Route::prefix('file-manager')->group(function () {
        Route::get('/', [FileManagerController::class, 'index'])->name('file-manager.index');
        Route::post('/upload', [FileManagerController::class, 'upload'])->name('file-manager.upload');
        Route::post('/folder', [FileManagerController::class, 'createFolder'])->name('file-manager.folder');
        Route::get('/download/{id}', [FileManagerController::class, 'download'])->name('file-manager.download');
        Route::put('/rename/{id}', [FileManagerController::class, 'rename'])->name('file-manager.rename');
        Route::delete('/{id}', [FileManagerController::class, 'delete'])->name('file-manager.delete');
        Route::post('/{id}/public', [FileManagerController::class, 'makePublic'])->name('file-manager.public');
        Route::post('/{id}/archive', [FileManagerController::class, 'archive'])->name('file-manager.archive');
    });
});
```

**And add at the top** (with other imports):
```php
use App\Http\Controllers\FileManagerController;
```

**Verify**: Check that routes are added to `routes/web.php` ✓

---

## STEP 9: Update Routes - API Routes
**What**: Add REST API routes

**File to edit**: `routes/api.php`

**What to add** (add this code):
```php
// Project Files API Routes
Route::middleware('auth:api')->prefix('project-files')->group(function () {
    Route::get('/', [ProjectFileApiController::class, 'index']);
    Route::post('/', [ProjectFileApiController::class, 'store']);
    Route::get('/{id}', [ProjectFileApiController::class, 'show']);
    Route::put('/{id}', [ProjectFileApiController::class, 'update']);
    Route::delete('/{id}', [ProjectFileApiController::class, 'destroy']);
    Route::post('/folder', [ProjectFileApiController::class, 'createFolder']);
    Route::get('/{id}/download', [ProjectFileApiController::class, 'download']);
    Route::get('/tree/{project_id}', [ProjectFileApiController::class, 'getTree']);
    Route::get('/stats/{project_id}', [ProjectFileApiController::class, 'getStats']);
    Route::get('/search', [ProjectFileApiController::class, 'search']);
});
```

**And add at the top** (with other imports):
```php
use App\Http\Controllers\Api\ProjectFileApiController;
```

**Verify**: Check that routes are added to `routes/api.php` ✓

---

## STEP 10: Update AuthServiceProvider
**What**: Register the authorization policy

**File to edit**: `app/Providers/AuthServiceProvider.php`

**Find this section**:
```php
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
```

**Add this import**:
```php
use App\Models\ProjectFile;
use App\Policies\ProjectFilePolicy;
```

**Find the `$policies` array**:
```php
protected $policies = [
    // 'Model' => 'Policy',
];
```

**Update to**:
```php
protected $policies = [
    ProjectFile::class => ProjectFilePolicy::class,
];
```

**Verify**: Check that policy is registered ✓

---

## STEP 11: Run Database Migration
**What**: Create the project_files table in your database

**Command to run**:
```bash
cd c:\wamp64\www\SitePilot
php artisan migrate
```

**Expected output**:
```
Migrating: 2026_01_21_000001_create_project_files_table
Migrated:  2026_01_21_000001_create_project_files_table (xxx ms)
```

**Verify** - Check database:
```bash
php artisan tinker
# Then type:
DB::table('project_files')->count()
# Should return: 0
```

Or use MySQL:
```bash
mysql -u root -p SitePilot -e "DESCRIBE project_files;"
```
Should show all columns ✓

---

## STEP 12: Clear Application Cache
**What**: Refresh Laravel's cache so it recognizes new classes

**Commands to run**:
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

**Expected output**:
```
Application cache cleared!
Route cache cleared!
Configuration cache cleared!
```

**Verify**: All commands completed without errors ✓

---

## STEP 13: Check Storage Directory
**What**: Ensure storage/projects directory exists and is writable

**Create directory**:
```bash
mkdir c:\wamp64\www\SitePilot\storage\projects
```

**Set permissions** (Windows):
- Right-click `storage` folder
- Properties → Security
- Edit → Select "IIS_IUSRS"
- Check "Modify" and "Write"
- Click OK

**Or use command**:
```bash
icacls "c:\wamp64\www\SitePilot\storage" /grant:r "IIS_IUSRS:(OI)(CI)F" /T
```

**Verify**: Directory exists and is writable ✓

---

## STEP 14: Test Web Interface
**What**: Access the file manager dashboard

**Steps**:
1. Open browser
2. Navigate to: `http://localhost/SitePilot/file-manager`
3. Login if asked (use your admin account)
4. Should see file manager dashboard

**Expected**:
- ✓ Project selector dropdown visible
- ✓ Folder tree on left side
- ✓ File list in middle
- ✓ Upload button at top
- ✓ "Create Folder" button
- ✓ Storage stats at bottom

**If something is missing**:
- Check browser console (F12 → Console)
- Check Laravel logs: `storage/logs/laravel-*.log`

---

## STEP 15: Test File Upload
**What**: Upload a test file

**Steps**:
1. Click "Upload File" button
2. Select a small test file (e.g., .txt, .pdf)
3. Click "Upload"
4. Wait for success message

**Expected**:
- ✓ Toast notification: "File uploaded successfully"
- ✓ File appears in list
- ✓ File details visible (name, size, date)

**If upload fails**:
- Check browser console for JavaScript errors
- Check Laravel logs for PHP errors
- Verify file type is allowed (see config in code)
- Check file size (max 100MB)

---

## STEP 16: Test File Operations
**What**: Test rename, download, delete

**Test Rename**:
1. Right-click uploaded file
2. Click "Rename"
3. Enter new name
4. Click "Rename"
5. Verify name changed

**Test Download**:
1. Click file in list
2. Click "Download" button
3. File should download to your computer

**Test Delete**:
1. Right-click file
2. Click "Delete"
3. Click "Confirm"
4. File should disappear from list

**Expected**: All operations work smoothly ✓

---

## STEP 17: Get API Token
**What**: Get JWT token for API testing

**Option A - Use Tinker**:
```bash
php artisan tinker
# Then type:
$user = User::find(1);
$token = $user->createToken('test')->plainTextToken;
echo $token;
```

**Option B - Use API Endpoint**:
```bash
curl -X POST http://localhost/SitePilot/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

**Copy the token** (looks like: `123abc456def...`)

---

## STEP 18: Test API - List Files
**What**: Test API endpoint to list files

**Command**:
```bash
curl -X GET "http://localhost/SitePilot/api/project-files?project_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

**Replace**: `YOUR_TOKEN_HERE` with token from Step 17

**Expected response**:
```json
{
  "success": true,
  "message": "Files retrieved successfully",
  "data": {
    "files": [...],
    "folders": [...]
  }
}
```

---

## STEP 19: Test API - Upload File
**What**: Test file upload via API

**Command**:
```bash
curl -X POST "http://localhost/SitePilot/api/project-files" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "file=@/path/to/file.txt" \
  -F "project_id=1" \
  -F "folder_path=/"
```

**Expected response**:
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": { "file_id": 123, ... }
}
```

---

## STEP 20: Verify Cross-Project Isolation
**What**: Ensure users can't access other projects' files

**Test**:
1. Create file in Project 1
2. Login as user from Project 2
3. Try to access Project 1 files
4. Should get "Unauthorized" error

**Command**:
```bash
# Try to get file from another project
curl -X GET "http://localhost/SitePilot/api/project-files/999" \
  -H "Authorization: Bearer USER2_TOKEN"
```

**Expected**: 403 Forbidden error ✓

---

## Troubleshooting

### Issue: "Class not found" error
**Solution**:
```bash
php artisan cache:clear
php artisan route:clear
composer dump-autoload
```

### Issue: "Table not found" error
**Solution**:
```bash
php artisan migrate
php artisan cache:clear
```

### Issue: "Unauthorized" on API
**Solution**:
- Check token is valid
- Check header format: `Authorization: Bearer TOKEN`
- Verify user exists in database
- Check `auth:api` middleware is working

### Issue: Upload fails
**Solution**:
- Check storage/projects directory exists and is writable
- Check file type is in allowed list
- Check file size is under limit
- Check permissions on storage folder

### Issue: CORS errors
**Solution**:
- For mobile apps, ensure you're using same domain
- Or add CORS middleware to API routes

---

## Success Checklist

After completing all steps:

- [ ] ProjectFile model created
- [ ] ProjectFilePolicy created
- [ ] ProjectFileService created
- [ ] FileManagerController created
- [ ] ProjectFileApiController created
- [ ] Migration created
- [ ] Views created
- [ ] Routes updated (web + API)
- [ ] AuthServiceProvider updated
- [ ] Migration executed
- [ ] Cache cleared
- [ ] Storage directory exists
- [ ] Web UI accessible at /file-manager
- [ ] Can upload files
- [ ] Can download files
- [ ] Can rename files
- [ ] Can delete files
- [ ] API token working
- [ ] API list endpoint works
- [ ] Cross-project isolation verified

---

## Quick Reference

| Step | Action | Command | Status |
|------|--------|---------|--------|
| 1 | Copy ProjectFile model | `cp ...` | □ |
| 2 | Copy ProjectFilePolicy | `cp ...` | □ |
| 3 | Copy ProjectFileService | `cp ...` | □ |
| 4 | Copy FileManagerController | `cp ...` | □ |
| 5 | Copy ProjectFileApiController | `cp ...` | □ |
| 6 | Copy Migration | `cp ...` | □ |
| 7 | Copy Views | `cp ...` | □ |
| 8 | Update web routes | Edit routes/web.php | □ |
| 9 | Update API routes | Edit routes/api.php | □ |
| 10 | Update AuthServiceProvider | Edit app/Providers/AuthServiceProvider.php | □ |
| 11 | Run migration | `php artisan migrate` | □ |
| 12 | Clear cache | `php artisan cache:clear` | □ |
| 13 | Check storage | Create storage/projects | □ |
| 14 | Test web UI | Open /file-manager | □ |
| 15 | Test upload | Upload test file | □ |
| 16 | Test operations | Rename, download, delete | □ |
| 17 | Get API token | `php artisan tinker` | □ |
| 18 | Test API list | Run curl command | □ |
| 19 | Test API upload | Run curl command | □ |
| 20 | Test isolation | Verify cross-project | □ |

---

## Getting Help

**If stuck on any step**:
1. Check Laravel logs: `storage/logs/laravel-*.log`
2. Check browser console: Open DevTools (F12)
3. Run: `php artisan tinker` to test code manually
4. Review documentation files for more details

**Documentation files**:
- `PROJECT_FILE_MANAGER.md` - Full documentation
- `PROJECT_FILE_MANAGER_QUICK_START.md` - Quick reference
- `API_REFERENCE.md` - API details
- `DEPLOYMENT_CHECKLIST.md` - Deployment steps

---

**You're all set! Follow these 20 steps and your file manager will be fully integrated.** 🚀
