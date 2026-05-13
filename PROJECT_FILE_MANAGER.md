# Project File Management System - Documentation

## Overview

A comprehensive project-based file management system with secure, project-isolated storage, REST APIs for mobile apps, and an intuitive web UI. Built with Laravel, featuring JWT authentication, role-based access control, and support for nested directories.

## Features

### ✅ Implemented Features

1. **Project-Isolated File Storage**
   - Each project has isolated storage at `/storage/projects/{project_id}`
   - Dynamic root resolution based on active project
   - Secure cross-project isolation

2. **File Operations**
   - ✅ Upload files with validation
   - ✅ Download files with tracking
   - ✅ Rename files and folders
   - ✅ Delete files (soft delete)
   - ✅ Create nested directory structures
   - ✅ Archive files
   - ✅ Make files public/private

3. **Web UI**
   - ✅ Responsive dashboard
   - ✅ Project switcher
   - ✅ Folder tree sidebar
   - ✅ File listing with pagination
   - ✅ Breadcrumb navigation
   - ✅ Storage statistics
   - ✅ Action buttons (download, rename, delete)
   - ✅ Matches existing Tabler UI theme

4. **REST APIs with JWT**
   - ✅ List files/folders
   - ✅ Upload files
   - ✅ Create folders
   - ✅ Download files
   - ✅ Rename files
   - ✅ Delete files
   - ✅ Search functionality
   - ✅ Get folder tree structure
   - ✅ Storage statistics
   - ✅ JSON responses

5. **Security**
   - ✅ JWT authentication middleware
   - ✅ Project-aware policies
   - ✅ Cross-project isolation
   - ✅ File type validation
   - ✅ File size limits (100MB default)
   - ✅ User permission checks
   - ✅ Soft deletes with audit trail

6. **Database**
   - ✅ project_files table with full metadata
   - ✅ Relationships (project, user)
   - ✅ Full-text search support
   - ✅ Download statistics
   - ✅ Archive status

## Installation

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `project_files` table with:
- File metadata (name, size, type, path)
- Project and user relationships
- Archive and public flags
- Download tracking
- Full-text search index

### 2. Clear Cache

```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

## File Structure

### New Files Created

```
app/
├── Models/
│   └── ProjectFileNew.php              # Model with relations and helpers
├── Policies/
│   └── ProjectFilePolicy.php        # Authorization policy
├── Services/
│   └── ProjectFileService.php       # Business logic for file operations
├── Http/Controllers/
│   ├── FileManagerController.php    # Web UI controller
│   └── Api/
│       └── ProjectFileApiController.php  # REST API controller
└── Providers/
    └── AuthServiceProvider.php      # Updated with ProjectFilePolicy

resources/views/file-manager/
├── index.blade.php                  # Main file manager page
└── folder-tree.blade.php            # Folder tree component

database/migrations/
└── 2026_01_21_000001_create_project_files_table.php

routes/
├── web.php                          # Added file manager routes
└── api.php                          # Added API routes
```

## Usage

### Web Interface

#### Access File Manager
```
Route: /file-manager
Name:  file-manager.index
```

Navigate to `/file-manager` after logging in. The page displays:
- Project selector with switcher
- Folder tree sidebar
- File listing
- Storage statistics
- Breadcrumb navigation

#### Upload File
```javascript
// Click "Upload" button → Select file → Optional description → Upload
```

#### Create Folder
```javascript
// Click "New Folder" → Enter name → Create
// Name must be alphanumeric (letters, numbers, hyphens, underscores)
```

#### Navigate Folders
```javascript
// Click folder in list or breadcrumb
// Click folder in sidebar tree
```

#### Download File
```javascript
// Click download icon → File downloads and download count increments
```

#### Rename File/Folder
```javascript
// Click rename icon → Edit name → Save
// Works for both files and folders
```

#### Delete File/Folder
```javascript
// Click delete icon → Confirm → Soft deleted (recoverable via database)
```

### REST API

#### Authentication
All API endpoints require JWT token in header:
```
Authorization: Bearer {jwt_token}
```

Get token via login:
```bash
POST /api/login
{
    "email": "user@example.com",
    "password": "password"
}
```

#### Endpoints

##### 1. List Files/Folders
```bash
GET /api/project-files?project_id=1&folder=/path

Response:
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "document.pdf",
            "is_folder": false,
            "file_path": "documents/document.pdf",
            "mime_type": "application/pdf",
            "file_size": 2048,
            "file_size_formatted": "2 KB",
            "download_count": 5,
            "icon": "ti ti-file-pdf",
            "created_at": "2026-01-21T10:00:00Z",
            "uploaded_by": {
                "id": 1,
                "name": "John Doe"
            }
        }
    ],
    "folder_path": "/path"
}
```

##### 2. Get Folder Structure (Tree)
```bash
GET /api/project-files/tree?project_id=1

