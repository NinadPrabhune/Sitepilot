# Database Tables Documentation

This document provides a comprehensive listing of all database tables used in the SitePilot system, categorized by their functional modules.

---

## Table of Contents

1. [ERP Module](#erp)
2. [HRM Module](#hrm)
3. [Inventory Module](#inventory)
4. [Payment Module](#payment)
5. [Reports Module](#reports)
6. [Truncate Plan](#truncate-plan)

---

## ERP Module

The ERP module handles core business operations including user management, authentication, workspaces, settings, subscriptions, and communication.

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`users`](#) | Stores user account information | `id`, `name`, `email`, `password`, `referral_code`, `password_text` |
| [`password_reset_tokens`](#) | Stores password reset tokens | `email`, `token`, `created_at` |
| [`password_resets`](#) | Legacy password reset table | `email`, `token`, `created_at` |
| [`personal_access_tokens`](#) | API token management for Sanctum | `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `expires_at` |
| [`sessions`](#) | User session management | `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` |
| [`work_spaces`](#) | Multi-workspace/tenant management | `id`, `name`, `slug`, `email`, `phone`, `address`, `business_info`, `created_by` |
| [`settings`](#) | Global and workspace-specific settings | `name`, `value`, `workspace_id` |
| [`user_active_modules`](#) | Tracks active modules per user/workspace | `user_id`, `module`, `workspace_id` |
| [`plans`](#) | Subscription plans configuration | `name`, `price`, `duration`, `status`, `package_price_monthly`, `package_price_yearly` |
| [`add_ons`](#) | System plugins/extensions | `name`, `description`, `price`, `image`, `status` |
| [`orders`](#) | Subscription and order records | `order_id`, `user_id`, `plan_id`, `amount`, `status`, `is_refund` |
| [`user_coupons`](#) | User-coupon assignments | `user_id`, `coupon_id` |
| [`coupons`](#) | Discount coupon definitions | `code`, `discount_type`, `discount`, `limit`, `type` |
| [`currencies`](#) | Currency definitions | `name`, `symbol`, `code`, `exchange_rate` |
| [`languages`](#) | System languages | `name`, `code`, `direction`, `is_enable` |
| [`email_templates`](#) | Email template definitions | `name`, `body`, `module` |
| [`email_template_langs`](#) | Localized email templates | `template_id`, `lang`, `subject`, `content` |
| [`notifications`](#) | System notifications | `user_id`, `type`, `data`, `read_at`, `created_at` |
| [`notification_template_langs`](#) | Notification template translations | `parent_id`, `lang`, `variables`, `content` |
| [`user_notifications`](#) | User-specific notifications | `user_id`, `notification_id`, `is_read` |
| [`login_details`](#) | User login history tracking | `user_id`, `ip_address`, `login_date`, `browser`, `platform` |
| [`api_key_settings`](#) | API key management | `key_name`, `key_value`, `status` |
| [`device_tokens`](#) | Mobile device tokens for push notifications | `user_id`, `token`, `device_type` |
| [`referral_transactions`](#) | Referral transaction records | `user_id`, `referred_by`, `amount`, `type` |
| [`referral_settings`](#) | Referral program configuration | `percentage`, `amount`, `is_active` |
| [`transaction_orders`](#) | Order transaction history | `order_id`, `transaction_id`, `amount` |
| [`helpdesk_tickets`](#) | Support ticket system | `ticket_id`, `user_id`, `category_id`, `subject`, `status`, `priority` |
| [`helpdesk_ticket_categories`](#) | Ticket category definitions | `name`, `created_by` |
| [`helpdesk_conversions`](#) | Ticket conversation/messages | `ticket_id`, `user_id`, `message`, `sender_type` |
| [`chatify_favorites`](#) | Chat favorite contacts | `user_id`, `favorite_id` |
| [`chatify_messages`](#) | Chat message storage | `id`, `message_id`, `from_id`, `to_id`, `body`, `seen`, `created_at` |
| [`ch_notifications`](#) | In-app chat notifications | `type`, `from_id`, `to_id`, `data`, `read_at` |
| [`ch_notification_users`](#) | User-specific chat notification preferences | `user_id`, `notification_type`, `is_active` |
| [`custom_domain_requests`](#) | Custom domain setup requests | `workspace_id`, `domain`, `status` |
| [`cache`](#) | Cache storage | `key`, `value`, `expiration` |
| [`failed_jobs`](#) | Failed queue job records | `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` |
| [`jobs`](#) | Queued job definitions | `queue`, `payload`, `attempts`, `reserved_at`, `available_at` |

### Laratrust Tables (Authentication & Authorization)

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`roles`](#) | User role definitions | `name`, `display_name`, `description` |
| [`permissions`](#) | Permission definitions | `name`, `display_name`, `description` |
| [`role_user`](#) | User-role assignments (pivot) | `user_id`, `role_id` |
| [`permission_role`](#) | Role-permission assignments (pivot) | `permission_id`, `role_id` |

---

## HRM Module

The HRM (Human Resource Management) module handles employee management, leaves, attendance, payroll, and HR-related processes.

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`branches`](#) | Branch/location management | `id`, `name`, `workspace`, `created_by` |
| [`departments`](#) | Department definitions | `id`, `branch_id`, `name`, `workspace`, `created_by` |
| [`designations`](#) | Job designation titles | `id`, `branch_id`, `department_id`, `name`, `workspace`, `created_by` |
| [`employees`](#) | Employee records | `id`, `user_id`, `name`, `dob`, `gender`, `phone`, `address`, `email`, `employee_id`, `branch_id`, `department_id`, `designation_id`, `company_doj`, `salary`, `is_active`, `workspace`, `created_by` |
| [`documents`](#) | Company documents/policies | `id`, `name`, `workspace`, `created_by` |
| [`document_types`](#) | Document type definitions | `id`, `name`, `workspace`, `created_by` |
| [`employee_documents`](#) | Employee document uploads | `id`, `employee_id`, `document_id`, `document_value`, `created_by` |
| [`company_policies`](#) | Company policy documents | `id`, `title`, `workspace`, `created_by` |
| [`leave_types`](#) | Leave type definitions | `id`, `title`, `days`, `created_by`, `workspace` |
| [`leaves`](#) | Leave requests/records | `id`, `employee_id`, `user_id`, `leave_type_id`, `applied_on`, `start_date`, `end_date`, `total_leave_days`, `leave_reason`, `status`, `workspace`, `created_by`, `approved_days`, `status_reason` |
| [`award_types`](#) | Award category definitions | `id`, `name`, `workspace`, `created_by` |
| [`awards`](#) | Employee awards | `id`, `employee_id`, `award_type_id`, `date`, `gift`, `description`, `workspace`, `created_by` |
| [`transfers`](#) | Employee transfers | `id`, `employee_id`, `branch_id`, `department_id`, `transfer_date`, `description`, `workspace`, `created_by` |
| [`resignations`](#) | Employee resignations | `id`, `employee_id`, `notice_date`, `resignation_date`, `description`, `workspace`, `created_by` |
| [`attendances`](#) | Attendance records | `id`, `employee_id`, `date`, `status`, `clock_in`, `clock_out`, `late`, `early_leaving`, `overtime`, `workspace`, `site_id`, `created_by` |
| [`travels`](#) | Travel requests | `id`, `employee_id`, `place`, `start_date`, `end_date`, `purpose`, `description`, `status`, `workspace`, `created_by` |
| [`promotions`](#) | Promotion records | `id`, `employee_id`, `designation_id`, `promotion_date`, `description`, `workspace`, `created_by` |
| [`complaints`](#) | Employee complaints | `id`, `employee_id`, `complaint_from`, `date`, `description`, `workspace`, `created_by` |
| [`warnings`](#) | Warning records | `id`, `employee_id`, `warning_by`, `date`, `description`, `workspace`, `created_by` |
| [`terminations`](#) | Termination records | `id`, `employee_id`, `termination_type_id`, `notice_date`, `termination_date`, `description`, `workspace`, `created_by` |
| [`termination_types`](#) | Termination type definitions | `id`, `name`, `workspace`, `created_by` |
| [`announcements`](#) | Company announcements | `id`, `title`, `description`, `start_date`, `end_date`, `workspace`, `created_by` |
| [`announcement_employees`](#) | Announcement-employee mapping | `id`, `announcement_id`, `employee_id` |
| [`holidays`](#) | Holiday calendar | `id`, `date`, `occasion`, `workspace`, `created_by` |
| [`time_sheets`](#) | Time tracking records | `id`, `employee_id`, `date`, `hours`, `description`, `workspace`, `created_by` |
| [`payslip_types`](#) | Payslip type definitions | `id`, `name`, `workspace`, `created_by` |
| [`allowance_options`](#) | Allowance option definitions | `id`, `name`, `workspace`, `created_by` |
| [`loan_options`](#) | Loan option definitions | `id`, `name`, `workspace`, `created_by` |
| [`deduction_options`](#) | Deduction option definitions | `id`, `name`, `workspace`, `created_by` |
| [`allowances`](#) | Employee allowances | `id`, `employee_id`, `allowance_option_id`, `amount`, `type`, `workspace`, `created_by` |
| [`commissions`](#) | Employee commissions | `id`, `employee_id`, `title`, `amount`, `type`, `workspace`, `created_by` |
| [`loans`](#) | Employee loans | `id`, `employee_id`, `loan_option_id`, `loan_amount`, `loan_intrest`, `amount_paid`, `monthly_payment`, `workspace`, `created_by` |
| [`saturation_deductions`](#) | Saturation deductions | `id`, `employee_id`, `deduction_option_id`, `amount`, `type`, `workspace`, `created_by` |
| [`other_payments`](#) | Other payments | `id`, `employee_id`, `title`, `amount`, `type`, `workspace`, `created_by` |
| [`overtime`](#) | Overtime records | `id`, `employee_id`, `title`, `number_of_days`, `hours`, `rate`, `workspace`, `created_by` |
| [`pay_slips`](#) | Payslip records | `id`, `employee_id`, `net_payble`, `salary_month`, `status`, `basic_salary`, `allowance`, `commission`, `loan`, `saturation_deduction`, `other_payment`, `overtime`, `workspace`, `created_by` |
| [`events`](#) | Company events | `id`, `title`, `start_date`, `end_date`, `description`, `workspace`, `created_by` |
| [`event_employees`](#) | Event-employee mapping | `id`, `event_id`, `employee_id` |
| [`joining_letters`](#) | Joining letter templates | `id`, `template`, `created_by` |
| [`experience_certificates`](#) | Experience certificate templates | `id`, `template`, `created_by` |
| [`noc_certificates`](#) | NOC certificate templates | `id`, `template`, `created_by` |
| [`ip_restricts`](#) | IP restrictions for login | `id`, `user_id`, `ip_address`, `is_active`, `workspace` |
| [`tax_brackets`](#) | Tax bracket configurations | `id`, `min_amount`, `max_amount`, `tax_rate`, `workspace`, `created_by` |
| [`tax_rebates`](#) | Tax rebate settings | `id`, `rebate_amount`, `workspace`, `created_by` |
| [`tax_thresholds`](#) | Tax threshold definitions | `id`, `threshold_amount`, `workspace`, `created_by` |
| [`allowance_taxes`](#) | Allowance tax calculations | `id`, `allowance_id`, `tax_bracket_id`, `workspace`, `created_by` |
| [`company_contributions`](#) | Company contribution records | `id`, `employee_id`, `title`, `amount`, `type`, `workspace`, `created_by` |
| [`general_transfer`](#) | Employee/material/machinery transfers | `id`, `transfer_type`, `machinery_id`, `tools_and_equipment_id`, `employee_id`, `from_warehouse_id`, `to_warehouse_id`, `from_site_id`, `to_site_id`, `transfer_date`, `status`, `created_by` |

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`users`](#) | Stores user account information | `id`, `name`, `email`, `password`, `referral_code`, `password_text` |
| [`password_reset_tokens`](#) | Stores password reset tokens | `email`, `token`, `created_at` |
| [`password_resets`](#) | Legacy password reset table | `email`, `token`, `created_at` |
| [`personal_access_tokens`](#) | API token management for Sanctum | `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `expires_at` |
| [`sessions`](#) | User session management | `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` |
| [`work_spaces`](#) | Multi-workspace/tenant management | `id`, `name`, `slug`, `email`, `phone`, `address`, `business_info`, `created_by` |
| [`settings`](#) | Global and workspace-specific settings | `name`, `value`, `workspace_id` |
| [`user_active_modules`](#) | Tracks active modules per user/workspace | `user_id`, `module`, `workspace_id` |
| [`plans`](#) | Subscription plans configuration | `name`, `price`, `duration`, `status`, `package_price_monthly`, `package_price_yearly` |
| [`add_ons`](#) | System plugins/extensions | `name`, `description`, `price`, `image`, `status` |
| [`orders`](#) | Subscription and order records | `order_id`, `user_id`, `plan_id`, `amount`, `status`, `is_refund` |
| [`user_coupons`](#) | User-coupon assignments | `user_id`, `coupon_id` |
| [`coupons`](#) | Discount coupon definitions | `code`, `discount_type`, `discount`, `limit`, `type` |
| [`currencies`](#) | Currency definitions | `name`, `symbol`, `code`, `exchange_rate` |
| [`languages`](#) | System languages | `name`, `code`, `direction`, `is_enable` |
| [`email_templates`](#) | Email template definitions | `name`, `body`, `module` |
| [`email_template_langs`](#) | Localized email templates | `template_id`, `lang`, `subject`, `content` |
| [`notifications`](#) | System notifications | `user_id`, `type`, `data`, `read_at`, `created_at` |
| [`notification_template_langs`](#) | Notification template translations | `parent_id`, `lang`, `variables`, `content` |
| [`user_notifications`](#) | User-specific notifications | `user_id`, `notification_id`, `is_read` |
| [`login_details`](#) | User login history tracking | `user_id`, `ip_address`, `login_date`, `browser`, `platform` |
| [`api_key_settings`](#) | API key management | `key_name`, `key_value`, `status` |
| [`device_tokens`](#) | Mobile device tokens for push notifications | `user_id`, `token`, `device_type` |
| [`referral_transactions`](#) | Referral transaction records | `user_id`, `referred_by`, `amount`, `type` |
| [`referral_settings`](#) | Referral program configuration | `percentage`, `amount`, `is_active` |
| [`transaction_orders`](#) | Order transaction history | `order_id`, `transaction_id`, `amount` |
| [`helpdesk_tickets`](#) | Support ticket system | `ticket_id`, `user_id`, `category_id`, `subject`, `status`, `priority` |
| [`helpdesk_ticket_categories`](#) | Ticket category definitions | `name`, `created_by` |
| [`helpdesk_conversions`](#) | Ticket conversation/messages | `ticket_id`, `user_id`, `message`, `sender_type` |
| [`chatify_favorites`](#) | Chat favorite contacts | `user_id`, `favorite_id` |
| [`chatify_messages`](#) | Chat message storage | `id`, `message_id`, `from_id`, `to_id`, `body`, `seen`, `created_at` |
| [`ch_notifications`](#) | In-app chat notifications | `type`, `from_id`, `to_id`, `data`, `read_at` |
| [`ch_notification_users`](#) | User-specific chat notification preferences | `user_id`, `notification_type`, `is_active` |
| [`custom_domain_requests`](#) | Custom domain setup requests | `workspace_id`, `domain`, `status` |
| [`cache`](#) | Cache storage | `key`, `value`, `expiration` |
| [`failed_jobs`](#) | Failed queue job records | `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` |
| [`jobs`](#) | Queued job definitions | `queue`, `payload`, `attempts`, `reserved_at`, `available_at` |

### Laratrust Tables (Authentication & Authorization)

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`roles`](#) | User role definitions | `name`, `display_name`, `description` |
| [`permissions`](#) | Permission definitions | `name`, `display_name`, `description` |
| [`role_user`](#) | User-role assignments (pivot) | `user_id`, `role_id` |
| [`permission_role`](#) | Role-permission assignments (pivot) | `permission_id`, `role_id` |

---

## Inventory Module

The Inventory module manages materials, suppliers, warehouses, stock transactions, and procurement processes.

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`materials`](#) | Material/item inventory | `id`, `name`, `sku`, `category_id`, `unit_id`, `price`, `reorder_level`, `status`, `hsn_sac`, `gst_rate` |
| [`material_categories`](#) | Material category definitions | `id`, `name`, `created_by`, `workspace_id` |
| [`suppliers`](#) | Supplier/vendor information | `id`, `name`, `category_id`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `pan_number`, `bank_name`, `account_number`, `ifsc_code` |
| [`supplier_categories`](#) | Supplier category classifications | `id`, `name`, `created_by`, `workspace_id` |
| [`machineries`](#) | Equipment/machinery inventory | `id`, `name`, `category_id`, `purchase_date`, `status`, `hourly_rate`, `maintenance_date` |
| [`machinery_categories`](#) | Machinery category definitions | `id`, `name`, `created_by`, `workspace_id` |
| [`units`](#) | Unit of measurement definitions | `id`, `name`, `symbol`, `created_by`, `workspace_id` |
| [`warehouses`](#) | Warehouse/location management | `id`, `name`, `address`, `city`, `city_zip`, `workspace`, `created_by` |
| [`warehouse_products`](#) | Product-stock mapping per warehouse | `warehouse_id`, `product_id`, `quantity` |
| [`warehouse_transfers`](#) | Inter-warehouse stock transfers | `id`, `from_warehouse_id`, `to_warehouse_id`, `transfer_date`, `status`, `created_by` |
| [`assets_tools_and_equipment`](#) | Tools and equipment tracking | `id`, `name`, `category`, `quantity`, `status`, `purchase_date`, `assigned_to` |
| [`assets_tools_and_equipment_transfer`](#) | Asset transfer records | `id`, `asset_id`, `from_user_id`, `to_user_id`, `transfer_date`, `status` |
| [`material_transfers`](#) | Material transfer requests | `id`, `transfer_number`, `from_site_id`, `to_site_id`, `transfer_date`, `status`, `created_by` |
| [`material_transfer_items`](#) | Items in material transfers | `transfer_id`, `material_id`, `quantity`, `unit_id` |
| [`stock_transactions`](#) | Stock movement history | `id`, `project_id`, `material_id`, `type`, `quantity`, `rate`, `reference_type`, `reference_id`, `created_by` |
| [`material_project_stock`](#) | Material stock per project/site | `project_id`, `material_id`, `quantity`, `updated_at` |
| [`grns`](#) | Goods Receipt Notes | `id`, `grn_number`, `po_id`, `supplier_id`, `site_id`, `grn_date`, `received_by`, `status`, `created_by` |
| [`grn_items`](#) | Items received in GRN | `grn_id`, `material_id`, `quantity`, `accepted_qty`, `rejected_qty` |
| [`indents`](#) | Material indent requests | `id`, `indent_number`, `indent_date`, `supplier_id`, `total_amount`, `status`, `site_id`, `created_by` |
| [`indent_items`](#) | Items in indent requests | `indent_id`, `material_id`, `quantity`, `unit_id`, `required_date` |
| [`purchase_orders`](#) | Purchase order records | `id`, `po_number`, `po_date`, `supplier_id`, `total_amount`, `status`, `site_id`, `indent_id`, `created_by` |
| [`purchase_order_items`](#) | Items in purchase orders | `po_id`, `material_id`, `quantity`, `rate`, `amount`, `received_qty`, `tax_amount`, `cgst`, `sgst`, `igst` |
| [`purchase_invoices`](#) | Supplier purchase invoices | `id`, `invoice_number`, `invoice_date`, `supplier_id`, `total_amount`, `status`, `invoice_file`, `site_id`, `grn_id`, `created_by` |
| [`purchase_invoice_items`](#) | Items in purchase invoices | `purchase_invoice_id`, `material_id`, `quantity`, `rate`, `amount`, `grn_item_id`, `cgst`, `sgst`, `igst` |
| [`purchases`](#) | Legacy purchase records | `purchase_id`, `user_id`, `vender_id`, `warehouse_id`, `purchase_date`, `status` |
| [`purchase_products`](#) | Products in purchases | `purchase_id`, `product_id`, `quantity`, `price`, `tax_amount` |
| [`purchase_payments`](#) | Purchase payment records | `purchase_id`, `user_id`, `amount`, `date`, `payment_method`, `reference` |
| [`purchase_debit_notes`](#) | Purchase debit/credit notes | `id`, `purchase_id`, `amount`, `reason`, `date` |
| [`purchase_attachments`](#) | Purchase document attachments | `purchase_id`, `file_name`, `file_path` |

---

## Payment Module

The Payment module handles invoicing, payments, billing, and financial transactions.

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`invoices`](#) | Customer/sales invoices | `id`, `invoice_id`, `user_id`, `customer_id`, `issue_date`, `due_date`, `category_id`, `status`, `invoice_module` |
| [`invoice_products`](#) | Products/services in invoices | `invoice_id`, `product_id`, `quantity`, `price`, `tax`, `discount` |
| [`invoice_payments`](#) | Invoice payment records | `invoice_id`, `user_id`, `amount`, `date`, `payment_method`, `reference` |
| [`invoice_attechments`](#) | Invoice document attachments | `invoice_id`, `file_name`, `file_path` |
| [`receipts`](#) | Payment receipt records | `id`, `receipt_number`, `customer_id`, `amount`, `date`, `payment_method`, `created_by` |
| [`proposals`](#) | Business proposals/quotes | `id`, `proposal_id`, `user_id`, `customer_id`, `issue_date`, `status`, `proposal_module` |
| [`proposal_products`](#) | Products in proposals | `proposal_id`, `product_id`, `quantity`, `price`, `tax` |
| [`proposal_attechments`](#) | Proposal document attachments | `proposal_id`, `file_name`, `file_path` |
| [`credit_notes`](#) | Credit note records | `id`, `invoice_id`, `amount`, `reason`, `date` |
| [`payments_modules`](#) | Payment module configuration | `id`, `module_name`, `is_active`, `workspace_id` |
| [`payment_module_allocations`](#) | Payment allocation records | `id`, `module_id`, `amount`, `reference_id`, `type`, `workspace_id` |
| [`bank_transfer_payments`](#) | Bank transfer payment records | `id`, `order_id`, `user_id`, `request`, `status`, `type`, `price`, `attachment`, `bank_accounts_id` |
| [`general_transfer`](#) | General fund transfers | `id`, `from_account`, `to_account`, `amount`, `date`, `status`, `description`, `created_by` |

---

## Reports Module

The Reports module tracks activities, daily progress, consumptions, man-power, and project-related documentation.

| Table Name | Purpose | Key Columns |
|------------|---------|--------------|
| [`activities`](#) | Project/site activities tracking | `id`, `title`, `date`, `scope`, `quantity`, `unit`, `priority`, `status`, `created_by`, `workspace_id`, `site_id`, `reference_file` |
| [`activities_completed`](#) | Completed activity records | `id`, `activity_id`, `completed_date`, `completed_quantity`, `remarks`, `created_by`, `completed_reference_file` |
| [`daily_progress_reports`](#) | Daily progress reporting | `id`, `date`, `machine_start_reading`, `machine_end_reading`, `number_of_operators`, `work_details`, `diesel_consumption`, `status`, `created_by`, `workspace_id`, `site_id`, `machinery_id`, `activity_id` |
| [`daily_consumption_masters`](#) | Daily consumption records | `id`, `consumption_number`, `consumption_date`, `consumption_type`, `machinery_type`, `machinery_id`, `site_id`, `consumption_file`, `status`, `created_by`, `workspace_id` |
| [`daily_consumption_details`](#) | Detailed consumption items | `master_id`, `material_id`, `quantity`, `rate`, `amount` |
| [`project_documents`](#) | Project file/document management | `id`, `project_id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `folder_path`, `description` |
| [`project_files_new`](#) | Additional project file storage | `id`, `project_id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `storage_disk` |
| [`man_power_types`](#) | Manpower category definitions | `id`, `name`, `rate_per_day`, `rate_per_hour`, `created_by`, `workspace_id` |
| [`man_power_masters`](#) | Manpower allocation records | `id`, `man_power_type_id`, `date`, `quantity`, `site_id`, `activity_id`, `status`, `created_by`, `workspace_id` |
| [`man_power_details`](#) | Detailed manpower entries | `master_id`, `worker_name`, `aadhar_number`, `wage_rate`, `total_days`, `total_amount` |
| [`gst_masters`](#) | GST rate configurations | `id`, `cgst_rate`, `sgst_rate`, `igst_rate`, `hsn_code`, `description` |

---

## Truncate Plan

> ⚠️ **Warning**: Execute these commands with caution. Truncating tables will delete all data permanently. Ensure you have a complete backup before running any of these commands.

### ERP Module Tables

```sql
-- Authentication & User Management
TRUNCATE TABLE users;
TRUNCATE TABLE password_reset_tokens;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE personal_access_tokens;
TRUNCATE TABLE sessions;

-- Workspaces & Settings
TRUNCATE TABLE work_spaces;
TRUNCATE TABLE settings;
TRUNCATE TABLE user_active_modules;

-- Subscriptions & Billing
TRUNCATE TABLE plans;
TRUNCATE TABLE add_ons;
TRUNCATE TABLE orders;
TRUNCATE TABLE user_coupons;
TRUNCATE TABLE coupons;
TRUNCATE TABLE currencies;

-- Communications
TRUNCATE TABLE languages;
TRUNCATE TABLE email_templates;
TRUNCATE TABLE email_template_langs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE notification_template_langs;
TRUNCATE TABLE user_notifications;
TRUNCATE TABLE login_details;
TRUNCATE TABLE device_tokens;

-- Referrals
TRUNCATE TABLE referral_transactions;
TRUNCATE TABLE referral_settings;
TRUNCATE TABLE transaction_orders;

-- Support & Chat
TRUNCATE TABLE helpdesk_tickets;
TRUNCATE TABLE helpdesk_ticket_categories;
TRUNCATE TABLE helpdesk_conversions;
TRUNCATE TABLE chatify_favorites;
TRUNCATE TABLE chatify_messages;
TRUNCATE TABLE ch_notifications;
TRUNCATE TABLE ch_notification_users;

-- System
TRUNCATE TABLE custom_domain_requests;
TRUNCATE TABLE api_key_settings;
TRUNCATE TABLE cache;
TRUNCATE TABLE failed_jobs;
TRUNCATE TABLE jobs;

-- Laratrust (RBAC)
TRUNCATE TABLE roles;
TRUNCATE TABLE permissions;
TRUNCATE TABLE role_user;
TRUNCATE TABLE permission_role;
```

### HRM Module Tables

```sql
-- Organization Structure
TRUNCATE TABLE branches;
TRUNCATE TABLE departments;
TRUNCATE TABLE designations;

-- Employee Management
TRUNCATE TABLE employees;
TRUNCATE TABLE documents;
TRUNCATE TABLE document_types;
TRUNCATE TABLE employee_documents;
TRUNCATE TABLE company_policies;

-- Leave Management
TRUNCATE TABLE leave_types;
TRUNCATE TABLE leaves;

-- Awards & Recognition
TRUNCATE TABLE award_types;
TRUNCATE TABLE awards;

-- Employee Movements
TRUNCATE TABLE transfers;
TRUNCATE TABLE resignations;
TRUNCATE TABLE terminations;
TRUNCATE TABLE termination_types;
TRUNCATE TABLE promotions;

-- Time & Attendance
TRUNCATE TABLE attendances;
TRUNCATE TABLE time_sheets;
TRUNCATE TABLE holidays;

-- Travel & Expenses
TRUNCATE TABLE travels;

-- Employee Relations
TRUNCATE TABLE complaints;
TRUNCATE TABLE warnings;

-- Payroll - Types
TRUNCATE TABLE payslip_types;
TRUNCATE TABLE allowance_options;
TRUNCATE TABLE loan_options;
TRUNCATE TABLE deduction_options;

-- Payroll - Records
TRUNCATE TABLE allowances;
TRUNCATE TABLE commissions;
TRUNCATE TABLE loans;
TRUNCATE TABLE saturation_deductions;
TRUNCATE TABLE other_payments;
TRUNCATE TABLE overtime;
TRUNCATE TABLE pay_slips;
TRUNCATE TABLE company_contributions;

-- Events & Announcements
TRUNCATE TABLE events;
TRUNCATE TABLE event_employees;
TRUNCATE TABLE announcements;
TRUNCATE TABLE announcement_employees;

-- Certificates & Documents
TRUNCATE TABLE joining_letters;
TRUNCATE TABLE experience_certificates;
TRUNCATE TABLE noc_certificates;

-- Security
TRUNCATE TABLE ip_restricts;

-- Tax Configuration
TRUNCATE TABLE tax_brackets;
TRUNCATE TABLE tax_rebates;
TRUNCATE TABLE tax_thresholds;
TRUNCATE TABLE allowance_taxes;

-- General Transfers
TRUNCATE TABLE general_transfer;
```

### Inventory Module Tables

```sql
-- Material & Product Management
TRUNCATE TABLE materials;
TRUNCATE TABLE material_categories;
TRUNCATE TABLE units;

-- Suppliers
TRUNCATE TABLE suppliers;
TRUNCATE TABLE supplier_categories;

-- Machinery & Equipment
TRUNCATE TABLE machineries;
TRUNCATE TABLE machinery_categories;
TRUNCATE TABLE assets_tools_and_equipment;
TRUNCATE TABLE assets_tools_and_equipment_transfer;

-- Warehouses
TRUNCATE TABLE warehouses;
TRUNCATE TABLE warehouse_products;
TRUNCATE TABLE warehouse_transfers;

-- Stock Management
TRUNCATE TABLE stock_transactions;
TRUNCATE TABLE material_project_stock;

-- Procurement
TRUNCATE TABLE indents;
TRUNCATE TABLE indent_items;
TRUNCATE TABLE purchase_orders;
TRUNCATE TABLE purchase_order_items;
TRUNCATE TABLE grns;
TRUNCATE TABLE grn_items;
TRUNCATE TABLE purchase_invoices;
TRUNCATE TABLE purchase_invoice_items;

-- Legacy Procurement
TRUNCATE TABLE purchases;
TRUNCATE TABLE purchase_products;
TRUNCATE TABLE purchase_payments;
TRUNCATE TABLE purchase_debit_notes;
TRUNCATE TABLE purchase_attachments;

-- Material Transfers
TRUNCATE TABLE material_transfers;
TRUNCATE TABLE material_transfer_items;
```

### Payment Module Tables

```sql
-- Invoices
TRUNCATE TABLE invoices;
TRUNCATE TABLE invoice_products;
TRUNCATE TABLE invoice_payments;
TRUNCATE TABLE invoice_attechments;
TRUNCATE TABLE receipts;
TRUNCATE TABLE credit_notes;

-- Proposals
TRUNCATE TABLE proposals;
TRUNCATE TABLE proposal_products;
TRUNCATE TABLE proposal_attechments;

-- Payment Processing
TRUNCATE TABLE payments_modules;
TRUNCATE TABLE payment_module_allocations;
TRUNCATE TABLE bank_transfer_payments;
TRUNCATE TABLE general_transfer;
```

### Reports Module Tables

```sql
-- Activities & Progress
TRUNCATE TABLE activities;
TRUNCATE TABLE activities_completed;
TRUNCATE TABLE daily_progress_reports;
TRUNCATE TABLE daily_consumption_masters;
TRUNCATE TABLE daily_consumption_details;

-- Project Documentation
TRUNCATE TABLE project_documents;
TRUNCATE TABLE project_files_new;

-- Man Power
TRUNCATE TABLE man_power_types;
TRUNCATE TABLE man_power_masters;
TRUNCATE TABLE man_power_details;

-- Tax Configuration
TRUNCATE TABLE gst_masters;
```

---

## Notes

- Tables are organized by functional modules for easy navigation
- Key columns listed are the primary fields relevant to each table's purpose
- Some tables may have foreign key dependencies - consider the order when truncating
- The truncate plan includes all tables grouped by their respective modules
- Always backup data before performing any truncate operations

---

*Document generated for SitePilot Database Architecture*
