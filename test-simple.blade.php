<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Image Preview Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('components.image-preview-modal')
</head>
<body>
    <div class="container mt-5">
        <h1>Simple Image Preview Test</h1>
        
        <div class="mb-3">
            <p>Click the image below to test the preview modal:</p>
            <img src="https://picsum.photos/300/200?random=1" 
                 alt="Test Image" 
                 style="max-width: 150px; cursor: pointer; border: 1px solid #ddd;" 
                 onclick="openImagePreview({
                     images: ['https://picsum.photos/1200/800?random=1']
                 })">
        </div>
        
        <div class="mb-3">
            <p>Or click the button:</p>
            <button class="btn btn-primary" onclick="openImagePreview({
                images: ['https://picsum.photos/1200/800?random=2']
            })">
                Open Preview
            </button>
        </div>
        
        <div class="mb-3">
            <p>Test function availability:</p>
            <button class="btn btn-secondary" onclick="testFunction()">
                Test openImagePreview Function
            </button>
            <div id="test-result"></div>
        </div>
    </div>
    
    <script>
        function testFunction() {
            const result = document.getElementById('test-result');
            if (typeof openImagePreview === 'function') {
                result.innerHTML = '<div class="alert alert-success">✅ openImagePreview function is available!</div>';
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
