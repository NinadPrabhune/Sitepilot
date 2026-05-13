# Image Preview Modal - Usage Guide

## Overview

The Image Preview Modal is a reusable, accessible component that provides a full-featured image viewing experience with zoom, navigation, and keyboard support. It's designed to work globally across the application and handles modal stacking properly.

## Features

- **Single & Multiple Image Support**: Preview one image or navigate through a gallery
- **Zoom Controls**: Zoom in/out with mouse wheel, buttons, or keyboard shortcuts
- **Fullscreen Mode**: Toggle fullscreen viewing
- **Keyboard Navigation**: Full keyboard support (Esc, arrows, +/-, F)
- **Mobile Responsive**: Touch-friendly with drag-to-pan support
- **Modal Stacking**: Properly handles multiple modals with z-index management
- **Accessibility**: ARIA labels, focus trapping, and keyboard navigation
- **Loading States**: Handles loading, error, and empty states gracefully

## Quick Start

### 1. Include the Component

Add this to any Blade template where you want to use the image preview:

```blade
@include('components.image-preview-modal')
```

**Note**: The component uses `@once` directive, so it will only include the CSS/JS once per page load, even if included multiple times.

### 2. Basic Usage

#### Single Image Preview

```blade
<!-- Click on thumbnail -->
<img src="{{ asset('path/to/image.jpg') }}" 
     alt="Preview" 
     style="cursor: pointer;" 
     onclick="openImagePreview({
         images: ['{{ asset('path/to/image.jpg') }}']
     })">

<!-- Using the helper button component -->
@include('components.image-preview-button', [
    'src' => asset('path/to/image.jpg'),
    'text' => 'Preview Image'
])
```

#### Multiple Images Gallery

```blade
<button onclick="openImagePreview({
    images: [
        '{{ asset('image1.jpg') }}',
        '{{ asset('image2.jpg') }}',
        '{{ asset('image3.jpg') }}'
    ],
    initialIndex: 1
})">
    View Gallery (3 images)
</button>
```

## API Reference

### `openImagePreview(options)`

Opens the image preview modal with the specified options.

#### Parameters

- `images` (Array, required): Array of image URLs
- `initialIndex` (Number, default: 0): Index of the image to show first
- `enableZoom` (Boolean, default: true): Enable zoom functionality
- `enableFullscreen` (Boolean, default: true): Enable fullscreen mode
- `zIndex` (Number, default: 10000): Base z-index for the modal

#### Example

```javascript
openImagePreview({
    images: [
        'https://example.com/image1.jpg',
        'https://example.com/image2.jpg'
    ],
    initialIndex: 0,
    enableZoom: true,
    enableFullscreen: true,
    zIndex: 10000
});
```

## Helper Component

### `image-preview-button`

A convenient button component that handles the JavaScript call for you.

#### Props

- `src` (String): Single image URL
- `images` (Array): Array of image URLs (overrides `src`)
- `initialIndex` (Number, default: 0): Starting image index
- `class` (String, default: 'btn btn-sm btn-primary'): CSS classes
- `icon` (String, default: '🔍'): Button icon
- `text` (String, default: 'Preview'): Button text
- `alt` (String, default: 'Preview image'): ARIA label

#### Examples

```blade
<!-- Simple preview button -->
@include('components.image-preview-button', [
    'src' => asset('path/to/image.jpg')
])

<!-- Custom styling -->
@include('components.image-preview-button', [
    'src' => asset('path/to/image.jpg'),
    'class' => 'btn btn-success btn-lg',
    'icon' => '📷',
    'text' => 'View Photo'
])

<!-- Gallery preview -->
@include('components.image-preview-button', [
    'images' => [
        asset('photo1.jpg'),
        asset('photo2.jpg'),
        asset('photo3.jpg')
    ],
    'initialIndex' => 1,
    'text' => 'View Gallery'
])
```

## Keyboard Shortcuts

When the modal is open:

