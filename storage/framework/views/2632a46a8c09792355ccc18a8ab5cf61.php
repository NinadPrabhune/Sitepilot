<style>
    /* Notification badge */
    #badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #f56565;
        color: #fff;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: bold;
        border: 2px solid #fff;
    }

    /* Dropdown container */
    #dropdown {
        position: absolute;
        top: 60px;
        right: 0;
        width: 320px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        display: none;
        flex-direction: column;
        z-index: 1000;
    }
    #dropdown.active {
        display: flex;
    }

    /* Dropdown header */
    #dropdown .dropdown-header {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #dropdown .dropdown-header h3 {
        font-size: 16px;
        color: #2d3748;
    }
    #dropdown .mark-read {
        color: #667eea;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
    }
    #dropdown .mark-read:hover {
        text-decoration: underline;
    }

    /* Notifications list */
    .notifications-list {
        max-height: 300px;
        overflow-y: auto;
    }
    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f7fafc;
        display: flex;
        gap: 10px;
        cursor: pointer;
    }
    .notification-item.unread {
        background: #edf2f7;
    }
    .notification-item:hover {
        background: #f7fafc;
    }

    /* Notification icon */
    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .notification-icon.info {
        background: #bee3f8;
        color: #2c5282;
    }
    .notification-icon.success {
        background: #c6f6d5;
        color: #22543d;
    }
    .notification-icon.warning {
        background: #feebc8;
        color: #7c2d12;
    }

    /* Notification text */
    .notification-title {
        font-weight: 600;
        font-size: 14px;
        color: #2d3748;
    }
    .notification-message {
        font-size: 13px;
        color: #718096;
        line-height: 1.4;
    }
    .notification-time {
        font-size: 12px;
        color: #a0aec0;
    }

    /* Dropdown footer */
    #dropdown .dropdown-footer {
        padding: 12px;
        border-top: 1px solid #e2e8f0;
        text-align: center;
    }
    #dropdown .see-more {
        color: #667eea;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
    }
    #dropdown .see-more:hover {
        text-decoration: underline;
    }

    /* Empty state */
    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: #a0aec0;
    }

</style>
<?php
    $transparent = $company_settings['site_transparent'] ?? null;
?>

<header class="dash-header <?php echo e(empty($transparent) || $transparent == 'on' ? 'transprent-bg' : ''); ?>">

    <div class="header-wrapper">
        



        <div class="ms-auto">
            <ul class="list-unstyled">







                <li class="dash-h-item dropdown">
                    <a href="#" class="dash-head-link" id="bellIcon">
                        <i class="ti ti-bell"></i>
                        <span class="bg-danger dash-h-badge message-counter custom_messanger_counter" id="notificationBadge">
                            0<span class="sr-only"></span>
                        </span>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end" id="dropdown" style="width: 320px;">
                        <div class="dropdown-header d-flex justify-content-between align-items-center">
                            <h3><?php echo e(__('Notifications')); ?></h3>
                            <a href="<?php echo e(route('notifications.markAllAsRead')); ?>" class="mark-read" id="markRead"><?php echo e(__('Mark all as read')); ?></a>
                        </div>
                        <div class="notifications-list" id="notificationsList" role="list">
                            <div class="empty-state">
                                <i class="ti ti-bell"></i>
                                <p><?php echo e(__('No notifications')); ?></p>
                            </div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="<?php echo e(route('notifications.index')); ?>" class="see-more"><?php echo e(__('See All Notifications')); ?></a>
                        </div>
                    </div>
                </li>





                <?php if (is_impersonating()) : ?>
                <li class="dropdown dash-h-item drp-company">
                    <a class="btn btn-danger btn-sm me-3" href="<?php echo e(route('exit.company')); ?>"><i class="ti ti-ban"></i>
                        <?php echo e(__('Exit Company Login')); ?>

                    </a>
                </li>
                <?php endif; ?>
                <?php if (app('laratrust')->hasPermission('user chat manage')) : ?>
                <li class="dash-h-item">
                    <a class="dash-head-link me-0" href="<?php echo e(url('/chatify')); ?>">
                        <i class="ti ti-message-circle"></i>
                        <span
                            class="bg-danger dash-h-badge message-counter custom_messanger_counter" id="chatifyBadge">0<span
                                class="sr-only"></span>
                    </a>
                </li>
                <?php endif; // app('laratrust')->permission ?>
                <?php if (app('laratrust')->hasPermission('workspace create')) : ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(PlanCheck('Workspace', Auth::user()->id) == true): ?>
