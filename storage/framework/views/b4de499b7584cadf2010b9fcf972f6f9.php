<nav class="dash-sidebar light-sidebar <?php echo e(empty($company_settings['site_transparent']) || $company_settings['site_transparent'] == 'on' ? 'transprent-bg' : ''); ?>">
    <div class="navbar-wrapper">
        <div class="m-header main-logo">
             <a href="<?php echo e(Auth::check() && Auth::user()->isAbleTo('dashboard manage') ? route('home') : route('taskly.dashboard')); ?>" class="b-brand">
                <!-- ========   change your logo hear   ============ -->
                <img src="<?php echo e(get_file(sidebar_logo())); ?><?php echo e('?' . time()); ?>" alt="" class="logo logo-lg 000" />
                
            </a>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($company_settings['category_wise_sidemenu']) && $company_settings['category_wise_sidemenu'] == 'on'): ?>
          <div class="tab-container">
            <div class="tab-sidemenu">
              <ul class="dash-tab-link nav flex-column" role="tablist" id="dash-layout-submenus">
              </ul>
            </div>
            <div class="tab-link">
              <div class="navbar-content">
                <div class="tab-content" id="dash-layout-tab">
                </div>
                <ul class="dash-navbar">
                    <?php echo getMenu(); ?>

                    <?php echo $__env->yieldPushContent('custom_side_menu'); ?>
                </ul>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="navbar-content">
              <ul class="dash-navbar">
                  <?php echo getMenu(); ?>

                  <?php echo $__env->yieldPushContent('custom_side_menu'); ?>
                  
                  
                  
<!--                  <li class="dash-item dash-hasmenu">
                      <a href="http://sitepilot/product-service" class="dash-link"> 
                            <span class="dash-micon"><i class="ti ti-shopping-cart"></i></span>
                            <span class="dash-mtext">Inventory</span>
                      </a>
                  </li>-->
                  
                  
                  
                  
                  
              </ul>
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>  
        
    </div>
</nav>
<?php /**PATH C:\wamp64\www\SitePilot\resources\views/partials/sidebar.blade.php ENDPATH**/ ?>