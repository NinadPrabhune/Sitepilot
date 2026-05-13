<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'src' => null,
    'alt' => 'Preview image',
    'images' => null,
    'initialIndex' => 0,
    'class' => 'btn btn-sm btn-primary',
    'icon' => '🔍',
    'text' => 'Preview'
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'src' => null,
    'alt' => 'Preview image',
    'images' => null,
    'initialIndex' => 0,
    'class' => 'btn btn-sm btn-primary',
    'icon' => '🔍',
    'text' => 'Preview'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $imageArray = $images ?? ($src ? [$src] : []);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($imageArray)): ?>
    <button 
        type="button"
        class="<?php echo e($class); ?> image-preview-trigger"
        onclick="openImagePreview({
            images: <?php echo \Illuminate\Support\Js::from($imageArray)->toHtml() ?>,
            initialIndex: <?php echo e($initialIndex); ?>,
            enableZoom: true,
            enableFullscreen: true,
            zIndex: 10000
        })"
        aria-label="<?php echo e($alt); ?>"
    >
        <?php echo e($icon); ?> <?php echo e($text); ?>

    </button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/components/image-preview-button.blade.php ENDPATH**/ ?>