# SitePilot ERP End-to-End Functional Testing Report

====================================================
Generated: 2026-04-03

## Executive Summary
---------------------
This document provides comprehensive end-to-end testing of the SitePilot ERP system covering the complete procurement flow from Indent to Payment.

## 1. System Architecture Overview
-----------------------------------

### Core Modules Tested
- Indent Management
- Purchase Orders (PO)
- Goods Receipt Note (GRN)
- Purchase Invoice (PI)
- Payment Request & Payment
- Inventory / Stock
- Supplier Ledger / Accounting
- Reports

### Database Tables
| Table | Purpose |
|-------|---------|
| indents | Material requirement requests |
| indent_items | Items in indents |
| purchase_orders | PO records linked to indents |
| purchase_order_items | Items in POs |
| grns | Goods Receipt Notes |
| grn_items | Items in GRNs |
| purchase_invoices | Supplier invoices |
| purchase_invoice_items | Invoice line items |
| payments_module | Payment records |
| material_project_stock | Stock per site |
| stock_transaction | Stock movement history |
| supplier_transactions | Supplier ledger |

### Key Model Relationships


