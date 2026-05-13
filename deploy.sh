#!/bin/bash

# Post-Deploy Hook Script for SitePilot ERP
# This script runs after deployment to ensure API docs are up-to-date

echo "=========================================="
echo "SitePilot ERP Post-Deploy Hook"
echo "=========================================="
echo ""

# Run migrations
echo "Step 1: Running database migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    echo "ERROR: Migration failed!"
    exit 1
fi
echo "✓ Migrations completed"
echo ""

# Clear configuration cache
echo "Step 2: Clearing configuration cache..."
php artisan config:clear
echo "✓ Configuration cache cleared"
echo ""

# Clear application cache
echo "Step 3: Clearing application cache..."
php artisan cache:clear
echo "✓ Application cache cleared"
echo ""

# Clear route cache
echo "Step 4: Clearing route cache..."
php artisan route:clear
echo "✓ Route cache cleared"
echo ""

# Clear view cache
echo "Step 5: Clearing view cache..."
php artisan view:clear
echo "✓ View cache cleared"
echo ""

# Generate API documentation
echo "Step 6: Generating API documentation..."
php artisan api:generate-docs
if [ $? -ne 0 ]; then
    echo "ERROR: API documentation generation failed!"
    exit 1
fi
echo "✓ API documentation generated"
echo ""

# Verify OpenAPI file exists
echo "Step 7: Verifying OpenAPI file..."
if [ -f "public/docs/openapi.yaml" ]; then
    echo "✓ OpenAPI file verified: public/docs/openapi.yaml"
else
    echo "ERROR: OpenAPI file missing!"
    exit 1
fi

# Validate OpenAPI file format
echo "Step 8: Validating OpenAPI file format..."
if grep -q "openapi:" public/docs/openapi.yaml; then
    echo "✓ OpenAPI file format valid"
else
    echo "ERROR: Invalid OpenAPI file - missing 'openapi:' header!"
    exit 1
fi

# Check OpenAPI version
if grep -q "version:" public/docs/openapi.yaml; then
    echo "✓ OpenAPI version present"
else
    echo "WARNING: OpenAPI version missing"
fi

# Verify file is not empty
if [ -s "public/docs/openapi.yaml" ]; then
    echo "✓ OpenAPI file is not empty"
else
    echo "ERROR: OpenAPI file is empty!"
    exit 1
fi

# Verify paths section exists (indicates API routes were documented)
if grep -q "paths:" public/docs/openapi.yaml; then
    echo "✓ API paths documented"
else
    echo "ERROR: No API paths documented in OpenAPI file!"
    exit 1
fi
echo ""

# Optimize for production
if [ "$APP_ENV" = "production" ]; then
    echo "Step 9: Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    echo "✓ Production optimization completed"
    echo ""
fi

echo "=========================================="
echo "Post-Deploy Hook Completed Successfully!"
echo "=========================================="
echo ""
echo "API Documentation:"
echo "  - OpenAPI Spec: public/docs/openapi.yaml"
echo "  - HTML Docs: public/docs/index.html"
echo "  - Postman Collection: public/docs/collection.json"
echo ""
echo "Access URLs:"
echo "  - API Docs: https://yourdomain.com/api-docs"
echo "  - OpenAPI: https://yourdomain.com/docs/openapi.yaml"
echo ""
