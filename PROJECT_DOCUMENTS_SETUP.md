# Project Document Management System - Installation & Setup Guide

## Prerequisites
- Laravel 11.0+
- PHP 8.2+
- Database (MySQL, PostgreSQL, etc.)
- Composer
- Storage disk configured (local or cloud)

---

## Installation Steps

### Step 1: Database Migration

Run the migration to create the `project_documents` table:

```bash
php artisan migrate
```

This creates:
- `project_documents` table with document metadata
- Foreign key relationships to projects and users
- Indexes for optimal performance

### Step 2: Configuration Files

The following configuration files are already in place:

#### Service Provider Registration
No additional provider registration needed. The service is auto-discovered.

#### Model Setup
- `App\Models\ProjectDocument` - Document model with relationships
- Already includes soft deletes and helpful scopes

#### Controller Setup
- `App\Http\Controllers\ProjectDocumentController` - Web interface
- `App\Http\Controllers\Api\ProjectDocumentApiController` - REST API

### Step 3: Route Registration

Routes are already registered in:
- `routes/web.php` - Web UI routes
- `routes/api.php` - API routes with Sanctum authentication

### Step 4: Create Storage Directories

Ensure storage directories exist:

```bash
mkdir -p storage/projects
chmod -R 775 storage/projects
```

### Step 5: Clear Application Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## Configuration

### Environment Variables

Add to `.env` if needed:

```env
# File Storage
FILESYSTEM_DISK=local
BACKUP_FILESYSTEM_DISK=local

# API Configuration
API_DOCUMENT_MAX_SIZE=104857600  # 100MB in bytes
```

### Service Configuration

The `ProjectDocumentService` class has default configuration:

```php
private $config = [
    'max_file_size' => 104857600,      // 100MB
    'allowed_extensions' => [ /* see service file */ ],
    'storage_disk' => 'local',
    'base_storage_path' => 'storage/projects',
];
```

To customize, modify in `app/Services/ProjectDocumentService.php` or pass custom config:

```php
$service = app(ProjectDocumentService::class);
$service->setConfig([
    'max_file_size' => 52428800,  // 50MB instead
    'storage_disk' => 's3',  // Use S3 instead of local
]);
```

---

## File Structure

```
app/
├── Models/
│   └── ProjectDocument.php          # Document model
├── Services/
│   └── ProjectDocumentService.php   # Core business logic
├── Http/
│   ├── Controllers/
│   │   ├── ProjectDocumentController.php       # Web controller
│   │   └── Api/
│   │       └── ProjectDocumentApiController.php # API controller
│   ├── Middleware/
│   │   └── ValidateProjectAccess.php           # Authorization
│   ├── Resources/
│   │   └── ProjectDocumentResource.php         # API response format
│   └── Policies/
│       └── ProjectDocumentPolicy.php           # Authorization policy
├── Helper/
│   └── helper.php                  # Contains getActiveProject()
└── Providers/
    └── AuthServiceProvider.php      # Policy registration

resources/
└── views/
    └── project-documents/
        ├── index.blade.php          # Main interface
        └── document-item.blade.php  # Document component

routes/
├── web.php                         # Web routes
└── api.php                         # API routes

database/
└── migrations/
    └── 2024_01_20_000001_create_project_documents_table.php
```

---

## Authorization & Policies

### Policy Registration

Policies are auto-discovered by Laravel. The `ProjectDocumentPolicy` handles:
- View documents
- Create documents
- Update (rename) documents
- Delete documents
- Force delete (admin only)

### Usage in Controllers

```php
// Authorize action
$this->authorize('view', $document);
$this->authorize('delete', $document);

// Check without throwing
if ($user->can('update', $document)) {
    // Allow action
}
```

### Middleware Registration

The middleware is manually applied to routes. To apply globally:

```php
// In bootstrap/app.php or middleware stack
'project-access' => \App\Http\Middleware\ValidateProjectAccess::class,
```

---

## Testing

### Manual Testing

#### Test Upload
```bash
curl -X POST "http://localhost:8000/api/projects/1/documents/upload" \
  -H "Authorization: Bearer token" \
  -F "file=@test.pdf" \
  -F "folder_path=documents"
```

#### Test List Documents
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents" \
  -H "Authorization: Bearer token"
