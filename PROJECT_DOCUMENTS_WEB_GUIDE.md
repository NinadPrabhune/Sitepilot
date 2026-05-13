# Project Document Management System - Web Implementation Guide

## Overview
This guide explains how to use the Project Document Management System in the web application.

---

## Features

### 1. Dynamic Project Sidebar
- **Left-side Menu**: Shows all projects you have access to
- **Active Indicator**: Current project is highlighted in green
- **Project Selection**: Click any project to switch and view its documents

### 2. Document Management
- **Upload Files**: Drag & drop or click to select files
- **Create Folders**: Organize documents into nested folders
- **Download Files**: Click the download button on any document
- **Rename Documents**: Edit document names easily
- **Delete Documents**: Remove documents with confirmation

### 3. Real-time Statistics
- **Total Files**: Count of all documents in active project
- **Storage Used**: Total storage consumption formatted (KB, MB, GB)
- **Total Folders**: Number of organized folders
- **Active Project**: Quick reference of current project

---

## File Support

### Supported File Types
- **Documents**: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), PowerPoint (.ppt, .pptx), Text, CSV
- **Images**: JPG, PNG, GIF, WebP, SVG
- **Archives**: ZIP, RAR, 7Z
- **Media**: MP4, AVI, MOV, MKV, MP3, WAV, FLAC, M4A

### File Limits
- **Maximum File Size**: 100 MB per file
- **Recommended**: 50 MB or less for optimal performance

---

## Getting Started

### Access the System
1. Log in to your account
2. Navigate to **Project Documents** in the sidebar menu
3. Select an active project from the left panel

### Upload Your First Document
1. Click on the upload area or drag files over it
2. Select files from your computer (up to 100MB each)
3. Wait for upload to complete
4. Document will appear in the list

### Create a Folder
1. Click **"New Folder"** button
2. Enter folder name (e.g., "Reports", "Attachments")
3. Click **"Create"**
4. Start organizing your documents

---

## Action Buttons Explained

| Icon | Action | Description |
|------|--------|-------------|
| ⬇️ | Download | Download the file to your computer |
| ✏️ | Rename | Change the file name |
| 🗑️ | Delete | Remove the document (with confirmation) |

---

## Keyboard Shortcuts

- **Enter** (in rename dialog) - Confirm rename
- **Escape** (in rename dialog) - Cancel rename
- **Ctrl+C** (when hovering document) - Copy file info

---

## Storage Management

### View Statistics
- **Files Card**: Shows total document count
- **Storage Card**: Displays space usage with human-readable format
- **Folders Card**: Shows number of organized folders
- **Active Project**: Displays current project name

### Free Up Space
1. Identify large files in the storage stats
2. Click the **delete button** on unwanted documents
3. Confirm deletion

---

## Best Practices

### Organization
- ✅ Use meaningful folder names
- ✅ Keep files organized by project phase
- ✅ Regular cleanup of old versions
- ❌ Avoid storing duplicate files

### Naming Conventions
- Use descriptive file names
- Include dates for version control: `Report_2024-01-20.pdf`
- Keep names concise but meaningful

### Security
- ✅ Only upload documents you need to share
- ✅ Verify file contents before upload
- ✅ Don't upload sensitive credentials
- ❌ Avoid executable files (.exe, .bat, .cmd)

---

## Troubleshooting

### Issue: Upload Failed
**Solution**: 
- Check file size (must be under 100MB)
- Verify file type is supported
- Try uploading with a different file name

### Issue: File Not Appearing
**Solution**:
- Refresh the page (F5)
- Check if it was uploaded to the correct folder
- Verify project selection

### Issue: Can't Delete Document
**Solution**:
- Only the uploader or admin can delete
- Check if you have the required permissions
- Contact admin if needed

### Issue: Folder Not Created
**Solution**:
- Folder name cannot be empty
- Avoid special characters in folder names
- Check if folder already exists

---

## Mobile App Integration

For mobile apps, see the [API Documentation](PROJECT_DOCUMENTS_API.md) for REST endpoint details.

---

## Default Active Project Logic

The system automatically opens the last active project when you visit the page. If no project is set:
- First project in your list is selected
- Super admin users see a prompt to select a project

---

## Keyboard Navigation

| Key | Action |
|-----|--------|
| `Tab` | Navigate between elements |
| `Space` | Toggle folder expansion |
| `Delete` | Delete selected document |
| `F5` | Refresh document list |

---

## Tips & Tricks

### Quick Upload
- Drag files directly from File Explorer into the upload zone
- Select multiple files at once

### Fast Navigation
- Use project sidebar to quickly switch projects
- Folder structure loads automatically

### Storage Optimization
- Archive old projects into ZIP files
- Delete temporary uploads regularly
- Move completed projects to archive folder

---

## File Icons Legend

- 📄 Document (PDF, Word, Excel, etc.)
- 🖼️ Image (JPG, PNG, GIF, etc.)
- 📦 Archive (ZIP, RAR, 7Z)
- 🎥 Video (MP4, AVI, MOV)
- 🎵 Audio (MP3, WAV, FLAC)
- 📄 Text (TXT, CSV)

---

## Performance Tips

1. **Batch Uploads**: Upload multiple files together
2. **Organize Proactively**: Create folders before uploading
3. **Clean Regularly**: Delete old versions
4. **Compress Large Files**: Use ZIP for better performance

---

## Support & Issues

For technical support or issues:
1. Check the troubleshooting section above
2. Contact your system administrator
3. Report bugs with screenshots

---

## FAQ

**Q: Can I recover deleted files?**
A: Deleted files are soft-deleted and recoverable by administrators. Contact your admin immediately if needed.

**Q: What happens if I switch projects?**
A: The system remembers your last active project and reopens it next time.

**Q: Can I share documents with other users?**
A: Users automatically have access to all files in projects they're assigned to.

**Q: Is there a file size limit?**
A: Yes, maximum 100 MB per file. Recommended limit is 50 MB.

**Q: Can I move files between projects?**
A: Currently, you must download and re-upload. Consider requesting this feature.

---

## Version Information

**System Version**: 1.0.0  
**Last Updated**: January 20, 2024  
**Minimum Browser**: Chrome/Firefox/Safari (latest 2 versions)
