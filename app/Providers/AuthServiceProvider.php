<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\ProjectDocument;
use App\Models\ProjectFileNew;
use App\Policies\ProjectDocumentPolicy;
use App\Policies\ProjectFilePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ProjectDocument::class => ProjectDocumentPolicy::class,
        ProjectFileNew::class => ProjectFilePolicy::class,
        \App\Policies\NumberingConfigPolicy::class => \App\Policies\NumberingConfigPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
