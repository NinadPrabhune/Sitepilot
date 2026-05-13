<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Classes\Module;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\MigrationsEnded;

use Illuminate\Support\Facades\Gate;
use App\Models\ProjectFileNew;
use App\Policies\ProjectFilePolicy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use App\Models\Indent;
use App\Models\PurchaseOrder;
use App\Models\Grn;
use App\Models\PurchaseInvoice;
use App\Models\PaymentRequest;
use App\Observers\IndentObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\GrnObserver;
use App\Observers\PurchaseInvoiceObserver;
use App\Observers\PaymentRequestObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    
    protected $policies = [ ProjectFileNew::class => ProjectFilePolicy::class, ];
    
    
    public function register(): void
    {
        // Existing binding
        $this->app->singleton('module', function ($app) {
            return new Module();
        });

        // Bind NotificationService so it can be injected into jobs/controllers
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register procurement notification observers
        Indent::observe(IndentObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        Grn::observe(GrnObserver::class);
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
        PaymentRequest::observe(PaymentRequestObserver::class);

        // 🚨 FORENSIC LOGGING: Track all migration events
        Event::listen(MigrationsStarted::class, function ($event) {
            Log::critical('🚨 MIGRATIONS STARTED', [
                'time' => now()->toDateTimeString(),
                'url' => request()->fullUrl() ?? 'CLI',
                'method' => request()->method() ?? 'CLI',
                'user_id' => auth()->id() ?? null,
                'ip' => request()->ip() ?? 'CLI',
            ]);
        });

        Event::listen(MigrationsEnded::class, function ($event) {
            Log::critical('✅ MIGRATIONS ENDED', [
                'time' => now()->toDateTimeString(),
            ]);
        });

        // 🛡️ NUCLEAR SAFETY SWITCH: Block all Artisan::call from web requests
        // Note: macro() not supported on Artisan facade in current Laravel version
        // Alternative: Use middleware or direct checks in controllers instead

        // 🧨 DB QUERY LOGGING: Track INSERT/UPDATE/DELETE operations (TEMPORARY DEBUG MODE)
        if (config('app.log_db_operations', false)) {
            DB::listen(function ($query) {
                $sql = strtolower($query->sql);
                if (str_contains($sql, 'insert') ||
                    str_contains($sql, 'update') ||
                    str_contains($sql, 'delete') ||
                    str_contains($sql, 'truncate') ||
                    str_contains($sql, 'drop')) {

                    Log::warning('🧨 DB WRITE OPERATION', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                        'url' => request()->fullUrl() ?? 'CLI',
                        'user_id' => auth()->id() ?? null,
                    ]);
                }
            });
        }

        // 🔐 GLOBAL TRUNCATE BLOCK: Prevent truncate operations outside local environment
        DB::listen(function ($query) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'truncate')) {
                if (!app()->environment('local')) {
                    Log::critical('🚨 TRUNCATE BLOCKED IN PRODUCTION', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'url' => request()->fullUrl() ?? 'CLI',
                        'user_id' => auth()->id() ?? null,
                    ]);

                    throw new \Exception('🚨 TRUNCATE is not allowed outside local environment for production safety.');
                }
            }
        });

        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }

        if (function_exists('getAdminAllSetting')) {
            $admin_settings = getAdminAllSetting();

            // Only override if admin settings have valid values, otherwise use .env defaults
            Config::set('broadcasting.connections.pusher.key', $admin_settings['PUSHER_APP_KEY'] ?? env('PUSHER_APP_KEY', ''));
            Config::set('broadcasting.connections.pusher.secret', $admin_settings['PUSHER_APP_SECRET'] ?? env('PUSHER_APP_SECRET', ''));
            Config::set('broadcasting.connections.pusher.app_id', $admin_settings['PUSHER_APP_ID'] ?? env('PUSHER_APP_ID', ''));
            Config::set('broadcasting.connections.pusher.options.cluster', $admin_settings['PUSHER_APP_CLUSTER'] ?? env('PUSHER_APP_CLUSTER', 'ap2'));
            Config::set('broadcasting.connections.pusher.options.useTLS', true);
        }

        // Slow Query Logger for Payment System
        if (config('app.env') !== 'production' || config('app.debug')) {
            DB::listen(function ($query) {
                $slowThreshold = config('app.slow_query_threshold', 200);

                // Log slow queries related to payment system
                if ($query->time > $slowThreshold) {
                    $isPaymentQuery = (
                        strpos($query->sql, 'payment_requests') !== false ||
                        strpos($query->sql, 'payments_module') !== false ||
                        strpos($query->sql, 'advance_adjustments') !== false ||
                        strpos($query->sql, 'purchase_invoices') !== false
                    );

                    if ($isPaymentQuery) {
                        Log::warning('Slow Payment Query Detected', [
                            'time_ms' => $query->time,
                            'sql' => $query->sql,
                            'bindings' => $query->bindings,
                            'url' => request()->fullUrl() ?? 'N/A',
                        ]);
                    }
                }
            });
        }

        // Auto-sync Admin permissions with Company to prevent drift
        try {
            $company = \App\Models\Role::where('name', 'company')->first();
            $admin = \App\Models\Role::where('name', 'admin')->first();

            if ($company && $admin) {
                if ($admin->permissions->count() !== $company->permissions->count()) {
                    $admin->syncPermissions($company->permissions);
                    Log::info("Auto-synced Admin permissions to match Company");
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to auto-sync permissions: " . $e->getMessage());
        }

        // Auto-generate API docs on every request (dev only) with smart change detection
        // DISABLED: User will create API docs manually
        // Only run in local environment AND explicitly enabled via env variable
        /*
        if (app()->environment('local') && env('AUTO_GENERATE_API_DOCS', false) && !app()->environment('production')) {
            if (!app()->runningInConsole()) {
                // Performance guard: prevent concurrent generation
                $lock = Cache::lock('scribe_generation_lock', 60);
                if ($lock->get()) {
                    try {
                        // Smart regeneration: only when controllers actually change
                        $controllersPath = app_path('Http/Controllers');
                        $lastModified = 0;

                        if (File::exists($controllersPath)) {
                            $files = File::allFiles($controllersPath);
                            foreach ($files as $file) {
                                $lastModified = max($lastModified, $file->getMTime());
                            }
                        }

                        $cachedLastModified = Cache::get('scribe_last_modified', 0);

                        // Only regenerate if controllers have changed
                        if ($lastModified > $cachedLastModified) {
                            if (true) {
                                Artisan::call('scribe:generate', ['--force' => true]);
                                Cache::put('scribe_last_modified', $lastModified, now()->addHours(1));
                                Log::info('API documentation auto-generated (controllers changed) at ' . now()->format('Y-m-d H:i:s'));
                            } else {
                                Log::warning('scribe:generate command not found - skipping API documentation auto-generation');
                                Cache::put('scribe_last_modified', $lastModified, now()->addHours(1));
                            }

                            // API Change Tracking: Detect actual schema changes
                            $openapiPath = public_path('docs/openapi.yaml');
                            if (file_exists($openapiPath)) {
                                $currentHash = md5_file($openapiPath);
                                $lastHash = Cache::get('last_openapi_hash', '');

                                if ($currentHash !== $lastHash) {
                                    Log::info('API schema changed detected', [
                                        'old_hash' => substr($lastHash, 0, 8) . '...',
                                        'new_hash' => substr($currentHash, 0, 8) . '...',
                                        'timestamp' => now()->format('Y-m-d H:i:s'),
                                        'file_size' => filesize($openapiPath) . ' bytes',
                                    ]);

                                    // Store new hash for future comparisons
                                    Cache::forever('last_openapi_hash', $currentHash);

                                    // Store change history (last 10 changes)
                                    $changeHistory = Cache::get('api_change_history', []);
                                    array_unshift($changeHistory, [
                                        'hash' => $currentHash,
                                        'timestamp' => now()->toISOString(),
                                        'file_size' => filesize($openapiPath),
                                    ]);
                                    Cache::put('api_change_history', array_slice($changeHistory, 0, 10));
                                }
                            }
                        }

                        $lock->release();
                    } catch (\Exception $e) {
                        $lock->release();
                        Log::error('Auto-generation of API docs failed: ' . $e->getMessage());
                    }
                }
            }
        }
        */
    }
}