<!--                <li class="dash-h-item">
                    <a href="#!" class="dash-head-link dropdown-toggle arrow-none me-0 cust-btn"
                       data-url="<?php echo e(route('workspace.create')); ?>" data-ajax-popup="true" data-size="lg"
                       data-title="<?php echo e(__('Create New Workspace')); ?>">
                        <i class="ti ti-circle-plus"></i>
                        <span class="hide-mob"><?php echo e(__('Create Workspace')); ?></span>
                    </a>
                </li>-->
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; // app('laratrust')->permission ?>
                <?php
//                dd(getWorkspace());
                ?>

                
                <li class="dash-h-item">
                    <a class="dash-head-link me-0" href="<?php echo e(url('/clear-all')); ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo e(__('Refresh / Clear Cache')); ?>">
                        <i class="ti ti-refresh"></i>
                        <span class="sr-only"><?php echo e(__('Refresh')); ?></span>
                    </a>
                </li>
                

                <?php if (app('laratrust')->hasPermission('workspace manage')) : ?>
                <li class="dropdown dash-h-item drp-language">
                    <a class="dash-head-link dropdown-toggle arrow-none me-0 cust-btn" data-bs-toggle="dropdown"
                       href="#" role="button" aria-haspopup="false" aria-expanded="false"
                       data-bs-placement="bottom" data-bs-original-title="Select your bussiness">
                        <i class="ti ti-apps"></i>
                        <span class="hide-mob"><?php echo e(Auth::user()->ActiveWorkspaceName()); ?></span>
                        <i class="ti ti-chevron-down drp-arrow nocolor"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end" >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = getWorkspace(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $workspace): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace->id == getActiveWorkSpace()): ?>
                        <div class="d-flex justify-content-between bd-highlight">
                            <a href=" # " class="dropdown-item ">
                                <i class="ti ti-checks text-primary"></i>
                                <span><?php echo e($workspace->name); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace->created_by == Auth::user()->id): ?>
                                <span class="badge bg-dark">
                                    <?php echo e(Auth::user()->roles->first()?->name ?? __('User')); ?></span>
                                <?php else: ?>
                                <span class="badge bg-dark"> <?php echo e(__('Shared')); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace->created_by == Auth::user()->id): ?>
                            <?php if (app('laratrust')->hasPermission('workspace edit')) : ?>
                            <div class="action-btn mt-2">
                                <a data-url="<?php echo e(route('workspace.edit', $workspace->id)); ?>"
                                   class="mx-3 btn" data-ajax-popup="true" data-size="xl"
                                   data-title="<?php echo e(__('Edit Workspace Name')); ?>" data-toggle="tooltip"
                                   data-original-title="<?php echo e(__('Edit')); ?>">
                                    <i class="ti ti-pencil text-success"></i>
                                </a>
                            </div>
                            <?php endif; // app('laratrust')->permission ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php
                        $route = ($workspace->is_disable == 1) ?  route('workspace.change', $workspace->id) : '#';
                        ?>
                        <div class="d-flex justify-content-between bd-highlight">

                            <a href="<?php echo e($route); ?>" class="dropdown-item">
                                <span><?php echo e($workspace->name); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace->created_by == Auth::user()->id): ?>
                                <span class="badge bg-dark"> <?php echo e(Auth::user()->roles->first()?->name ?? __('User')); ?></span>
                                <?php else: ?>
                                <span class="badge bg-dark"> <?php echo e(__('Shared')); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace->is_disable == 0): ?>
                            <div class="action-btn mt-2">
                                <i class="ti ti-lock"></i>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(getWorkspace()->count() > 1): ?>
                        
                        <hr class="dropdown-divider" />
                        <a href="#!" data-url="<?php echo e(route('company.info', Auth::user()->id)); ?>" class="dropdown-item" data-ajax-popup="true" data-size="lg" data-title="<?php echo e(__('Workspace Info')); ?>">
                            <i class="ti ti-circle-x"></i>
                            <span><?php echo e(__('View')); ?></span> <br>
                        </a>


                        <hr class="dropdown-divider" />
                        <?php if (app('laratrust')->hasPermission('workspace create')) : ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(PlanCheck('Workspace', Auth::user()->id) == true): ?>
                        <a href="#!" class="dropdown-item"
                            data-url="<?php echo e(route('workspace.create')); ?>" data-ajax-popup="true" data-size="xl"
                            data-title="<?php echo e(__('Create New Workspace')); ?>">
                             <i class="ti ti-circle-plus"></i>
                             <span class="hide-mob"><?php echo e(__('Create Workspace')); ?></span>
                         </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; // app('laratrust')->permission ?>
                        <?php if (app('laratrust')->hasPermission('workspace delete')) : ?>
                        <!--                                    <form id="remove-workspace-form"
                                                                action="<?php echo e(route('workspace.destroy', getActiveWorkSpace())); ?>" method="POST">
                                                                <?php echo csrf_field(); ?>
                                                                <?php echo method_field('DELETE'); ?>
                                                                <a href="#!" class="dropdown-item remove_workspace">
                                                                    <i class="ti ti-circle-x"></i>
                                                                    <span><?php echo e(__('Remove')); ?></span> <br>
                                                                    <small class="text-danger"><?php echo e(__('Active Workspace Will Consider')); ?></small>
                                                                </a>
                                                            </form>-->
                        <?php endif; // app('laratrust')->permission ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </li>
                <?php endif; // app('laratrust')->permission ?>

                <?php if (app('laratrust')->hasPermission('project manage')) : ?>
                <?php
