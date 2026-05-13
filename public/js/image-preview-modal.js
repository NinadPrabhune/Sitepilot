class ImagePreviewModal {
    constructor() {
        this.modal = null;
        this.currentImages = [];
        this.currentIndex = 0;
        this.currentZoom = 1;
        this.isFullscreen = false;
        this.isDragging = false;
        this.dragStart = { x: 0, y: 0 };
        this.imagePosition = { x: 0, y: 0 };
        this.activeModalCount = 0;
        this.originalZIndex = null;
        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        const modalHtml = `
            <div class="image-preview-modal" id="imagePreviewModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
                <div class="image-preview-modal__content">
                    <div class="image-preview-modal__image-container">
                        <img class="image-preview-modal__image" id="modalImage" alt="Preview image" draggable="false">
                        <div class="image-preview-modal__loading" id="modalLoading">Loading...</div>
                        <div class="image-preview-modal__error" id="modalError" style="display: none;">
                            <div>Failed to load image</div>
                            <small>Please check if the image URL is correct</small>
                        </div>
                    </div>
                    
                    <button class="image-preview-modal__close" id="modalClose" aria-label="Close preview">&times;</button>
                    <button class="image-preview-modal__nav image-preview-modal__nav--prev" id="modalPrev" aria-label="Previous image">‹</button>
                    <button class="image-preview-modal__nav image-preview-modal__nav--next" id="modalNext" aria-label="Next image">›</button>
                    
                    <div class="image-preview-modal__counter" id="modalCounter" style="display: none;"></div>
                    
                    <div class="image-preview-modal__toolbar">
                        <button class="image-preview-modal__tool-btn" id="modalZoomIn" aria-label="Zoom in">+</button>
                        <button class="image-preview-modal__tool-btn" id="modalZoomOut" aria-label="Zoom out">−</button>
                        <button class="image-preview-modal__tool-btn" id="modalReset" aria-label="Reset zoom">⟲</button>
                        <button class="image-preview-modal__tool-btn" id="modalFullscreen" aria-label="Toggle fullscreen">⛶</button>
                    </div>
                </div>
            </div>
        `;
        
        // Create portal container for modal
        const portal = document.createElement('div');
        portal.id = 'image-preview-modal-portal';
        portal.innerHTML = modalHtml;
        document.body.appendChild(portal);
        
        this.modal = document.getElementById('imagePreviewModal');
    }

    bindEvents() {
        // Close button
        document.getElementById('modalClose').addEventListener('click', () => this.close());
        
        // Navigation
        document.getElementById('modalPrev').addEventListener('click', () => this.navigate(-1));
        document.getElementById('modalNext').addEventListener('click', () => this.navigate(1));
        
        // Toolbar
        document.getElementById('modalZoomIn').addEventListener('click', () => this.zoom(1.2));
        document.getElementById('modalZoomOut').addEventListener('click', () => this.zoom(0.8));
        document.getElementById('modalReset').addEventListener('click', () => this.resetZoom());
        document.getElementById('modalFullscreen').addEventListener('click', () => this.toggleFullscreen());
        
        // Modal backdrop click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
        
        // Keyboard events
        document.addEventListener('keydown', (e) => {
            if (!this.modal.classList.contains('active')) return;
            
            switch(e.key) {
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.navigate(-1);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.navigate(1);
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    this.zoom(1.2);
                    break;
                case '-':
                case '_':
                    e.preventDefault();
                    this.zoom(0.8);
                    break;
                case '0':
                    e.preventDefault();
                    this.resetZoom();
                    break;
                case 'f':
                case 'F':
                    e.preventDefault();
                    this.toggleFullscreen();
                    break;
            }
        });
        
        // Image drag events
        const image = document.getElementById('modalImage');
        image.addEventListener('mousedown', (e) => this.startDrag(e));
        document.addEventListener('mousemove', (e) => this.drag(e));
        document.addEventListener('mouseup', () => this.endDrag());
        
        // Touch events for mobile
        image.addEventListener('touchstart', (e) => this.startDrag(e.touches[0]));
        document.addEventListener('touchmove', (e) => {
            if (this.isDragging) {
                e.preventDefault();
                this.drag(e.touches[0]);
            }
        });
        document.addEventListener('touchend', () => this.endDrag());
        
        // Mouse wheel zoom
        image.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.9 : 1.1;
            this.zoom(delta);
        });
    }

    open(options = {}) {
        const {
            images = [],
            initialIndex = 0,
            enableZoom = true,
            enableFullscreen = true,
            zIndex = 10000
        } = options;
        
        if (!images.length || !images[initialIndex]) {
            console.error('No valid images provided');
            return;
        }
        
        this.currentImages = images;
        this.currentIndex = initialIndex;
        this.originalZIndex = zIndex;
        
        // Set modal z-index
        this.modal.style.zIndex = zIndex;
        
        // Handle modal stacking
        this.handleModalStacking(true);
        
        // Show modal
        this.modal.classList.add('active');
        document.body.classList.add('image-preview-modal--no-scroll');
        
        // Load initial image
        this.loadImage(this.currentIndex);
        
        // Update UI
        this.updateNavigation();
        this.updateCounter();
        
        // Focus management
        this.trapFocus();
    }

    close() {
        this.modal.classList.remove('active');
        document.body.classList.remove('image-preview-modal--no-scroll');
        
        // Handle modal stacking
        this.handleModalStacking(false);
        
        // Reset state
        this.resetZoom();
        this.isFullscreen = false;
        this.modal.classList.remove('fullscreen');
        
        // Restore focus
        this.removeFocusTrap();
    }

    navigate(direction) {
        const newIndex = this.currentIndex + direction;
        
        if (newIndex < 0 || newIndex >= this.currentImages.length) {
            return;
        }
        
        this.currentIndex = newIndex;
        this.loadImage(this.currentIndex);
        this.updateNavigation();
        this.updateCounter();
        this.resetZoom();
    }

    loadImage(index) {
        const image = document.getElementById('modalImage');
        const loading = document.getElementById('modalLoading');
        const error = document.getElementById('modalError');
        
        loading.style.display = 'block';
        error.style.display = 'none';
        image.style.display = 'none';
        
        const img = new Image();
        img.onload = () => {
            image.src = this.currentImages[index];
            image.style.display = 'block';
            loading.style.display = 'none';
        };
        
        img.onerror = () => {
            loading.style.display = 'none';
            error.style.display = 'block';
        };
        
        img.src = this.currentImages[index];
    }

    updateNavigation() {
        const prevBtn = document.getElementById('modalPrev');
        const nextBtn = document.getElementById('modalNext');
        
        if (this.currentImages.length <= 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }
        
        prevBtn.style.display = 'flex';
        nextBtn.style.display = 'flex';
        
        if (this.currentIndex === 0) {
            prevBtn.classList.add('image-preview-modal__nav--disabled');
        } else {
            prevBtn.classList.remove('image-preview-modal__nav--disabled');
        }
        
        if (this.currentIndex === this.currentImages.length - 1) {
            nextBtn.classList.add('image-preview-modal__nav--disabled');
        } else {
            nextBtn.classList.remove('image-preview-modal__nav--disabled');
        }
    }

    updateCounter() {
        const counter = document.getElementById('modalCounter');
        
        if (this.currentImages.length <= 1) {
            counter.style.display = 'none';
            return;
        }
        
        counter.textContent = `${this.currentIndex + 1} / ${this.currentImages.length}`;
        counter.style.display = 'block';
    }

    zoom(factor) {
        this.currentZoom *= factor;
        this.currentZoom = Math.max(0.1, Math.min(5, this.currentZoom));
        this.applyTransform();
    }

    resetZoom() {
        this.currentZoom = 1;
        this.imagePosition = { x: 0, y: 0 };
        this.applyTransform();
    }

    toggleFullscreen() {
        this.isFullscreen = !this.isFullscreen;
        this.modal.classList.toggle('fullscreen', this.isFullscreen);
    }

    startDrag(e) {
        this.isDragging = true;
        this.dragStart = { x: e.clientX - this.imagePosition.x, y: e.clientY - this.imagePosition.y };
        document.getElementById('modalImage').classList.add('grabbing');
    }

    drag(e) {
        if (!this.isDragging) return;
        
        this.imagePosition.x = e.clientX - this.dragStart.x;
        this.imagePosition.y = e.clientY - this.dragStart.y;
        this.applyTransform();
    }

    endDrag() {
        this.isDragging = false;
        document.getElementById('modalImage').classList.remove('grabbing');
    }

    applyTransform() {
        const image = document.getElementById('modalImage');
        image.style.transform = `translate(${this.imagePosition.x}px, ${this.imagePosition.y}px) scale(${this.currentZoom})`;
    }

    handleModalStacking(opening) {
        if (opening) {
            this.activeModalCount++;
            
            // Find all existing modals and reduce their z-index
            const existingModals = document.querySelectorAll('.modal, .modal-backdrop, [role="dialog"]');
            existingModals.forEach(modal => {
                const currentZ = parseInt(window.getComputedStyle(modal).zIndex) || 0;
                if (currentZ > 0 && modal !== this.modal) {
                    modal.style.zIndex = currentZ - 1;
                }
            });
        } else {
            this.activeModalCount--;
            
            // Restore z-index values when closing
            if (this.activeModalCount === 0) {
                const existingModals = document.querySelectorAll('.modal, .modal-backdrop, [role="dialog"]');
                existingModals.forEach(modal => {
                    if (modal.style.zIndex) {
                        modal.style.zIndex = '';
                    }
                });
            }
        }
    }

    trapFocus() {
        this.focusableElements = this.modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        this.firstFocusableElement = this.focusableElements[0];
        this.lastFocusableElement = this.focusableElements[this.focusableElements.length - 1];
        
        this.modal.addEventListener('keydown', this.handleFocusTrap);
        this.firstFocusableElement?.focus();
    }

    handleFocusTrap = (e) => {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === this.firstFocusableElement) {
                    e.preventDefault();
                    this.lastFocusableElement?.focus();
                }
            } else {
                if (document.activeElement === this.lastFocusableElement) {
                    e.preventDefault();
                    this.firstFocusableElement?.focus();
                }
            }
        }
    }

    removeFocusTrap() {
        this.modal.removeEventListener('keydown', this.handleFocusTrap);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create global instance
    window.imagePreviewModal = new ImagePreviewModal();
    
    // Create global API function
    window.openImagePreview = function(options) {
        window.imagePreviewModal.open(options);
    };
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading
} else {
    // DOM is already loaded
    if (!window.imagePreviewModal) {
        window.imagePreviewModal = new ImagePreviewModal();
        window.openImagePreview = function(options) {
            window.imagePreviewModal.open(options);
        };
    }
}
