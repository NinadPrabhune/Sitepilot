<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Preview Modal Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('components.image-preview-modal')
</head>
<body>
    <div class="container mt-5">
        <h1>Image Preview Modal Test Page</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Single Image Tests</h3>
                
                <div class="mb-3">
                    <h5>Thumbnail Click</h5>
                    <img src="https://picsum.photos/300/200?random=1" 
                         alt="Test Image 1" 
                         style="max-width: 150px; cursor: pointer; border: 1px solid #ddd;" 
                         onclick="openImagePreview({
                             images: ['https://picsum.photos/1200/800?random=1']
                         })">
                </div>
                
                <div class="mb-3">
                    <h5>Helper Button</h5>
                    @include('components.image-preview-button', [
                        'src' => 'https://picsum.photos/1200/800?random=2',
                        'text' => 'Preview Single Image',
                        'class' => 'btn btn-primary'
                    ])
                </div>
            </div>
            
            <div class="col-md-6">
                <h3>Gallery Tests</h3>
                
                <div class="mb-3">
                    <h5>Gallery Navigation</h5>
                    <button class="btn btn-success" onclick="openImagePreview({
                        images: [
                            'https://picsum.photos/1200/800?random=3',
                            'https://picsum.photos/1200/800?random=4',
                            'https://picsum.photos/1200/800?random=5',
                            'https://picsum.photos/1200/800?random=6'
                        ],
                        initialIndex: 1
                    })">
                        Open Gallery (4 images)
                    </button>
                </div>
                
                <div class="mb-3">
                    <h5>Gallery with Helper Component</h5>
                    @include('components.image-preview-button', [
                        'images' => [
                            'https://picsum.photos/1200/800?random=7',
                            'https://picsum.photos/1200/800?random=8',
                            'https://picsum.photos/1200/800?random=9'
                        ],
                        'initialIndex' => 0,
                        'text' => 'View Gallery (3 images)',
                        'class' => 'btn btn-info'
                    ])
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h3>Modal Stacking Test</h3>
                <p>Test opening this modal first, then clicking on images to see proper stacking:</p>
                
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#testModal">
                    Open Test Modal First
                </button>
                
                <!-- Test Modal -->
                <div class="modal fade" id="testModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Test Modal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Click on the images below to test modal stacking:</p>
                                
                                <div class="d-flex gap-3">
                                    <img src="https://picsum.photos/150/100?random=10" 
                                         alt="Stack Test 1" 
                                         style="cursor: pointer; border: 1px solid #ddd;" 
                                         onclick="openImagePreview({
                                             images: ['https://picsum.photos/1200/800?random=10']
                                         })">
                                    
                                    <img src="https://picsum.photos/150/100?random=11" 
                                         alt="Stack Test 2" 
                                         style="cursor: pointer; border: 1px solid #ddd;" 
                                         onclick="openImagePreview({
                                             images: [
                                                 'https://picsum.photos/1200/800?random=11',
                                                 'https://picsum.photos/1200/800?random=12'
                                             ]
                                         })">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h3>Keyboard Shortcuts Reference</h3>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li><kbd>Esc</kbd> - Close modal</li>
                            <li><kbd>←</kbd> / <kbd>→</kbd> - Previous/Next image</li>
                            <li><kbd>+</kbd> / <kbd>=</kbd> - Zoom in</li>
                            <li><kbd>-</kbd> / <kbd>_</kbd> - Zoom out</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li><kbd>0</kbd> - Reset zoom</li>
                            <li><kbd>F</kbd> - Toggle fullscreen</li>
                            <li><kbd>Tab</kbd> - Navigate controls</li>
                            <li><kbd>Mouse Wheel</kbd> - Zoom in/out</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
