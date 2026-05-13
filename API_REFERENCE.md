# Project File Manager - API Reference Card

## Quick Reference

### Base URL
```
http://localhost:8000/api/project-files
```

### Authentication
All endpoints require JWT in header:
```
Authorization: Bearer {access_token}
```

### Response Format
```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": { /* endpoint specific */ }
}
```

---

## Endpoints

### 1. List Files in Folder
```http
GET /api/project-files?project_id=1&folder=documents
```

**Parameters:**
- `project_id` (required, integer) - Project ID
- `folder` (optional, string) - Folder path, defaults to root

**Response:**
```json
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
      "icon": "ti ti-file-pdf",
      "download_count": 5,
      "created_at": "2026-01-21T10:00:00Z",
      "uploaded_by": { "id": 1, "name": "John Doe" }
    },
    {
      "id": 2,
      "name": "archive",
      "is_folder": true,
      "file_path": "documents/archive",
      "created_at": "2026-01-21T10:00:00Z"
    }
  ],
  "folder_path": "documents"
}
```

---

### 2. Get File Details
```http
GET /api/project-files/{id}
```

**Response:** Single file object (see List Files above)

---

### 3. Upload File
```http
POST /api/project-files
Content-Type: multipart/form-data
```

**Form Parameters:**
- `file` (required, file) - File to upload
- `project_id` (required, integer) - Project ID
- `folder` (optional, string) - Folder path
- `description` (optional, string) - File description

**Response:**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": { /* file object */ }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer {token}" \
  -F "file=@document.pdf" \
  -F "project_id=1" \
  -F "description=My Document"
```

---

### 4. Create Folder
```http
POST /api/project-files/folder
Content-Type: application/json
```

**Body:**
```json
{
  "name": "My Folder",
  "project_id": 1,
  "folder": ""
}
```

**Response:**
```json
{
  "success": true,
  "message": "Folder created successfully",
  "data": { /* folder object */ }
}
```

---

### 5. Update File (Rename/Description)
```http
PUT /api/project-files/{id}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "new-name.pdf",
  "description": "New description",
  "is_public": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "File updated successfully",
  "data": { /* updated file object */ }
}
```

---

### 6. Delete File
```http
DELETE /api/project-files/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "File deleted successfully"
}
```

---

### 7. Download File
```http
GET /api/project-files/{id}/download
```

**Response:** Binary file stream

**Headers:**
```
Content-Type: application/octet-stream
Content-Disposition: attachment; filename="original-name.pdf"
```

---

### 8. Get Folder Tree Structure
```http
GET /api/project-files/tree?project_id=1
```

**Response:**
```json
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

---

### 9. Get Storage Statistics
```http
GET /api/project-files/stats?project_id=1
```

**Response:**
```json
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

---

### 10. Search Files
```http
GET /api/project-files/search?project_id=1&query=report
```

**Parameters:**
- `project_id` (required, integer) - Project ID
- `query` (required, string) - Search term (min 2 chars)

**Response:**
```json
{
  "success": true,
  "data": [ /* matching files */ ]
}
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthorized"
}
```
**Cause**: Missing or invalid JWT token

### 403 Forbidden
```json
{
  "success": false,
  "message": "Unauthorized"
}
```
**Cause**: No access to project

### 404 Not Found
```json
{
  "success": false,
  "message": "File not found"
}
```
**Cause**: File ID doesn't exist

### 422 Validation Error
```json
{
  "success": false,
  "message": "File type .exe is not allowed"
}
```
**Cause**: Invalid input or file validation failed

### 500 Server Error
```json
{
  "success": false,
  "message": "Upload failed"
}
```
**Cause**: Server-side error (check logs)

---

## Common Workflows

### Upload and Get Details
```bash
# Upload file
RESPONSE=$(curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer {token}" \
  -F "file=@document.pdf" \
  -F "project_id=1")

FILE_ID=$(echo $RESPONSE | jq -r '.data.id')

# Get details
curl http://localhost:8000/api/project-files/$FILE_ID \
  -H "Authorization: Bearer {token}"
```

### Create Folder and Upload File
```bash
# Create folder
FOLDER_RESPONSE=$(curl -X POST http://localhost:8000/api/project-files/folder \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name":"archive","project_id":1,"folder":""}')

FOLDER_PATH=$(echo $FOLDER_RESPONSE | jq -r '.data.file_path')

# Upload to folder
curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer {token}" \
  -F "file=@document.pdf" \
  -F "project_id=1" \
  -F "folder=$FOLDER_PATH"
```

### Get Project Stats and Files
```bash
# Get stats
curl "http://localhost:8000/api/project-files/stats?project_id=1" \
  -H "Authorization: Bearer {token}"

# Get folder structure
curl "http://localhost:8000/api/project-files/tree?project_id=1" \
  -H "Authorization: Bearer {token}"

# List root files
curl "http://localhost:8000/api/project-files?project_id=1" \
  -H "Authorization: Bearer {token}"
```

### Search and Download
```bash
# Search files
SEARCH=$(curl "http://localhost:8000/api/project-files/search?project_id=1&query=report" \
  -H "Authorization: Bearer {token}")

FILE_ID=$(echo $SEARCH | jq -r '.data[0].id')

# Download file
curl "http://localhost:8000/api/project-files/$FILE_ID/download" \
  -H "Authorization: Bearer {token}" \
  -o downloaded-file.pdf
```

---

## Request Examples

### JavaScript/Fetch
```javascript
const token = 'your-jwt-token';

// List files
fetch('http://localhost:8000/api/project-files?project_id=1', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(r => r.json())
.then(data => console.log(data));

// Upload file
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('project_id', 1);

fetch('http://localhost:8000/api/project-files', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
})
.then(r => r.json())
.then(data => console.log(data));
```

### Python/Requests
```python
import requests

token = 'your-jwt-token'
headers = {'Authorization': f'Bearer {token}'}

# List files
response = requests.get(
    'http://localhost:8000/api/project-files',
    params={'project_id': 1},
    headers=headers
)
print(response.json())

# Upload file
with open('document.pdf', 'rb') as f:
    files = {'file': f}
    data = {'project_id': 1}
    response = requests.post(
        'http://localhost:8000/api/project-files',
        files=files,
        data=data,
        headers=headers
    )
    print(response.json())
```

### cURL
```bash
TOKEN="your-jwt-token"

# List files
curl "http://localhost:8000/api/project-files?project_id=1" \
  -H "Authorization: Bearer $TOKEN"

# Upload file
curl -X POST http://localhost:8000/api/project-files \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@document.pdf" \
  -F "project_id=1"

# Download file
curl "http://localhost:8000/api/project-files/1/download" \
  -H "Authorization: Bearer $TOKEN" \
  -o document.pdf
```

---

## Rate Limiting

Currently not implemented. Consider adding:
- Per-user rate limits
- Per-IP rate limits
- Per-endpoint rate limits

---

## Pagination

Currently not implemented. Consider adding:
- Page/limit parameters
- Total count in response
- Next page URL

---

## Versioning

API Version: v1
Current Endpoint: `/api/project-files`

Future: `/api/v2/project-files`

---

## Changelog

### v1.0 (2026-01-21)
- Initial release
- File upload, download, rename, delete
- Folder creation and management
- Search functionality
- Storage statistics
- JWT authentication

---

## Need Help?

See full documentation: `PROJECT_FILE_MANAGER.md`
Quick start: `PROJECT_FILE_MANAGER_QUICK_START.md`