//                var_dump(getActiveProjectName());
                ?>

                <li class="dropdown dash-h-item drp-project">
                    <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                       role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-briefcase nocolor"></i>
                        <span class="drp-text hide-mob"><?php echo e(Str::upper(getActiveProjectName())); ?></span>
                        <i class="ti ti-chevron-down drp-arrow nocolor"></i>
                    </a>

                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = getProject(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('project.changeProject', $project->id)); ?>"
                           class="dropdown-item <?php if($project->id == getActiveProject()): ?> text-danger <?php endif; ?>">
                            <span><?php echo e(Str::ucfirst($project->name)); ?></span>
                        </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->type == 'super admin'): ?>
                        <?php if (app('laratrust')->hasPermission('project create')) : ?>
                        <a href="#" data-url="<?php echo e(route('projects.create')); ?>"
                           class="dropdown-item border-top pt-3 text-primary" data-ajax-popup="true"
                           data-title="<?php echo e(__('Create New Project')); ?>">
                            <span><?php echo e(__('Create Project')); ?></span>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>

                        <?php if (app('laratrust')->hasPermission('project manage')) : ?>
                        <a href="<?php echo e(route('projects.index')); ?>"
                           class="dropdown-item pt-3 text-primary">
                            <span><?php echo e(__('Manage Projects')); ?></span>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </li>
                <?php endif; // app('laratrust')->permission ?>


                <li class="dropdown dash-h-item drp-language d-none">
                    <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                       role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-world nocolor"></i>
                        <span class="drp-text hide-mob"><?php echo e(Str::upper(getActiveLanguage())); ?></span>
                        <i class="ti ti-chevron-down drp-arrow nocolor"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = languages(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $language): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('lang.change', $key)); ?>"
                           class="dropdown-item <?php if($key == getActiveLanguage()): ?> text-danger <?php endif; ?>">
                            <span><?php echo e(Str::ucfirst($language)); ?></span>
                        </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->type == 'super admin'): ?>
                        <?php if (app('laratrust')->hasPermission('language create')) : ?>
                        <a href="#" data-url="<?php echo e(route('create.language')); ?>"
                           class="dropdown-item border-top pt-3 text-primary" data-ajax-popup="true"
                           data-title="<?php echo e(__('Create New Language')); ?>">
                            <span><?php echo e(__('Create Language')); ?></span>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>
                        <?php if (app('laratrust')->hasPermission('language manage')) : ?>
                        <a href="<?php echo e(route('lang.index', [Auth::user()->lang])); ?>"
                           class="dropdown-item  pt-3 text-primary">
                            <span><?php echo e(__('Manage Languages')); ?></span>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </li>
            </ul>
        </div>
        <div class="dash-mob-drp">
            <ul class="list-unstyled">
                <li class="dash-h-item mob-hamburger">
                    <a href="#!" class="dash-head-link" id="mobile-collapse">
                        <div class="hamburger hamburger--arrowturn">
                            <div class="hamburger-box">
                                <div class="hamburger-inner"></div>
                            </div>
                        </div>
                    </a>
                </li>

                <li class="dropdown dash-h-item drp-company">
                    <a class="dash-head-link dropdown-toggle arrow-none m-0" data-bs-toggle="dropdown" href="#"
                       role="button" aria-haspopup="false"aria-expanded="false">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty(Auth::user()->avatar)): ?>
                        <span class="theme-avtar">
                            <img alt="#" src="<?php echo e(check_file(Auth::user()->avatar) ? get_file(Auth::user()->avatar) : ''); ?>"
                                 class="rounded border-2 border border-primary" style="width: 100% ; height: 100%">
                        </span>
                        <?php else: ?>
                        <span class="theme-avtar"><?php echo e(substr(Auth::user()->name ?? 'U', 0, 1)); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="hide-mob ms-2"><?php echo e(Auth::user()->name ?? __('User')); ?></span>
                        <i class="ti ti-chevron-down drp-arrow nocolor hide-mob"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown">
                        <?php if (app('laratrust')->hasPermission('user profile manage')) : ?>
                        <a href="<?php echo e(route('profile')); ?>" class="dropdown-item">
                            <i class="ti ti-user"></i>
                            <span><?php echo e(__('Profile')); ?></span>
                        </a>
                        <?php endif; // app('laratrust')->permission ?>
                        <a href="<?php echo e(route('logout')); ?>"
                           onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                           class="dropdown-item">
                            <i class="ti ti-power"></i>
                            <span><?php echo e(__('Logout')); ?></span>
                        </a>
                        <form id="frm-logout" action="<?php echo e(route('logout')); ?>" method="POST" class="d-none">
                            <?php echo e(csrf_field()); ?>

                        </form>
                    </div>
                </li>


                
            </ul>


        </div>
    </div>
    
