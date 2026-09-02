import React from 'react';
import { createRoot } from 'react-dom/client';
import { SaleOrderApp } from './SaleOrderApp';

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('sale-order-next-app');
  if (container) {
    const recordIdAttr = container.getAttribute('data-record-id');
    const recordId = recordIdAttr ? parseInt(recordIdAttr, 10) : undefined;

    const quotationIdAttr = container.getAttribute('data-quotation-id');
    const quotationId = quotationIdAttr ? parseInt(quotationIdAttr, 10) : undefined;

    const root = createRoot(container);
    root.render(
      <React.StrictMode>
        <SaleOrderApp recordId={recordId} initialQuotationId={quotationId} />
      </React.StrictMode>
    );
  }
});
