/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 */

"use strict";
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    cache: false,
    complete: function () {
        // Initialize Bootstrap 5 tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        // Also support legacy Bootstrap 4 syntax for backward compatibility
        $('[data-toggle="tooltip"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'tooltip');
                if ($el.attr('data-original-title') && !$el.attr('data-bs-original-title')) {
                    $el.attr('data-bs-original-title', $el.attr('data-original-title'));
                }
                new bootstrap.Tooltip($el[0]);
            }
        });
    },
});

$(function () {
    if ($('.custom-scroll').length) {
        $(".custom-scroll").niceScroll();
        $(".custom-scroll-horizontal").niceScroll();
    }

    if ($('.activity-wrap').length) {
        $(".activity-wrap").niceScroll();
    }

    // if ($(".select2").length > 0) {
    //     $(".select2").select2({
    //         disableOnMobile: false,
    //         nativeOnMobile: false
    //     });
    // }


});

function validation() {

    var forms = document.querySelectorAll('.needs-validation');

    Array.prototype.forEach.call(forms, function (form) {

        form.addEventListener('submit', function (event) {
            var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
            }
            if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();

                //for debug

                // var invalidInputs = Array.from(form.elements).filter(function (input) {
                //     return input.validity.valid === false && input.type !== "submit"; // Exclude submit buttons
                // });


                if (submitButton) {
                    submitButton.disabled = false;
                }
            }

            form.classList.add('was-validated');
        }, false);
    });
}

$(document).ready(function () {
    if ($(".pc-dt-simple").length > 0) {
        $($(".pc-dt-simple")).each(function (index, element) {
            var id = $(element).attr('id');
            const dataTable = new simpleDatatables.DataTable("#" + id);
        });
    }

    if ($(".needs-validation").length > 0) {
        validation();
    }

    // Initialize indent forms if present (for direct page loads)
    if ($('#indent-items-table').length) {
        initIndentForm();
    }

    common_bind();
    summernote();


    // for Choose file
    $(document).on('change', 'input[type=file]', function () {
        var fileclass = $(this).attr('data-filename');
        var finalname = $(this).val().split('\\').pop();
        $('.' + fileclass).html(finalname);
    });
});