</header>
<script>
// Notification API Base URL
    if (typeof notificationApiBase === 'undefined') {
        var notificationApiBase = '/notifications';
    }
    if (typeof authToken === 'undefined') {
        var authToken = document.querySelector('meta[name="csrf-token"]')?.content;
    }

// Get CSRF token from meta tag or cookies
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ||
                document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1];
    }

// Notification dropdown logic
    if (typeof bellIcon === 'undefined') {
        var bellIcon = document.getElementById('bellIcon');
    }
    if (typeof dropdown === 'undefined') {
        var dropdown = document.getElementById('dropdown');
    }
    if (typeof notificationBadge === 'undefined') {
        var notificationBadge = document.getElementById('notificationBadge');
    }
    if (typeof notificationsList === 'undefined') {
        var notificationsList = document.getElementById('notificationsList');
    }
    if (typeof markReadBtn === 'undefined') {
        var markReadBtn = document.getElementById('markRead');
    }

// Fetch notifications from API
    async function fetchNotifications(unreadOnly = true) {
        try {
            const endpoint = unreadOnly ? `${notificationApiBase}/unread` : `${notificationApiBase}`;
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (data.success) {
                updateNotificationsList(data.notifications);
                updateBadgeCount(data.unread_count);
            }
        } catch (error) {
    }
    }

// Update notifications list in UI
    function updateNotificationsList(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = `
            <div class="empty-state">
                <i class="ti ti-bell"></i>
                <p>${document.documentElement.lang === 'ar' ? 'لا توجد إشعارات' : 'No notifications'}</p>
            </div>
        `;
            return;
        }
        notificationsList.innerHTML = notifications.map(notif => `
        <a href="/notifications">
            <div class="notification-item ${notif.read ? '' : 'unread'}" data-notif-id="${notif.user_notif_id}">
                <div class="notification-icon ${notif.icon_type}">
                    ${getNotificationIcon(notif.type)}
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notif.title}</div>
                    <div class="notification-message">${notif.message}</div>
                    <div class="notification-time">${notif.time}</div>
                </div>
            </div>
        </a>
    `).join('');
    }

// Get appropriate icon for notification type
    function getNotificationIcon(type) {
        const icons = {
            'low_stock': '<i class="ti ti-alert-triangle"></i>',
            'birthday': '<i class="ti ti-cake"></i>',
            'announcement': '<i class="ti ti-bell-ringing"></i>',
            'holiday': '<i class="ti ti-calendar-event"></i>',
            'event': '<i class="ti ti-calendar"></i>',
        };
        return icons[type] || '<i class="ti ti-info-circle"></i>';
    }

// Update badge count
    function updateBadgeCount(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    }

// Handle notification click - mark as read
    async function handleNotificationClick(e) {
        const item = e.currentTarget;
        const notifId = item.getAttribute('data-notif-id');

        if (item.classList.contains('unread')) {
            markNotificationAsRead(notifId);
            item.classList.remove('unread');
        }

        // Navigate if action URL exists
        const actionUrl = item.getAttribute('data-action-url');
        if (actionUrl) {
            window.location.href = actionUrl;
        }
    }

