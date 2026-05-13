<?php

/**
 * CI/CD Drift Detection Setup - Simple Version
 * 
 * This tool sets up automated drift detection for
 * continuous integration pipelines using Laravel commands.
 * 
 * USAGE: php ci_drift_detection_simple.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class CIDriftDetectionSimple
{
    private $outputPath;

    public function __construct()
    {
        $this->outputPath = __DIR__ . '/ci_scripts';
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Execute CI drift detection setup
     */
    public function execute()
    {
        echo "=== CI/CD DRIFT DETECTION SETUP (SIMPLE) ===\n\n";

        $this->createDriftDetectionScript();
        $this->createGitHubActions();
        $this->createGitLabCI();
        $this->createJenkinsPipeline();
        $this->generateDocumentation();
    }

    /**
     * Create drift detection script
     */
    private function createDriftDetectionScript()
    {
        echo "STEP 1: CREATING DRIFT DETECTION SCRIPT\n";
        echo str_repeat("=", 50) . "\n";

        $scriptContent = $this->generateDriftDetectionScript();
        $scriptFile = $this->outputPath . '/detect-drift.sh';

        file_put_contents($scriptFile, $scriptContent);
        chmod($scriptFile, 0755);

        echo "✓ Drift detection script: $scriptFile\n\n";
    }

    /**
     * Create GitHub Actions workflow
     */
    private function createGitHubActions()
    {
        echo "STEP 2: CREATING GITHUB ACTIONS WORKFLOW\n";
        echo str_repeat("=", 50) . "\n";

        $workflowContent = $this->generateGitHubActionsWorkflow();
        $workflowDir = __DIR__ . '/.github/workflows';
        
        if (!is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        $workflowFile = $workflowDir . '/database-drift-detection.yml';
        file_put_contents($workflowFile, $workflowContent);

        echo "✓ GitHub Actions workflow: $workflowFile\n\n";
    }

    /**
     * Create GitLab CI configuration
     */
    private function createGitLabCI()
    {
        echo "STEP 3: CREATING GITLAB CI CONFIGURATION\n";
        echo str_repeat("=", 50) . "\n";

        $gitlabContent = $this->generateGitLabCIConfig();
        $gitlabFile = __DIR__ . '/.gitlab-ci.yml';
        
        file_put_contents($gitlabFile, $gitlabContent);

        echo "✓ GitLab CI configuration: $gitlabFile\n\n";
    }

    /**
     * Create Jenkins pipeline
     */
    private function createJenkinsPipeline()
    {
        echo "STEP 4: CREATING JENKINS PIPELINE\n";
        echo str_repeat("=", 50) . "\n";

        $jenkinsContent = $this->generateJenkinsPipeline();
        $jenkinsFile = $this->outputPath . '/Jenkinsfile';
        
        file_put_contents($jenkinsFile, $jenkinsContent);

        echo "✓ Jenkins pipeline: $jenkinsFile\n\n";
    }

    /**
     * Generate documentation
     */
    private function generateDocumentation()
    {
        echo "STEP 5: GENERATING DOCUMENTATION\n";
        echo str_repeat("=", 50) . "\n";

        $docFile = $this->outputPath . '/DRIFT_DETECTION_README.md';
        $content = $this->generateDocumentationContent();
        
        file_put_contents($docFile, $content);

        echo "✓ Documentation: $docFile\n\n";
    }

    /**
     * Generate drift detection script
     */
    private function generateDriftDetectionScript()
    {
        return '#!/bin/bash

# Database Drift Detection Script - Laravel Based
# This script detects schema drift using Laravel commands

set -e

echo "🔍 Detecting database drift..."

# Colors for output
RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
NC="\033[0m" # No Color

# Check if we are in a Laravel project
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: Not in a Laravel project directory${NC}"
    exit 1
fi

# Run migration pretend to detect drift
echo "📋 Running migration simulation..."
MIGRATION_OUTPUT=$(php artisan migrate --pretend 2>&1)

# Check for drift indicators
if echo "$MIGRATION_OUTPUT" | grep -q "Nothing to migrate"; then
    echo -e "${GREEN}✅ No database drift detected${NC}"
    exit 0
fi

# Count potential issues
CREATE_TABLES=$(echo "$MIGRATION_OUTPUT" | grep -c "create table" || true)
ADD_COLUMNS=$(echo "$MIGRATION_OUTPUT" | grep -c "add column" || true)
DROP_COLUMNS=$(echo "$MIGRATION_OUTPUT" | grep -c "drop column" || true)
MODIFY_INDEXES=$(echo "$MIGRATION_OUTPUT" | grep -c "add index\|drop index" || true)

TOTAL_CHANGES=$((CREATE_TABLES + ADD_COLUMNS + DROP_COLUMNS + MODIFY_INDEXES))

if [ $TOTAL_CHANGES -eq 0 ]; then
    echo -e "${GREEN}✅ No database drift detected${NC}"
    exit 0
fi

echo -e "${YELLOW}⚠️  Database drift detected!${NC}"
echo "Changes found:"
echo "  - Create tables: $CREATE_TABLES"
echo "  - Add columns: $ADD_COLUMNS"
echo "  - Drop columns: $DROP_COLUMNS"
echo "  - Modify indexes: $MODIFY_INDEXES"

echo ""
echo "📄 Detailed migration output:"
echo "$MIGRATION_OUTPUT"

# Check for critical changes
if [ $CREATE_TABLES -gt 0 ] || [ $DROP_COLUMNS -gt 0 ]; then
    echo -e "${RED}🚨 Critical schema changes detected!${NC}"
    echo "These changes require manual review and may cause data loss."
    exit 1
fi

echo -e "${YELLOW}⚠️  Schema modifications detected${NC}"
echo "Please review and update your migrations."
exit 1';
    }

    /**
     * Generate GitHub Actions workflow
     */
    private function generateGitHubActionsWorkflow()
    {
        return 'name: Database Drift Detection

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  drift-detection:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: test_db
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - name: Checkout code
      uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: "8.1"
        extensions: pdo, mysql, bcmath, json, mbstring, tokenizer, xml

    - name: Copy environment file
      run: cp .env.example .env

    - name: Install dependencies
      run: composer install --no-progress --no-interaction --prefer-dist --optimize-autoloader

    - name: Generate application key
      run: php artisan key:generate

    - name: Setup database
      run: |
        php artisan config:cache
        php artisan migrate --force

    - name: Detect drift
      run: |
        chmod +x ci_scripts/detect-drift.sh
        ./ci_scripts/detect-drift.sh

    - name: Upload drift report
      if: failure()
      uses: actions/upload-artifact@v3
      with:
        name: drift-report
        path: |
          storage/logs/laravel.log
        retention-days: 7';
    }

    /**
     * Generate GitLab CI configuration
     */
    private function generateGitLabCIConfig()
    {
        return 'stages:
  - test
  - drift-detection

variables:
  MYSQL_DATABASE: test_db
  MYSQL_USER: root
  MYSQL_PASSWORD: password
  MYSQL_ROOT_PASSWORD: password

services:
  - mysql:8.0

drift-detection:
  stage: drift-detection
  image: php:8.1-cli
  services:
    - mysql:8.0
  before_script:
    - apt-get update -yqq
    - apt-get install -yqq git unzip libzip-dev zip
    - docker-php-ext-install pdo_mysql zip bcmath
    - composer install --no-dev --optimize-autoloader
    - cp .env.example .env
    - php artisan key:generate
    - php artisan config:cache
  script:
    - chmod +x ci_scripts/detect-drift.sh
    - ./ci_scripts/detect-drift.sh
  artifacts:
    when: on_failure
    paths:
      - storage/logs/laravel.log
    expire_in: 1 week
  only:
    - main
    - develop
    - merge_requests';
    }

    /**
     * Generate Jenkins pipeline
     */
    private function generateJenkinsPipeline()
    {
        return 'pipeline {
    agent any
    
    environment {
        MYSQL_DATABASE = \'test_db\'
        MYSQL_USER = \'root\'
        MYSQL_PASSWORD = \'password\'
    }
    
    stages {
        stage(\'Checkout\') {
            steps {
                checkout scm
            }
        }
        
        stage(\'Setup\') {
            steps {
                sh \'cp .env.example .env\'
                sh \'composer install --no-dev --optimize-autoloader\'
                sh \'php artisan key:generate\'
                sh \'php artisan config:cache\'
            }
        }
        
        stage(\'Drift Detection\') {
            steps {
                sh \'chmod +x ci_scripts/detect-drift.sh\'
                sh \'./ci_scripts/detect-drift.sh\'
            }
            post {
                failure {
                    archiveArtifacts artifacts: \'storage/logs/laravel.log\', fingerprint: true
                }
            }
        }
    }
}';
    }

    /**
     * Generate documentation content
     */
    private function generateDocumentationContent()
    {
        return '# Database Drift Detection Setup - Simple Version

## Overview

This setup provides automated detection of database schema drift in your CI/CD pipeline, preventing mismatches between migrations and database state.

## Components

### 1. Drift Detection Script (`detect-drift.sh`)

A bash script that:
- Runs `php artisan migrate --pretend` to simulate migrations
- Detects schema changes (tables, columns, indexes)
- Categorizes changes by risk level
- Exits with appropriate status codes

### 2. CI/CD Integrations

#### GitHub Actions
- Triggers on push/PR to main/develop
- Sets up MySQL test database
- Runs drift detection
- Uploads logs on failure

#### GitLab CI
- Similar functionality for GitLab
- Uses MySQL service container
- Artifacts on failure

#### Jenkins Pipeline
- Declarative pipeline syntax
- Multi-stage execution
- Artifact collection

## Usage

### Local Testing
```bash
# Test drift detection locally
chmod +x ci_scripts/detect-drift.sh
./ci_scripts/detect-drift.sh
```

### CI/CD Integration
1. Copy the generated configuration files to your repository
2. Commit and push to your CI/CD platform
3. Configure database credentials in your CI environment
4. Test the pipeline

## Exit Codes

- `0`: No drift detected
- `1`: Schema drift detected

## Risk Assessment

The script categorizes changes as:
- **Critical**: Table creation/deletion, column dropping
- **Warning**: Column addition, index modifications
- **Info**: Minor schema changes

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   chmod +x ci_scripts/detect-drift.sh
   ```

2. **Database Connection Failed**
   - Verify database service is running
   - Check environment variables
   - Ensure proper credentials

3. **False Positives**
   - Review migration content
   - Adjust detection logic
   - Consider expected changes

### Debug Mode

Add debug output to the script:
```bash
set -x  # Enable debug mode
```

## Best Practices

1. **Run in All Environments**
   - Development: Catch drift early
   - Staging: Pre-production validation
   - Production: Monitor for unexpected changes

2. **Integrate with Code Review**
   - Block merges on drift detection
   - Require manual review for critical changes
   - Document exceptions

3. **Monitor and Alert**
   - Set up notifications for failures
   - Track drift trends over time
   - Establish response procedures

## Maintenance

- Update detection logic as migration patterns evolve
- Review CI/CD configurations regularly
- Monitor script performance
- Document any customizations

## Support

For issues or questions:
1. Check script output for specific error messages
2. Verify CI/CD configuration
3. Test in isolated environment
4. Review this documentation for common solutions';
    }
}

// Execute CI drift detection setup
try {
    $setup = new CIDriftDetectionSimple();
    $setup->execute();
    
    echo "✅ CI/CD DRIFT DETECTION SETUP COMPLETED\n";
    echo "📋 Review generated configurations\n";
    echo "⚠️  Test in development before production\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