function summernote() {
    if ($(".summernote").length > 0) {
        $( $(".summernote") ).each(function( index,element ) {
            var id = $(element).attr('id');
            $('#'+id).summernote({
                placeholder: "Write Here… ",
                tabsize: 2,
                minHeight: 200,
                maxHeight: 250,
                toolbar: [
                    ['style', ['style']],
                    ['color', ['color']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough']],
                    ['list', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link', 'unlink']],
                ]
            });
        });
    }
}

function toastrs(text, message, type) {
    
    
    
    var f = document.getElementById('liveToast');
    var a = new bootstrap.Toast(f).show();
    if (type == 'success') {
        $('#liveToast').removeClass('bg-danger');
        $('#liveToast').addClass('bg-primary');
    } else {
        $('#liveToast').removeClass('bg-primary');
        $('#liveToast').addClass('bg-danger');
    }
    $('#liveToast .toast-body').html(message);
}


$(document).on('click', 'a[data-ajax-popup="true"], button[data-ajax-popup="true"], div[data-ajax-popup="true"]', function (e) {
    e.preventDefault();
    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');
    $("#commonModal .modal-title").html(title);
    // Remove all modal size classes before adding new one
    $("#commonModal .modal-dialog").removeClass('modal-sm modal-md modal-lg modal-xl modal-xxl');
    $("#commonModal .modal-dialog").addClass('modal-' + size);
    $.ajax({
        url: url,
        beforeSend: function () {
            $(".loader-wrapper").removeClass('d-none');
        },
        success: function (data) {
            $(".loader-wrapper").addClass('d-none');
            
            // Validate response before injecting
            if (typeof data !== 'string') {
                console.error('Invalid AJAX response type:', typeof data, data);
                toastrs('Error', 'Invalid server response', 'error');
                return;
            }
            
            try {
                // Inject HTML (should be script-free)
                $('#commonModal .body').html(data);
                $("#commonModal").modal('show');
                
                // Trigger custom event for DPR initialization
                $(document).trigger('dpr:contentLoaded', ['#commonModal .body']);
                
                // Initialize standard components
                initializeModalComponents();
                
                // console.log('DPR content loaded successfully');
            } catch (error) {
                console.error('Error loading DPR content:', error);
                toastrs('Error', 'Failed to load content', 'error');
            }
        },
        error: function (xhr) {
            $(".loader-wrapper").addClass('d-none');
            toastrs('Error', xhr.responseJSON.error, 'error')
        }
    });
});

// Helper function to extract scripts from HTML
function extractScripts(html) {
    const scripts = [];
    const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    let match;
    
    while ((match = scriptRegex.exec(html)) !== null) {
        scripts.push({
            fullMatch: match[0],
            content: match[1].trim(),
            hasSrc: /src=/i.test(match[0])
        });
    }
    
    return scripts;
}

// Helper function to validate JavaScript syntax
function validateJavaScript(code) {
    try {
        // Skip validation for Laravel Blade templates with @json() - they're usually valid
        if (code.includes('@json(') || code.includes('{{') || code.includes('{!!')) {
            // Just check for obvious syntax errors like duplicate closing braces
            const braceCount = (code.match(/};/g) || []).length;
            if (braceCount > 3) { // Allow some closing braces, but flag excessive ones
                console.warn('Multiple closing braces detected, but allowing for Blade template');
            }
            return true; // Assume Blade templates are valid unless obviously broken
        }
        
        // For pure JavaScript, do strict validation
        new Function(code);
        return true;
    } catch (error) {
        console.error('JavaScript validation failed:', error.message);
        console.error('Problematic code snippet:', code.substring(0, 200));
        return false;
    }
}

// Helper function for safe HTML injection without executing scripts
function safeHtmlInjection(selector, html) {
    try {
        // Remove all script tags to prevent execution
        const cleanHtml = html.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
        
        // Inject cleaned HTML
        $(selector).html(cleanHtml);
        $("#commonModal").modal('show');
        
        // Initialize components safely
        initializeModalComponents();
        
        console.log('Safe injection completed - scripts were removed');
    } catch (error) {
        console.error('Safe injection failed:', error);
        toastrs('Error', 'Failed to load content safely', 'error');
    }
}

// Helper function to initialize modal components with error handling
function initializeModalComponents() {
    try {
        if (typeof summernote === 'function') summernote();
        if (typeof taskCheckbox === 'function') taskCheckbox();
        if (typeof common_bind === 'function') common_bind("#commonModal");
        if (typeof validation === 'function') validation();
        
        // Initialize indent forms if present
        if ($('#indent-items-table').length && typeof initIndentForm === 'function') {
            initIndentForm();
        }
    } catch (initError) {
        console.error('Error initializing modal components:', initError);
    }
}

$(document).on('click', 'a[data-ajax-popup-over="true"], button[data-ajax-popup-over="true"], div[data-ajax-popup-over="true"]', function (e) {
    e.preventDefault();

    var validate = $(this).attr('data-validate');
    var id = '';
    if (validate) {
        id = $(validate).val();
    }

    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');

    $("#commonModalOver .modal-title").html(title);
    // Remove all modal size classes before adding new one
    $("#commonModalOver .modal-dialog").removeClass('modal-sm modal-md modal-lg modal-xl modal-xxl');
    $("#commonModalOver .modal-dialog").addClass('modal-' + size);

    $.ajax({
        url: url + '?id=' + id,
        beforeSend: function () {
            $(".loader-wrapper").removeClass('d-none');
        },
        success: function (data) {
            $(".loader-wrapper").addClass('d-none');
            $('#commonModalOver .body').html(data);
            $("#commonModalOver").modal('show');
            summernote();
            taskCheckbox();
            validation();
        },
        error: function (xhr) {
            $(".loader-wrapper").addClass('d-none');
            toastrs('Error', xhr.responseJSON.error, 'error')
        }
    });

});

function arrayToJson(form) {
    var data = $(form).serializeArray();
    var indexed_array = {};

    $.map(data, function (n, i) {
        indexed_array[n['name']] = n['value'];
    });

    return indexed_array;
}

$(document).on("submit", "#commonModalOver form", function (e) {
    e.preventDefault();
    var data = arrayToJson($(this));
    data.ajax = true;

    var url = $(this).attr('action');
    $.ajax({
        url: url,
        data: data,
        type: 'POST',
        success: function (data) {
            toastrs('Success', data.success, 'success');
            $(data.target).append('<option value="' + data.record.id + '">' + data.record.name + '</option>');
            $(data.target).val(data.record.id);
            $(data.target).trigger('change');
            $("#commonModalOver").modal('hide');


        },
        error: function (data) {
            data = data.responseJSON;
            toastrs('Error', data.error, 'error')
        }
    });
});
function common_bind(selector = "body") {
    var $datepicker = $(selector + ' .datepicker');
    if ($(".datepicker-input").length) {
        const d_disable = new Datepicker(document.querySelector('.datepicker-input'), {
            buttonClass: 'btn',
            autohide: true
        });

    }
    if ($(".flatpickr-time-input").length) {
        $(".flatpickr-time-input").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });
    }
    if ($(".flatpickr-input").length) {
        $(".flatpickr-input").flatpickr({
            enableTime: false,
            dateFormat: "Y-m-d",
        });
    }
    if ($(".multi-flatpickr-input").length) {
        $(".multi-flatpickr-input").flatpickr({
            mode: "multiple",
            enableTime: false,
            dateFormat: "Y-m-d",
        });
    }
    if ($(".pc-timepicker-2").length) {
        document.querySelector(".pc-timepicker-2").flatpickr({
            enableTime: true,
            noCalendar: true,
        });
    }
    if ($(".flatpickr-with-datetime").length) {
        $(".flatpickr-with-datetime").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });
    }
    if ($(".flatpickr-to-input").length) {
        $(".flatpickr-to-input").flatpickr({
            mode: "range",
            dateFormat: "Y-m-d",
        });
    }
    if ($(".custom-datepicker").length) {
        $('.custom-datepicker').daterangepicker({
            singleDatePicker: true,
            format: 'Y-MM',
            locale: {
                format: 'Y-MM'
            }
        });
    }

    if ($(".choices").length > 0) {
        $($(".choices")).each(function (index, element) {
            var id = $(element).attr('id');
            var searchEnabled = $(element).attr('searchEnabled');
            if (searchEnabled == undefined) {
                searchEnabled = false;
            }
            else if (searchEnabled == 'true') {
                searchEnabled = true;
            }
            else {
                searchEnabled = false;
            }
            if (id !== undefined) {
                var multipleCancelButton = new Choices(
                    '#' + id, {
                        loadingText: 'Loading...',
                        searchEnabled: searchEnabled,
                        placeholder: true,
                        placeholderValue: "Please Select",
                        removeItemButton: true,
                }
                );
            }
        });
    }

    if ($(".jscolor").length) {
        jscolor.installByClassName("jscolor");
    }
    if ($("[avatar]").length) {

        LetterAvatar.transform();
    }
}