// Mark single notification as read
    async function markNotificationAsRead(notifId) {
        try {
            const response = await fetch(`${notificationApiBase}/${notifId}/read`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (response.ok) {
                const data = await response.json();
                updateBadgeCount(data.unread_count);
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

// Mark all notifications as read
    async function markAllNotificationsAsRead() {
        try {
            const response = await fetch(`${notificationApiBase}/read-all`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (response.ok) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                updateBadgeCount(0);
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

// Delete notification
    async function deleteNotification(notifId) {
        try {
            await fetch(`${notificationApiBase}/delete`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({notification_id: notifId}),
            });

            fetchNotifications();
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }

// Toggle dropdown
    bellIcon.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropdown.classList.toggle('active');
        if (dropdown.classList.contains('active')) {
            fetchNotifications();
        }
    });

// Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== bellIcon && !bellIcon.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

// Mark all as read button
    markReadBtn.addEventListener('click', (e) => {
        e.preventDefault();
        markAllNotificationsAsRead();
    });

// Polling - refresh notifications every 30 seconds (fallback if Echo fails)
    setInterval(() => {
        if (dropdown.classList.contains('active')) {
            fetchNotifications();
        }
    }, 30000);

// Real-time Echo subscription for notifications
    if (window.Echo && window.pusherConfig && window.pusherConfig.key) {
        const currentProjectId = <?php echo e(getActiveProject() ?? 'null'); ?>;

        if (currentProjectId) {
            Echo.private(`site.${currentProjectId}`)
                .listen('.notification.created', (e) => {
                    // Refresh notifications when new notification is created
                    if (dropdown.classList.contains('active')) {
                        fetchNotifications();
                    } else {
                        // Just update badge count if dropdown is closed
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            badge.textContent = parseInt(badge.textContent) + 1;
                            badge.style.display = 'inline-block';
                        }
                    }
                })
                .error((error) => {
                    // Fallback to polling if Echo fails
                });
        }

        // Echo subscription for chatify messages
        Echo.private('private-chatify')
            .listen('messaging', (e) => {
                // Check if message is sent to current user
                if (e.to_id === <?php echo e(Auth::id()); ?>) {
                    const chatifyBadge = document.getElementById('chatifyBadge');
                    if (chatifyBadge) {
                        chatifyBadge.textContent = parseInt(chatifyBadge.textContent) + 1;
                        chatifyBadge.style.display = 'inline-block';
                    }
                }
            })
            .error((error) => {
            });
    }

// Initial load
document.addEventListener('DOMContentLoaded', () => {
    // Fetch unread count on page load
    fetch(`${notificationApiBase}/count`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateBadgeCount(data.unread_count);
        }
    })
    .catch(error => console.error('Error fetching notification count:', error));

    // Fetch notifications list on page load
    fetchNotifications();

    // Fetch chatify unseen count on page load
    fetch('/api/chat/unseen-count', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const chatifyBadge = document.getElementById('chatifyBadge');
            if (chatifyBadge) {
                chatifyBadge.textContent = data.unseen_count;
                chatifyBadge.style.display = data.unseen_count > 0 ? 'inline-block' : 'none';
            }
        }
    })
    .catch(error => console.error('Error fetching chatify count:', error));
});

// Firebase Cloud Messaging (FCM) Support
    if ('serviceWorker' in navigator && 'caches' in window) {
        // Initialize FCM if available
        if (typeof firebase !== 'undefined' && firebase.messaging) {
            try {
                const messaging = firebase.messaging();

                // Request notification permission
                if (Notification.permission === 'default') {
                    messaging.requestPermission()
                            .then(() => getAndSaveToken())
                            .catch((err) => {});
                } else if (Notification.permission === 'granted') {
                    getAndSaveToken();
                }

                // Handle foreground notifications
                messaging.onMessage((payload) => {
                    // Refresh notifications list
                    fetchNotifications();
                    // Show browser notification if in foreground
                    if (payload.notification) {
                        new Notification(payload.notification.title, {
                            icon: payload.notification.icon,
                            body: payload.notification.body,
                        });
                    }
                });
            } catch (error) {
            }
        }
    }

// Get and save FCM token
    async function getAndSaveToken() {
        try {
            const messaging = firebase.messaging();
            const token = await messaging.getToken({
                vapidKey: document.querySelector('meta[name="fcm-vapid-key"]')?.content,
            });

            if (token) {
                // Save token to server
                await fetch('/api/me/device-tokens', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('api_token') || ''}`,
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({token: token}),
                });
            }
        } catch (error) {
            console.error('Error getting FCM token:', error);
        }
    }
</script>

<?php /**PATH C:\wamp64\www\SitePilot\resources\views/partials/header.blade.php ENDPATH**/ ?>