Response:
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Documents",
            "path": "documents",
            "children": [
                {
                    "id": 2,
                    "name": "Archive",
                    "path": "documents/archive",
                    "children": []
                }
            ]
        }
    ]
}
```

##### 3. Upload File
```bash
POST /api/project-files/
Content-Type: multipart/form-data

Parameters:
- file: (required) File to upload
- project_id: (required) Project ID
- folder: (optional) Folder path
- description: (optional) File description

Response:
{
    "success": true,
    "message": "File uploaded successfully",
    "data": { ... file object ... }
}
```

##### 4. Create Folder
```bash
POST /api/project-files/folder
Content-Type: application/json

{
    "name": "My Folder",
    "project_id": 1,
    "folder": "parent/path"
}

Response:
{
    "success": true,
    "message": "Folder created successfully",
    "data": { ... folder object ... }
}
```

##### 5. Get File Details
```bash
GET /api/project-files/{id}

Response:
{
    "success": true,
    "data": { ... file object with all details ... }
}
```

##### 6. Update File (Rename/Description)
```bash
PUT /api/project-files/{id}
Content-Type: application/json

{
    "name": "new-name.pdf",
    "description": "Updated description",
    "is_public": true
}

Response:
{
    "success": true,
    "message": "File updated successfully",
    "data": { ... updated file object ... }
}
```

##### 7. Delete File
```bash
DELETE /api/project-files/{id}

Response:
{
    "success": true,
    "message": "File deleted successfully"
}
```

##### 8. Download File
```bash
GET /api/project-files/{id}/download

Returns: Binary file stream
```

##### 9. Search Files
```bash
GET /api/project-files/search?project_id=1&query=document

Response:
{
    "success": true,
    "data": [ ... matching files ... ]
}
```

##### 10. Storage Statistics
```bash
GET /api/project-files/stats?project_id=1

Response:
{
    "success": true,
    "data": {
        "total_size": 1024000,
        "total_size_formatted": "1000 KB",
        "file_count": 15,
        "folder_count": 3,
        "max_size": 104857600,
        "max_size_formatted": "100 MB",
        "usage_percent": 0.98
    }
}
```

## File Operations Details

### Supported File Types

**Documents**: pdf, doc, docx, xls, xlsx, csv, ppt, pptx, txt, odt, ods, odp

**Images**: jpg, jpeg, png, gif, webp, svg, bmp, ico

**Archives**: zip, rar, 7z, tar, gz

**Video**: mp4, avi, mov, mkv, wmv, flv, webm

**Audio**: mp3, wav, flac, m4a, aac, ogg

**Code**: php, js, css, html, json, xml, sql, py, java

**Other**: sh, exe, dmg, iso

### File Size Limits
- Default: 100MB per file
- Configurable in `ProjectFileService::$config['max_file_size']`

### Storage Location
```
storage/projects/
├── 1/                    # Project 1
│   ├── file1.pdf
│   ├── documents/
│   │   └── report.docx
│   └── archive/
├── 2/                    # Project 2
│   ├── images/
│   │   └── photo.jpg
│   └── ...
```

## Authorization & Permissions

### Policy Rules

| Action | Requirements |
|--------|--------------|
| View | User has project access |
| Upload | User has project access |
| Download | Public file OR user has project access |
| Rename | User is file uploader OR super admin |
| Delete | User is file uploader OR super admin |
| Make Public | User is file uploader OR super admin |
| Archive | User is file uploader OR super admin |

### Access Check
```php
// Check if user can access project
$fileService->userHasProjectAccess($userId, $projectId);

// Available to:
// - Super admin (type === 'super admin')
// - Users in UserProject relationship
```

## Models & Relationships

### ProjectFileNew Model
```php
// Relations
$file->project()        // Project this file belongs to
$file->uploadedBy()     // User who uploaded file
$file->children()       // Child files in folder
$file->parent()         // Parent folder

// Scopes
$query->files()         // Only files (not folders)
$query->folders()       // Only folders
$query->inProject($id)  // In specific project
$query->inFolder($path) // In specific folder
$query->active()        // Not archived
$query->public()        // Public files
$query->search($term)   // Full-text search

// Methods
$file->getHumanFileSize()       // Format: "2.5 MB"
$file->getExtension()           // File extension
$file->getFileIcon()            // Tabler icon class
$file->isImage()                // Check if image
$file->isDocument()             // Check if document
$file->recordDownload()         // Increment download count
$file->archive()                // Archive file
$file->deleteFromStorage()      // Delete from disk
```

## Service Methods

### ProjectFileService
```php
// Upload file
$service->uploadFile($file, $projectId, $userId, $folderPath, $description)

// Create folder
$service->createFolder($projectId, $userId, $folderName, $parentPath)

// Get folder contents
$service->getFolderContents($projectId, $folderPath)

// Get folder structure (tree)
$service->getFolderStructure($projectId, $parentPath, $depth, $maxDepth)

// Rename file/folder
$service->rename($file, $newName)