// Indent form initialization for AJAX-loaded modals
function initIndentForm() {
    // console.log('===== INDENT FORM INITIALIZATION START =====');
    initializeIndentFormLogic();
}

function initializeIndentFormLogic() {
    // console.log('Initializing indent form logic with Choices.js...');
    
    // Get categories from data attribute
    var categories = $('#indent-items-table').data('categories');
    // console.log('Categories loaded:', categories);

    function initRow(row) {
        // Populate category select from categories array
        let categorySelect = row.find('.category-select')[0];
        let materialSelect = row.find('.material-select')[0];
        
        // Store materials data for lookup
        let materialsData = {};
        
        // Check if this is edit mode (has existing data attributes)
        let isEditMode = row.data('category-id') !== undefined;
        let existingCategoryId = String(row.data('category-id') || '');
        let existingMaterialId = String(row.data('material-id') || '');
        
        // Clear existing options
        categorySelect.innerHTML = '';
        categorySelect.innerHTML = '<option value="">Select Category</option>';
        categories.forEach(cat => {
            let option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            categorySelect.appendChild(option);
        });

        // Initialize Choices.js for category select
        let categoryChoices = new Choices(categorySelect, {
            placeholder: true,
            placeholderValue: 'Select Category',
            searchEnabled: true,
            removeItemButton: true,
            shouldSort: false
        });

        // Material select - initially disabled
        materialSelect.disabled = true;
        materialSelect.innerHTML = '<option value="">Select Material</option>';
        
        let materialChoices = new Choices(materialSelect, {
            placeholder: true,
            placeholderValue: 'Type to search materials...',
            searchEnabled: true,
            removeItemButton: true,
            shouldSort: false,
            itemSelectText: 'Press to select'
        });
        
        // Disable Choices.js initially
        materialChoices.disable();

        // Category change handler
        categorySelect.addEventListener('change', function() {
            // console.log('Category changed, value:', this.value);
            let categoryId = this.value;
            
            // Clear material select
            materialChoices.clearStore();
            materialChoices.clearInput();
            materialSelect.innerHTML = '<option value="">Select Material</option>';
            materialChoices.setChoices([], 'value');
            
            // Clear materials data
            materialsData = {};
            
            // Enable/disable material select using Choices.js API
            if (categoryId) {
                materialChoices.enable();
            } else {
                materialChoices.disable();
            }
            
            // Clear unit and price (only in create mode)
            if (!isEditMode) {
                row.find('.unit').val('');
                row.find('.price').val('');
                row.find('.subtotal').text('0.00');
                calculateTotal();
            }
            
            // Load materials for selected category
            if (categoryId) {
                // console.log('Fetching materials for category ID:', categoryId);
                $.ajax({
                    url: '/materials/ajax',
                    data: { category_id: categoryId },
                    dataType: 'json',
                    success: function(data) {
                        // console.log('AJAX response:', data);
                        if (data && data.data) {
                            if (data.data.length === 0) {
                                // No materials in this category
                                materialChoices.setChoices([{ value: '', label: 'No materials found in this category', disabled: true }], 'value');
                            } else {
                                // Store materials data for lookup
                                data.data.forEach(item => {
                                    materialsData[String(item.id)] = {
                                        unit: item.unit?.name,
                                        price: item.price
                                    };
                                });
                                
                                let choices = data.data.map(item => ({
                                    value: String(item.id),
                                    label: item.name
                                }));
                                // console.log('Setting choices:', choices);
                                materialChoices.setChoices(choices, 'value');
                                
                                // If edit mode and material exists, select it after loading
                                if (isEditMode && existingMaterialId) {
                                    setTimeout(function() {
                                        materialChoices.setChoiceByValue(String(existingMaterialId));
                                    }, 300);
                                }
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // console.error('AJAX request failed:', status, error);
                    }
                });
            }
        });

        // Material selection handler - use Choices.js event
        materialChoices.passedElement.element.addEventListener('change', function(e) {
            let selectedValue = materialSelect.value;
            // console.log('Material select value:', selectedValue);
            // console.log('Materials data lookup:', materialsData);
            
            if (selectedValue && materialsData[selectedValue]) {
                let materialData = materialsData[selectedValue];
                // console.log('Material data:', materialData);
                row.find('.unit').val(materialData.unit || '');
                row.find('.price').val(materialData.price || 0);
                calculateSubtotal(row);
            }
        });
        
        // Preselect category and material in edit mode
        if (isEditMode && existingCategoryId) {
            categoryChoices.setChoiceByValue(String(existingCategoryId));
            // Manually trigger category change to load materials
            categorySelect.dispatchEvent(new Event('change'));
        }
    }

    function addMaterialRow() {
        var itemCount = $('#indent-items-table tbody .item-row').length + 1;
        
        var row = '<tr class="item-row" data-row-id="' + itemCount + '">' +
            '<td><select class="form-control category-select"></select></td>' +
            '<td><select name="items[' + itemCount + '][material_id]" class="form-control material-select" disabled></select></td>' +
            '<td><input type="number" name="items[' + itemCount + '][quantity]" class="form-control quantity" min="1" step="1" required value="1"></td>' +
            '<td><input type="text" name="items[' + itemCount + '][unit]" class="form-control unit" readonly></td>' +
            '<td><input type="number" name="items[' + itemCount + '][price]" class="form-control price" min="0" step="0.01" required></td>' +
            '<td><span class="subtotal">0.00</span></td>' +
            '<td><input type="text" name="items[' + itemCount + '][remarks]" class="form-control" placeholder="Remarks"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="ti ti-trash"></i></button></td>' +
            '</tr>';
        
        $('#indent-items-table tbody').append(row);
        
        // Initialize Select2 for the new row
        initRow($('#indent-items-table tbody .item-row').last());
    }

    function calculateSubtotal(row) {
        var quantity = parseFloat(row.find('.quantity').val()) || 0;
        var price = parseFloat(row.find('.price').val()) || 0;
        var subtotal = quantity * price;
        row.find('.subtotal').text(subtotal.toFixed(2));
        calculateTotal();
    }

    function calculateTotal() {
        var total = 0;
        $('#indent-items-table tbody .item-row').each(function() {
            var quantity = parseFloat($(this).find('.quantity').val()) || 0;
            var price = parseFloat($(this).find('.price').val()) || 0;
            total += quantity * price;
        });
        $('#total-amount').text(total.toFixed(2));
    }

    // Check if this is edit mode (rows already exist)
    var isEditMode = $('#indent-items-table tbody .item-row').length > 0;
    
    if (isEditMode) {
        // Initialize existing rows (edit mode)
        $('#indent-items-table tbody .item-row').each(function() {
            initRow($(this));
            
            // Pre-select category and material (async-safe)
            let row = $(this);
            let categoryId = row.data('category-id');
            let materialId = row.data('material-id');
            let materialName = row.data('material-name');
            let unit = row.data('unit');
            let price = row.data('price');
            
            if (categoryId) {
                console.log('Edit mode: Pre-selecting category', categoryId, 'and material', materialId);
                // Set category
                row.find('.category-select').val(categoryId).trigger('change');
                
                // Manually inject material option (DO NOT wait for AJAX)
                let materialSelect = row.find('.material-select');
                let option = new Option(materialName, materialId, true, true);
                materialSelect.append(option).trigger('change');
                
                // Enable material select
                materialSelect.prop('disabled', false);
                
                // Unit and price are already set in HTML
            }
        });
    } else {
        // Add default row on page load (create mode)
        addMaterialRow();
    }

    // Add row button click handler
    $('#add-item-row').on('click', function() {
        // Validate last row before adding new row
        var lastRow = $('#indent-items-table tbody .item-row').last();
        var category = lastRow.find('.category-select').val();
        var material = lastRow.find('.material-select').val();
        var quantity = lastRow.find('.quantity').val();
        var price = lastRow.find('.price').val();
        
        if (!category || !material || !quantity || !price) {
            alert('Please complete the previous row before adding a new one.');
            // Highlight incomplete fields
            if (!category) lastRow.find('.category-select').addClass('is-invalid');
            if (!material) lastRow.find('.material-select').addClass('is-invalid');
            if (!quantity) lastRow.find('.quantity').addClass('is-invalid');
            if (!price) lastRow.find('.price').addClass('is-invalid');
            return false;
        }
        
        // Remove any validation styling
        lastRow.find('.is-invalid').removeClass('is-invalid');
        
        addMaterialRow();
    });

    // Quantity/price changes handler
    $(document).on('input', '.quantity, .price', function() {
        var row = $(this).closest('.item-row');
        // Clear validation styling when user starts typing
        row.find('.quantity').removeClass('is-invalid');
        row.find('.price').removeClass('is-invalid');
        calculateSubtotal(row);
    });

    // Remove row handler
    $(document).on('click', '.remove-row', function() {
        // Prevent removing the last row - at least one row must remain
        if ($('#indent-items-table tbody .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            calculateTotal();
        } else {
            // Clear the last row instead of removing
            var row = $(this).closest('.item-row');
            row.find('.category-select').val(null).trigger('change');
            row.find('.material-select').val(null).trigger('change').prop('disabled', true);
            row.find('.quantity').val(isEditMode ? '' : '1');
            row.find('.unit').val('');
            row.find('.price').val('');
            row.find('.subtotal').text('0.00');
            row.find('.remarks').val('');
            calculateTotal();
        }
    });

    // Calculate initial totals
    calculateTotal();

    console.log('===== INDENT FORM INITIALIZATION COMPLETE =====');
}

function choices(id = null) {
    
    document.querySelectorAll('.choices').forEach(el => {
        el.style.width = '100%';
    });
    if ($(".choices").length > 0) {
        
        $($(".choices")).each(function (index, element) {
            
            var searchEnabled = $(element).attr('searchEnabled');
            
            if (searchEnabled == undefined) {
                searchEnabled = false;
            }
            else if (searchEnabled == 'true') {
                searchEnabled = true;
            }
            else {
                searchEnabled = false;
            }

            if (id != null) {
                var id = id;
            }
            else {
                var id = $(element).attr('id');
            }

            if (id !== undefined) {
               
                var multipleCancelButton = new Choices(
                    '#' + id, {
                    removeItemButton: false,
                    loadingText: 'Loading...',
                    searchEnabled: searchEnabled,
                    placeholder: true,
                    placeholderValue: "Please Select",
                }
                );
            }
        });
    }
}
function common_bind_confirmation() {
    if ($("[data-confirm]").length) {

        $('[data-confirm]').each(function () {
            var me = $(this),
                me_data = me.data('confirm');

            me_data = me_data.split("|");
            me.fireModal({
                title: me_data[0],
                body: me_data[1],
                buttons: [
                    {
                        text: me.data('confirm-text-yes') || 'Yes',
                        class: 'btn btn-sm btn-danger rounded-pill',
                        handler: function () {
                            eval(me.data('confirm-yes'));
                        }
                    },
                    {
                        text: me.data('confirm-text-cancel') || 'Cancel',
                        class: 'btn btn-sm btn-secondary rounded-pill',
                        handler: function (modal) {
                            $.destroyModal(modal);
                            eval(me.data('confirm-no'));
                        }
                    }
                ]
            })
        });
    }
}
function JsSearchBox() {
    if ($(".js-searchBox").length) {
        $(".js-searchBox").each(function (index) {
            if ($(this).parent().find('.formTextbox').length == 0) {
                $(this).searchBox({ elementWidth: '250' });
            }
        });
    }
}
function taskCheckbox() {
    var checked = 0;
    var count = 0;
    var percentage = 0;

    count = $("#check-list input[type=checkbox]").length;
    checked = $("#check-list input[type=checkbox]:checked").length;
    percentage = parseInt(((checked / count) * 100), 10);
    if (isNaN(percentage)) {
        percentage = 0;
    }
    $(".custom-label").text(percentage + "%");
    $('#taskProgress').css('width', percentage + '%');


    $('#taskProgress').removeClass('bg-warning');
    $('#taskProgress').removeClass('bg-primary');
    $('#taskProgress').removeClass('bg-success');
    $('#taskProgress').removeClass('bg-danger');

    if (percentage <= 15) {
        $('#taskProgress').addClass('bg-danger');
    } else if (percentage > 15 && percentage <= 33) {
        $('#taskProgress').addClass('bg-warning');
    } else if (percentage > 33 && percentage <= 70) {
        $('#taskProgress').addClass('bg-primary');
    } else {
        $('#taskProgress').addClass('bg-success');
    }
}

(function ($, window, i) {
    // Bootstrap 4 Modal
    $.fn.fireModal = function (options) {
        var options = $.extend({
            size: 'modal-md',
            center: false,
            animation: true,
            title: 'Modal Title',
            closeButton: false,
            header: true,
            bodyClass: '',
            footerClass: '',
            body: '',
            buttons: [],
            autoFocus: true,
            created: function () {
            },
            appended: function () {
            },
            onFormSubmit: function () {
            },
            modal: {}
        }, options);
        this.each(function () {
            i++;
            var id = 'fire-modal-' + i,
                trigger_class = 'trigger--' + id,
                trigger_button = $('.' + trigger_class);
            $(this).addClass(trigger_class);
            // Get modal body
            let body = options.body;
            if (typeof body == 'object') {
                if (body.length) {
                    let part = body;
                    body = body.removeAttr('id').clone().removeClass('modal-part');
                    part.remove();
                } else {
                    body = '<div class="text-danger">Modal part element not found!</div>';
                }
            }
            // Modal base template
            var modal_template = '   <div class="modal' + (options.animation == true ? ' fade' : '') + '" tabindex="-1" role="dialog" id="' + id + '">  ' +
                '     <div class="modal-dialog ' + options.size + (options.center ? ' modal-dialog-centered' : '') + '" role="document">  ' +
                '       <div class="modal-content">  ' +
                ((options.header == true) ?
                    '         <div class="modal-header">  ' +
                    '           <h5 class="modal-title mx-auto">' + options.title + '</h5>  ' +
                    ((options.closeButton == true) ?
                        '           <button type="button" class="close" data-dismiss="modal" aria-label="Close">  ' +
                        '             <span aria-hidden="true">&times;</span>  ' +
                        '           </button>  '
                        : '') +
                    '         </div>  '
                    : '') +
                '         <div class="modal-body text-center text-dark">  ' +
                '         </div>  ' +
                (options.buttons.length > 0 ?
                    '         <div class="modal-footer mx-auto">  ' +
                    '         </div>  '
                    : '') +
                '       </div>  ' +
                '     </div>  ' +
                '  </div>  ';
            // Convert modal to object
            var modal_template = $(modal_template);
            // Start creating buttons from 'buttons' option
            var this_button;
            options.buttons.forEach(function (item) {
                // get option 'id'
                let id = "id" in item ? item.id : '';
                // Button template
                this_button = '<button type="' + ("submit" in item && item.submit == true ? 'submit' : 'button') + '" class="' + item.class + '" id="' + id + '">' + item.text + '</button>';
                // add click event to the button
                this_button = $(this_button).off('click').on("click", function () {
                    // execute function from 'handler' option
                    item.handler.call(this, modal_template);
                });
                // append generated buttons to the modal footer
                $(modal_template).find('.modal-footer').append(this_button);
            });
            // append a given body to the modal
            $(modal_template).find('.modal-body').append(body);
            // add additional body class
            if (options.bodyClass) $(modal_template).find('.modal-body').addClass(options.bodyClass);
            // add footer body class
            if (options.footerClass) $(modal_template).find('.modal-footer').addClass(options.footerClass);
            // execute 'created' callback
            options.created.call(this, modal_template, options);
            // modal form and submit form button
            let modal_form = $(modal_template).find('.modal-body form'),
                form_submit_btn = modal_template.find('button[type=submit]');
            // append generated modal to the body
            $("body").append(modal_template);
            // execute 'appended' callback
            options.appended.call(this, $('#' + id), modal_form, options);
            // if modal contains form elements
            if (modal_form.length) {
                // if `autoFocus` option is true
                if (options.autoFocus) {
                    // when modal is shown
                    $(modal_template).on('shown.bs.modal', function () {
                        // if type of `autoFocus` option is `boolean`
                        if (typeof options.autoFocus == 'boolean')
                            modal_form.find('input:eq(0)').focus(); // the first input element will be focused
                        // if type of `autoFocus` option is `string` and `autoFocus` option is an HTML element
                        else if (typeof options.autoFocus == 'string' && modal_form.find(options.autoFocus).length)
                            modal_form.find(options.autoFocus).focus(); // find elements and focus on that
                    });
                }
                // form object
                let form_object = {
                    startProgress: function () {
                        modal_template.addClass('modal-progress');
                    },
                    stopProgress: function () {
                        modal_template.removeClass('modal-progress');
                    }
                };
                // if form is not contains button element
                if (!modal_form.find('button').length) $(modal_form).append('<button class="d-none" id="' + id + '-submit"></button>');
                // add click event
                form_submit_btn.click(function () {
                    modal_form.submit();
                });
                // add submit event
                modal_form.submit(function (e) {
                    // start form progress
                    form_object.startProgress();
                    // execute `onFormSubmit` callback
                    options.onFormSubmit.call(this, modal_template, e, form_object);
                });
            }
            $(document).on("click", '.' + trigger_class, function () {
                $('#' + id).modal(options.modal);
                return false;
            });
        });
    }

    // Bootstrap Modal Destroyer
    $.destroyModal = function (modal) {
        modal.modal('hide');
        modal.on('hidden.bs.modal', function () {
        });
    }
})(jQuery, this, 0);

var Charts = (function () {
    // Variable
    var $toggle = $('[data-toggle="chart"]');
    var mode = 'light';//(themeMode) ? themeMode : 'light';
    var fonts = {
        base: 'Open Sans'
    }

    // Colors
    var colors = {
        gray: {
            100: '#f6f9fc',
            200: '#e9ecef',
            300: '#dee2e6',
            400: '#ced4da',
            500: '#adb5bd',
            600: '#8898aa',
            700: '#525f7f',
            800: '#32325d',
            900: '#212529'
        },
        theme: {
            'default': '#172b4d',
            'primary': '#5e72e4',
            'secondary': '#f4f5f7',
            'info': '#11cdef',
            'success': '#2dce89',
            'danger': '#f5365c',
            'warning': '#fb6340'
        },
        black: '#12263F',
        white: '#FFFFFF',
        transparent: 'transparent',
    };


    // Methods

    // Chart.js global options
    function chartOptions() {

        // Options
        var options = {
            defaults: {
                global: {
                    responsive: true,
                    maintainAspectRatio: false,
                    defaultColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontFamily: fonts.base,
                    defaultFontSize: 13,
                    layout: {
                        padding: 0
                    },
                    legend: {
                        display: false,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 16
                        }
                    },
                    elements: {
                        point: {
                            radius: 0,
                            backgroundColor: colors.theme['primary']
                        },
                        line: {
                            tension: .4,
                            borderWidth: 4,
                            borderColor: colors.theme['primary'],
                            backgroundColor: colors.transparent,
                            borderCapStyle: 'rounded'
                        },
                        rectangle: {
                            backgroundColor: colors.theme['warning']
                        },
                        arc: {
                            backgroundColor: colors.theme['primary'],
                            borderColor: (mode == 'dark') ? colors.gray[800] : colors.white,
                            borderWidth: 4
                        }
                    },
                    tooltips: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                    }
                },
                doughnut: {
                    cutoutPercentage: 83,
                    legendCallback: function (chart) {
                        var data = chart.data;
                        var content = '';

                        data.labels.forEach(function (label, index) {
                            var bgColor = data.datasets[0].backgroundColor[index];

                            content += '<span class="chart-legend-item">';
                            content += '<i class="chart-legend-indicator" style="background-color: ' + bgColor + '"></i>';
                            content += label;
                            content += '</span>';
                        });

                        return content;
                    }
                }
            }
        }

        // yAxes
        Chart.scaleService.updateScaleDefaults('linear', {
            gridLines: {
                borderDash: [2],
                borderDashOffset: [2],
                color: (mode == 'dark') ? colors.gray[900] : colors.gray[300],
                drawBorder: false,
                drawTicks: false,
                drawOnChartArea: true,
                zeroLineWidth: 0,
                zeroLineColor: 'rgba(0,0,0,0)',
                zeroLineBorderDash: [2],
                zeroLineBorderDashOffset: [2]
            },
            ticks: {
                beginAtZero: true,
                padding: 10,
                callback: function (value) {
                    if (!(value % 10)) {
                        return value
                    }
                }
            }
        });

        // xAxes
        Chart.scaleService.updateScaleDefaults('category', {
            gridLines: {
                drawBorder: false,
                drawOnChartArea: false,
                drawTicks: false
            },
            ticks: {
                padding: 20
            },
            maxBarThickness: 10
        });

        return options;

    }

    // Parse global options
    function parseOptions(parent, options) {
        for (var item in options) {
            if (typeof options[item] !== 'object') {
                parent[item] = options[item];
            } else {
                parseOptions(parent[item], options[item]);
            }
        }
    }

    // Push options
    function pushOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].push(data);
                });
            } else {
                pushOptions(parent[item], options[item]);
            }
        }
    }

    // Pop options
    function popOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].pop();
                });
            } else {
                popOptions(parent[item], options[item]);
            }
        }
    }

    // Toggle options
    function toggleOptions(elem) {
        var options = elem.data('add');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        if (elem.is(':checked')) {

            // Add options
            pushOptions($chart, options);

            // Update chart
            $chart.update();
        } else {

            // Remove options
            popOptions($chart, options);

            // Update chart
            $chart.update();
        }
    }

    // Update options
    function updateOptions(elem) {
        var options = elem.data('update');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        // Parse options
        parseOptions($chart, options);

        // Toggle ticks
        toggleTicks(elem, $chart);

        // Update chart
        $chart.update();
    }



    // Toggle ticks
    function toggleTicks(elem, $chart) {

        if (elem.data('prefix') !== undefined || elem.data('prefix') !== undefined) {
            var prefix = elem.data('prefix') ? elem.data('prefix') : '';
            var suffix = elem.data('suffix') ? elem.data('suffix') : '';

            // Update ticks
            $chart.options.scales.yAxes[0].ticks.callback = function (value) {
                if (!(value % 10)) {
                    return prefix + value + suffix;
                }
            }

            // Update tooltips
            $chart.options.tooltips.callbacks.label = function (item, data) {
                var label = data.datasets[item.datasetIndex].label || '';
                var yLabel = item.yLabel;
                var content = '';

                if (data.datasets.length > 1) {
                    content += '<span class="popover-body-label mr-auto">' + label + '</span>';
                }

                content += '<span class="popover-body-value">' + prefix + yLabel + suffix + '</span>';
                return content;
            }

        }
    }

    $('.remove_workspace').click(function (event) {
        var form = $(this).closest("form");
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        })
        swalWithBootstrapButtons.fire({
            title: 'Are you sure?',
            text: "This action can not be undone. Do you want to continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        })
    });

    $(document).on('click', '.show_confirm', function () {
        var form = $(this).closest("form");
        var title = $(this).attr("data-confirm");
        var text = $(this).attr("data-text");
        if (title == '' || title == undefined) {
            title = "Are you sure?";

        }
        if (text == '' || text == undefined) {
            text = "This action can not be undone. Do you want to continue?";

        }
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        })
        swalWithBootstrapButtons.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        })
    });




    // Events

    // Parse global options
    if (window.Chart) {
        parseOptions(Chart, chartOptions());
    }

    // Toggle options
    $toggle.on({
        'change': function () {
            var $this = $(this);

            if ($this.is('[data-add]')) {
                toggleOptions($this);
            }
        },
        'click': function () {
            var $this = $(this);

            if ($this.is('[data-update]')) {
                updateOptions($this);
            }
        }
    });


    // Return

    return {
        colors: colors,
        fonts: fonts,
        mode: mode
    };

})();
function postAjax(url, data, cb) {
    var token = $('meta[name="csrf-token"]').attr('content');
    var jdata = { _token: token };

    for (var k in data) {
        jdata[k] = data[k];
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: jdata,
        success: function (data) {
            if (typeof (data) === 'object') {
                cb(data);
            } else {
                cb(data);
            }
        },
    });
}

