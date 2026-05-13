{{ Form::open(['route' => 'spent.store', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => true, 'placeholder' => __('Enter Name')]) }}
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('project_name', __('Site'), ['class' => 'form-label']) }}
            {{ Form::text('project_name', \Workdo\Taskly\Entities\Project::find(getActiveProject())->name ?? '', ['class' => 'form-control', 'readonly' => true]) }}
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('spent_ledger_id', __('Spent Ledger'), ['class' => 'form-label']) }}<x-required></x-required>
            <div class="input-group">
                {{ Form::select('spent_ledger_id', $ledgers->prepend(__('Select Ledger'), ''), null, ['class' => 'form-control', 'required' => true, 'id' => 'spent_ledger_id']) }}
                @permission('spent ledger create')
                <button type="button" class="btn btn-primary" onclick="showLedgerModal()">
                    + Add Ledger
                </button>
                @endpermission
            </div>
            @error('spent_ledger_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group col-md-6">
            {{ Form::label('amount', __('Amount'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::number('amount', null, ['class' => 'form-control', 'step' => '0.01', 'required' => true, 'placeholder' => __('Enter Amount'), 'min' => '0']) }}
            @error('amount') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}

@permission('spent ledger create')
<div class="modal fade" id="ledgerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Add New Ledger') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="ledger-form">
                    <div class="form-group">
                        {{ Form::label('ledger_name', __('Ledger Name'), ['class' => 'form-label']) }}<x-required></x-required>
                        {{ Form::text('ledger_name', null, ['class' => 'form-control', 'required' => true, 'placeholder' => __('Enter Ledger Name'), 'id' => 'ledger_name']) }}
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" id="save-ledger-btn" class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
@endpermission

<script>
window.showLedgerModal = function() {
    var modal = $('#ledgerModal');
    modal.appendTo('body');
    modal.modal('show');
};

$(document).on('click', '#save-ledger-btn', function() {
    var name = $('#ledger_name').val();
    if (!name) {
        alert('{{ __("Ledger name is required") }}');
        return;
    }

    $.ajax({
        url: "{{ route('spent.ledger.store') }}",
        type: 'POST',
        data: {
            name: name,
            _token: '{{ csrf_token() }}'
        },
        success: function(data) {
            if (data.success) {
                $('#spent_ledger_id').append('<option value="' + data.id + '" selected>' + data.name + '</option>');
                $('#ledgerModal').modal('hide');
                $('#ledger_name').val('');
                toastrs('Success', data.message, 'success');
            } else {
                toastrs('Error', data.message, 'error');
            }
        },
        error: function(xhr) {
            var errors = xhr.responseJSON.errors;
            if (errors && errors.name) {
                toastrs('Error', errors.name[0], 'error');
            } else {
                toastrs('Error', '{{ __("Error creating ledger") }}', 'error');
            }
        }
    });
});
</script>
