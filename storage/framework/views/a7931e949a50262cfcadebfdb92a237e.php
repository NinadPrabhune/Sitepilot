<?php
    use Knuckles\Scribe\Tools\Utils as u;
?>
<a href="#" id="nav-button">
    <span>
        MENU
        <img src="<?php echo $assetPathPrefix; ?>images/navbar.png" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($metadata['logo'] != false): ?>
        <img src="<?php echo e($metadata['logo']); ?>" alt="logo" class="logo" style="padding-top: 10px;" width="100%"/>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($metadata['example_languages'])): ?>
        <div class="lang-selector">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $metadata['example_languages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $lang): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if (is_numeric($name)) $name = $lang; ?>
                <button type="button" class="lang-button" data-language-name="<?php echo e($lang); ?>"><?php echo e($name); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="<?php echo e(u::trans("scribe::labels.search")); ?>">
    </div>

    <div id="toc">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $headings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h1): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <ul id="tocify-header-<?php echo e($h1['slug']); ?>" class="tocify-header">
                <li class="tocify-item level-1" data-unique="<?php echo $h1['slug']; ?>">
                    <a href="#<?php echo $h1['slug']; ?>"><?php echo $h1['name']; ?></a>
                </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($h1['subheadings']) > 0): ?>
                    <ul id="tocify-subheader-<?php echo $h1['slug']; ?>" class="tocify-subheader">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $h1['subheadings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h2): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="tocify-item level-2" data-unique="<?php echo $h2['slug']; ?>">
                                <a href="#<?php echo $h2['slug']; ?>"><?php echo $h2['name']; ?></a>
                            </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($h2['subheadings']) > 0): ?>
                                <ul id="tocify-subheader-<?php echo $h2['slug']; ?>" class="tocify-subheader">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $h2['subheadings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h3): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li class="tocify-item level-3" data-unique="<?php echo $h3['slug']; ?>">
                                            <a href="#<?php echo $h3['slug']; ?>"><?php echo $h3['name']; ?></a>
                                        </li>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </ul>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <ul class="toc-footer" id="toc-footer">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($metadata['postman_collection_url']): ?>
            <li style="padding-bottom: 5px;"><a href="<?php echo $metadata['postman_collection_url']; ?>"><?php echo u::trans("scribe::links.postman"); ?></a></li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($metadata['openapi_spec_url']): ?>
            <li style="padding-bottom: 5px;"><a href="<?php echo $metadata['openapi_spec_url']; ?>"><?php echo u::trans("scribe::links.openapi"); ?></a></li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li><?php echo e($metadata['last_updated']); ?></li>
    </ul>
</div>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/vendor/scribe/themes/default/sidebar.blade.php ENDPATH**/ ?>