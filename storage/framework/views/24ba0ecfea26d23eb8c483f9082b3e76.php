<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($authenticated): ?><?php $__env->startComponent('scribe::components.badges.base', ['colour' => "darkred", 'text' => 'requires authentication']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/vendor/scribe/components/badges/auth.blade.php ENDPATH**/ ?>