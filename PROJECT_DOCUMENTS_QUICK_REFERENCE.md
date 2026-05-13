# Project Document Management System - Quick Reference

## 🚀 Quick Start (3 Steps)

### Step 1: Run Migration
```bash
php artisan migrate
```

### Step 2: Create Directory
```bash
mkdir -p storage/projects && chmod 775 storage/projects
```

### Step 3: Clear Cache
```bash
php artisan cache:clear && php artisan route:clear
```

**✅ Done! Access at**: http://localhost/project-documents

---

## 📂 File Locations

| Component | Location |
|-----------|----------|
| Model | `app/Models/ProjectDocument.php` |
| Service | `app/Services/ProjectDocumentService.php` |
| Web Controller | `app/Http/Controllers/ProjectDocumentController.php` |
| API Controller | `app/Http/Controllers/Api/ProjectDocumentApiController.php` |
| Policy | `app/Policies/ProjectDocumentPolicy.php` |
| Middleware | `app/Http/Middleware/ValidateProjectAccess.php` |
| Web View | `resources/views/project-documents/index.blade.php` |
| Migration | `database/migrations/2024_01_20_000001_...` |
| Web Routes | `routes/web.php` |
| API Routes | `routes/api.php` |

---

## 🔗 Main Routes

### Web Routes
```
GET    /project-documents                           → List projects & documents
POST   /project-documents/upload                    → Upload file
DELETE /project-documents/{projectId}/delete/{id}   → Delete document
PUT    /project-documents/{projectId}/rename/{id}   → Rename document
POST   /project-documents/{projectId}/folder        → Create folder
GET    /project-documents/{projectId}/download/{id} → Download file
POST   /project-documents/{projectId}/switch        → Switch active project
```

### API Routes (with Bearer token)
```
GET    /api/projects/{id}/documents                     → List documents
GET    /api/projects/{id}/documents/structure           → Folder structure
POST   /api/projects/{id}/documents/upload              → Upload file
GET    /api/projects/{id}/documents/{docId}             → Get details
PUT    /api/projects/{id}/documents/{docId}             → Update document
DELETE /api/projects/{id}/documents/{docId}             → Delete document
POST   /api/projects/{id}/documents/folders             → Create folder
GET    /api/projects/{id}/documents/{docId}/download    → Download file
GET    /api/projects/{id}/documents/stats               → Get statistics
```

---

## 🔑 Key Features

### Web Interface
- ✅ Dynamic project sidebar
- ✅ Drag-and-drop upload
- ✅ Folder organization
- ✅ File management (download, rename, delete)
- ✅ Real-time statistics
- ✅ Responsive design

### REST API
- ✅ Sanctum authentication
- ✅ Complete CRUD operations
- ✅ File upload/download
- ✅ Folder management
- ✅ Storage statistics
- ✅ JSON responses

### Security
- ✅ Project-level access control
- ✅ File type validation
- ✅ Size limits (100MB max)
- ✅ Authorization checks
- ✅ Audit logging

---

## 📝 Configuration

### File Size Limit
```php
// File: app/Services/ProjectDocumentService.php
'max_file_size' => 104857600,  // Change this value
```

### Allowed File Types
```php
'allowed_extensions' => [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    'zip', 'rar', '7z',
    'mp4', 'avi', 'mov', 'mkv',
    'mp3', 'wav', 'flac', 'm4a',
    'txt', 'csv'
]
```

### Storage Disk
```php
'storage_disk' => 'local',  // or 's3', 'ftp', etc.
'base_storage_path' => 'storage/projects',
```

---

## 🧪 Test API Quickly

### Get API Token
```bash
php artisan tinker
> User::first()->createToken('api')->plainTextToken
```

### List Documents
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Upload File
```bash
curl -X POST "http://localhost:8000/api/projects/1/documents/upload" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@document.pdf" \
  -F "folder_path=reports"
```

