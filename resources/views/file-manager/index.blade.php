@extends('layouts.main')

@section('page-title')
    {{ __('File Manager') }}
@endsection

@push('script-page')
@endpush

@section('page-breadcrumb')
    {{ __('Material / File Manager') }}
@endsection

@section('page-action')
    <div class="d-flex">
        @stack('addButtonHook')
       
            <button class="btn btn-sm btn-primary me-2" id="upload-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="ti ti-upload"></i> {{ __('Upload') }}
            </button>
        
            <button class="btn btn-sm btn-outline-primary" id="create-folder-btn" data-bs-toggle="modal" data-bs-target="#folderModal">
                <i class="ti ti-folder-plus"></i> {{ __('New Folder') }}
            </button>
        
    </div>
@endsection



@section('content')
    <div class="row">
        <!-- Sidebar - Folder Tree -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ __('Folders') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="file-tree">
                        <div class="tree-item" data-path="">
                            <a href="?folder=" class="tree-link @if(empty($currentFolder)) active @endif">
                                <i class="ti ti-home"></i> {{ __('Root') }}
                            </a>
                        </div>
                        @if(!empty($folderStructure))
                            @include('file-manager.folder-tree', ['folders' => $folderStructure, 'level' => 0])
                        @endif
                    </div>
                </div>
            </div>

            <!-- Storage Stats -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title">{{ __('Storage') }}</h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar" style="width: {{ $storageStats['usage_percent'] ?? 0 }}%"></div>
                    </div>
                    <small class="text-muted">
                        {{ $storageStats['total_size_formatted'] ?? '0 B' }} /
                        {{ $storageStats['max_size_formatted'] ?? '100 MB' }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Main Content - File List -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ __('Files') }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Size') }}</th>
                                <th>{{ __('Uploaded By') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="file-list">
                            @forelse($contents as $item)
                                <tr data-file-id="{{ $item->id }}">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="{{ $item->getFileIcon() }} me-2"></i>
                                            @if($item->is_folder)
                                                <a href="?folder={{ urlencode($item->file_path) }}">{{ $item->name }}</a>
                                            @else
                                                <span>{{ $item->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($item->is_folder)
                                            <span class="badge bg-info">{{ __('Folder') }}</span>
                                        @else
                                            <small class="text-muted">{{ $item->getExtension() ?? 'file' }}</small>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $item->getHumanFileSize() }}</small></td>
                                    <td>@if($item->uploadedBy)<small>{{ $item->uploadedBy->name }}</small>@endif</td>
                                    <td><small class="text-muted">{{ $item->getCreatedAtFormatted() }}</small></td>
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            @if(!$item->is_folder)
                                                <a href="{{ route('file-manager.download', $item->id) }}" class="btn btn-sm btn-ghost-primary" title="{{ __('Download') }}">
                                                    <i class="ti ti-download"></i>
                                                </a>
                                            @endif
                                            <button class="btn btn-sm btn-ghost-secondary rename-btn" data-file-id="{{ $item->id }}" title="{{ __('Rename') }}">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-ghost-danger delete-btn" data-file-id="{{ $item->id }}" title="{{ __('Delete') }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="ti ti-folder-off" style="font-size: 3rem;"></i>
                                        <p class="mt-3">{{ __('No files in this folder') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload / Folder / Rename Modals -->
    <!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Upload File') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="uploadForm" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Select File') }}</label>
            <input type="file" id="fileInput" name="file" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Folder Modal -->
<div class="modal fade" id="folderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Create Folder') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="folderForm">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Folder Name') }}</label>
            <input type="text" id="folderName" name="name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Rename') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="renameForm">
        <input type="hidden" id="renameFileId" name="file_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('New Name') }}</label>
            <input type="text" id="newName" name="name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Rename') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const currentFolder = '{{ $currentFolder }}';

    // Upload button
    $('#upload-btn').on('click', function() {
        $('#uploadModal').modal('show');
    });

    // Upload form
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('file', $('#fileInput')[0].files[0]);
        formData.append('folder', currentFolder);
        formData.append('description', $('textarea[name="description"]').val());

        $.ajax({
            url: '{{ route("file-manager.upload") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                showAlert('success', response.message);
                $('#uploadModal').modal('hide');
                location.reload();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.error || 'Upload failed';
                showAlert('error', error);
            }
        });
    });

    // Create folder button
    $('#create-folder-btn').on('click', function() {
        $('#folderModal').modal('show');
    });

    // Create folder form
    $('#folderForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '{{ route("file-manager.create-folder") }}',
            type: 'POST',
            data: {
                name: $('#folderName').val(),
                folder: currentFolder,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                showAlert('success', response.message);
                $('#folderModal').modal('hide');
                location.reload();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.error || 'Creation failed';
                showAlert('error', error);
            }
        });
    });

    // Rename button
    $(document).on('click', '.rename-btn', function() {
        const fileId = $(this).data('file-id');
        const fileName = $(this).closest('tr').find('td:first').text().trim();
        
        $('#renameFileId').val(fileId);
        $('#newName').val(fileName);
        $('#renameModal').modal('show');
    });

    // Rename form
    $('#renameForm').on('submit', function(e) {
        e.preventDefault();
        
        const fileId = $('#renameFileId').val();
        
        $.ajax({
            url: '{{ route("file-manager.rename", ":id") }}'.replace(':id', fileId),
            type: 'POST',
            data: {
                name: $('#newName').val(),
                _token: '{{ csrf_token() }}',
                _method: 'POST'
            },
            success: function(response) {
                showAlert('success', response.message);
                $('#renameModal').modal('hide');
                location.reload();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.error || 'Rename failed';
                showAlert('error', error);
            }
        });
    });

    // Delete button
    $(document).on('click', '.delete-btn', function() {
        const fileId = $(this).data('file-id');
        
        if(!confirm('{{ __("Are you sure?") }}')) return;
        
        $.ajax({
            url: '{{ route("file-manager.delete", ":id") }}'.replace(':id', fileId),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function(response) {
                showAlert('success', response.message);
                location.reload();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.error || 'Delete failed';
                showAlert('error', error);
            }
        });
    });

    // Helper function
    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alert = $(`<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`);
        
        $('body').prepend(alert);
        setTimeout(() => alert.fadeOut(), 3000);
    }
});
</script>
@endpush
