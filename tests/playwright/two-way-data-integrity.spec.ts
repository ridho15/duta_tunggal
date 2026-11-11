import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Two-Way Data Integrity & Database Verification Suite', () => {
  let scriptOutput = '';

  test.beforeAll(() => {
    scriptOutput = execSync('php scripts/verify-data-integrity.php').toString();
  });

  // ---------------------------------------------------------------------------
  // 1. DECIMAL PRECISION & MONETARY INTEGRITY
  // ---------------------------------------------------------------------------
  test('INT-01: Decimal precision on invoices - no floating point drift (UNBALANCED_INVOICES: 0)', async () => {
    expect(scriptOutput).toContain('UNBALANCED_INVOICES: 0');
  });

  // ---------------------------------------------------------------------------
  // 2. FOREIGN KEY INTEGRITY
  // ---------------------------------------------------------------------------
  test('INT-02: Relational Foreign Key Integrity - No orphaned items in OR and SO', async () => {
    expect(scriptOutput).toContain('ORPHAN_OR: 0');
    expect(scriptOutput).toContain('ORPHAN_SO: 0');
  });

  // ---------------------------------------------------------------------------
  // 3. FINANCIAL JOURNAL INTEGRITY (DEBIT == CREDIT)
  // ---------------------------------------------------------------------------
  test('INT-03: Double-Entry Bookkeeping - Every journal transaction has Debit == Credit (UNBALANCED_JOURNALS: 0)', async () => {
    expect(scriptOutput).toContain('UNBALANCED_JOURNALS: 0');
  });

  // ---------------------------------------------------------------------------
  // 4. ZERO DATA-LOSS & SOFT DELETES CONSISTENCY
  // ---------------------------------------------------------------------------
  test('INT-04: Zero Data-Loss - Soft-deleted columns exist across core transaction tables', async () => {
    expect(scriptOutput).toContain('SOFT_DELETE_OR: YES');
    expect(scriptOutput).toContain('SOFT_DELETE_SO: YES');
    expect(scriptOutput).toContain('SOFT_DELETE_PO: YES');
  });
});