### Download File
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/1/download" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o document.pdf
```

### Get Statistics
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/stats" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔐 Authorization Rules

| Action | Super Admin | Regular User |
|--------|:----------:|:----------:|
| View all projects | ✅ | ❌* |
| Create document | ✅ | ✅* |
| Download document | ✅ | ✅* |
| Rename own document | ✅ | ✅ |
| Delete own document | ✅ | ✅ |
| Rename others' document | ✅ | ❌ |
| Delete others' document | ✅ | ❌ |

*Only assigned projects/documents

---

## 🎨 Supported File Types (19)

**Documents** (9): pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv  
**Images** (5): jpg, jpeg, png, gif, webp, svg  
**Archives** (3): zip, rar, 7z  
**Media** (4): mp4, avi, mov, mkv, mp3, wav, flac, m4a  

---

## 🐛 Common Issues & Fixes

### Migration Fails
```bash
php artisan migrate:rollback
php artisan migrate
```

### Storage Directory Permission Error
```bash
chmod -R 775 storage/projects
chown -R www-data:www-data storage/projects
```

### Routes Not Found
```bash
php artisan route:clear
php artisan route:list | grep project-documents
```

### API Returns 401
```bash
# Generate new token
php artisan tinker
> User::first()->createToken('api')->plainTextToken
```

### Files Not Uploading
- Check file size (< 100MB)
- Verify file type is allowed
- Ensure storage directory writable

---

## 📚 Documentation

| Guide | Purpose |
|-------|---------|
| `PROJECT_DOCUMENTS_README.md` | Main overview & features |
| `PROJECT_DOCUMENTS_API.md` | Complete API reference |
| `PROJECT_DOCUMENTS_WEB_GUIDE.md` | User guide for web UI |
| `PROJECT_DOCUMENTS_SETUP.md` | Installation & config |
| `PROJECT_DOCUMENTS_IMPLEMENTATION_SUMMARY.md` | Implementation details |

---

## 💡 Tips

### Performance
- ✅ Batch upload files together
- ✅ Organize with folders
- ✅ Delete old versions
- ✅ Use compression for large files

### Organization
- ✅ Use meaningful folder names
- ✅ Include dates in filenames
- ✅ Keep file names concise
- ✅ Regular cleanup

### Security
- ✅ Only upload needed files
- ✅ Don't store credentials
- ✅ Avoid executable files
- ✅ Verify before downloading

---

## 🔍 Useful Commands

```bash
# View routes
php artisan route:list | grep project-documents

# View database
php artisan tinker
> ProjectDocument::all()

# Check logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear

# Generate API token
php artisan tinker
> User::first()->createToken('api')->plainTextToken

# Database operations
php artisan migrate
php artisan migrate:rollback
php artisan migrate:refresh
```

---

## 🎯 Helper Functions

```php
// Get active project ID
$projectId = getActiveProject();

// Get active project name
$projectName = getActiveProjectName();

// In Service
$service = app(ProjectDocumentService::class);

// Upload file
$result = $service->uploadFile($file, $projectId, $userId);

// Get files
$documents = $service->getProjectFiles($projectId);

// Check access
$hasAccess = $service->userHasProjectAccess($userId, $projectId);

// Get stats
$stats = $service->getProjectStorageStats($projectId);
```

---

## 📊 Database

### Table: project_documents
```sql
id                  → Primary Key
project_id          → Project (FK)
user_id             → Uploader (FK)
file_name          → Original name
file_path          → Storage path
file_type          → MIME type
file_size          → Bytes
storage_disk       → Disk name
description        → Optional
folder_path        → Folder location
created_at         → Timestamp
updated_at         → Timestamp
deleted_at         → Soft delete
```

### Indexes
- `(project_id, user_id)` - Fast authorization
- `(project_id, folder_path)` - Fast folder queries

---

## 🚀 Deployment

### Production Checklist
- [ ] Database migrated
- [ ] Storage writable
- [ ] Cache cleared
- [ ] Routes registered
- [ ] HTTPS enabled
- [ ] Backups configured
- [ ] Monitoring enabled
- [ ] Logs configured
- [ ] CORS setup
- [ ] Rate limiting

### Deploy Command
```bash
php artisan migrate
php artisan cache:clear
chmod -R 775 storage
```

---

## 🆘 Quick Support

**Issue**: Files won't upload  
**Fix**: `chmod -R 775 storage/projects`

**Issue**: API 401 error  
**Fix**: `php artisan tinker` → `User::first()->createToken('api')`

**Issue**: Routes not found  
**Fix**: `php artisan route:clear`

**Issue**: Database error  
**Fix**: `php artisan migrate:rollback && php artisan migrate`

---

## 📞 Documentation Links

- Main README: [PROJECT_DOCUMENTS_README.md](PROJECT_DOCUMENTS_README.md)
- API Docs: [PROJECT_DOCUMENTS_API.md](PROJECT_DOCUMENTS_API.md)
- Web Guide: [PROJECT_DOCUMENTS_WEB_GUIDE.md](PROJECT_DOCUMENTS_WEB_GUIDE.md)
- Setup: [PROJECT_DOCUMENTS_SETUP.md](PROJECT_DOCUMENTS_SETUP.md)
- Summary: [PROJECT_DOCUMENTS_IMPLEMENTATION_SUMMARY.md](PROJECT_DOCUMENTS_IMPLEMENTATION_SUMMARY.md)

---

## ✅ Checklist

- [ ] Database migration run
- [ ] Storage directory created
- [ ] Cache cleared
- [ ] Routes tested
- [ ] Web UI accessible
- [ ] API tested with token
- [ ] File upload working
- [ ] Authorization verified
- [ ] Documentation read
- [ ] Team trained

---

**Version**: 1.0.0  
**Date**: January 20, 2024  
**Status**: Production Ready  

🎉 **System Ready for Use!**