// Delete file
$service->delete($file)

// Get storage stats
$service->getStorageStats($projectId)

// Search files
$service->search($projectId, $query, $limit)

// Check user access
$service->userHasProjectAccess($userId, $projectId)

// Validate file
$service->validateFile($file, $maxFileSize)
```

## UI/UX Features

### Theme Integration
- Uses existing Tabler UI theme
- Consistent with app styling
- Responsive design
- Bootstrap components

### Interactive Elements
- Modal dialogs for upload/create folder
- Confirmation dialogs for delete
- Toast alerts for notifications
- AJAX requests for smooth operations
- Breadcrumb navigation
- Folder tree sidebar

### Icons (Tabler Icons)
- Folder: `ti ti-folder`
- File: `ti ti-file`
- Upload: `ti ti-upload`
- Download: `ti ti-download`
- Rename: `ti ti-edit`
- Delete: `ti ti-trash`
- And more based on file type

## Security Considerations

1. **Cross-Project Isolation**
   - All queries filtered by project_id
   - Policy checks on every action
   - User access validated

2. **File Validation**
   - Extension whitelist
   - Size limits
   - MIME type checking

3. **JWT Authentication**
   - All API routes require auth:api
   - Token expires per JWT config
   - Refresh token endpoint available

4. **Soft Deletes**
   - Files not permanently deleted
   - Recovery possible via database
   - Audit trail maintained

5. **Upload Security**
   - Unique filenames (UUID based)
   - Safe storage paths
   - No direct execution

## Configuration

### Modify File Service Config
Edit `app/Services/ProjectFileService.php`:

```php
private $config = [
    'max_file_size' => 104857600,    // Change max file size
    'allowed_extensions' => [ ... ],  // Add/remove file types
    'storage_disk' => 'local',        // Change storage disk
    'base_path' => 'projects',        // Change base path
];
```

### JWT Configuration
Edit `.env`:
```
JWT_SECRET=your-secret-key
JWT_ALGORITHM=HS256
JWT_TTL=60  # token lifetime in minutes
```

## Troubleshooting

### Files Not Uploading
1. Check file size limit
2. Verify file extension is allowed
3. Ensure storage/projects directory is writable
4. Check disk space availability

### Permission Denied Errors
1. User not in UserProject relationship
2. User is not super admin
3. Trying to edit others' files

### Missing Files in Storage
1. Check storage/projects/{project_id} exists
2. Verify database records match file system
3. Check soft deletes (is_archived flag)

### API 401 Unauthorized
1. Missing JWT token in header
2. Token expired - use refresh endpoint
3. Invalid token format

## Examples

### Upload via cURL
```bash
curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer {token}" \
  -F "file=@document.pdf" \
  -F "project_id=1" \
  -F "description=My Document"
```

### Upload via JavaScript
```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('project_id', 1);

fetch('/api/project-files/', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
}).then(r => r.json()).then(data => console.log(data));
```

### List Files via Python
```python
import requests

headers = {'Authorization': f'Bearer {token}'}
params = {'project_id': 1, 'folder': ''}

response = requests.get(
    'http://localhost:8000/api/project-files',
    headers=headers,
    params=params
)

files = response.json()['data']
```

## Testing

### Test File Upload
```bash
php artisan tinker

>>> $file = new \Illuminate\Http\UploadedFile(
    base_path('test.pdf'),
    'test.pdf',
    'application/pdf',
    null,
    true
);
>>> $service = app(\App\Services\ProjectFileService::class);
>>> $result = $service->uploadFile($file, 1, 1, '', 'Test file');
>>> $result
```

### Test API
```bash
# Get JWT token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Use token to access API
curl http://localhost:8000/api/project-files?project_id=1 \
  -H "Authorization: Bearer {token}"
```

## Performance Optimization

### Database Indexes
The migration includes:
- Composite index on (project_id, folder_path, is_folder)
- Index on (project_id, is_archived)
- Index on created_at
- Full-text search index on (name, description)

### Caching (Future)
Consider implementing:
- Cache folder structures
- Cache storage stats
- Cache folder contents

### Pagination (Future)
Consider adding:
- Limit/offset in API responses
- Pagination in web UI
- Lazy loading in folder tree

## Additional Notes

### Existing Functionality Preserved
- ProjectDocument model remains unchanged
- All existing routes work as before
- No conflicts with existing features
- Backward compatible

### Integration Points
- Uses existing getActiveProject() helper
- Works with existing User model
- Uses existing authentication system
- Integrates with Tabler UI theme

### Future Enhancements
- File versioning
- Collaboration features
- Activity logging
- Virus scanning
- CDN integration
- Compression
- Image thumbnails
- Full-text search improvements

## Support

For issues or questions:
1. Check database migrations ran successfully
2. Verify permissions on storage directory
3. Review error logs in storage/logs/
4. Check browser console for JavaScript errors
5. Test API endpoints with Postman
