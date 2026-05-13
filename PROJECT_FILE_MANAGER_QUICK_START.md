# Project File Manager - Quick Start Guide

## 🚀 Getting Started

### Step 1: Run Migration
```bash
cd c:\wamp64\www\SitePilot
php artisan migrate
```

Expected output: Migration creates `project_files` table

### Step 2: Clear Cache
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

### Step 3: Access File Manager
1. Log in to the application
2. Navigate to `/file-manager`
3. Or use the link in the sidebar menu

## 📁 Web Interface Usage

### Upload Files
1. Click "Upload" button
2. Select file
3. Add optional description
4. Click "Upload"

### Create Folders
1. Click "New Folder" button
2. Enter folder name (alphanumeric only)
3. Click "Create"

### Navigate
- Click folder in list to open
- Click breadcrumb to jump to level
- Click folder in sidebar tree

### Download Files
1. Click download icon next to file
2. File downloads with original name
3. Download count increments

### Rename
1. Click rename icon
2. Edit name
3. Click "Rename"

### Delete
1. Click delete icon
2. Confirm deletion
3. File is soft-deleted (recoverable)

## 🔌 REST API Usage

### Authentication
Get JWT token:
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "your-password"
  }'
```

Response:
```json
{
  "access_token": "eyJ0eXAi...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

### List Files
```bash
curl http://localhost:8000/api/project-files?project_id=1&folder= \
  -H "Authorization: Bearer eyJ0eXAi..."
```

### Upload File
```bash
curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer eyJ0eXAi..." \
  -F "file=@myfile.pdf" \
  -F "project_id=1" \
  -F "description=My Document"
```

### Create Folder
```bash
curl -X POST http://localhost:8000/api/project-files/folder \
  -H "Authorization: Bearer eyJ0eXAi..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Folder",
    "project_id": 1,
    "folder": ""
  }'
```

### Download File
```bash
curl -O http://localhost:8000/api/project-files/1/download \
  -H "Authorization: Bearer eyJ0eXAi..."
```

### Search Files
```bash
curl "http://localhost:8000/api/project-files/search?project_id=1&query=report" \
  -H "Authorization: Bearer eyJ0eXAi..."
```

### Storage Stats
```bash
curl "http://localhost:8000/api/project-files/stats?project_id=1" \
  -H "Authorization: Bearer eyJ0eXAi..."
```

## 📊 Key Features

✅ **Project Isolation** - Each project has separate storage
✅ **Nested Directories** - Unlimited folder depth
✅ **File Types** - 50+ file types supported
✅ **Access Control** - Role-based permissions
✅ **JWT APIs** - Mobile app ready
✅ **Download Tracking** - Monitor file usage
✅ **Soft Deletes** - Recover deleted files
✅ **Search** - Full-text search capability
✅ **Responsive UI** - Works on all devices

## 📱 Mobile App Integration

### JavaScript Example
```javascript
// Get API token
const loginResponse = await fetch('http://api.example.com/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password'
  })
});

const { access_token } = await loginResponse.json();

// List files
const filesResponse = await fetch(
  'http://api.example.com/api/project-files?project_id=1',
  {
    headers: { 'Authorization': `Bearer ${access_token}` }
  }
);

const files = await filesResponse.json();
console.log(files.data);
```

### React/React Native Example
```jsx
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://api.example.com/api',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

// Fetch files
const getFiles = async (projectId) => {
  const response = await api.get('/project-files', {
    params: { project_id: projectId }
  });
  return response.data.data;
};

// Upload file
const uploadFile = async (projectId, file) => {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('project_id', projectId);

  return await api.post('/project-files/', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });
};
```

## 🔐 Security Features

- **JWT Authentication** - Secure token-based API access
- **Cross-Project Isolation** - No access to other projects
- **File Validation** - Type and size restrictions
- **Soft Deletes** - Audit trail preserved
- **Role-Based Access** - Only authorized users
- **Permission Policies** - Granular access control

## 🛠️ Configuration

### Maximum File Size
Edit `app/Services/ProjectFileService.php`:
```php
'max_file_size' => 104857600,  // 100MB
```

### Allowed File Types
Edit `app/Services/ProjectFileService.php`:
```php
'allowed_extensions' => [
    'pdf', 'doc', 'docx', 'xls', 'xlsx',
    'jpg', 'jpeg', 'png', 'gif',
    'zip', 'rar', '7z',
    // ... add more as needed
],
```

### Storage Disk
Edit `.env`:
```
FILESYSTEM_DISK=local
```

## 📂 File Structure

```
Routes:
  /file-manager                    (Web UI)
  /api/project-files               (API endpoints)

Controllers:
  FileManagerController            (Web)
  ProjectFileApiController         (API)

Models:
  ProjectFileNew                      (Database)

Policies:
  ProjectFilePolicy                (Authorization)

Services:
  ProjectFileService               (Business Logic)

Views:
  resources/views/file-manager/    (UI Templates)
```

## ⚠️ Troubleshooting

### Issue: Files not uploading
**Solution**: Check file size and type are allowed

### Issue: Permission denied
**Solution**: User must be in project via UserProject model

### Issue: API returns 401
**Solution**: Verify JWT token in Authorization header

### Issue: Storage directory not writable
**Solution**: Fix permissions on storage/projects directory
```bash
chmod -R 755 storage/projects
```

## 📞 Need Help?

1. Check `PROJECT_FILE_MANAGER.md` for detailed documentation
2. Review error logs in `storage/logs/`
3. Test API endpoints with Postman
4. Check browser console for JavaScript errors

## ✨ What's New

### Database
- ✅ `project_files` table with full metadata
- ✅ Full-text search index
- ✅ Project and user relationships

### Controllers
- ✅ FileManagerController for web UI
- ✅ ProjectFileApiController for REST APIs

### Models & Services
- ✅ ProjectFileNew model with helpers
- ✅ ProjectFileService for business logic
- ✅ ProjectFilePolicy for authorization

### Routes
- ✅ Web routes in /file-manager prefix
- ✅ API routes in /api/project-files prefix

### Views
- ✅ File manager dashboard
- ✅ Folder tree sidebar
- ✅ File listing with actions

## 🎯 Next Steps

1. **Run migrations** to create tables
2. **Test web interface** - upload some files
3. **Get JWT token** for API testing
4. **Test API endpoints** with curl or Postman
5. **Integrate with mobile app** using REST APIs
6. **Configure** settings as needed

---

**Ready to go! 🚀**

Navigate to `/file-manager` and start uploading files!
