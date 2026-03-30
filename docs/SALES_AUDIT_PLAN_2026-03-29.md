# Sales Audit Improvement Plan

**Date:** 2026-03-29  
**Based on:** Sales audit of quotation -> SO -> delivery -> invoice -> payment

## Goal

Stabilize the sales flow so the system has one clear source of truth for each stage, predictable state transitions, and auditable payment posting from invoice sampai lunas.

## Priority 1 - Normalize Payment Data Flow

### Why this comes first

The payment flow is the most fragile part of the audit. It currently mixes header fields, JSON allocation data, receipt items, and observer-side AR updates.

### Tasks

- choose one canonical payload for customer receipts
  - recommended: `customerReceiptItem` as the source of truth
  - keep JSON fields only as transient UI state, not persistent business state
- remove direct AR mutation from page code if the observer can own it
- make receipt creation and edit paths share one allocation normalizer
- reject invalid allocation instead of silently auto-correcting where possible
- add one atomic transaction for receipt header, receipt items, AR update, invoice status update, and journal posting

### Acceptance criteria

- one receipt cannot be posted twice
- one invoice cannot be paid beyond its remaining balance
- multi-invoice receipts update all related invoice balances consistently
- save/edit operations leave AR and invoice status in sync

## Priority 2 - Harden Sales Completion and Delivery

### Why this matters

The final state transition from approved SO to completed SO is split across resource rules, service logic, and related delivery records.

### Tasks

- centralize SO status transition rules in the service layer
- make completion rules explicit for each shipment mode
  - Ambil Sendiri
  - Kirim Langsung
- ensure delivery completion cannot bypass the required SO or warehouse checks
- add one definitive state transition test for each mode
- make the completion action fail loudly if the preconditions are not satisfied

### Acceptance criteria

- approved SO only moves to completed when all required fulfillment conditions are met
- no hidden auto-transition changes the final status unexpectedly
- test coverage proves the approved -> confirmed -> received -> completed path

## Priority 3 - Make AR Creation Idempotent

### Why this matters

Invoice creation currently creates AR automatically. That is correct as a default, but the contract needs to be explicit and safe if the same invoice path is retried.

### Tasks

- add a guard for duplicate AR creation by invoice id
- ensure test fixtures do not re-create AR rows that the observer already owns
- document whether AR is created only by observer or also by importer / seed path
- if a retry is needed, update the existing AR row instead of inserting a second one

### Acceptance criteria

- creating an invoice twice does not create duplicate AR rows
- retrying a receipt save does not duplicate AR posting
- tests no longer rely on manual AR inserts unless the scenario explicitly wants it

## Priority 4 - Reduce Hidden Compatibility Paths

### Why this matters

Legacy compatibility is useful, but the audit shows that it also increases maintenance cost.

### Tasks

- keep `invoice_id` compatibility only where a legacy consumer still needs it
- prefer one model of payment allocation in the UI and one model in persistence
- document all compatibility fields in the sales module
- add comments or service methods that state what is legacy and what is current

### Acceptance criteria

- developers can tell which fields are canonical without reading multiple files
- new work does not add more fallback state unless it is justified

## Priority 5 - Expand Regression Coverage

### Why this matters

Targeted tests already show failure points. The plan is to turn those into stable regression coverage.

### Tasks

- keep the current sales feature tests as the baseline regression set
- add a focused test for quotation -> SO branch inheritance
- add a focused test for final delivery completion in both shipment modes
- add a focused test for single-invoice and multi-invoice receipt posting
- add a focused test for AR idempotency on invoice create

### Acceptance criteria

- quotation, SO, delivery, invoice, and receipt paths each have one regression test that fails when the contract changes
- the current failures are either fixed or turned into explicit documented expectations

## Recommended Implementation Order

1. Payment normalization
2. Completion / delivery hardening
3. AR idempotency
4. Compatibility cleanup
5. Regression expansion

## Definition of Done

The sales flow audit can be considered closed when:

- the payment path has one canonical write path
- the SO completion path has one canonical service decision point
- AR creation is idempotent
- the targeted sales tests pass consistently
- the audit report no longer needs to mention legacy compatibility as a risk
