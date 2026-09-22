import React from 'react';
import { createRoot } from 'react-dom/client';
import { QuotationApp } from './QuotationApp';

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('quotation-next-app');
  if (container) {
    const recordIdAttr = container.getAttribute('data-record-id');
    const recordId = recordIdAttr ? parseInt(recordIdAttr, 10) : undefined;

    const root = createRoot(container);
    root.render(
      <React.StrictMode>
        <QuotationApp recordId={recordId} />
      </React.StrictMode>
    );
  }
});
