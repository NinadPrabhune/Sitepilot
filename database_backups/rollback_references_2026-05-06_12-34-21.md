# Migration Rollback Reference Guide

Generated: 2026-05-06 12:34:21

## Purpose

This document provides rollback references for orphaned migrations that need to be reconstructed.

## High-Risk Migration Rollback Templates

### 2019_05_08_094315_create_user_projects_table

**Operation Type**: create_table
**Target Table**: user_projects

**Rollback Command**:
```php
Schema::dropIfExists('user_projects');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_05_13_061456_create_tasks_table

**Operation Type**: create_table
**Target Table**: tasks

**Rollback Command**:
```php
Schema::dropIfExists('tasks');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_05_15_054812_create_task_files_table

**Operation Type**: create_table
**Target Table**: task_files

**Rollback Command**:
```php
Schema::dropIfExists('task_files');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_10_14_220244_create_milestones_table

**Operation Type**: create_table
**Target Table**: milestones

**Rollback Command**:
```php
Schema::dropIfExists('milestones');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_10_14_233948_create_sub_tasks_table

**Operation Type**: create_table
**Target Table**: sub_tasks

**Rollback Command**:
```php
Schema::dropIfExists('sub_tasks');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_10_18_114133_create_activity_logs_table

**Operation Type**: create_table
**Target Table**: activity_logs

**Rollback Command**:
```php
Schema::dropIfExists('activity_logs');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2020_03_23_153638_create_stages_table

**Operation Type**: create_table
**Target Table**: stages

**Rollback Command**:
```php
Schema::dropIfExists('stages');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_06_16_092727_create_product_services_table

**Operation Type**: create_table
**Target Table**: product_services

**Rollback Command**:
```php
Schema::dropIfExists('product_services');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_06_16_101208_create_categories_table

**Operation Type**: create_table
**Target Table**: categories

**Rollback Command**:
```php
Schema::dropIfExists('categories');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_06_16_105042_create_taxes_table

**Operation Type**: create_table
**Target Table**: taxes

**Rollback Command**:
```php
Schema::dropIfExists('taxes');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_06_21_104337_create_projects_table

**Operation Type**: create_table
**Target Table**: projects

**Rollback Command**:
```php
Schema::dropIfExists('projects');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_14_085216_create_bug_stages_table

**Operation Type**: create_table
**Target Table**: bug_stages

**Rollback Command**:
```php
Schema::dropIfExists('bug_stages');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_14_132351_create_comments_table

**Operation Type**: create_table
**Target Table**: comments

**Rollback Command**:
```php
Schema::dropIfExists('comments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_18_065110_create_bug_reports_table

**Operation Type**: create_table
**Target Table**: bug_reports

**Rollback Command**:
```php
Schema::dropIfExists('bug_reports');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_18_084714_create_bug_comments_table

**Operation Type**: create_table
**Target Table**: bug_comments

**Rollback Command**:
```php
Schema::dropIfExists('bug_comments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_18_085513_create_bug_files_table

**Operation Type**: create_table
**Target Table**: bug_files

**Rollback Command**:
```php
Schema::dropIfExists('bug_files');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_19_060243_create_project_files_table

**Operation Type**: create_table
**Target Table**: project_files

**Rollback Command**:
```php
Schema::dropIfExists('project_files');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_19_065033_create_client_projects_table

**Operation Type**: create_table
**Target Table**: client_projects

**Rollback Command**:
```php
Schema::dropIfExists('client_projects');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_07_19_065099_create_vender_projects_table

**Operation Type**: create_table
**Target Table**: vender_projects

**Rollback Command**:
```php
Schema::dropIfExists('vender_projects');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_08_25_110944_create_bank_accounts_table

**Operation Type**: create_table
**Target Table**: bank_accounts

**Rollback Command**:
```php
Schema::dropIfExists('bank_accounts');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_08_26_050328_create_bank_transfers_table

**Operation Type**: create_table
**Target Table**: bank_transfers

**Rollback Command**:
```php
Schema::dropIfExists('bank_transfers');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_08_29_071056_create_units_table

**Operation Type**: create_table
**Target Table**: units

**Rollback Command**:
```php
Schema::dropIfExists('units');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_08_31_033838_create_customers_table

**Operation Type**: create_table
**Target Table**: customers

