# Project Document Management System

A comprehensive document management system for Laravel projects with both web UI and REST API support for mobile applications.

## 📋 Table of Contents
- [Features](#features)
- [Architecture](#architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Security](#security)
- [Troubleshooting](#troubleshooting)

## ✨ Features

### Web Application
- **Dynamic Project Sidebar**: Lists all accessible projects with visual indicators
- **Automatic Active Project**: Opens the last active project automatically
- **Drag-and-Drop Upload**: Intuitive file upload with drag-and-drop support
- **Folder Organization**: Create and manage nested folder structures
- **File Management**: Download, rename, and delete documents
- **Real-time Statistics**: Storage usage, file count, and folder count
- **Responsive Design**: Works on desktop and tablet devices
- **Matching Theme**: Seamlessly integrates with existing application colors

### REST API (Mobile-Ready)
- **Sanctum Authentication**: Secure token-based authentication
- **Complete CRUD Operations**: Create, read, update, delete documents
- **Folder Management**: Create and navigate folder structures
- **File Upload/Download**: Direct file transfer with streaming support
- **Storage Statistics**: Real-time usage metrics
- **Error Handling**: Comprehensive error responses with status codes
- **JSON Responses**: Structured responses for mobile apps

### Security Features
- **Project-Level Authorization**: Users can only access assigned projects
- **User-Specific Access**: Documents accessible only by assigned project members
- **File Type Validation**: Only allowed file types can be uploaded
- **Size Validation**: Maximum 100MB per file with configurable limits
- **Soft Deletes**: Deleted files are recoverable by administrators
- **Audit Logging**: All operations are logged for compliance

## 🏗️ Architecture

### Directory Structure
```
Project Document Management System
├── Models
│   └── ProjectDocument          (Document model with relationships)
├── Services
│   └── ProjectDocumentService   (Core business logic)
├── Controllers
│   ├── ProjectDocumentController        (Web interface)
│   └── Api/ProjectDocumentApiController (REST API)
├── Middleware
│   └── ValidateProjectAccess            (Authorization)
├── Policies
│   └── ProjectDocumentPolicy            (Permission checks)
├── Resources
│   └── ProjectDocumentResource          (API formatting)
├── Views
│   └── project-documents/
│       ├── index.blade.php    (Main interface)
│       └── document-item.blade.php (Component)
├── Routes
│   ├── web.php  (Web routes)
│   └── api.php  (API routes)
└── Database
    └── migrations/
        └── create_project_documents_table
```

### Database Design
```sql
project_documents TABLE
├── id (Primary Key)
├── project_id (Foreign Key → projects)
├── user_id (Foreign Key → users)
├── file_name (String)
├── file_path (String - storage path)
├── file_type (MIME type)
├── file_size (Bytes)
├── storage_disk (local/s3)
├── description (Optional)
├── folder_path (Nested folder support)
├── deleted_at (Soft delete)
├── created_at, updated_at
└── Indexes: (project_id, user_id), (project_id, folder_path)
```

### Helper Functions
```php
getActiveProject($user_id)  // Get user's active project
getActiveProjectName()       // Get active project name
```

## 🚀 Installation

### Quick Start

1. **Run Migration**
   ```bash
   php artisan migrate
   ```

2. **Create Storage Directory**
   ```bash
   mkdir -p storage/projects && chmod 775 storage/projects
   ```

3. **Clear Cache**
   ```bash
   php artisan cache:clear && php artisan route:clear
   ```

4. **Done!** Navigate to `/project-documents` in your application

### Detailed Setup
See [PROJECT_DOCUMENTS_SETUP.md](PROJECT_DOCUMENTS_SETUP.md) for comprehensive setup instructions.

## ⚙️ Configuration

### File Size Limits
Modify in `app/Services/ProjectDocumentService.php`:
```php
'max_file_size' => 104857600,  // 100MB
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
Default is local storage. To use S3:
```php
'storage_disk' => 's3',
'base_storage_path' => 'documents',
```

## 📖 Usage

### Web Interface

#### Accessing the System
1. Log in to your account
2. Click "Project Documents" in the sidebar (or navigate to `/project-documents`)
3. Select a project to view its documents

#### Uploading Files
```
Method 1: Drag & Drop
- Drag files from your file explorer directly into the upload zone

Method 2: Click Upload
- Click the upload zone to select files from your computer
- Select multiple files to upload together
```

#### Organizing Documents
```
Creating Folders:
1. Click "New Folder" button
2. Enter folder name
3. Click "Create"

Moving Files:
- Upload to a specific folder by selecting folder path during upload
```

#### Managing Documents
```
Download: Click download icon
Rename: Click edit icon, enter new name
Delete: Click delete icon (requires confirmation)
```

#### Viewing Statistics
- **Files**: Total document count
- **Storage**: Total space used (formatted)
- **Folders**: Number of organized folders
- **Project**: Currently active project name

### REST API

#### Authentication
```bash
# Generate API token
php artisan tinker
> User::find(1)->createToken('api-token')->plainTextToken

# Use in requests
Authorization: Bearer your-token-here
```

#### Example: List Documents
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents" \
  -H "Authorization: Bearer your-token"
```

#### Example: Upload File
```bash
curl -X POST "http://localhost:8000/api/projects/1/documents/upload" \
  -H "Authorization: Bearer your-token" \
  -F "file=@document.pdf" \
  -F "folder_path=reports" \
  -F "description=Monthly report"
```

#### Example: Download File
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/1/download" \
  -H "Authorization: Bearer your-token" \
  -o document.pdf
```

## 📚 API Documentation

Comprehensive API documentation available in [PROJECT_DOCUMENTS_API.md](PROJECT_DOCUMENTS_API.md)

### Available Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/projects/{id}/documents` | List documents |
| GET | `/api/projects/{id}/documents/structure` | Get folder structure |
| POST | `/api/projects/{id}/documents/upload` | Upload file |
| GET | `/api/projects/{id}/documents/{docId}` | Get document details |
| PUT | `/api/projects/{id}/documents/{docId}` | Update document |
| DELETE | `/api/projects/{id}/documents/{docId}` | Delete document |
| POST | `/api/projects/{id}/documents/folders` | Create folder |
| GET | `/api/projects/{id}/documents/stats` | Get storage stats |
| GET | `/api/projects/{id}/documents/{docId}/download` | Download file |

## 🔐 Security

### Authorization Model
```
Super Admin: ✅ Access to all projects and documents
Regular User: ✅ Access only to assigned projects
Document Owner: ✅ Can modify own documents (rename/delete)
```

### File Security
- ✅ File type validation (extension + MIME)
- ✅ File size limits (max 100MB)
- ✅ Path traversal prevention
- ✅ Soft deletes for recovery
- ✅ Audit logging of all operations

### API Security
- ✅ Sanctum token authentication
- ✅ CORS protection
- ✅ Rate limiting (implement as needed)
- ✅ Project access validation

## 🐛 Troubleshooting

### Issue: Files Not Uploading
**Check:**
1. Storage directory exists and is writable: `ls -la storage/projects`
2. File size is under 100MB
3. File type is in allowed list
4. Disk space available

**Fix:**
```bash
chmod -R 775 storage/projects
chown -R www-data:www-data storage/projects
```

### Issue: API Returns 401 Unauthorized
**Check:**
1. Token is valid and not expired
2. Using correct Authorization header format
3. Sanctum middleware is enabled

**Fix:**
```bash
php artisan tinker
> User::first()->createToken('api')->plainTextToken
```

### Issue: Documents Not Appearing
**Check:**
1. Project is correctly selected
2. Documents were uploaded to correct project
3. User has access to project

**Fix:**
```bash
php artisan cache:clear
```

### Issue: Permission Denied Errors
**Check:**
1. User is assigned to the project
2. User role has necessary permissions
3. Document owner is correct user

**Fix:**
```php
// In database
SELECT * FROM user_projects WHERE project_id = ? AND user_id = ?
```

## 📱 Mobile App Integration

### Getting Started
1. Request API token from server
2. Include token in Authorization header
3. Use REST API endpoints (see API docs)
4. Handle JSON responses and errors

### Example React Code
```javascript
// Setup
const apiClient = axios.create({
    baseURL: 'http://localhost:8000/api',
    headers: { 'Authorization': `Bearer ${token}` }
});

// List documents
const docs = await apiClient.get(`/projects/${projectId}/documents`);

// Upload
const formData = new FormData();
formData.append('file', file);
await apiClient.post(`/projects/${projectId}/documents/upload`, formData);

// Download
const blob = await apiClient.get(`/projects/${projectId}/documents/${id}/download`, {
    responseType: 'blob'
});
```

See [PROJECT_DOCUMENTS_API.md](PROJECT_DOCUMENTS_API.md) for complete examples.

## 📊 Performance Considerations

### Optimization Tips
1. **Pagination**: Use pagination for large document lists
2. **Caching**: Cache folder structure (3600s recommended)
3. **Lazy Loading**: Load statistics on demand
4. **Batch Operations**: Upload multiple files together
5. **Compression**: Compress large files before upload

### Database Indexes
Automatically created during migration:
- `(project_id, user_id)` - Fast user authorization
- `(project_id, folder_path)` - Fast folder queries

## 🔄 Version History

### v1.0.0 (January 20, 2024)
- Initial release
- Web UI with drag-drop upload
- REST API with Sanctum auth
- Folder organization
- Storage statistics
- Project-level access control
- Comprehensive documentation

## 📝 File Support Reference

### Supported Types
| Category | Types |
|----------|-------|
| Documents | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV |
| Images | JPG, PNG, GIF, WEBP, SVG |
| Archives | ZIP, RAR, 7Z |
| Media | MP4, AVI, MOV, MKV, MP3, WAV, FLAC, M4A |

### Limits
- **Max File Size**: 100 MB
- **Max Uploads**: Unlimited
- **Total Storage**: Depends on disk space
- **Concurrent Users**: Unlimited

## 🤝 Contributing

To extend the system:

1. **Add File Types**: Update `allowed_extensions` in ProjectDocumentService
2. **Change Limits**: Modify `max_file_size` configuration
3. **Custom Storage**: Implement different storage drivers
4. **Analytics**: Add tracking to DocumentUploaded events

## 📞 Support & Documentation

- **Web Guide**: See [PROJECT_DOCUMENTS_WEB_GUIDE.md](PROJECT_DOCUMENTS_WEB_GUIDE.md)
- **API Guide**: See [PROJECT_DOCUMENTS_API.md](PROJECT_DOCUMENTS_API.md)
- **Setup Guide**: See [PROJECT_DOCUMENTS_SETUP.md](PROJECT_DOCUMENTS_SETUP.md)
- **Code Comments**: Detailed comments in source files

## 📄 License

This system is part of the SitePilot application.

---

**Created**: January 20, 2024  
**Status**: Production Ready  
**Maintenance**: Active  
**Support**: Available

For questions or issues, please refer to the documentation files or contact your system administrator.