- `Esc`: Close modal
- `←` / `→`: Previous/Next image
- `+` / `=`: Zoom in
- `-` / `_`: Zoom out
- `0`: Reset zoom
- `F`: Toggle fullscreen
- `Tab`: Navigate between controls (with focus trapping)

## Integration Examples

### Attendance Modal (Current Implementation)

```blade
<!-- Clock In Image -->
@if($attendance->clock_in_image)
    <div class="d-flex align-items-center gap-2">
        <img src="{{ asset($attendance->clock_in_image) }}" 
             alt="Clock In" 
             style="max-width: 150px; cursor: pointer;" 
             onclick="openImagePreview({images: ['{{ asset($attendance->clock_in_image) }}']})">
        @include('components.image-preview-button', [
            'src' => asset($attendance->clock_in_image), 
            'text' => 'Preview', 
            'class' => 'btn btn-sm btn-info'
        ])
    </div>
@endif
```

### Product Gallery

```blade
<div class="product-gallery">
    @foreach($product->images as $index => $image)
        <img src="{{ asset($image->thumbnail_path) }}" 
             alt="Product image {{ $index + 1 }}"
             style="cursor: pointer;"
             onclick="openImagePreview({
                 images: @js($product->images->pluck('full_path')->toArray()),
                 initialIndex: {{ $index }}
             })">
    @endforeach
</div>
```

### Document Viewer

```blade
@if($document->has_images)
    <button class="btn btn-primary" onclick="openImagePreview({
        images: @js($document->get_image_urls()),
        initialIndex: 0
    })">
        View Document Images ({{ $document->image_count }})
    </button>
@endif
```

## Advanced Usage

### Dynamic Image Loading

```javascript
// Load images dynamically
function loadDocumentImages(documentId) {
    fetch(`/api/documents/${documentId}/images`)
        .then(response => response.json())
        .then(images => {
            openImagePreview({
                images: images.map(img => img.url),
                initialIndex: 0
            });
        });
}
```

### Custom Event Integration

```javascript
// Listen for custom events
document.addEventListener('imagePreview:opened', (e) => {
    console.log('Image preview opened', e.detail);
});

document.addEventListener('imagePreview:closed', (e) => {
    console.log('Image preview closed');
});
```

### Integration with Existing Modals

The image preview modal automatically handles modal stacking. If you have an existing modal open, the image preview will appear on top:

```blade
<!-- Inside any existing modal -->
<div class="modal fade" id="existingModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <!-- Image preview will stack on top -->
                <img src="{{ asset('image.jpg') }}" 
                     onclick="openImagePreview({images: ['{{ asset('image.jpg') }}']})"
                     style="cursor: pointer;">
            </div>
        </div>
    </div>
</div>
```

## Styling Customization

The modal uses CSS variables for easy customization:

```css
/* Override styles in your custom CSS */
.image-preview-modal {
    --modal-bg: rgba(0, 0, 0, 0.95);
    --toolbar-bg: rgba(0, 0, 0, 0.8);
    --button-bg: rgba(255, 255, 255, 0.1);
    --button-hover-bg: rgba(255, 255, 255, 0.2);
}
```

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Images Not Loading

1. Check that image URLs are accessible
2. Verify CORS headers for external images
3. Check browser console for error messages

### Modal Not Appearing

1. Ensure `@include('components.image-preview-modal')` is included
2. Check that JavaScript is not throwing errors
3. Verify z-index conflicts with other modals

### Keyboard Navigation Not Working

1. Ensure the modal has focus when opened
2. Check for other event listeners that might interfere
3. Verify no other elements are trapping focus

## Performance Considerations

- Images are loaded on-demand when navigated to
- Large images are automatically scaled to fit viewport
- Modal uses CSS transforms for smooth zoom/pan performance
- Memory is cleaned up when modal is closed

## Security Notes

- All image URLs should be properly validated
- Consider implementing access controls for sensitive images
- Use HTTPS for external image URLs when possible