```

#### Test Download
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/1/download" \
  -H "Authorization: Bearer token" \
  -o downloaded.pdf
```

### Unit Tests (Future Implementation)

Create tests in `tests/Feature/ProjectDocumentTest.php`:

```php
public function test_user_can_upload_document()
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    
    $response = $this->actingAs($user)
        ->post('/project-documents/upload', [
            'file' => UploadedFile::fake()->create('document.pdf'),
            'project_id' => $project->id,
        ]);
    
    $response->assertSuccessful();
}
```

---

## Troubleshooting Installation

### Issue: Migration Fails
**Solution**:
```bash
php artisan migrate:rollback
php artisan migrate
```

### Issue: Storage Directory Not Writable
**Solution**:
```bash
chmod -R 775 storage/projects
chown -R www-data:www-data storage/projects  # For Apache
```

### Issue: Routes Not Registering
**Solution**:
```bash
php artisan route:clear
php artisan route:list | grep project-documents
```

### Issue: API Returns 401 Unauthorized
**Solution**:
- Verify Sanctum token is valid
- Check `auth:sanctum` middleware is enabled
- Generate new token: `php artisan tinker` then `User::first()->createToken('api')`

---

## Database Indexes

The migration creates the following indexes for performance:

```sql
-- Composite indexes for faster queries
CREATE INDEX idx_project_user ON project_documents(project_id, user_id);
CREATE INDEX idx_project_folder ON project_documents(project_id, folder_path);
```

These optimize queries for:
- Finding all documents in a project
- Filtering by folder
- User authorization checks

---

## Security Configuration

### 1. Enable CORS for API (if needed)

```php
// config/cors.php
'allowed_origins' => ['https://yourapp.com'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

### 2. Implement Rate Limiting

```php
// In routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    // API routes here
});
```

### 3. Add Audit Logging

Create a listener for document operations:

```php
// app/Listeners/LogDocumentActivity.php
public function handle(DocumentUploaded $event)
{
    Log::channel('documents')->info('Document uploaded', [
        'user_id' => $event->user_id,
        'document_id' => $event->document_id,
        'ip_address' => request()->ip(),
    ]);
}
```

---

## Performance Optimization

### 1. Eager Load Relationships

```php
// In controller
$documents = ProjectDocument::with(['project', 'uploadedBy'])
    ->where('project_id', $projectId)
    ->get();
```

### 2. Cache Folder Structure

```php
Cache::remember("project_{$projectId}_structure", 3600, function () {
    return $this->documentService->getProjectFolderStructure($projectId);
});
```

### 3. Pagination for Large Projects

```php
$documents = ProjectDocument::where('project_id', $projectId)
    ->paginate(50);
```

---

## Backup & Recovery

### Backup Documents

```bash
# Backup storage directory
tar -czf backup_documents_$(date +%Y%m%d).tar.gz storage/projects/

# Backup database
mysqldump -u root -p sitepilot > backup_db_$(date +%Y%m%d).sql
```

### Restore Documents

```bash
# Restore from tar
tar -xzf backup_documents_20240120.tar.gz -C /

# Restore database
mysql -u root -p sitepilot < backup_db_20240120.sql
```

---

## Upgrading

### Version Updates

When updating the system:

1. Backup existing data
2. Run migrations: `php artisan migrate`
3. Clear caches: `php artisan cache:clear`
4. Test API endpoints
5. Verify file access

---

## Monitoring

### Log Important Events

Logs are stored in `storage/logs/laravel.log`

Monitor:
- File upload failures
- Authorization errors
- Disk space usage
- API performance

### View Recent Activity

```php
// In controller
$recent = ProjectDocument::latest()->take(10)->get();
```

---

## Support

For issues or questions:
1. Check the documentation files
2. Review Laravel documentation
3. Check application logs
4. Contact support team

---

## Checklist

- [ ] Database migration completed
- [ ] Storage directories created and writable
- [ ] Routes registered correctly
- [ ] Sanctum authentication configured
- [ ] CORS configured (if needed)
- [ ] Logging configured
- [ ] Backups set up
- [ ] Testing completed
- [ ] Performance verified

---

## Version Information

**System Version**: 1.0.0  
**Laravel Version**: 11.0+  
**PHP Version**: 8.2+  
**Last Updated**: January 20, 2024