function deleteAjax(url, data, cb) {
    var token = $('meta[name="csrf-token"]').attr('content');
    var jdata = { _token: token };

    for (var k in data) {
        jdata[k] = data[k];
    }

    $.ajax({
        type: 'DELETE',
        url: url,
        data: jdata,
        success: function (data) {
            if (typeof (data) === 'object') {
                cb(data);
            } else {
                cb(data);
            }
        },
    });
}
// Import Data
function SetData(params, count = 0) {
    if (count < 8) {
        var process_area = document.getElementById("process_area");
        if (process_area) {
            $('#process_area').html(params);
        }
        else {
            setTimeout(function () {
                SetData(params, count + 1);
            }, 500);
        }
    }
    else {
        toastrs('Success', '{{ __("Something went wrong please try again!") }}', 'success');
    }
}

function decodeHtmlEntities(str) {
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}
function formatCurrency(price, settingsEntity) {
    let symbolPosition = 'pre';
    let currencySpace = null;
    let symbol = '$';
    let format = 2;
    let decimalSeparator = ',';
    let thousandSeparator = '.';
    const decodedString = decodeHtmlEntities(settingsEntity);
    const settings = JSON.parse(decodedString);

    price = parseFloat(price);
    if (isNaN(price)) {
        price = 0;
    }

    let length = price.toFixed(format).split('.')[0].length;
    if (settings) {
        if (settings.site_currency_symbol_position === 'post') {
            symbolPosition = 'post';
        }
        if (settings.defult_currancy_symbol) {
            symbol = settings.defult_currancy_symbol;
        }
        if (settings.currency_format) {
            format = parseInt(settings.currency_format, 10);
        }
        if (settings.currency_space) {
            currencySpace = settings.currency_space;
        }
        if (settings.site_currency_symbol_name) {
            symbol = settings.site_currency_symbol_name === 'symbol' ? settings.defult_currancy_symbol : settings.defult_currancy;
        }

        if (length > 3) {
            decimalSeparator = settings.float_number && settings.float_number !== 'dot' ? ',' : '.';
        } else {
            decimalSeparator = settings.decimal_separator && settings.decimal_separator !== 'dot' ? ',' : '.';
        }
        thousandSeparator = settings.thousand_separator === 'dot' ? '.' : ',';
    }

    let [integerPart, fractionalPart] = price.toFixed(format).split('.');
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

    let formattedPrice = integerPart + (fractionalPart ? decimalSeparator + fractionalPart : '');

    return (
        (symbolPosition === 'pre' ? symbol : '') +
        (currencySpace === 'withspace' ? ' ' : '') +
        formattedPrice +
        (currencySpace === 'withspace' ? ' ' : '') +
        (symbolPosition === 'post' ? symbol : '')
    );
    
    // General function to check if a field value is negative
    function checkNotNegative(fieldId) {
        let value = parseFloat($('#' + fieldId).val());

        if (!isNaN(value) && value < 0) {
            toastrs('Error', 'Value cannot be negative', 'error');
            $('#' + fieldId).val(0); // reset to 0 or any default
            return false;
        }
        return true;
    };

}



