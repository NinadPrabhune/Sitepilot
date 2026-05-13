# Database Drift Detection Setup - Simple Version

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
4. Review this documentation for common solutions