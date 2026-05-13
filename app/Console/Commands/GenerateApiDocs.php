<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API documentation using Scribe (OpenAPI + HTML)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if auto-generation is enabled
        if (!env('AUTO_GENERATE_API_DOCS', true)) {
            $this->info('API docs auto-generation is disabled via AUTO_GENERATE_API_DOCS env variable.');
            return self::SUCCESS;
        }

        $this->info('Generating API documentation...');

        try {
            Artisan::call('scribe:generate', ['--force' => true]);
            $this->info('API documentation regenerated successfully!');
            $this->info('OpenAPI spec: public/docs/openapi.yaml');
            $this->info('HTML docs: public/docs/index.html');

            // Fail-safe check: verify OpenAPI file exists
            if (!file_exists(public_path('docs/openapi.yaml'))) {
                $this->error('CRITICAL: OpenAPI file missing after generation!');
                $this->error('Expected location: public/docs/openapi.yaml');
                return self::FAILURE;
            } else {
                $this->info('✓ OpenAPI file verified and accessible.');
            }

            // Verify HTML docs exist
            if (!file_exists(public_path('docs/index.html'))) {
                $this->warn('WARNING: HTML docs file missing after generation.');
                $this->warn('Expected location: public/docs/index.html');
            } else {
                $this->info('✓ HTML docs file verified and accessible.');
            }

        } catch (\Exception $e) {
            $this->error('Failed to generate API documentation: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
