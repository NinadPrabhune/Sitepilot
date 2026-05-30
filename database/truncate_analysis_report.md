# Database Truncate Analysis Report

## 1. Suspected Master/Reference Tables (Remove from Truncation)

These tables define reference/configuration data and should NOT be truncated. They preserve structural integrity of the application.

| Table | Reason |
|-------|--------|
| `bug_stages` | Pipeline stage definitions for bug tracking (reference/config) |
| `milestones` | Project milestone definitions (reference/config) |
| `lead_stages` | Lead pipeline stage definitions (reference/config) |
| `deal_stages` | Deal pipeline stage definitions (reference/config) |
| `client_projects` | Pivot table defining client-project relationships |
| `vender_projects` | Pivot table defining vendor-project relationships |

**Note:** Removing these 6 tables from the truncate list ensures stage definitions, milestone templates, and project-user relationships survive the reset.

---

## 2. Newly Added Transaction Tables (Include in Truncation)

### Machinery Module
| Table | Type |
|-------|------|
| `machinery_payment_request_items` | Payment request line items |
| `machinery_payment_allocations` | Payment allocation records |
| `machinery_usage_logs` | Machinery runtime/usage logs |
| `machinery_ownerships` | Machinery ownership change records |
| `machinery_supplier_rates` | Supplier-specific machinery rates |
| `machinery_rate_histories` | Rate change history (plural, separate from `machinery_rate_history`) |

### Financial/Ledger Module
| Table | Type |
|-------|------|
| `ledger_entries` | General ledger entries |
| `supplier_ledger_entries` | Supplier ledger detail entries |
| `financial_postings` | Financial posting records |
| `posting_batches` | Posting batch records |
| `posting_failures` | Failed posting records |
| `financial_period_locks` | Financial period lock records |
| `financial_gate_blocks` | Financial gate block records |
| `financial_escalations` | Financial escalation records |
| `journal_adjustments` | Journal adjustment records |
| `calculation_versions` | Calculation version tracking |
| `monthly_closures` | Monthly closure records |
| `advance_audit_logs` | Advance audit log records |

### Audit/Reconciliation/Health Module
| Table | Type |
|-------|------|
| `reconciliation_logs` | Reconciliation audit trail |
| `payment_audit_logs` | Payment audit trail |
| `payment_health_logs` | Payment health check records |
| `payment_calculation_snapshots` | Payment calculation snapshots |
| `payment_request_status_logs` | Payment request status change logs |
| `payment_request_histories` | Payment request history |
| `payment_reversals` | Payment reversal records |
| `ledger_integrity_logs` | Ledger integrity check logs |
| `transaction_integrity_logs` | Transaction integrity logs |
| `invariant_logs` | Invariant violation logs |
| `destructive_command_attempts` | Destructive command audit trail |
| `dpr_edit_history` | DPR edit history |
| `dpr_anomalies` | DPR anomaly detection results |
| `daily_health_check_logs` | Daily health check records |
| `daily_system_snapshots` | Daily system snapshots |
| `system_health_logs` | System health monitoring logs |
| `workflow_transitions` | Workflow transition records |
| `workflow_state_histories` | Workflow state change history |
| `workflow_audits` | Workflow audit records |
| `escalation_requests` | Escalation request records |
| `material_consumption_audits` | Material consumption audit |
| `material_consumption_versions` | Material consumption versioning |
| `usage_calculation_logs` | Usage calculation logs |

### Numbering/Sequence Module
| Table | Type |
|-------|------|
| `skipped_numbers` | Skipped number tracking |
| `number_sequences` | Number sequence state |
| `numbering_config_logs` | Numbering config change logs |

### Notifications
| Table | Type |
|-------|------|
| `notification_logs` | Notification delivery logs |

### Migration
| Table | Type |
|-------|------|
| `payment_migration_snapshot` | Migration snapshot records |

### HRM
| Table | Type |
|-------|------|
| `leave_request_dates` | Individual dates within leave requests |

---

## 3. Foreign Key Dependency Analysis

With `SET FOREIGN_KEY_CHECKS = 0`, truncation order is technically unconstrained. However, the following parent-child dependency chains exist:

### Parent Tables (should be truncated AFTER children if FK_CHECKS were enabled)
- `purchase_orders` → child: `purchase_order_items`, `grns`, `purchase_invoices`, `payments_module`, `payment_requests`, `po_status_logs`
- `indents` → child: `indent_items`, `purchase_orders`
- `grns` → child: `grn_items`, `purchase_invoices`
- `purchase_invoices` → child: `purchase_invoice_items`, `payments_module`, `advance_utilizations`
- `supplier_advances` → child: `advance_utilizations`, `supplier_advance_audit_logs`
- `machinery_payment_requests` → child: `machinery_payment_request_items`, `machinery_payment_allocations`, `machinery_ledger`
- `machinery_ledger` → child: `machinery_bills`
- `machinery_bills` → child: `machinery_billing_items`
- `material_issues` → child: `material_issue_items`, `material_returns`
- `material_returns` → child: `material_return_items`
- `material_transfers` → child: `material_transfer_items`
- `daily_consumption_masters` → child: `daily_consumption_details`
- `man_power_masters` → child: `man_power_details`
- `supplier_ledger` → child: `supplier_ledger_entries`
- `activities` → child: `activities_completed`, `man_power_masters`, `daily_progress_reports`
- `daily_progress_reports` → child: `daily_consumption_masters`, `machinery_billing_items`

### Safe Recommended Order (child-first within each group):
1. **Detail/Junction tables first** (items, details, attachments, logs)
2. **Transaction headers second** (orders, invoices, ledgers, requests)
3. **Aggregate/summary tables last** (snapshots, balances, ledgers)

However, since the script uses `FOREIGN_KEY_CHECKS = 0`, alphabetical or logical module ordering is acceptable.

---

## 4. Special Handling Notes

| Table | Issue |
|-------|-------|
| `machinery_rate_history` vs `machinery_rate_histories` | Both exist in schema. Current list has singular; plural should also be added. |
| `transactions` / `transaction_lines` | Legacy package tables (from `packages/workdo/Account`). Include only if they exist in target database. |
| `client_projects` / `vender_projects` | REMOVED from truncate list - they are pivot/relationship tables, not transactions. |
| `bug_stages` / `lead_stages` / `deal_stages` / `milestones` | REMOVED - they are pipeline stage/milestone definitions, not transactions. |
| Activity & consumption tables | `daily_progress_reports` FK references `machinery_ledger` and `users`. |
| All `*_audit_logs`, `*_history`, `*_logs` | Pure transactional audit data - safe to truncate. |
