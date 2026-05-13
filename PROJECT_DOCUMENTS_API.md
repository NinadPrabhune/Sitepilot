# Project Document Management System - API Documentation

## Overview
The Project Document Management System provides secure REST APIs for managing project documents with file upload, download, organization, and retrieval capabilities. The system is designed for both web and mobile applications.

---

## Authentication

### Token-Based Authentication (JWT Auth)
All API endpoints require authentication using JWT (JSON Web Tokens) issued by the application's JWT Auth system.

#### Getting a JWT Token
```bash
POST /api/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}

Response:
{
    "access_token": "your-jwt-token",
    "token_type": "Bearer",
    "expires_in": 3600
}
```

#### Using the Token in Requests
```bash
Authorization: Bearer your-jwt-token
```

#### Token Refresh
JWT tokens have an expiration time. To refresh an expired token:
```bash
POST /api/refresh
Authorization: Bearer your-jwt-token

Response:
{
    "access_token": "new-jwt-token",
    "token_type": "Bearer",
    "expires_in": 3600
}
```

---

## API Endpoints

### 1. List Project Documents

**Endpoint:** `GET /api/projects/{projectId}/documents`

**Authentication:** Required (Bearer Token)

**Query Parameters:**
- `folder` (optional): Folder path to list documents from

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents?folder=" \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "data": [
        {
            "id": 1,
            "project_id": 1,
            "file_name": "document.pdf",
            "file_path": "storage/projects/1/document.pdf",
            "file_type": "application/pdf",
            "file_size": 1024000,
            "file_size_formatted": "1000 KB",
            "storage_disk": "local",
            "description": "Project proposal",
            "folder_path": null,
            "file_icon": "ti-file-pdf",
            "uploaded_by": {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com"
            },
            "created_at": "2024-01-20T10:30:00Z",
            "updated_at": "2024-01-20T10:30:00Z"
        }
    ],
    "meta": {
        "project_id": 1,
        "folder_path": "",
        "total_count": 1
    }
}
```

**Error Response (403):**
```json
{
    "error": "Unauthorized - You do not have access to this project",
    "status": 403
}
```

---

### 2. Get Folder Structure

**Endpoint:** `GET /api/projects/{projectId}/documents/structure`

**Authentication:** Required

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/structure" \
  -H "Authorization: Bearer your-token"
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "data": {
        "root_files": [
            {
                "id": 1,
                "file_name": "README.md",
                "file_size": 2048,
                "file_icon": "ti-file-text",
                "created_at": "2024-01-20T10:30:00Z"
            }
        ],
        "folders": {
            "reports": [
                {
                    "id": 2,
                    "file_name": "report.pdf",
                    "file_type": "application/pdf"
                }
            ],
            "attachments": []
        },
        "folder_list": ["reports", "attachments"]
    }
}
```

---

### 3. Upload File

**Endpoint:** `POST /api/projects/{projectId}/documents/upload`

**Authentication:** Required

**Request Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer your-token
```

**Form Parameters:**
- `file` (required): The file to upload (max 100MB)
- `folder_path` (optional): Target folder path
- `description` (optional): File description

**Example Request:**
```bash
curl -X POST "http://localhost:8000/api/projects/1/documents/upload" \
  -H "Authorization: Bearer your-token" \
  -F "file=@document.pdf" \
  -F "folder_path=reports" \
  -F "description=Monthly report"
```

**Success Response (201):**
```json
{
    "success": true,
    "status": 201,
    "message": "File uploaded successfully",
    "data": {
        "id": 5,
        "project_id": 1,
        "file_name": "document.pdf",
        "file_path": "storage/projects/1/reports/document.pdf",
        "file_type": "application/pdf",
        "file_size": 1024000,
        "file_size_formatted": "1000 KB",
        "description": "Monthly report",
        "folder_path": "reports",
        "file_icon": "ti-file-pdf",
        "uploaded_by": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "created_at": "2024-01-20T11:15:00Z"
    }
}
```

**Error Response (422):**
```json
{
    "error": "File type 'exe' is not allowed",
    "status": 422
}
```

---

### 4. Download File

**Endpoint:** `GET /api/projects/{projectId}/documents/{documentId}/download`

**Authentication:** Required

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/5/download" \
  -H "Authorization: Bearer your-token" \
  -o document.pdf
```

**Response:** File binary stream (Content-Type: application/octet-stream)

---

### 5. Get Document Details

**Endpoint:** `GET /api/projects/{projectId}/documents/{documentId}`

