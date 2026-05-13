# 🛡️ Safe Migration Commands Guide

## ✅ SAFE Commands (Data Protected)

### Basic Migration Operations
```bash
# ✅ Run new migrations without deleting data
php artisan migrate

# ✅ Check migration status
php artisan migrate:status

# ✅ Show next pending migration
php artisan migrate:pending

# ✅ Rollback one migration (if safe)
php artisan migrate:rollback

# ✅ Rollback specific number of migrations
php artisan migrate:rollback --step=3
```

### Migration Creation
```bash
# ✅ Create new migration
php artisan make:migration create_new_table

# ✅ Create migration with model
php artisan make:model ModelName -m

# ✅ Create migration for specific table
php artisan make:migration add_column_to_table --table=table_name
```

## 🚫 DANGEROUS Commands (Data Loss Risk)

### ❌ NEVER Run These Commands
```bash
# ❌ DELETES ALL DATA - Drops all tables and re-runs migrations
php artisan migrate:fresh

# ❌ DELETES ALL DATA - Rolls back and re-runs all migrations  
php artisan migrate:refresh

# ❌ DELETES ALL DATA - Rolls back all migrations
php artisan migrate:reset

# ❌ MAY DELETE DATA - Runs seeders (now disabled but still risky)
php artisan db:seed
php artisan migrate:fresh --seed
php artisan migrate:refresh --seed
```

## 🔄 Safe Migration Workflow

### Before Any Migration:
```bash
# 1. Check current status
php artisan migrate:status

# 2. Create backup
mysqldump -u root -p sitepilot_local > pre_migration_backup_$(date +%Y%m%d_%H%M%S).sql

# 3. Put app in maintenance (optional)
php artisan down
```

### During Migration:
```bash
# 4. Run migration safely
php artisan migrate --force

# 5. Verify success
php artisan migrate:status
```

### After Migration:
```bash
# 6. Test application
# 7. Bring back online (if maintenance mode)
php artisan up

# 8. Keep backup for at least 7 days
```

## 🚨 Emergency Recovery

### If Migration Fails:
```bash
# 1. Stop immediately
# 2. Check what went wrong
php artisan migrate:status

# 3. Restore from backup if needed
mysql -u root -p sitepilot_local < pre_migration_backup_YYYYMMDD_HHMMSS.sql

# 4. Try again with fixes
```

### If Data Is Lost:
```bash
# 1. Stop all processes
php artisan down

# 2. Restore from most recent backup
mysql -u root -p sitepilot_local < emergency_backup.sql

# 3. Verify data integrity
php artisan migrate:status

# 4. Bring back online
php artisan up
```

## 📋 Migration Safety Checklist

### Before Running:
- [ ] Created full database backup
- [ ] Checked migration status
- [ ] Reviewed migration files for safety
- [ ] Tested in staging environment
- [ ] Scheduled maintenance window if needed

### After Running:
- [ ] Verified migration completed successfully
- [ ] Tested application functionality
- [ ] Checked data integrity
- [ ] Kept backup for recovery
- [ ] Documented changes

## 🎯 Best Practices

1. **Always backup before any migration**
2. **Never use `migrate:fresh` or `migrate:refresh` in production**
3. **Test migrations in staging first**
4. **Use `--force` flag only in production/CI environments**
5. **Keep multiple backup versions**
6. **Monitor application after migration**
7. **Document all migration changes**

## 🔍 Migration Troubleshooting

### Common Issues:
```bash
# Check if migration is already run
php artisan migrate:status | grep migration_name

# Force run specific migration (if stuck)
php artisan migrate --force --path=database/migrations/2024_01_01_000000_create_table.php

# Check migration batch
php artisan migrate:rollback --step=1 --pretend
```

### Migration Conflicts:
```bash
# Check what migrations exist
ls database/migrations/

# Check which ones are run
php artisan migrate:status

# Compare to find missing ones
```

---

**Remember:** Your data is now protected by SAFE_SEED_ONLY=true and disabled dangerous seeders. These safe commands will not cause data loss when used properly.
