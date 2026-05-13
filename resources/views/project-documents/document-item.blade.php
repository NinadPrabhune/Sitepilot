<div class="document-card list-view-item" data-document-id="{{ $document->id }}" style="display: flex; align-items: center; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; transition: all 0.3s ease; background: white; cursor: pointer;" ondblclick="@if($document->file_type === 'folder') openFolder('{{ $document->file_name }}') @endif">
    <div class="document-icon" style="font-size: 24px; width: 40px; min-width: 40px; height: 40px; text-align: center; margin-right: 12px; display: flex; align-items: center; justify-content: center; color: #18bf6b; border-radius: 6px; background: #f0fdf4;">
        @if($document->file_type === 'folder')
            <i class="ti ti-folder" style="font-size: 24px;"></i>
        @elseif(in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']))
            <img src="{{ route('project-documents.download', [$document->project_id, $document->id]) }}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" alt="{{ $document->file_name }}" title="{{$document->file_name}}">
        @else
            <i class="{{ $document->getFileIcon() }}" style="font-size: 24px;"></i>
        @endif
    </div>
    
    <div class="document-info" style="flex: 1; min-width: 0;">
        <div class="document-name" style="font-weight: 500; color: #333; word-break: break-word; margin-bottom: 4px;" title="{{ $document->file_name }}">{{ $document->file_name }}</div>
        <div class="document-meta" style="font-size: 12px; color: #999; display: flex; gap: 12px;">
            @if($document->file_type === 'folder')
                <span><i class="ti ti-folder" style="font-size: 11px;"></i> {{ __('Folder') }}</span>
            @else
                <span><i class="ti ti-file" style="font-size: 11px;"></i> {{ $document->getHumanFileSize() }}</span>
                <span><i class="ti ti-clock" style="font-size: 11px;"></i> {{ $document->created_at->diffForHumans() }}</span>
            @endif
        </div>
    </div>

    <div class="document-actions" style="display: flex; gap: 6px; margin-left: 8px;">
        @if($document->file_type === 'folder')
            <button class="btn btn-sm btn-outline-secondary" onclick="openFolder('{{ $document->file_name }}')" title="{{ __('Open') }}" style="padding: 5px 8px; font-size: 12px;">
                <i class="ti ti-arrow-right"></i>
            </button>
        @elseif(in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']))
            <button class="btn btn-sm btn-outline-info" onclick="showImagePreview('{{ $document->id }}', '{{ route('project-documents.download', [$document->project_id, $document->id]) }}', '{{ $document->file_name }}')" title="{{ __('View Image') }}" style="padding: 5px 8px; font-size: 12px;">
                <i class="ti ti-eye"></i>
            </button>
        @endif
        
        <a href="{{ route('project-documents.download', [$document->project_id, $document->id]) }}" 
           class="btn btn-sm btn-primary" title="{{ __('Download') }}" style="padding: 5px 8px; font-size: 12px;" @if($document->file_type === 'folder') onclick="return false;" @endif>
            <i class="ti ti-download"></i>
        </a>
        
        <button class="btn btn-sm btn-outline-secondary" onclick="renameDocument({{ $document->id }}, '{{ addslashes($document->file_name) }}')" title="{{ __('Rename') }}" style="padding: 5px 8px; font-size: 12px;">
            <i class="ti ti-edit" style="font-size: 14px;"></i>
        </button>
        
        <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument({{ $document->id }})" title="{{ __('Delete') }}" style="padding: 5px 8px; font-size: 12px;">
            <i class="ti ti-trash" style="font-size: 14px;"></i>
        </button>
    </div>
</div>

<!-- Grid View Card -->
<div class="document-card grid-view-item" data-document-id="{{ $document->id }}" style="display: none; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s ease; background: white; cursor: pointer; flex-direction: column; justify-content: flex-start; min-height: 220px;" ondblclick="@if($document->file_type === 'folder') openFolder('{{ $document->file_name }}') @endif">
    <div class="document-icon-grid" style="font-size: 48px; margin-bottom: 12px; color: #18bf6b; display: flex; align-items: center; justify-content: center; min-height: 80px;">
        @if($document->file_type === 'folder')
            <i class="ti ti-folder"></i>
        @elseif(in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']))
            <img src="{{ route('project-documents.download', [$document->project_id, $document->id]) }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" alt="{{ $document->file_name }}">
        @else
            <i class="{{ $document->getFileIcon() }}" style="font-size: 48px;"></i>
        @endif
    </div>
    
    <div class="document-info-grid" style="margin-bottom: 12px; flex: 1;">
        <div class="document-name-grid" style="font-weight: 500; color: #333; word-break: break-word; margin-bottom: 4px; font-size: 13px;" title="{{ $document->file_name }}">{{ Str::limit($document->file_name, 20) }}</div>
        <div class="document-meta-grid" style="font-size: 11px; color: #999;">
            @if($document->file_type === 'folder')
                {{ __('Folder') }}
            @else
                {{ $document->getHumanFileSize() }}
            @endif
        </div>
    </div>

    <div class="document-actions-grid" style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; width: 100%;">
        @if($document->file_type === 'folder')
            <button class="btn btn-sm btn-outline-secondary" onclick="openFolder('{{ $document->file_name }}')" title="{{ __('Open') }}" style="padding: 4px 8px; font-size: 11px;">
                <i class="ti ti-arrow-right" style="font-size: 14px;"></i>
            </button>
        @elseif(in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']))
            <button class="btn btn-sm btn-outline-info" onclick="showImagePreview('{{ $document->id }}', '{{ route('project-documents.download', [$document->project_id, $document->id]) }}', '{{ $document->file_name }}')" title="{{ __('View') }}" style="padding: 4px 8px; font-size: 11px;">
                <i class="ti ti-eye" style="font-size: 14px;"></i>
            </button>
        @endif
        
        <a href="{{ route('project-documents.download', [$document->project_id, $document->id]) }}" 
           class="btn btn-sm btn-primary" title="{{ __('Download') }}" style="padding: 4px 8px; font-size: 11px;" @if($document->file_type === 'folder') onclick="return false;" @endif>
            <i class="ti ti-download" style="font-size: 14px;"></i>
        </a>
        
        <button class="btn btn-sm btn-outline-secondary" onclick="renameDocument({{ $document->id }}, '{{ addslashes($document->file_name) }}')" title="{{ __('Rename') }}" style="padding: 4px 8px; font-size: 11px;">
            <i class="ti ti-edit" style="font-size: 14px;"></i>
        </button>
        
        <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument({{ $document->id }})" title="{{ __('Delete') }}" style="padding: 4px 8px; font-size: 11px;">
            <i class="ti ti-trash" style="font-size: 14px;"></i>
        </button>
    </div>
</div>

<script>
if (typeof appBaseUrl === 'undefined') {
        var appBaseUrl = "{{ url('/') }}";
}    
function showImagePreview(docId, imageUrl, fileName) {
    const modal = document.createElement('div');
    modal.id = 'imagePreviewModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    modal.innerHTML = `
        <div style="position: relative; max-width: 90vw; max-height: 90vh; background: white; border-radius: 8px; padding: 20px;">
            <button onclick="document.getElementById('imagePreviewModal').remove()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; z-index: 10000;">×</button>
            <img src="${imageUrl}" style="max-width: 100%; max-height: 80vh; object-fit: contain;" alt="${fileName}">
            <p style="text-align: center; margin-top: 10px; color: #666; font-size: 12px;">${fileName}</p>
        </div>
    `;
    document.body.appendChild(modal);
}

function copyFileLink(projectId, docId) {
    const fullUrl = `${appBaseUrl}/project-documents/${projectId}/download/${docId}`;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(fullUrl)
            .then(() => alert("File link copied: " + fullUrl))
            .catch(err => console.error("Clipboard error:", err));
    } else {
        // Fallback for older browsers
        const tempInput = document.createElement("input");
        tempInput.value = fullUrl;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        alert("File link copied: " + fullUrl);
    }
}


function openFolder(folderName) {
    const projectId = {{ $document->project_id }};
    
    // Update the hidden folder_path input in upload form
    const folderPathInput = document.querySelector('input[name="folder_path"]');
    if (folderPathInput) {
        folderPathInput.value = folderName;
    }
    
    // Store current folder in sessionStorage
    sessionStorage.setItem('currentFolder', folderName);
    sessionStorage.setItem('currentProjectId', projectId);
    
    // Load folder contents via API
    fetch(`/project-documents/${projectId}/folder?folder_path=${encodeURIComponent(folderName)}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.documents) {
            // Update the documents list with folder contents
            const documentsList = document.getElementById('documentsList');
            documentsList.innerHTML = '';
            
            // Add breadcrumb/back button
            const headerDiv = document.createElement('div');
            headerDiv.style.cssText = 'padding: 12px 15px; margin-bottom: 12px; background: #f0fdf4; border: 1px solid #d1fae5; border-radius: 8px;';
            headerDiv.innerHTML = `
                <button onclick="goBackFolder()" style="background: none; border: none; color: #18bf6b; cursor: pointer; font-weight: 500;">
                    <i class="ti ti-arrow-left"></i> {{ __('Back') }}
                </button>
                <span style="margin-left: 10px; color: #666;">{{ __('Folder:') }} <strong>${folderName}</strong></span>
            `;
            documentsList.appendChild(headerDiv);
            
            if (data.documents.length > 0) {
                // Add documents to list
                data.documents.forEach(doc => {
                    const itemDiv = document.createElement('div');
                    
                    // Determine if it's an image
                    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                    const fileExt = doc.file_name.split('.').pop().toLowerCase();
                    const isImage = imageExtensions.includes(fileExt);
                    
                    // Get file icon based on extension
//                    const iconMap = {
//                        'pdf': 'ti ti-file-pdf',
//                        'doc': 'ti ti-file-word',
//                        'docx': 'ti ti-file-word',
//                        'xls': 'ti ti-file-spreadsheet',
//                        'xlsx': 'ti ti-file-spreadsheet',
//                        'ppt': 'ti ti-presentation',
//                        'pptx': 'ti ti-presentation',
//                        'txt': 'ti ti-file-text',
//                        'zip': 'ti ti-file-zip',
//                        'rar': 'ti ti-file-zip',
//                        '7z': 'ti ti-file-zip',
//                        'mp4': 'ti ti-file-video',
//                        'avi': 'ti ti-file-video',
//                        'mov': 'ti ti-file-video',
//                        'mkv': 'ti ti-file-video',
//                        'mp3': 'ti ti-file-music',
//                        'wav': 'ti ti-file-music',
//                        'flac': 'ti ti-file-music',
//                        'm4a': 'ti ti-file-music',
//                    };

                    const iconMap = {
                        // Documents
                        'pdf': 'ti ti-file-type-pdf',
                        'doc': 'ti ti-file-type-doc',
                        'docx': 'ti ti-file-type-doc',
                        'xls': 'ti ti-file-spreadsheet',
                        'xlsx': 'ti ti-file-spreadsheet',
                        'ppt': 'ti ti-file-type-ppt',
                        'pptx': 'ti ti-file-type-ppt',
                        'txt': 'ti ti-file-text',

                        // Archives
                        'zip': 'ti ti-file-zip',
                        'rar': 'ti ti-file-zip',
                        '7z': 'ti ti-file-zip',

                        // Video
                        'mp4': 'ti ti-file-video',
                        'avi': 'ti ti-file-video',
                        'mov': 'ti ti-file-video',
                        'mkv': 'ti ti-file-video',

                        // Audio
                        'mp3': 'ti ti-file-music',
                        'wav': 'ti ti-file-music',
                        'flac': 'ti ti-file-music',
                        'm4a': 'ti ti-file-music',
                    };



                    const fileIcon = iconMap[fileExt] || 'ti ti-file';
                    
                    // Build icon HTML
                    let iconHTML = '';
                    if (doc.file_type === 'folder') {
                        iconHTML = '<i class="ti ti-folder" style="font-size: 24px;"></i>';
                    } else if (isImage) {
                        iconHTML = `<img src="/project-documents/${projectId}/download/${doc.id}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" alt="${doc.file_name}">`;
                    } else {
                        iconHTML = `<i class="${fileIcon}" style="font-size: 24px;"></i>`;
                    }
                    
                    itemDiv.innerHTML = `
                        <div class="document-card list-view-item" style="display: flex; align-items: center; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; transition: all 0.3s ease; background: white; cursor: pointer;">
                            <div class="document-icon" style="font-size: 24px; width: 40px; min-width: 40px; height: 40px; text-align: center; margin-right: 12px; display: flex; align-items: center; justify-content: center; color: #18bf6b; border-radius: 6px; background: #f0fdf4;">
                                ${iconHTML}
                            </div>
                            <div class="document-info" style="flex: 1; min-width: 0;">
                                <div class="document-name" style="font-weight: 500; color: #333; word-break: break-word; margin-bottom: 4px;" title="${doc.file_name}">${doc.file_name}</div>
                                <div class="document-meta" style="font-size: 12px; color: #999;">
                                    ${doc.file_type === 'folder' ? '{{ __('Folder') }}' : (doc.file_size ? doc.file_size : 'File')}
                                </div>
                            </div>
                            <div class="document-actions" style="display: flex; gap: 6px; margin-left: 8px;">
                                ${doc.file_type === 'folder' ? `
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openFolder('${doc.file_name}')" title="{{ __('Open') }}" style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-arrow-right"></i>
                                    </button>
                                ` : ''}
                                ${isImage ? `
                                    <button class="btn btn-sm btn-outline-info" onclick="showImagePreview('${doc.id}', '/project-documents/${projectId}/download/${doc.id}', '${doc.file_name}')" title="{{ __('View') }}" style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                ` : ''}
                                ${doc.file_type !== 'folder' ? `
                                    <a href="/project-documents/${projectId}/download/${doc.id}" 
                                       class="btn btn-sm btn-primary" 
                                       title="{{ __('Download') }}" 
                                       style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-download"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="copyFileLink(${projectId}, ${doc.id})"
                                            title="{{ __('Copy Link') }}" 
                                            style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-link"></i>
                                    </button>
                                ` : ''}

                                <button class="btn btn-sm btn-outline-secondary" onclick="renameDocument(${doc.id}, '${doc.file_name}')" title="{{ __('Rename') }}" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="ti ti-edit" style="font-size: 14px;"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${doc.id})" title="{{ __('Delete') }}" style="padding: 5px 8px; font-size: 12px;">
                                    <i class="ti ti-trash" style="font-size: 14px;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="document-card grid-view-item" style="display: none; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; text-align: center; transition: all 0.3s ease; background: white; cursor: pointer; flex-direction: column; justify-content: flex-start; min-height: 220px;">
                            <div class="document-icon-grid" style="font-size: 48px; margin-bottom: 12px; color: #18bf6b; display: flex; align-items: center; justify-content: center; min-height: 80px;">
                                ${doc.file_type === 'folder' ? '<i class="ti ti-folder"></i>' : (isImage ? `<img src="/project-documents/${projectId}/download/${doc.id}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" alt="${doc.file_name}">` : `<i class="${fileIcon}" style="font-size: 48px;"></i>`)}
                            </div>
                            <div class="document-info-grid" style="margin-bottom: 12px; flex: 1;">
                                <div class="document-name-grid" style="font-weight: 500; color: #333; word-break: break-word; margin-bottom: 4px; font-size: 13px;" title="${doc.file_name}">${doc.file_name.length > 20 ? doc.file_name.substring(0, 20) + '...' : doc.file_name}</div>
                                <div class="document-meta-grid" style="font-size: 11px; color: #999;">
                                    ${doc.file_type === 'folder' ? '{{ __('Folder') }}' : (doc.file_size ? doc.file_size : 'File')}
                                </div>
                            </div>
                            <div class="document-actions-grid" style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; width: 100%;">
                                ${doc.file_type === 'folder' ? `
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openFolder('${doc.file_name}')" title="{{ __('Open') }}" style="padding: 4px 8px; font-size: 11px;">
                                        <i class="ti ti-arrow-right" style="font-size: 14px;"></i>
                                    </button>
                                ` : ''}
                                ${isImage ? `
                                    <button class="btn btn-sm btn-outline-info" onclick="showImagePreview('${doc.id}', '/project-documents/${projectId}/download/${doc.id}', '${doc.file_name}')" title="{{ __('View') }}" style="padding: 4px 8px; font-size: 11px;">
                                        <i class="ti ti-eye" style="font-size: 14px;"></i>
                                    </button>
                                ` : ''}
                                ${doc.file_type !== 'folder' ? `
                                    <a href="/project-documents/${projectId}/download/${doc.id}" 
                                       class="btn btn-sm btn-primary" 
                                       title="{{ __('Download') }}" 
                                       style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-download"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="copyFileLink(${projectId}, ${doc.id})" 
                                            title="{{ __('Copy Link') }}" 
                                            style="padding: 5px 8px; font-size: 12px;">
                                        <i class="ti ti-link"></i>
                                    </button>
                                ` : ''}

                                <button class="btn btn-sm btn-outline-secondary" onclick="renameDocument(${doc.id}, '${doc.file_name}')" title="{{ __('Rename') }}" style="padding: 4px 8px; font-size: 11px;">
                                    <i class="ti ti-edit" style="font-size: 14px;"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${doc.id})" title="{{ __('Delete') }}" style="padding: 4px 8px; font-size: 11px;">
                                    <i class="ti ti-trash" style="font-size: 14px;"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    documentsList.appendChild(itemDiv);
                });
            } else {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'empty-state';
                emptyDiv.innerHTML = `
                    <i class="ti ti-folder-off" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 15px;"></i>
                    <p>{{ __('Folder is empty') }}</p>
                `;
                documentsList.appendChild(emptyDiv);
            }
            
            showAlert('success', '✓ {{ __('Opened folder:') }} ' + folderName);
        } else {
            showAlert('danger', '✗ {{ __('Failed to open folder') }}');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', '✗ {{ __('Error opening folder') }}');
    });
}

function goBackFolder() {
    // Clear folder path and reload
    const folderPathInput = document.querySelector('input[name="folder_path"]');
    if (folderPathInput) {
        folderPathInput.value = '';
    }
    sessionStorage.removeItem('currentFolder');
    sessionStorage.removeItem('currentProjectId');
    location.reload();
}

function renameDocument(documentId, currentName) {
    const newName = prompt('{{ __("Enter new name:") }}', currentName);
    if (newName && newName.trim() && newName !== currentName) {
        const projectId = {{ $document->project_id }};
        fetch(`/project-documents/${projectId}/rename/${documentId}`, {
            method: 'PUT',
            body: JSON.stringify({ name: newName.trim() }),
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', '✓ {{ __("File renamed successfully") }}');
                setTimeout(() => location.reload(), 800);
            } else {
                showAlert('danger', '✗ ' + (data.error || '{{ __("Failed to rename file") }}'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', '✗ {{ __("Error renaming file") }}');
        });
    }
}
</script>
