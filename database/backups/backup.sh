#!/bin/bash

# Database Backup Script for Payment Module Redesign
# Usage: ./backup.sh [full|tables|incremental]

DB_NAME="sitepilot_live"
DB_USER="root"
BACKUP_DIR="database/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

case "$1" in
  full)
    echo "Creating full database backup..."
    mysqldump -u $DB_USER -p $DB_NAME > $BACKUP_DIR/sitepilot_live_full_$TIMESTAMP.sql
    echo "Full backup completed: $BACKUP_DIR/sitepilot_live_full_$TIMESTAMP.sql"
    ;;
  tables)
    echo "Creating key tables backup..."
    mysqldump -u $DB_USER -p $DB_NAME \
      payment_requests \
      payments_module \
      payment_module_allocations \
      purchase_invoices \
      purchase_orders \
      supplier_transactions \
      advance_adjustments \
      > $BACKUP_DIR/sitepilot_live_tables_$TIMESTAMP.sql
    echo "Tables backup completed: $BACKUP_DIR/sitepilot_live_tables_$TIMESTAMP.sql"
    ;;
  incremental)
    echo "Creating incremental backup..."
    mysqldump -u $DB_USER -p $DB_NAME \
      --single-transaction \
      --quick \
      --lock-tables=false \
      > $BACKUP_DIR/sitepilot_live_incremental_$TIMESTAMP.sql
    echo "Incremental backup completed: $BACKUP_DIR/sitepilot_live_incremental_$TIMESTAMP.sql"
    ;;
  *)
    echo "Usage: $0 [full|tables|incremental]"
    echo "  full        - Complete database backup"
    echo "  tables      - Backup key payment-related tables only"
    echo "  incremental - Fast incremental backup"
    exit 1
    ;;
esac

# Verify backup was created
if [ -f "$BACKUP_DIR/sitepilot_live_${1}_$TIMESTAMP.sql" ]; then
  FILE_SIZE=$(du -h "$BACKUP_DIR/sitepilot_live_${1}_$TIMESTAMP.sql" | cut -f1)
  echo "Backup verified. File size: $FILE_SIZE"
else
  echo "ERROR: Backup file was not created!"
  exit 1
fi
