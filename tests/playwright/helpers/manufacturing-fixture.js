import { execSync } from 'node:child_process'
import { mkdirSync, rmdirSync } from 'node:fs'
import { expect } from '@playwright/test'

const PLAYWRIGHT_DB_LOCK_PATH = '/tmp/duta-tunggal-playwright-db.lock'

export const FIXTURE = {
  planNumber: 'PP-PW-MFG-001',
  planName: 'Fixture Production Plan Manufacturing',
  productSku: 'FG-PW-MFG-001',
  productName: 'Fixture Finished Good Manufacturing',
  rawMaterialSku: 'RM-PW-MFG-001',
  warehouseCode: 'GDG-PW-MFG-001',
  issueNumber: 'MI-PW-MFG-001',
}

function withDirectoryLock(lockPath, callback) {
  for (;;) {
    try {
      mkdirSync(lockPath)
      break
    } catch (error) {
      if (error.code !== 'EEXIST') {
        throw error
      }

      Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 100)
    }
  }

  try {
    return callback()
  } finally {
    rmdirSync(lockPath)
  }
}

export function seedManufacturingFixture() {
  execSync('php scripts/setup_manufacturing_playwright_data.php', { stdio: 'inherit' })
}

export function ensureManufacturingFixture() {
  return withDirectoryLock(PLAYWRIGHT_DB_LOCK_PATH, () => {
    seedManufacturingFixture()
  })
}

export async function acquirePlaywrightDbLock() {
  for (;;) {
    try {
      mkdirSync(PLAYWRIGHT_DB_LOCK_PATH)
      return () => rmdirSync(PLAYWRIGHT_DB_LOCK_PATH)
    } catch (error) {
      if (error.code !== 'EEXIST') {
        throw error
      }

      await new Promise((resolve) => setTimeout(resolve, 100))
    }
  }
}

export function querySingleValue(expression) {
  const result = execSync(`php artisan tinker --execute="echo ${expression};"`, {
    encoding: 'utf8',
  }).trim()

  return result
}

export async function selectFixtureProductionPlan(page) {
  const planField = page.locator('.fi-fo-field-wrp').filter({ hasText: 'Rencana Produksi' }).first()
  const planCombobox = planField.getByRole('combobox').first()
  await expect(planCombobox).toBeVisible()
  await planCombobox.click({ force: true })

  const search = page.locator('input.choices__input--cloned[aria-label="Pilih salah satu opsi"]:visible').first()
  await expect(search).toBeVisible()
  await search.fill(FIXTURE.planNumber)

  const option = page.locator('[role="option"]').filter({ hasText: FIXTURE.planNumber }).first()
  await expect(option).toBeVisible()
  await option.click({ force: true })

  await expect(planCombobox).toContainText(FIXTURE.planNumber)
  await page.waitForTimeout(1200)
}

export async function openRowAction(page, row, actionLabel) {
  const directButton = row.getByRole('button', { name: new RegExp(actionLabel, 'i') }).first()
  if (await directButton.count()) {
    const isVisible = await directButton.isVisible().catch(() => false)
    if (isVisible) {
      await directButton.click({ force: true })
      return
    }
  }

  const actionToggle = row.locator('button:visible').last()
  await expect(actionToggle).toBeVisible()
  await actionToggle.click({ force: true })
  await page.waitForTimeout(200)

  const action = page.locator('[role="menuitem"]:visible, button:visible, a:visible').filter({ hasText: new RegExp(actionLabel, 'i') }).last()
  await expect(action).toBeVisible()
  await action.click({ force: true })
}

export async function confirmDialogAction(page, actionLabel) {
  const primarySubmit = page.getByRole('button', {
    name: new RegExp(`^(${actionLabel}|Konfirmasi|Submit|Simpan|Complete)$`, 'i'),
  }).last()

  const becameVisible = await primarySubmit.waitFor({ state: 'visible', timeout: 2000 }).then(
    () => true,
    () => false,
  )

  if (becameVisible) {
    await primarySubmit.click({ force: true })
    await page.waitForTimeout(1500)
  }
}

export async function callMountedTableAction(page, actionName, recordId) {
  await page.evaluate(
    async ({ actionName: name, recordId: record }) => {
      const component = window.Livewire
        .all()
        .find((entry) => typeof entry.mountTableAction === 'function' && typeof entry.callMountedTableAction === 'function')

      if (!component) {
        throw new Error('Livewire table component not found')
      }

      await component.mountTableAction(name, String(record))
      await component.callMountedTableAction()
    },
    { actionName, recordId },
  )
  await page.waitForTimeout(1500)
}