**Rollback Command**:
```php
Schema::dropIfExists('customers');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_01_040217_create_venders_table

**Operation Type**: create_table
**Target Table**: venders

**Rollback Command**:
```php
Schema::dropIfExists('venders');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_01_112521_create_revenues_table

**Operation Type**: create_table
**Target Table**: revenues

**Rollback Command**:
```php
Schema::dropIfExists('revenues');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_02_052135_create_stock_reports_table

**Operation Type**: create_table
**Target Table**: stock_reports

**Rollback Command**:
```php
Schema::dropIfExists('stock_reports');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_05_092207_create_transactions_table

**Operation Type**: create_table
**Target Table**: transactions

**Rollback Command**:
```php
Schema::dropIfExists('transactions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_05_115417_create_leads_table

**Operation Type**: create_table
**Target Table**: leads

**Rollback Command**:
```php
Schema::dropIfExists('leads');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_05_125026_create_pipelines_table

**Operation Type**: create_table
**Target Table**: pipelines

**Rollback Command**:
```php
Schema::dropIfExists('pipelines');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_053233_create_lead_stages_table

**Operation Type**: create_table
**Target Table**: lead_stages

**Rollback Command**:
```php
Schema::dropIfExists('lead_stages');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_083901_create_deal_stages_table

**Operation Type**: create_table
**Target Table**: deal_stages

**Rollback Command**:
```php
Schema::dropIfExists('deal_stages');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_084035_create_deals_table

**Operation Type**: create_table
**Target Table**: deals

**Rollback Command**:
```php
Schema::dropIfExists('deals');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_095211_create_user_deals_table

**Operation Type**: create_table
**Target Table**: user_deals

**Rollback Command**:
```php
Schema::dropIfExists('user_deals');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_095417_create_user_leads_table

**Operation Type**: create_table
**Target Table**: user_leads

**Rollback Command**:
```php
Schema::dropIfExists('user_leads');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_110631_create_labels_table

**Operation Type**: create_table
**Target Table**: labels

**Rollback Command**:
```php
Schema::dropIfExists('labels');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_124424_create_bills_table

**Operation Type**: create_table
**Target Table**: bills

**Rollback Command**:
```php
Schema::dropIfExists('bills');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_07_125134_create_payments_table

**Operation Type**: create_table
**Target Table**: payments

**Rollback Command**:
```php
Schema::dropIfExists('payments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_053530_create_lead_files_table

**Operation Type**: create_table
**Target Table**: lead_files

**Rollback Command**:
```php
Schema::dropIfExists('lead_files');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_053708_create_lead_calls_table

**Operation Type**: create_table
**Target Table**: lead_calls

**Rollback Command**:
```php
Schema::dropIfExists('lead_calls');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_053744_create_lead_emails_table

**Operation Type**: create_table
**Target Table**: lead_emails

**Rollback Command**:
```php
Schema::dropIfExists('lead_emails');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_054005_create_lead_activity_logs_table

**Operation Type**: create_table
**Target Table**: lead_activity_logs