**Authentication:** Required

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/5" \
  -H "Authorization: Bearer your-token"
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "data": {
        "id": 5,
        "project_id": 1,
        "file_name": "document.pdf",
        "file_path": "storage/projects/1/reports/document.pdf",
        "file_type": "application/pdf",
        "file_size": 1024000,
        "file_size_formatted": "1000 KB",
        "storage_disk": "local",
        "description": "Monthly report",
        "folder_path": "reports",
        "file_icon": "ti-file-pdf",
        "uploaded_by": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "created_at": "2024-01-20T11:15:00Z",
        "updated_at": "2024-01-20T11:15:00Z"
    }
}
```

---

### 6. Update Document

**Endpoint:** `PUT /api/projects/{projectId}/documents/{documentId}`

**Authentication:** Required

**Request Headers:**
```
Content-Type: application/json
Authorization: Bearer your-token
```

**Request Body:**
```json
{
    "file_name": "new_name.pdf",
    "description": "Updated description"
}
```

**Example Request:**
```bash
curl -X PUT "http://localhost:8000/api/projects/1/documents/5" \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "file_name": "updated_document.pdf",
    "description": "Updated monthly report"
  }'
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "message": "Document updated successfully",
    "data": {
        "id": 5,
        "file_name": "updated_document.pdf",
        "description": "Updated monthly report",
        "updated_at": "2024-01-20T12:00:00Z"
    }
}
```

---

### 7. Delete Document

**Endpoint:** `DELETE /api/projects/{projectId}/documents/{documentId}`

**Authentication:** Required

**Example Request:**
```bash
curl -X DELETE "http://localhost:8000/api/projects/1/documents/5" \
  -H "Authorization: Bearer your-token"
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "message": "Document deleted successfully"
}
```

---

### 8. Create Folder

**Endpoint:** `POST /api/projects/{projectId}/documents/folders`

**Authentication:** Required

**Request Body:**
```json
{
    "folder_name": "reports"
}
```

**Example Request:**
```bash
curl -X POST "http://localhost:8000/api/projects/1/documents/folders" \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "folder_name": "reports"
  }'
```

**Success Response (201):**
```json
{
    "success": true,
    "status": 201,
    "message": "Folder created successfully",
    "data": {
        "folder_path": "reports"
    }
}
```

---

### 9. Get Storage Statistics

**Endpoint:** `GET /api/projects/{projectId}/documents/stats`

**Authentication:** Required

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/projects/1/documents/stats" \
  -H "Authorization: Bearer your-token"
```

**Success Response (200):**
```json
{
    "success": true,
    "status": 200,
    "data": {
        "total_size": 5242880,
        "total_size_formatted": "5 MB",
        "total_files": 12,
        "files_by_type": {
            "application/pdf": 5,
            "image/jpeg": 4,
            "text/plain": 3
        }
    }
}
```

---

## Error Handling

### Standard Error Response Format
```json
{
    "error": "Error message",
    "status": 400
}
```

### Common HTTP Status Codes
- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden (Access Denied)
- `404` - Not Found
- `422` - Unprocessable Entity (Validation Error)
- `500` - Internal Server Error

---

## Authorization & Access Control

### Project Access Rules
1. **Super Admin**: Has access to all projects and documents
2. **Regular Users**: Can only access projects they are assigned to via the projects table
3. **Document Ownership**: Only the uploader or super admin can delete/rename documents

### Example Authorization Headers
```bash
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## File Constraints

### Allowed File Types
- **Documents**: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv
- **Images**: jpg, jpeg, png, gif, webp, svg
- **Archives**: zip, rar, 7z
- **Media**: mp4, avi, mov, mkv, mp3, wav, flac, m4a

### File Size Limits
- **Maximum File Size**: 100 MB
- **Recommended**: Upload files < 50 MB for better performance

---

## Usage Examples

### Mobile App Integration (JavaScript/React)

#### Setup Axios Instance
```javascript
import axios from 'axios';

const apiClient = axios.create({
    baseURL: 'http://localhost:8000/api',
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
        'Content-Type': 'application/json'
    }
});
```

#### List Documents
```javascript
async function listDocuments(projectId) {
    try {
        const response = await apiClient.get(`/projects/${projectId}/documents`);
        console.log(response.data.data);
    } catch (error) {
        console.error('Error:', error.response.data);
    }
}
```

#### Upload File
```javascript
async function uploadFile(projectId, file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('folder_path', 'documents');
    formData.append('description', 'Uploaded from mobile app');

    try {
        const response = await apiClient.post(
            `/projects/${projectId}/documents/upload`,
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' }
            }
        );
        console.log('Upload successful:', response.data);
    } catch (error) {
        console.error('Upload failed:', error.response.data);
    }
}
```

#### Download File
```javascript
async function downloadFile(projectId, documentId) {
    try {
        const response = await apiClient.get(
            `/projects/${projectId}/documents/${documentId}/download`,
            { responseType: 'blob' }
        );
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'file.pdf');
        document.body.appendChild(link);
        link.click();
    } catch (error) {
        console.error('Download failed:', error);
    }
}
```

---

## Rate Limiting

Currently, there are no rate limits implemented. However, for production environments, implement:
- 100 requests per minute per user
- 10 MB/s upload bandwidth per user

---

## Security Considerations

1. **Always use HTTPS** in production
2. **Validate file types** on client and server
3. **Keep API tokens** secure and never expose them
4. **Use CORS** appropriately for cross-domain requests
5. **Implement audit logging** for all document operations
6. **Regular backups** of stored documents

---

## Changelog

### Version 1.0.0 (2024-01-20)
- Initial API release
- Basic CRUD operations for documents
- Folder structure support
- Storage statistics
- File upload/download with Sanctum authentication
