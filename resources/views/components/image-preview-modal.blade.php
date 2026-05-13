@once
@push('css')
<link href="{{ asset('css/image-preview-modal.css') }}" rel="stylesheet">
<style>
/* Fallback styles in case external CSS doesn't load */
.image-preview-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.95) !important;
    z-index: 10000 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 0 !important;
    visibility: hidden !important;
    transition: opacity 0.3s ease, visibility 0.3s ease !important;
}

.image-preview-modal.active {
    opacity: 1 !important;
    visibility: visible !important;
}

.image-preview-modal__content {
    position: relative !important;
    width: 90% !important;
    height: 90% !important;
    max-width: 1200px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.image-preview-modal__image-container {
    position: relative !important;
    width: 100% !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    overflow: hidden !important;
}

.image-preview-modal__image {
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: contain !important;
    transition: transform 0.3s ease !important;
    cursor: grab !important;
    user-select: none !important;
    -webkit-user-drag: none !important;
}

.image-preview-modal__close {
    position: absolute !important;
    top: 1rem !important;
    right: 1rem !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: white !important;
    font-size: 2rem !important;
    width: 3rem !important;
    height: 3rem !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.3s ease !important;
    z-index: 10 !important;
}

.image-preview-modal__close:hover {
    background: rgba(255, 255, 255, 0.2) !important;
}

.image-preview-modal__nav {
    position: absolute !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: white !important;
    font-size: 1.5rem !important;
    width: 3rem !important;
    height: 3rem !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.3s ease !important;
    z-index: 10 !important;
}

.image-preview-modal__toolbar {
    position: absolute !important;
    bottom: 1rem !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    background: rgba(0, 0, 0, 0.8) !important;
    border-radius: 2rem !important;
    padding: 0.5rem 1rem !important;
    display: flex !important;
    gap: 0.5rem !important;
    z-index: 10 !important;
}

.image-preview-modal__tool-btn {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: white !important;
    font-size: 1rem !important;
    width: 2.5rem !important;
    height: 2.5rem !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.3s ease !important;
}

.image-preview-modal__tool-btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/image-preview-modal.js') }}"></script>
@endpush
@endonce
