<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Local Attendance Image Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/image-preview-modal.css') }}" rel="stylesheet">
    @include('components.image-preview-modal')
</head>
<body>
    <div class="container mt-5">
        <h1>Test Local Attendance Image Preview</h1>
        
        <div class="alert alert-info">
            <h5>📋 Test Information</h5>
            <p>This page tests the image preview modal with locally created attendance records and images.</p>
            <p><strong>Employee:</strong> Shri (ID: 2)</p>
            <p><strong>Date:</strong> 2026-05-11</p>
            <p><strong>Status:</strong> Present</p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Clock In Image Test</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Image Path:</strong> uploads/attendance/clock_in_1778504866.jpg</p>
                        
                        <div class="mb-3">
                            <h6>Thumbnail (clickable):</h6>
                            <img src="{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}" 
                                 alt="Clock In" 
                                 style="max-width: 150px; cursor: pointer; border: 1px solid #ddd;" 
                                 onclick="openImagePreview({
                                     images: ['{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}']
                                 })">
                        </div>
                        
                        <div class="mb-3">
                            <h6>Preview Button:</h6>
                            @include('components.image-preview-button', [
                                'src' => asset('uploads/attendance/clock_in_1778504866.jpg'),
                                'text' => 'Preview Clock In',
                                'class' => 'btn btn-primary'
                            ])
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Clock Out Image Test</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Image Path:</strong> uploads/attendance/clock_out_1778504866.jpg</p>
                        
                        <div class="mb-3">
                            <h6>Thumbnail (clickable):</h6>
                            <img src="{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}" 
                                 alt="Clock Out" 
                                 style="max-width: 150px; cursor: pointer; border: 1px solid #ddd;" 
                                 onclick="openImagePreview({
                                     images: ['{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}']
                                 })">
                        </div>
                        
                        <div class="mb-3">
                            <h6>Preview Button:</h6>
                            @include('components.image-preview-button', [
                                'src' => asset('uploads/attendance/clock_out_1778504866.jpg'),
                                'text' => 'Preview Clock Out',
                                'class' => 'btn btn-success'
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Gallery Test (Both Images)</h5>
                    </div>
                    <div class="card-body">
                        <p>Test navigation between multiple images:</p>
                        
                        <button class="btn btn-info" onclick="openImagePreview({
                            images: [
                                '{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}',
                                '{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}'
                            ],
                            initialIndex: 0
                        })">
                            Open Gallery (Clock In First)
                        </button>
                        
                        <button class="btn btn-warning ms-2" onclick="openImagePreview({
                            images: [
                                '{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}',
                                '{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}'
                            ],
                            initialIndex: 1
                        })">
                            Open Gallery (Clock Out First)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Modal Stacking Test</h5>
                    </div>
                    <div class="card-body">
                        <p>Test modal stacking by opening this modal first, then clicking on images:</p>
                        
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#testModal">
                            Open Test Modal First
                        </button>
                        
                        <!-- Test Modal -->
                        <div class="modal fade" id="testModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Test Modal for Stacking</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Click on the images below to test modal stacking:</p>
                                        
                                        <div class="d-flex gap-3">
                                            <img src="{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}" 
                                                 alt="Stack Test 1" 
                                                 style="cursor: pointer; border: 1px solid #ddd;" 
                                                 onclick="openImagePreview({
                                                     images: ['{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}']
                                                 })">
                                            
                                            <img src="{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}" 
                                                 alt="Stack Test 2" 
                                                 style="cursor: pointer; border: 1px solid #ddd;" 
                                                 onclick="openImagePreview({
                                                     images: ['{{ asset('uploads/attendance/clock_out_1778504866.jpg') }}']
                                                 })">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Function Test</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-outline-primary" onclick="testFunction()">
                            Test openImagePreview Function
                        </button>
                        <div id="test-result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="{{ url('/attendance/monthly-report-new?month=2026-05&employee_id=2') }}" 
                               class="list-group-item list-group-item-action" target="_blank">
                                📊 Monthly Attendance Report (Live Test)
                            </a>
                            <a href="{{ url('/test-simple') }}" 
                               class="list-group-item list-group-item-action" target="_blank">
                                🖼️ Simple Image Preview Test
                            </a>
                            <a href="{{ url('/test-image-preview') }}" 
                               class="list-group-item list-group-item-action" target="_blank">
                                🎨 Comprehensive Image Preview Test
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function testFunction() {
            const result = document.getElementById('test-result');
            if (typeof openImagePreview === 'function') {
                result.innerHTML = '<div class="alert alert-success">✅ openImagePreview function is available!</div>';
                // Test with a simple image
                setTimeout(() => {
                    openImagePreview({
                        images: ['{{ asset('uploads/attendance/clock_in_1778504866.jpg') }}']
                    });
                }, 1000);
            } else {
                result.innerHTML = '<div class="alert alert-danger">❌ openImagePreview function is NOT available!</div>';
            }
        }
        
        // Test on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(testFunction, 1000);
        });
    </script>
</body>
</html>
