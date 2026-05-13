@foreach($folders as $folder)
<div class="tree-item" data-path="{{ $folder['path'] }}" style="margin-left: {{ ($level ?? 0) * 20 }}px;">
    <a href="?folder={{ urlencode($folder['path']) }}" class="tree-link @if($currentFolder == $folder['path']) active @endif">
        <i class="ti ti-folder"></i>
        {{ $folder['name'] }}
    </a>
    @if(!empty($folder['children']))
        @include('file-manager.folder-tree', ['folders' => $folder['children'], 'level' => ($level ?? 0) + 1])
    @endif
</div>
@endforeach
