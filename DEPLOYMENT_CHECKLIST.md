# Project File Manager - Deployment Checklist

## Pre-Deployment

- [ ] All files created in correct locations
- [ ] No conflicts with existing code
- [ ] Database migration file in place
- [ ] Routes updated correctly
- [ ] AuthServiceProvider updated with policy

## Database

- [ ] Run migration: `php artisan migrate`
- [ ] Verify `project_files` table created
- [ ] Check all columns and indexes present
- [ ] Verify relationships to projects and users
- [ ] Test soft delete functionality

## Laravel Setup

- [ ] Clear all caches:
  ```bash
  php artisan cache:clear
  php artisan route:clear
  php artisan config:clear
  php artisan view:clear
  ```
- [ ] Verify storage/projects directory exists and is writable:
  ```bash
  mkdir -p storage/projects
  chmod -R 755 storage/projects
  ```
- [ ] Check .env JWT configuration
- [ ] Verify `auth:api` middleware configured

## Web Interface

- [ ] Login to application
- [ ] Navigate to `/file-manager`
- [ ] Verify page loads correctly
- [ ] Test project selector dropdown
- [ ] Test upload file button
- [ ] Test create folder button
- [ ] Upload a test file
- [ ] Verify file appears in list
- [ ] Test download file
- [ ] Verify download count increments
- [ ] Test rename file
- [ ] Test rename folder
- [ ] Test delete file
- [ ] Verify soft delete works
- [ ] Test folder navigation
- [ ] Test breadcrumb navigation
- [ ] Test folder tree sidebar
- [ ] Verify storage stats display
- [ ] Test on mobile view

## API Testing

### Authentication
- [ ] Get JWT token: `POST /api/login`
- [ ] Verify token returned
- [ ] Verify token expiration set

### File Operations
- [ ] List files: `GET /api/project-files?project_id=1`
- [ ] Upload file: `POST /api/project-files`
- [ ] Create folder: `POST /api/project-files/folder`
- [ ] Get file: `GET /api/project-files/{id}`
- [ ] Update file: `PUT /api/project-files/{id}`
- [ ] Delete file: `DELETE /api/project-files/{id}`
- [ ] Download file: `GET /api/project-files/{id}/download`

### Advanced Features
- [ ] Get folder tree: `GET /api/project-files/tree?project_id=1`
- [ ] Get stats: `GET /api/project-files/stats?project_id=1`
- [ ] Search files: `GET /api/project-files/search?project_id=1&query=test`

### Error Handling
- [ ] Test 401 without token
- [ ] Test 403 for unauthorized project
- [ ] Test 404 for missing file
- [ ] Test 422 for invalid input
- [ ] Test 500 error responses
- [ ] Verify error messages are descriptive

## Security

### Authorization
- [ ] User A cannot access User B's files
- [ ] User cannot access other project files
- [ ] Super admin can access all projects
- [ ] Project members can access their project
- [ ] Non-members cannot access project

### File Validation
- [ ] Upload restricted file type rejected
- [ ] Upload oversized file rejected
- [ ] File extension validation works
- [ ] MIME type validation works
- [ ] Empty file rejected

### API Security
- [ ] API requires JWT token
- [ ] Invalid token rejected
- [ ] Expired token rejected
- [ ] CSRF protection on web forms
- [ ] SQL injection prevented

## Performance

- [ ] File upload speed acceptable
- [ ] File list loads quickly
- [ ] Search performs well
- [ ] No N+1 queries
- [ ] Database indexes working
- [ ] Storage operations efficient

## Cross-Browser Testing

- [ ] Chrome/Edge works
- [ ] Firefox works
- [ ] Safari works
- [ ] Mobile Safari works
- [ ] Chrome Mobile works

## Edge Cases

- [ ] Empty folder handling
- [ ] Large file handling
- [ ] Many files handling
- [ ] Deep folder nesting
- [ ] Special characters in filenames
- [ ] Simultaneous uploads
- [ ] Network interruption recovery
- [ ] Session timeout handling

## Documentation

- [ ] PROJECT_FILE_MANAGER.md complete
- [ ] PROJECT_FILE_MANAGER_QUICK_START.md complete
- [ ] IMPLEMENTATION_COMPLETE.md present
- [ ] Code comments present
- [ ] API documentation accurate
- [ ] Examples provided

## Monitoring

- [ ] Error logs checked
- [ ] Storage usage monitored
- [ ] API performance tracked
- [ ] Download statistics recorded
- [ ] User activity logged

## Production Readiness

- [ ] No debug information exposed
- [ ] Proper error messages (not stack traces)
- [ ] Rate limiting configured (if needed)
- [ ] CORS configured correctly (if needed)
- [ ] File permissions secure
- [ ] Storage directory not publicly accessible
- [ ] Backups configured
- [ ] Logging configured

## Rollback Plan

- [ ] Can revert migration if needed
- [ ] Backup of production database
- [ ] Previous code version available
- [ ] Rollback procedure documented

## Post-Deployment

- [ ] Monitor error logs for issues
- [ ] Check performance metrics
- [ ] Verify all features working
- [ ] Get user feedback
- [ ] Document any issues found
- [ ] Plan improvements

---

## Quick Deployment Steps

```bash
# 1. Pull/update code
cd /path/to/SitePilot
git pull origin main  # or copy files

# 2. Run migration
php artisan migrate

# 3. Clear caches
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear

# 4. Set permissions
mkdir -p storage/projects
chmod -R 755 storage/projects

# 5. Test
# - Navigate to /file-manager
# - Get JWT token from /api/login
# - Test API endpoints

# Done! 🚀
```

## Testing Commands

```bash
# Test web interface
curl http://localhost:8000/file-manager -H "Cookie: LARAVEL_SESSION=..."

# Test API with token
TOKEN=$(curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' \
  | jq -r '.access_token')

curl http://localhost:8000/api/project-files?project_id=1 \
  -H "Authorization: Bearer $TOKEN"
```

## Success Criteria

✅ All items checked
✅ No errors in logs
✅ All tests passing
✅ Performance acceptable
✅ Users can access
✅ API responding
✅ Files stored correctly
✅ Security measures in place

---

**Once all items are checked, the system is ready for production!**
