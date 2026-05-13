
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body">
                <div class="form-container">
                    <form action="{{ route('activities.store') }}" method="POST" id="activityForm" class="needs-validation" novalidate enctype="multipart/form-data">
                        @csrf
                        <div class="row">





                            <div class="form-group col-md-6">
                                {{ Form::label('assign_to', __('Assign To'), ['class' => 'form-label']) }}<x-required></x-required>

                                <select class="multi-select choices" id="assign_to" name="assign_to[]" multiple="multiple" 
                                        data-placeholder="{{ __('Select Users ...') }}" required>
                                    @foreach($users as $id => $name)
                                    <option value="{{ $id }}"
                                            @if(!empty($activity) && in_array($id, explode(',', $activity->assign_to))) selected @endif>
                                        {{ $name }}
                                    </option>
                                    @endforeach

                                </select>

                                <p class="text-danger d-none" id="user_validation">{{ __('Assign To field is required.') }}</p>
                            </div>





                            {{-- Activity Title --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title" class="form-label">{{ __('Title') }}<x-required></x-required></label>
                                    <input type="text" name="title" class="form-control" placeholder = "Enter Title" value="{{ old('title') }}" required>
                                </div>
                            </div>
                            {{-- Duration --}}
                            <div class="form-group col-md-6">
                                <label class="form-label">{{ __('Duration')}}</label><x-required></x-required>
                                <div class='input-group'>
                                    <input type='text' class=" form-control form-control-light" id="duration" name="duration" required autocomplete="off"
                                           placeholder="Select date range" />
                                    <input type="hidden" name="start_date">
                                    <input type="hidden" name="due_date">
                                    <span class="input-group-text"><i class="feather icon-calendar"></i></span>
                                </div>
                            </div>

                            {{-- Activity Scope --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scope" class="form-label">{{ __('Scope') }}</label><x-required></x-required>

                                    <input type="text" name="scope" class="form-control" placeholder = "Enter Scope" value="{{ old('scope') }}" required>
                                </div>
                            </div>

                            {{-- Quantity --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="quantity" class="form-label">{{ __('Total Quantity') }}<x-required></x-required></label>
                                    <input type="number" name="quantity" class="form-control" placeholder = "Enter Quantity" value="{{ old('quantity', 0) }}" min="0" required>
                                </div>
                            </div>

                            {{-- Unit --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="unit" class="form-label">{{ __('Unit') }}</label>
                                    <input type="text" name="unit" class="form-control" placeholder = "Enter Unit" value="{{ old('unit') }}" required>
                                </div>
                            </div>

                            {{-- Quantity --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="completed_quantity" class="form-label">{{ __('Completed Quantity') }}<x-required></x-required></label>
                                    <input type="number" name="completed_quantity[]" class="form-control" placeholder = "Enter Completed Quantity" value="{{ old('completed_quantity', 0) }}" min="0" required>
                                </div>
                            </div>

                            {{-- Priority --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority" class="form-label">{{ __('Priority') }}<x-required></x-required></label>
                                    <select name="priority" placeholder = "Enter Priority" class="form-control" required>
                                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                                        <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Reference File --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reference_file" class="form-label">{{ __('Reference File') }}</label>
                                    <input type="file" name="reference_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx">
                                    <small class="text-muted">{{ __('Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG, XLS, XLSX (Max: 20MB)') }}</small>
                                </div>
                            </div>




                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Create Activity') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('packages/workdo/Taskly/src/Resources/assets/libs/bootstrap-daterangepicker/daterangepicker.css')}} ">
    <script src="{{ asset('packages/workdo/Taskly/src/Resources/assets/libs/moment/min/moment.min.js')}}"></script>
    <script src="{{ asset('packages/workdo/Taskly/src/Resources/assets/libs/bootstrap-daterangepicker/daterangepicker.js')}}"></script>

    <script>
$(document).ready(function () {

const $form = $('#activityForm');
const $submitBtn = $form.find('button[type="submit"]');
function validateCompleted() {
let totalCompleted = 0;
let mainQuantity = parseInt($('input[name="quantity"]').val()) || 0;
$('input[name="completed_quantity[]"]').each(function () {
totalCompleted += parseInt($(this).val()) || 0;
});
if (totalCompleted > mainQuantity) {
$('#completedError').remove(); // remove old error if any
$('input[name="completed_quantity[]"]').last()
        .after('<div id="completedError" class="text-danger mt-1">Total completed quantity (' + totalCompleted + ') cannot exceed main quantity (' + mainQuantity + ').</div>');
return false;
} else {
$('#completedError').remove();
$submitBtn.prop('disabled', false);
return true;
}
}

// Run validation on change
$(document).on('input change', 'input[name="quantity"], input[name="completed_quantity[]"]', function () {
validateCompleted();
});
// Run validation on form submit
$('#activityForm').on('submit', function (e) {
if (!validateCompleted()) {
e.preventDefault();
}
});
});
    </script>

    <script>
        $(function () {
        var start = moment('{{ date('Y - m - d') }}', 'YYYY-MM-DD HH:mm:ss');
        var end = moment('{{ date('Y - m - d') }}', 'YYYY-MM-DD HH:mm:ss');
        function cb(start, end) {
        $("form #duration").val(start.format('MMM D, YY hh:mm A') + ' - ' + end.format('MMM D, YY hh:mm A'));
        $('form input[name="start_date"]').val(start.format('YYYY-MM-DD HH:mm:ss'));
        $('form input[name="due_date"]').val(end.format('YYYY-MM-DD HH:mm:ss'));
        }

        $('form #duration').daterangepicker({
        autoApply: true,
                timePicker: true,
                autoUpdateInput: false,
                startDate: start,
                endDate: end,
                locale: {
                format: 'MMMM D, YYYY hh:mm A',
                        applyLabel: "{{__('Apply')}}",
                        cancelLabel: "{{__('Cancel')}}",
                        fromLabel: "{{__('From')}}",
                        toLabel: "{{__('To')}}",
                        daysOfWeek: [
                                "{{__('Sun')}}",
                                "{{__('Mon')}}",
                                "{{__('Tue')}}",
                                "{{__('Wed')}}",
                                "{{__('Thu')}}",
                                "{{__('Fri')}}",
                                "{{__('Sat')}}"
                        ],
                        monthNames: [
                                "{{__('January')}}",
                                "{{__('February')}}",
                                "{{__('March')}}",
                                "{{__('April')}}",
                                "{{__('May')}}",
                                "{{__('June')}}",
                                "{{__('July')}}",
                                "{{__('August')}}",
                                "{{__('September')}}",
                                "{{__('October')}}",
                                "{{__('November')}}",
                                "{{__('December')}}"
                        ],
                }
        }, cb);
        cb(start, end);
        });
        $(document).on('change', "select[name=project_id]", function () {
        $.get('@auth('web'){{route('home')}}@elseauth{{route('client.home')}}@endauth' + '/userProjectJson/' + $(this).val(), function (data) {
        $('select[name=assign_to]').html('');
        data = JSON.parse(data);
        $(data).each(function (i, d) {
        $('select[name=assign_to]').append('<option value="' + d.id + '">' + d.name + ' - ' + d.email + '</option>');
        });
        });
        $.get('@auth('web'){{route('home')}}@elseauth{{route('client.home')}}@endauth' + '/projectMilestoneJson/' + $(this).val(), function (data) {
        $('select[name=milestone_id]').html('<option value="">{{__('Select Milestone')}}</option>');
        data = JSON.parse(data);
        $(data).each(function (i, d) {
        $('select[name=milestone_id]').append('<option value="' + d.id + '">' + d.title + '</option>');
        });
        })
        })
    </script>
    <script>
                $(function(){
                $("#submit").click(function() {
                var user = $("#assign_to option:selected").length;
                if (user == 0){
                $('#user_validation').removeClass('d-none')
                        return false;
                } else{
                $('#user_validation').addClass('d-none')
                }
                });
                });
    </script>

