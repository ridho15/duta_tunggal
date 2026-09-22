import React from 'react';
import { createRoot } from 'react-dom/client';
import { PurchaseOrderApp } from './PurchaseOrderApp';

declare global {
  interface Window {
    __PURCHASE_ORDER_EDIT_ID__?: number;
  }
}

export function mountPurchaseOrderApp() {
  const container = document.getElementById('purchase-order-next-app');
  if (container && !(container as any)._reactRoot) {
    const editIdAttr = container.getAttribute('data-edit-id');
    const editId = editIdAttr ? parseInt(editIdAttr, 10) : window.__PURCHASE_ORDER_EDIT_ID__;

    const root = createRoot(container);
    (container as any)._reactRoot = root;
    root.render(
      <React.StrictMode>
        <PurchaseOrderApp editId={editId} />
      </React.StrictMode>
    );
  }
}

// Mount on initial load and on Livewire / Turbolinks / SPA page navigations
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountPurchaseOrderApp);
  } else {
    mountPurchaseOrderApp();
  }

  // Support for Livewire / Filament SPA navigation
  document.addEventListener('livewire:navigated', mountPurchaseOrderApp);
  document.addEventListener('livewire:load', mountPurchaseOrderApp);
}

export { PurchaseOrderApp };
