# Ledger Recalculation Command

## Overview
The `ledger:recalculate` Artisan command recalculates the running balances for all supplier ledger transactions to fix balance drift and ensure data integrity.

## Command Signature

```bash
php artisan ledger:recalculate [options]
```

## Options

| Option | Description | Required |
|--------|-------------|----------|
| `--supplier_id=` | Specific supplier ID to recalculate | No |
| `--site_id=` | Specific site ID to recalculate | No |
| `--dry-run` | Show what would be updated without making changes | No |

## Usage Examples

### 1. Recalculate All Supplier Balances
```bash
php artisan ledger:recalculate
```
Recalculates running balances for all suppliers across all sites.

### 2. Recalculate Specific Supplier
```bash
php artisan ledger:recalculate --supplier_id=5
```
Recalculates balances only for supplier with ID 5.

### 3. Recalculate Specific Site
```bash
php artisan ledger:recalculate --site_id=10
```
Recalculates balances for all suppliers at site ID 10.

### 4. Combine Supplier and Site Filter
```bash
php artisan ledger:recalculate --supplier_id=5 --site_id=10
```
Recalculates balances for supplier 5 at site 10 only.

### 5. Dry Run - Preview Changes
```bash
php artisan ledger:recalculate --dry-run
```
Shows which suppliers have balance drift WITHOUT making any changes. Useful for auditing before applying fixes.

### 6. Dry Run with Specific Supplier
```bash
php artisan ledger:recalculate --supplier_id=5 --dry-run
```
Preview what would change for supplier 5.

## How It Works

### Balance Calculation Logic
The command iterates through each supplier's transactions in chronological order and recalculates the running balance:

```
Expected Balance = Previous Balance + Debit - Credit
```

If the stored balance doesn't match the expected balance, it's corrected.

### Process Flow
1. Load all suppliers (or filtered by `--supplier_id`)
2. For each supplier, load transactions ordered by date
3. Calculate running balance for each transaction
4. Compare with stored balance
5. If drift detected (> 0.01 difference), update the balance
6. Report summary of changes

## Example Output

### Normal Run
```
Starting Supplier Ledger Recalculation...
==========================================
Supplier ABC (ID: 1): Balance corrected
Supplier XYZ (ID: 2): Balance corrected
Supplier DEF (ID: 5): Balance corrected
==========================================
Recalculation Complete!
Total Suppliers Processed: 3
Suppliers with Balance Drift: 3
Errors: 0
```

### Dry Run
```
Starting Supplier Ledger Recalculation...
DRY RUN MODE - No changes will be made
==========================================
Supplier ABC (ID: 1): Would be corrected
Supplier XYZ (ID: 2): No drift detected
Supplier DEF (ID: 5): Would be corrected
==========================================
Recalculation Complete!
Total Suppliers Processed: 3
Suppliers with Balance Drift: 2
Errors: 0
This was a DRY RUN. No actual changes were made.
Run without --dry-run to apply changes.
```

## When to Use

### Recommended Scenarios
- **Monthly Maintenance**: Run monthly to ensure balances stay accurate
- **After Data Imports**: If you've imported historical data
- **Post-Migration**: After database migrations or transfers
- **Balance Discrepancy**: When reports show unexpected balances
- **Audit Preparation**: Verify ledger integrity before audits

### Warning
⚠️ **Important**: This command modifies the `balance` column in `supplier_transactions` table. Always:
1. Backup your database before running
2. Use `--dry-run` first to preview changes
3. Run during off-peak hours for large datasets

## Technical Details

### File Location
- **Command**: `app/Console/Commands/RecalculateSupplierLedger.php`

### Database Tables Affected
- `supplier_transactions` (balance column only)

### Performance Considerations
- Uses chunked processing with progress bar
- Suitable for large datasets (tested with 10,000+ transactions)
- Can take several minutes for very large datasets

## Error Handling

### Common Errors
1. **Permission Denied**: Ensure Laravel can write to logs
2. **Timeout**: Use `--supplier_id` to process in batches
3. **Database Lock**: Wait for other processes to complete

### Logs
All operations are logged to Laravel's default log. Check `storage/logs/` for details.

## Related Commands

```bash
# View supplier transaction summary
php artisan tinker
>>> App\Helpers\LedgerHelper::getPOSummary(1)

# Check balance for specific supplier
php artisan tinker
>>> App\Models\SupplierTransaction::getCurrentBalance(1)
```

## Version History
- **v1.0** (2026-04-03): Initial release