**Rollback Command**:
```php
Schema::dropIfExists('lead_activity_logs');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_054051_create_lead_discussions_table

**Operation Type**: create_table
**Target Table**: lead_discussions

**Rollback Command**:
```php
Schema::dropIfExists('lead_discussions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_060852_create_bill_products_table

**Operation Type**: create_table
**Target Table**: bill_products

**Rollback Command**:
```php
Schema::dropIfExists('bill_products');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_084653_create_client_deals_table

**Operation Type**: create_table
**Target Table**: client_deals

**Rollback Command**:
```php
Schema::dropIfExists('client_deals');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_084731_create_deal_discussions_table

**Operation Type**: create_table
**Target Table**: deal_discussions

**Rollback Command**:
```php
Schema::dropIfExists('deal_discussions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_084757_create_deal_files_table

**Operation Type**: create_table
**Target Table**: deal_files

**Rollback Command**:
```php
Schema::dropIfExists('deal_files');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_084858_create_deal_calls_table

**Operation Type**: create_table
**Target Table**: deal_calls

**Rollback Command**:
```php
Schema::dropIfExists('deal_calls');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_08_084924_create_deal_emails_table

**Operation Type**: create_table
**Target Table**: deal_emails

**Rollback Command**:
```php
Schema::dropIfExists('deal_emails');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_12_095504_create_sources_table

**Operation Type**: create_table
**Target Table**: sources

**Rollback Command**:
```php
Schema::dropIfExists('sources');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_12_121817_create_debit_notes_table

**Operation Type**: create_table
**Target Table**: debit_notes

**Rollback Command**:
```php
Schema::dropIfExists('debit_notes');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_12_122223_create_bill_payments_table

**Operation Type**: create_table
**Target Table**: bill_payments

**Rollback Command**:
```php
Schema::dropIfExists('bill_payments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_13_123608_create_deal_tasks_table

**Operation Type**: create_table
**Target Table**: deal_tasks

**Rollback Command**:
```php
Schema::dropIfExists('deal_tasks');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_13_123624_create_client_permissions_table

**Operation Type**: create_table
**Target Table**: client_permissions

**Rollback Command**:
```php
Schema::dropIfExists('client_permissions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_09_14_060053_create_deal_activity_logs_table

**Operation Type**: create_table
**Target Table**: deal_activity_logs

**Rollback Command**:
```php
Schema::dropIfExists('deal_activity_logs');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_11_02_070509_create_pos_table

**Operation Type**: create_table
**Target Table**: pos

**Rollback Command**:
```php
Schema::dropIfExists('pos');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_11_03_105649_create_pos_products_table

**Operation Type**: create_table
**Target Table**: pos_products

**Rollback Command**:
```php
Schema::dropIfExists('pos_products');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2022_11_03_112845_create_pos_payments_table

**Operation Type**: create_table
**Target Table**: pos_payments

**Rollback Command**:
```php
Schema::dropIfExists('pos_payments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_06_05_043450_create_landing_page_settings_table

**Operation Type**: create_table
**Target Table**: landing_page_settings

**Rollback Command**:
```php
Schema::dropIfExists('landing_page_settings');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_06_10_114031_create_join_us_table

**Operation Type**: create_table
**Target Table**: join_us

**Rollback Command**:
```php
Schema::dropIfExists('join_us');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_09_14_084432_create_chart_of_accounts_table

**Operation Type**: create_table
**Target Table**: chart_of_accounts

**Rollback Command**:
```php
Schema::dropIfExists('chart_of_accounts');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_09_14_084924_create_chart_of_account_types_table

**Operation Type**: create_table
**Target Table**: chart_of_account_types

**Rollback Command**:
```php
Schema::dropIfExists('chart_of_account_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_09_14_091741_create_chart_of_account_sub_types_table

**Operation Type**: create_table
**Target Table**: chart_of_account_sub_types

**Rollback Command**:
```php
Schema::dropIfExists('chart_of_account_sub_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_09_26_091528_create_lead_tasks_table

**Operation Type**: create_table
**Target Table**: lead_tasks

**Rollback Command**:
```php
Schema::dropIfExists('lead_tasks');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_09_29_105800_create_bill_attechments_table

**Operation Type**: create_table
**Target Table**: bill_attechments

**Rollback Command**:
```php
Schema::dropIfExists('bill_attechments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111418_create_documents_table

**Operation Type**: create_table
**Target Table**: documents

**Rollback Command**:
```php
Schema::dropIfExists('documents');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111501_create_branches_table

**Operation Type**: create_table
**Target Table**: branches

**Rollback Command**:
```php
Schema::dropIfExists('branches');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111545_create_departments_table

**Operation Type**: create_table
**Target Table**: departments

**Rollback Command**:
```php
Schema::dropIfExists('departments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111623_create_designations_table

**Operation Type**: create_table
**Target Table**: designations

**Rollback Command**:
```php
Schema::dropIfExists('designations');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111814_create_employees_table

**Operation Type**: create_table
**Target Table**: employees

**Rollback Command**:
```php
Schema::dropIfExists('employees');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111917_create_document_types_table

**Operation Type**: create_table
**Target Table**: document_types

**Rollback Command**:
```php
Schema::dropIfExists('document_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_111959_create_employee_documents_table

**Operation Type**: create_table
**Target Table**: employee_documents

**Rollback Command**:
```php
Schema::dropIfExists('employee_documents');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112555_create_company_policies_table

**Operation Type**: create_table
**Target Table**: company_policies

**Rollback Command**:
```php
Schema::dropIfExists('company_policies');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112635_create_leave_types_table

**Operation Type**: create_table
**Target Table**: leave_types

**Rollback Command**:
```php
Schema::dropIfExists('leave_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112711_create_leaves_table

**Operation Type**: create_table
**Target Table**: leaves

**Rollback Command**:
```php
Schema::dropIfExists('leaves');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112811_create_award_types_table

**Operation Type**: create_table
**Target Table**: award_types

**Rollback Command**:
```php
Schema::dropIfExists('award_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112848_create_awards_table

**Operation Type**: create_table
**Target Table**: awards

**Rollback Command**:
```php
Schema::dropIfExists('awards');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_112925_create_transfers_table

**Operation Type**: create_table
**Target Table**: transfers

**Rollback Command**:
```php
Schema::dropIfExists('transfers');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113000_create_resignations_table

**Operation Type**: create_table
**Target Table**: resignations

**Rollback Command**:
```php
Schema::dropIfExists('resignations');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113048_create_attendance_table

**Operation Type**: create_table
**Target Table**: attendance

**Rollback Command**:
```php
Schema::dropIfExists('attendance');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113122_create_travels_table

**Operation Type**: create_table
**Target Table**: travels

**Rollback Command**:
```php
Schema::dropIfExists('travels');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113158_create_promotions_table

**Operation Type**: create_table
**Target Table**: promotions

**Rollback Command**:
```php
Schema::dropIfExists('promotions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113235_create_complaints_table

**Operation Type**: create_table
**Target Table**: complaints

**Rollback Command**:
```php
Schema::dropIfExists('complaints');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113310_create_warnings_table

**Operation Type**: create_table
**Target Table**: warnings

**Rollback Command**:
```php
Schema::dropIfExists('warnings');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113355_create_terminations_table

**Operation Type**: create_table
**Target Table**: terminations

**Rollback Command**:
```php
Schema::dropIfExists('terminations');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113444_create_termination_types_table

**Operation Type**: create_table
**Target Table**: termination_types

**Rollback Command**:
```php
Schema::dropIfExists('termination_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113642_create_announcements_table

**Operation Type**: create_table
**Target Table**: announcements

**Rollback Command**:
```php
Schema::dropIfExists('announcements');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113724_create_announcement_employees_table

**Operation Type**: create_table
**Target Table**: announcement_employees

**Rollback Command**:
```php
Schema::dropIfExists('announcement_employees');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113808_create_holidays_table

**Operation Type**: create_table
**Target Table**: holidays

**Rollback Command**:
```php
Schema::dropIfExists('holidays');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113843_create_time_sheets_table

**Operation Type**: create_table
**Target Table**: time_sheets

**Rollback Command**:
```php
Schema::dropIfExists('time_sheets');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113916_create_payslip_types_table

**Operation Type**: create_table
**Target Table**: payslip_types

**Rollback Command**:
```php
Schema::dropIfExists('payslip_types');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_113949_create_allowance_options_table

**Operation Type**: create_table
**Target Table**: allowance_options

**Rollback Command**:
```php
Schema::dropIfExists('allowance_options');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114025_create_loan_options_table

**Operation Type**: create_table
**Target Table**: loan_options

**Rollback Command**:
```php
Schema::dropIfExists('loan_options');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114102_create_deduction_options_table

**Operation Type**: create_table
**Target Table**: deduction_options

**Rollback Command**:
```php
Schema::dropIfExists('deduction_options');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114134_create_allowances_table

**Operation Type**: create_table
**Target Table**: allowances

**Rollback Command**:
```php
Schema::dropIfExists('allowances');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114208_create_commissions_table

**Operation Type**: create_table
**Target Table**: commissions

**Rollback Command**:
```php
Schema::dropIfExists('commissions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114247_create_loans_table

**Operation Type**: create_table
**Target Table**: loans

**Rollback Command**:
```php
Schema::dropIfExists('loans');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114325_create_saturation_deductions_table

**Operation Type**: create_table
**Target Table**: saturation_deductions

**Rollback Command**:
```php
Schema::dropIfExists('saturation_deductions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114402_create_other_payments_table

**Operation Type**: create_table
**Target Table**: other_payments

**Rollback Command**:
```php
Schema::dropIfExists('other_payments');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114432_create_overtimes_table

**Operation Type**: create_table
**Target Table**: overtimes

**Rollback Command**:
```php
Schema::dropIfExists('overtimes');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114504_create_pay_slips_table

**Operation Type**: create_table
**Target Table**: pay_slips

**Rollback Command**:
```php
Schema::dropIfExists('pay_slips');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114543_create_events_table

**Operation Type**: create_table
**Target Table**: events

**Rollback Command**:
```php
Schema::dropIfExists('events');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114614_create_event_employees_table

**Operation Type**: create_table
**Target Table**: event_employees

**Rollback Command**:
```php
Schema::dropIfExists('event_employees');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114658_create_joining_letters_table

**Operation Type**: create_table
**Target Table**: joining_letters

**Rollback Command**:
```php
Schema::dropIfExists('joining_letters');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114733_create_experience_certificates_table

**Operation Type**: create_table
**Target Table**: experience_certificates

**Rollback Command**:
```php
Schema::dropIfExists('experience_certificates');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114807_create_noc_certificates_table

**Operation Type**: create_table
**Target Table**: noc_certificates

**Rollback Command**:
```php
Schema::dropIfExists('noc_certificates');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_11_20_114845_create_ip_restricts_table

**Operation Type**: create_table
**Target Table**: ip_restricts

**Rollback Command**:
```php
Schema::dropIfExists('ip_restricts');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2023_12_21_065145_add_follow_up_date_to_leads_table

**Operation Type**: add_columns
**Target Table**: leads_table

**Rollback Command**:
```php
Schema::table('leads_table', function (Blueprint $table) {
    $table->dropColumn('follow');
    $table->dropColumn('up');
    $table->dropColumn('date');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_02_063707_add_bank_branch_to_bank_accounts_table

**Operation Type**: add_columns
**Target Table**: bank_accounts_table

**Rollback Command**:
```php
Schema::table('bank_accounts_table', function (Blueprint $table) {
    $table->dropColumn('bank');
    $table->dropColumn('branch');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_02_063823_add_swift_to_bank_accounts_table

**Operation Type**: add_columns
**Target Table**: bank_accounts_table

**Rollback Command**:
```php
Schema::table('bank_accounts_table', function (Blueprint $table) {
    $table->dropColumn('swift');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_02_090436_add_from_type_to_bank_transfers_table

**Operation Type**: add_columns
**Target Table**: bank_transfers_table

**Rollback Command**:
```php
Schema::table('bank_transfers_table', function (Blueprint $table) {
    $table->dropColumn('from');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_02_090455_add_to_type_to_bank_transfers_table

**Operation Type**: add_columns
**Target Table**: bank_transfers_table

**Rollback Command**:
```php
Schema::table('bank_transfers_table', function (Blueprint $table) {
    $table->dropColumn('to');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_03_095721_add_account_type_to_bills_table

**Operation Type**: add_columns
**Target Table**: bills_table

**Rollback Command**:
```php
Schema::table('bills_table', function (Blueprint $table) {
    $table->dropColumn('account');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_08_084957_create_customer_credit_notes_table

**Operation Type**: create_table
**Target Table**: customer_credit_notes

**Rollback Command**:
```php
Schema::dropIfExists('customer_credit_notes');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_09_042731_add_credit_note_balance_to_customers_table

**Operation Type**: add_columns
**Target Table**: customers_table

**Rollback Command**:
```php
Schema::table('customers_table', function (Blueprint $table) {
    $table->dropColumn('credit');
    $table->dropColumn('note');
    $table->dropColumn('balance');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_11_033435_add_bank_type_to_bank_accounts_table

**Operation Type**: add_columns
**Target Table**: bank_accounts_table

**Rollback Command**:
```php
Schema::table('bank_accounts_table', function (Blueprint $table) {
    $table->dropColumn('bank');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_11_040859_add_wallet_type_to_bank_accounts_table

**Operation Type**: add_columns
**Target Table**: bank_accounts_table

**Rollback Command**:
```php
Schema::table('bank_accounts_table', function (Blueprint $table) {
    $table->dropColumn('wallet');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_16_040303_create_customer_debit_notes_table

**Operation Type**: create_table
**Target Table**: customer_debit_notes

**Rollback Command**:
```php
Schema::dropIfExists('customer_debit_notes');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_17_024814_add_debit_note_balance_to_vendors_table

**Operation Type**: add_columns
**Target Table**: vendors_table

**Rollback Command**:
```php
Schema::table('vendors_table', function (Blueprint $table) {
    $table->dropColumn('debit');
    $table->dropColumn('note');
    $table->dropColumn('balance');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_01_18_103913_add_type_to_category_table

**Operation Type**: add_columns
**Target Table**: category_table

**Rollback Command**:
```php
Schema::table('category_table', function (Blueprint $table) {
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_02_05_030146_create_products_log_times_table

**Operation Type**: create_table
**Target Table**: products_log_times

**Rollback Command**:
```php
Schema::dropIfExists('products_log_times');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_02_23_095542_add_account_type_to_employees_table

**Operation Type**: add_columns
**Target Table**: employees_table

**Rollback Command**:
```php
Schema::table('employees_table', function (Blueprint $table) {
    $table->dropColumn('account');
    $table->dropColumn('type');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_02_26_064257_add_warehouse_id_to_product_services_table

**Operation Type**: add_columns
**Target Table**: product_services_table

**Rollback Command**:
```php
Schema::table('product_services_table', function (Blueprint $table) {
    $table->dropColumn('warehouse');
    $table->dropColumn('id');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_02_26_084938_create_pixels_table

**Operation Type**: create_table
**Target Table**: pixels

**Rollback Command**:
```php
Schema::dropIfExists('pixels');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_01_092909_add_passport_country_to_employees_table

**Operation Type**: add_columns
**Target Table**: employees_table

**Rollback Command**:
```php
Schema::table('employees_table', function (Blueprint $table) {
    $table->dropColumn('passport');
    $table->dropColumn('country');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_02_060829_add_dates_to_commissions_table

**Operation Type**: add_columns
**Target Table**: commissions_table

**Rollback Command**:
```php
Schema::table('commissions_table', function (Blueprint $table) {
    $table->dropColumn('dates');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_02_060906_add_dates_to_overtimes_table

**Operation Type**: add_columns
**Target Table**: overtimes_table

**Rollback Command**:
```php
Schema::table('overtimes_table', function (Blueprint $table) {
    $table->dropColumn('dates');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_02_114951_create_tax_brackets_table

**Operation Type**: create_table
**Target Table**: tax_brackets

**Rollback Command**:
```php
Schema::dropIfExists('tax_brackets');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_02_115000_create_tax_rebates_table

**Operation Type**: create_table
**Target Table**: tax_rebates

**Rollback Command**:
```php
Schema::dropIfExists('tax_rebates');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_02_115008_create_tax_thresholds_table

**Operation Type**: create_table
**Target Table**: tax_thresholds

**Rollback Command**:
```php
Schema::dropIfExists('tax_thresholds');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_03_031954_create_allowance_taxs_table

**Operation Type**: create_table
**Target Table**: allowance_taxs

**Rollback Command**:
```php
Schema::dropIfExists('allowance_taxs');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_03_064759_add_company_contribution_to_pay_slips_table

**Operation Type**: add_columns
**Target Table**: pay_slips_table

**Rollback Command**:
```php
Schema::table('pay_slips_table', function (Blueprint $table) {
    $table->dropColumn('company');
    $table->dropColumn('contribution');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2024_04_05_034309_create_company_contributions_table

**Operation Type**: create_table
**Target Table**: company_contributions

**Rollback Command**:
```php
Schema::dropIfExists('company_contributions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2019_05_03_000002_create_subscriptions_table

**Operation Type**: create_table
**Target Table**: subscriptions

**Rollback Command**:
```php
Schema::dropIfExists('subscriptions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2026_03_16_000001_create_supplier_transactions_table

**Operation Type**: create_table
**Target Table**: supplier_transactions

**Rollback Command**:
```php
Schema::dropIfExists('supplier_transactions');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2026_04_30_000016_add_db_constraints_to_prevent_orphan_data

**Operation Type**: add_columns
**Target Table**: prevent_orphan_data

**Rollback Command**:
```php
Schema::table('prevent_orphan_data', function (Blueprint $table) {
    $table->dropColumn('db');
    $table->dropColumn('constraints');
});
```

**Data Impact**: medium
**Rollback Risk**: HIGH - Test thoroughly in staging

### 2026_05_02_000001_create_basic_tables

**Operation Type**: create_table
**Target Table**: basic

**Rollback Command**:
```php
Schema::dropIfExists('basic');
```

**Data Impact**: high
**Rollback Risk**: HIGH - Test thoroughly in staging

