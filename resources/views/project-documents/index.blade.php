@extends('layouts.main')

@section('page-title')
    {{ __('Project Documents') }}
@endsection

@push('styles')
<style>
    .sidebar-document-menu {
        max-height: 400px;
        overflow-y: auto;
        border-right: 1px solid #e0e0e0;
    }

    .project-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .project-item:hover {
        background-color: #f8f9fa;
    }

    .project-item.active {
        background-color: #e8f5e9;
        border-left: 4px solid #18bf6b;
        padding-left: 11px;
        font-weight: 600;
    }

    .project-icon {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #18bf6b;
    }

    .document-card {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }

    .document-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-color: #18bf6b;
    }

    .document-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .document-icon {
        font-size: 24px;
        color: #18bf6b;
        min-width: 24px;
    }

    .document-info {
        flex: 1;
    }

    .document-name {
        font-weight: 600;
        color: #333;
        word-break: break-word;
        margin-bottom: 4px;
    }

    .document-meta {
        font-size: 12px;
        color: #999;
    }

    .document-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .document-actions .btn {
        padding: 4px 12px;
        font-size: 12px;
    }

    .upload-zone {
        border: 2px dashed #18bf6b;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f0fdf4;
    }

    .upload-zone:hover {
        background-color: #e8f5e9;
    }

    .upload-zone.dragover {
        background-color: #e8f5e9;
        border-color: #0d7a3b;
    }

    .stats-card {
        background: linear-gradient(135deg, #18bf6b 0%, #0d7a3b 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 15px;
    }

    .stats-value {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stats-label {
        font-size: 12px;
        opacity: 0.9;
    }

    .folder-breadcrumb {
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 15px;
    }

    .breadcrumb-item {
        display: inline;
        margin-right: 10px;
    }

    .breadcrumb-item a {
        color: #18bf6b;
        text-decoration: none;
    }

    .breadcrumb-item a:hover {
        text-decoration: underline;
    }

    .breadcrumb-item.active {
        color: #666;
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ddd;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row" style="gap: 0;">
        <!-- Left Sidebar - Projects -->
        <div class="col-lg-3 col-md-4 d-none">
            <div class="card border-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="ti ti-folder-open"></i>
                        {{ __('Project Documents') }}
                    </h6>
                </div>
               
                
                
                <div class="sidebar-document-menu">
                    @if($projects->count() > 0)
                        @foreach($projects as $project)
                        
                            <div class="project-item {{ $activeProjectId == $project->id ? 'active' : '' }}"
                                 data-project-id="{{ $project->id }}"
                                 onclick="switchProject({{ $project->id }})">
                                <span class="project-icon"></span>
                                <span class="flex-grow-1" title="{{ $project->name }}">
                                    {{ Str::limit($project->name, 20) }}
                                </span>
                            </div>
                        @endforeach
                    @else
                        <div class="p-3 text-center text-muted">
                            <small>{{ __('No projects assigned') }}</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Content - File Manager -->
        <div class="col-lg-12 col-md-12">
            @if($activeProject)
                <!-- Statistics -->
                <div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-value">{{ $storageStats['total_files'] ?? 0 }}</div>
            <div class="stats-label">{{ __('Files') }}</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-value">
                {{ $storageStats['total_size_formatted'] ?? '0 B' }} / 300MB
            </div>
            <div class="stats-label">{{ __('Storage Used') }}</div>

            @php
                $usedBytes = $storageStats['total_size'] ?? 0;
                $maxBytes = 300 * 1024 * 1024; // 300MB in bytes
                $percentage = $maxBytes > 0 ? round(($usedBytes / $maxBytes) * 100) : 0;
            @endphp

            <div class="progress mt-2" style="height: 8px;">
                <div class="progress-bar 
                    @if($percentage >= 90) bg-danger 
                    @elseif($percentage >= 70) bg-warning 
                    @else bg-success 
                    @endif"
                    role="progressbar"
                    style="width: {{ $percentage }}%;"
                    aria-valuenow="{{ $percentage }}"
                    aria-valuemin="0"
                    aria-valuemax="100">
                </div>
            </div>
            <small>{{ $percentage }}% used</small>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-value">{{ $storageStats['total_folders'] ?? 0 }}</div>
            <div class="stats-label">{{ __('Folders') }}</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-value">{{ $activeProject->name }}</div>
            <div class="stats-label">{{ __('Active Project') }}</div>
        </div>
    </div>
</div>


                <!-- Upload Zone -->
                <div class="card border-0 mb-4">
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $activeProjectId }}">
                            <input type="hidden" name="folder_path" value="">

                            <div class="upload-zone" id="uploadZone" 
                                style="border: 2px dashed #18bf6b; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer;">
                               <div>
                                   <i class="ti ti-cloud-upload" style="font-size: 32px; color: #18bf6b;"></i>
                                   <p class="mt-2 mb-0">
                                       {{ __('Drag files here or click to select') }}
                                   </p>
                                   <small class="text-muted">
                                       {{ __('Max file size:') }} 50MB
                                   </small>
                               </div>
                               <input type="file" id="fileInput" name="file" multiple style="display: none;">
                           </div>

                        </form>
                    </div>
                </div>

                <!-- Documents List -->
                <div class="card border-0">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ __('Files') }}</h6>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <a href="javascript:void(0);" class="btn btn-sm btn-primary me-2 view-toggle active" data-view="list" data-bs-toggle="tooltip" data-bs-original-title="{{ __('List View') }}" onclick="toggleView('list')">
                                <i class="ti ti-list text-white"></i>
                            </a>
                            <a href="javascript:void(0);" class="btn btn-sm btn-primary me-2 view-toggle" data-view="grid" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Grid View') }}" onclick="toggleView('grid')">
                                <i class="ti ti-layout-grid text-white"></i>
                            </a>
                            <button class="btn btn-sm btn-primary" onclick="showCreateFolderModal()">
                                <i class="ti ti-folder-plus text-white"></i>
                                {{ __('New Folder') }}
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($documents->count() > 0)
                            <div id="documentsList" style="display: grid; grid-template-columns: 1fr;">
                                @foreach($documents as $document)
                                    @include('project-documents.document-item', ['document' => $document])
                                @endforeach
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="ti ti-file-off"></i>
                                <p>{{ __('No documents yet') }}</p>
                                <small>{{ __('Upload files to get started') }}</small>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card border-0">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-inbox" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 15px;"></i>
                        <h5 class="text-muted">{{ __('No Active Project') }}</h5>
                        <p class="text-muted">
                            {{ __('Please select a project from the sidebar to get started') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Create Folder') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createFolderForm" onsubmit="createFolder(event)">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('Folder Name') }}</label>
                        <input type="text" name="folder_name" class="form-control" required 
                               placeholder="e.g., Documents, Images">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
if (typeof appBaseUrl === 'undefined') {
        var appBaseUrl = "{{ url('/') }}";
}  
    // Upload handling
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const uploadForm = document.getElementById('uploadForm');

    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        Array.from(files).forEach(file => uploadFile(file));
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('project_id', {{ $activeProjectId }});
        formData.append('_token', '{{ csrf_token() }}');
        
        // Get folder_path from the hidden input
        const folderPathInput = document.querySelector('input[name="folder_path"]');
        if (folderPathInput && folderPathInput.value) {
            formData.append('folder_path', folderPathInput.value);
        }

        fetch('{{ route("project-documents.upload") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', '{{ __("File uploaded successfully") }}');
                reloadDocuments();
            } else {
                showAlert('danger', data.error || '{{ __("Upload failed") }}');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', '{{ __("Upload failed") }}');
        });
    }

    function switchProject(projectId) {
        fetch('{{ route("project-documents.switch", ":id") }}'.replace(':id', projectId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAlert('danger', data.error || '{{ __('Failed to switch project') }}');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', '{{ __('Error switching project') }}');
        });
    }

    function toggleView(viewType) {
        const documentsList = document.getElementById('documentsList');
        const toggleBtns = document.querySelectorAll('.view-toggle');
        
        // Update active button
        toggleBtns.forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-view="${viewType}"]`).classList.add('active');
        
        // Toggle view
        if (viewType === 'grid') {
            documentsList.style.gridTemplateColumns = 'repeat(auto-fill, minmax(200px, 1fr))';
            document.querySelectorAll('.list-view-item').forEach(item => item.style.display = 'none');
            document.querySelectorAll('.grid-view-item').forEach(item => item.style.display = 'flex');
        } else {
            documentsList.style.gridTemplateColumns = '1fr';
            document.querySelectorAll('.list-view-item').forEach(item => item.style.display = 'flex');
            document.querySelectorAll('.grid-view-item').forEach(item => item.style.display = 'none');
        }
    }

    function deleteDocument(documentId) {
        if (confirm('{{ __("Are you sure?") }}')) {
            fetch(`/project-documents/{{ $activeProjectId }}/delete/${documentId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    reloadDocuments();
                } else {
                    showAlert('danger', data.error);
                }
            });
        }
    }

    function showCreateFolderModal() {
        const modal = new bootstrap.Modal(document.getElementById('createFolderModal'));
        modal.show();
    }

    function createFolder(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        formData.append('project_id', {{ $activeProjectId }});

        fetch(`/project-documents/{{ $activeProjectId }}/folder`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('createFolderModal')).hide();
                showAlert('success', data.message);
                reloadDocuments();
            } else {
                showAlert('danger', data.error);
            }
        });
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




    function reloadDocuments() {
        // Check if we're in a folder
        const currentFolder = sessionStorage.getItem('currentFolder');
        const currentProjectId = sessionStorage.getItem('currentProjectId');
        
        if (currentFolder && currentProjectId) {
            // Reload the folder content without changing folder
            const projectId = {{ $activeProjectId }};
            const folderPathInput = document.querySelector('input[name="folder_path"]');
            
            fetch(`/project-documents/${projectId}/folder?folder_path=${encodeURIComponent(currentFolder)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.documents) {
                    const documentsList = document.getElementById('documentsList');
                    documentsList.innerHTML = '';
                    
                    // Add breadcrumb/back button
                    const headerDiv = document.createElement('div');
                    headerDiv.style.cssText = 'padding: 12px 15px; margin-bottom: 0px; background: #f0fdf4; border: 1px solid #d1fae5; border-radius: 8px;';
                    headerDiv.innerHTML = `
                        <button onclick="goBackFolder()" style="background: none; border: none; color: #18bf6b; cursor: pointer; font-weight: 500;">
                            <i class="ti ti-arrow-left"></i> {{ __('Back') }}
                        </button>
                        <span style="margin-left: 10px; color: #666;">{{ __('Folder:') }} <strong>${currentFolder}</strong></span>
                    `;
                    documentsList.appendChild(headerDiv);
                    
                    if (data.documents.length > 0) {
                        data.documents.forEach(doc => {
                            const itemDiv = document.createElement('div');
                            
                            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                            const fileExt = doc.file_name.split('.').pop().toLowerCase();
                            const isImage = imageExtensions.includes(fileExt);
                            
//                            const iconMap = {
//                                'pdf': 'ti ti-file-pdf',
//                                'doc': 'ti ti-file-word',
//                                'docx': 'ti ti-file-word',
//                                'xls': 'ti ti-file-spreadsheet',
//                                'xlsx': 'ti ti-file-spreadsheet',
//                                'ppt': 'ti ti-presentation',
//                                'pptx': 'ti ti-presentation',
//                                'txt': 'ti ti-file-text',
//                                'zip': 'ti ti-file-zip',
//                                'rar': 'ti ti-file-zip',
//                                '7z': 'ti ti-file-zip',
//                                'mp4': 'ti ti-file-video',
//                                'avi': 'ti ti-file-video',
//                                'mov': 'ti ti-file-video',
//                                'mkv': 'ti ti-file-video',
//                                'mp3': 'ti ti-file-music',
//                                'wav': 'ti ti-file-music',
//                                'flac': 'ti ti-file-music',
//                                'm4a': 'ti ti-file-music',
//                            };



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
                    }
                }
            });
        } else {
            // No folder open, reload page normally
            location.reload();
        }
    }




    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        const container = document.querySelector('.container-fluid');
        const firstRow = container.querySelector('.row');
        if (firstRow && firstRow.parentNode === container) {
            container.insertBefore(alertDiv, firstRow);
        } else {
            container.insertBefore(alertDiv, container.firstChild);
        }
        setTimeout(() => alertDiv.remove(), 5000);
    }
</script>
@endpush
