import { test, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const SCREENSHOT_DIR = 'C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/screenshots';

test('Download PDF PO dan Render Visual Layout PDF', async ({ page, request }) => {
  // 1. Download berkas PDF asli dari endpoint
  const [ download ] = await Promise.all([
    page.waitForEvent('download'),
    page.goto('http://127.0.0.1:8009/pembelian/po/1/pdf'),
  ]);

  const pdfPath = path.join(SCREENSHOT_DIR, 'qa_06_po_1_faktur_pajak.pdf');
  await download.saveAs(pdfPath);
  expect(fs.existsSync(pdfPath)).toBeTruthy();

  // 2. Render visual layout dokumen PDF yang sama langsung di browser
  // Mengambil HTML dari server untuk di-render fullpage screenshot
  const res = await request.get('http://127.0.0.1:8009/dev-login');
  // Ambil HTML view PDF via backend script
  const htmlContent = fs.readFileSync('C:/Users/ralam/.gemini/antigravity/brain/fa80b9da-259a-45a7-8f1e-c487a82b1211/scratch/po_preview.html', 'utf-8');
  await page.setContent(htmlContent, { waitUntil: 'networkidle' });
  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, 'qa_06_po_pdf_visual_layout.png'),
    fullPage: true,
  });
});
