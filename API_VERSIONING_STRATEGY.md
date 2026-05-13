# API Versioning Strategy - Future-Proof Architecture

## Overview
This document outlines the recommended API versioning strategy for SitePilot ERP to ensure long-term scalability and backward compatibility.

## Current State
- All API routes are flat under `api/*`
- Single OpenAPI specification for all endpoints
- No version separation

## Recommended Architecture

### 1. Version-Based Route Structure

**Proposed Structure:**
```
/api/v1/*  - Current stable version
/api/v2/*  - Beta/new features (when needed)
/api/public/*  - Public-facing APIs
/api/internal/*  - Internal/admin APIs
```

### 2. Implementation Steps

#### Step 1: Create Version-Specific Route Files

**File Structure:**
```
routes/
├── api.php          (current - keep for backward compatibility)
├── api.v1.php       (v1 stable APIs)
├── api.v2.php       (v2 future APIs)
└── api.public.php   (public APIs)
```

#### Step 2: Register Version Routes in RouteServiceProvider

**File:** `app/Providers/RouteServiceProvider.php`

```php
public function boot(): void
{
    $this->configureRateLimiting();

    $this->routes(function () {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(base_path('routes/api.v1.php'));

        Route::middleware('api')
            ->prefix('api/v2')
            ->group(base_path('routes/api.v2.php'));

        Route::middleware('api')
            ->prefix('api/public')
            ->group(base_path('routes/api.public.php'));

        Route::middleware('api')
            ->prefix('api/internal')
            ->group(base_path('routes/api.internal.php'));

        // Keep existing for backward compatibility
        Route::middleware('api')
            ->group(base_path('routes/api.php'));
    });
}
```

#### Step 3: Configure Scribe for Multiple Versions

**File:** `config/scribe.php`

```php
'routes' => [
    // v1 Stable APIs
    [
        'match' => [
            'prefixes' => ['api/v1/*'],
        ],
        'apply' => [
            'headers' => [
                'X-API-Version' => '1.0',
            ],
        ],
    ],
    // v2 Beta APIs (when needed)
    [
        'match' => [
            'prefixes' => ['api/v2/*'],
        ],
        'apply' => [
            'headers' => [
                'X-API-Version' => '2.0',
            ],
        ],
    ],
    // Public APIs
    [
        'match' => [
            'prefixes' => ['api/public/*'],
        ],
        'exclude' => [],
    ],
    // Exclude internal APIs from docs
    [
        'match' => [
            'prefixes' => ['api/internal/*'],
        ],
        'exclude' => [
            'api/internal/*',
        ],
    ],
],
```

#### Step 4: Generate Separate OpenAPI Files

**Option 1: Single File with Version Tags**
- Keep current approach
- Add version tags to endpoints
- Use `@group` annotations with version info

**Option 2: Separate Files per Version**
- Create separate scribe configs
- Generate `openapi.v1.yaml`, `openapi.v2.yaml`
- Apidog imports both separately

**Recommended:** Option 1 for simplicity

### 3. API Version Headers

**Request Headers:**
```http
X-API-Version: 1.0
Accept: application/json
```

**Response Headers:**
```http
X-API-Version: 1.0
X-API-Deprecated: false
```

### 4. Deprecation Strategy

**When Deprecating v1:**
1. Add `X-API-Deprecated: true` header to v1 responses
2. Add `X-API-Deprecation-Date: 2025-06-01` header
3. Add `X-API-Sunset-Date: 2025-12-01` header
4. Add warning message in response body
5. Document migration guide

**Example Response:**
```json
{
    "data": {...},
    "_meta": {
        "api_version": "1.0",
        "deprecated": true,
        "deprecation_date": "2025-06-01",
        "sunset_date": "2025-12-01",
        "migration_guide": "https://docs.sitepilot.com/api/v2-migration"
    }
}
```

### 5. Separation: Public vs Internal APIs

**Public APIs** (`api/public/*`):
- External integrations
- Mobile apps
- Third-party partners
- Documented in public OpenAPI

**Internal APIs** (`api/internal/*`):
- Admin operations
- Internal workflows
- Debug endpoints
- Excluded from public docs
- Separate authentication

**Security:**
```php
// routes/api.public.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('api/public')
    ->group(function () {
        // Public APIs
    });

// routes/api.internal.php
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('api/internal')
    ->group(function () {
        // Internal APIs
    });
```

### 6. Migration Path

**Phase 1: Preparation (Current)**
- Keep existing structure
- Document current APIs
- Plan version separation

**Phase 2: Version Introduction**
- Create `api/v1.php` with current routes
- Keep `api.php` for backward compatibility
- Update controllers to support version headers
- Generate v1-specific OpenAPI

**Phase 3: v2 Development**
- Create `api/v2.php` for new features
- Implement breaking changes in v2
- Maintain v1 for existing clients
- Generate v2-specific OpenAPI

**Phase 4: Deprecation**
- Add deprecation headers to v1
- Provide migration guide
- Monitor v1 usage
- Sunset v1 after transition period

### 7. Benefits

**For Frontend/Mobile:**
- Stable API contracts
- No breaking changes
- Clear migration path
- Multiple versions can coexist

**For Backend:**
- Safe refactoring
- Gradual feature rollout
- A/B testing capabilities
- Easy rollback

**For Documentation:**
- Clear version boundaries
- Separate OpenAPI specs
- Better organization
- Easier maintenance

### 8. OpenAPI Configuration for Versions

**Single File Approach (Recommended):**

```php
'groups' => [
    'order' => [
        'Authentication',
        'v1 Materials',
        'v1 Suppliers',
        'v1 Purchase Orders',
        'v2 Materials', // future
        'v2 Suppliers', // future
    ],
],
```

**Separate Files Approach:**

Create multiple config files:
- `scribe.v1.php` - for v1 APIs
- `scribe.v2.php` - for v2 APIs

Generate with:
```bash
php artisan scribe:generate --config=scribe.v1.php
php artisan scribe:generate --config=scribe.v2.php
```

### 9. Testing Strategy

**Version Testing:**
```php
// Test v1
$headers = ['X-API-Version' => '1.0'];
$this->withHeaders($headers)->get('/api/v1/materials');

// Test v2
$headers = ['X-API-Version' => '2.0'];
$this->withHeaders($headers)->get('/api/v2/materials');
```

**Integration Testing:**
- Test version header routing
- Test deprecation headers
- Test backward compatibility
- Test migration paths

### 10. Monitoring

**Metrics to Track:**
- v1 vs v2 usage
- Deprecated endpoint calls
- Error rates by version
- Response times by version

**Dashboard:**
```php
// Add to health check endpoint
'api_versions' => [
    'v1' => [
        'endpoints_count' => 45,
        'status' => 'stable',
        'deprecated' => false,
    ],
    'v2' => [
        'endpoints_count' => 12,
        'status' => 'beta',
        'deprecated' => false,
    ],
],
```

## Implementation Priority

**High Priority:**
1. Separate public vs internal APIs
2. Add version header support
3. Update Scribe config for exclusions

**Medium Priority:**
1. Create v1 route file
2. Add deprecation headers
3. Document migration strategy

**Low Priority:**
1. Implement v2 routes (when needed)
2. Separate OpenAPI files
3. Advanced versioning features

## Conclusion

This versioning strategy provides:
- ✅ Backward compatibility
- ✅ Safe evolution
- ✅ Clear boundaries
- ✅ Easy migration
- ✅ Production-ready architecture

**Status:** Ready for implementation when needed
**Priority:** Medium (can be implemented incrementally)
