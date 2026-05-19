<?php
    $favicon = isset($company_settings['favicon']) ? $company_settings['favicon'] : (isset($admin_settings['favicon']) ? $admin_settings['favicon'] : 'uploads/logo/favicon.png');
?>
<head>

    <title><?php echo $__env->yieldContent('page-title'); ?> | <?php echo e(!empty($company_settings['title_text']) ? $company_settings['title_text'] : (!empty($admin_settings['title_text']) ? $admin_settings['title_text'] :'WorkDo')); ?>

    </title>

    <meta name="title" content="<?php echo e(!empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'WOrkdo Dash'); ?>">
    <meta name="keywords" content="<?php echo e(!empty($admin_settings['meta_keywords']) ? $admin_settings['meta_keywords'] : 'WorkDo Dash,SaaS solution,Multi-workspace'); ?>">
    <meta name="description" content="<?php echo e(!empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'Discover the efficiency of Dash, a user-friendly web application by WorkDo.'); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e(env('APP_URL')); ?>">
    <meta property="og:title" content="<?php echo e(!empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'WOrkdo Dash'); ?>">
    <meta property="og:description" content="<?php echo e(!empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'Discover the efficiency of Dash, a user-friendly web application by WorkDo.'); ?> ">
    <meta property="og:image" content="<?php echo e(get_file(
  isset($admin_settings['meta_image']) && !empty($admin_settings['meta_image']) && check_file($admin_settings['meta_image'])
    ? $admin_settings['meta_image']
    : 'uploads/meta/meta_image.png'
)); ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo e(env('APP_URL')); ?>">
    <meta property="twitter:title" content="<?php echo e(!empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'WOrkdo Dash'); ?>">
    <meta property="twitter:description" content="<?php echo e(!empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'Discover the efficiency of Dash, a user-friendly web application by WorkDo.'); ?> ">
    <meta property="twitter:image" content="<?php echo e(get_file( (!empty($admin_settings['meta_image'])) ? (check_file($admin_settings['meta_image'])) ?  $admin_settings['meta_image'] : 'uploads/meta/meta_image.png' : 'uploads/meta/meta_image.png'  )); ?><?php echo e('?'.time()); ?>">

    <meta name="author" content="Workdo.io">

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="url" content="<?php echo e(url('').'/'.config('chatify.routes.prefix')); ?>" data-user="<?php echo e(Auth::user() ? Auth::user()->id : ''); ?>">

    

    <!-- Favicon icon -->
    <link rel="icon" href="<?php echo e(check_file($favicon) ? get_file($favicon) : get_file('uploads/logo/favicon.png')); ?><?php echo e('?'.time()); ?>" type="image/x-icon" />

    <!-- font css -->
    <link rel="stylesheet" href="<?php echo e(asset('assets/fonts/tabler-icons.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/fonts/feather.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/fonts/fontawesome.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/fonts/material.css')); ?>">

    <!-- vendor css -->
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/style.css')); ?>">

    <link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/bootstrap-switch-button.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/datepicker-bs5.min.css')); ?>" >
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/plugins/flatpickr.min.css')); ?>" >
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/customizer.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/custome.css')); ?>">
    <style>
        :root {
            --color-customColor: <?= $color ?>;
        }
    </style>

    <link rel="stylesheet" href="<?php echo e(asset('css/custom-color.css')); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((isset($company_settings['site_rtl']) ? $company_settings['site_rtl'] : 'off')== 'on'): ?>
        <link rel="stylesheet" href="<?php echo e(asset('assets/css/style-rtl.css')); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') == 'on'): ?>
        <link rel="stylesheet" href="<?php echo e(asset('assets/css/style-dark.css')); ?>" id="main-style-link">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((isset($company_settings['site_rtl']) ? $company_settings['site_rtl'] : 'off' )!= 'on' && (isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') != 'on'): ?>
        <link rel="stylesheet" href="<?php echo e(asset('assets/css/style.css')); ?>" id="main-style-link">
    <?php else: ?>
        <link rel="stylesheet" href="" id="main-style-link">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php echo $__env->yieldPushContent('css'); ?>
    <?php echo $__env->yieldPushContent('availabilitylink'); ?>
    <script src="<?php echo e(asset('js/jquery.min.js')); ?>"></script>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/nprogress.css')); ?>">
    <script src="<?php echo e(asset('assets/js/nprogress.js')); ?>"></script>
    <link rel="stylesheet" href="<?php echo e(asset('css/responsive.css')); ?>">
</head>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/partials/head.blade.php ENDPATH**/ ?>