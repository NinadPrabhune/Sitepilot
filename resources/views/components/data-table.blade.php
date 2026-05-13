@props([
    'id',
    'route',
    'columns',
    'filters' => [],
    'buttons' => [],
    'options' => []
])

<div class="card">
    <div class="card-body table-border-style">
        <div class="table-responsive">
            <table class="table table-striped datatable" id="{{ $id }}" {{ $attributes }}>
                <thead>
                    <tr>
                        @foreach($columns as $column)
                            <th>{{ __($column['label']) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        var tableConfig = {
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ $route }}",
                data: function (d) {
                    @foreach($filters as $filter)
                        d.{{ $filter }} = $('input[name={{ $filter }}]').val();
                    @endforeach
                }
            },
            columns: {!! json_encode($columns) !!},
            language: {
                "paginate": {
                    "next": '<i class="ti ti-chevron-right"></i>',
                    "previous": '<i class="ti ti-chevron-left"></i>'
                },
                'lengthMenu': "_MENU_" + '{{ __("Entries Per Page") }}',
                "searchPlaceholder": '{{ __("Search...") }}'
            }
        };

        @if(!empty($buttons))
            tableConfig.buttons = {!! json_encode($buttons) !!};
        @endif

        @if(!empty($options))
            @foreach($options as $key => $value)
                tableConfig.{{ $key }} = {!! json_encode($value) !!};
            @endforeach
        @endif

        var table = $('#{{ $id }}').DataTable(tableConfig);

        @if(!empty($buttons))
            table.buttons().container().appendTo('#{{ $id }}_wrapper .col-md-6:eq(0)');
        @endif

        @foreach($filters as $filter)
            $('#applyfilter').click(function() {
                table.ajax.reload();
            });

            $('#clearfilter').click(function() {
                $('input[name={{ $filter }}]').val('{{ \Carbon\Carbon::now()->startOfMonth()->toDateString() }}');
                table.ajax.reload();
            });
        @endforeach
    });
</script>
@endpush
