import React from 'react';
import { createRoot } from 'react-dom/client';
import { OrderRequestApp } from './OrderRequestApp';
import { FormDependencies, OrderRequestRecord } from './types';

declare global {
  interface Window {
    __ORDER_REQUEST_INITIAL_DATA__?: FormDependencies;
    __ORDER_REQUEST_RECORD__?: OrderRequestRecord;
  }
}

function mountOrderRequestApp() {
  const container = document.getElementById('order-request-next-app');
  if (container && !(container as any)._reactRoot) {
    const initialData = window.__ORDER_REQUEST_INITIAL_DATA__;
    const initialRecord = window.__ORDER_REQUEST_RECORD__;
    const root = createRoot(container);
    (container as any)._reactRoot = root;
    root.render(
      <React.StrictMode>
        <OrderRequestApp initialData={initialData} initialRecord={initialRecord} />
      </React.StrictMode>
    );
  }
}

// Mount on initial load and on Livewire / Turbolinks / SPA page navigations
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountOrderRequestApp);
  } else {
    mountOrderRequestApp();
  }

  // Support for Livewire / Filament SPA navigation
  document.addEventListener('livewire:navigated', mountOrderRequestApp);
  document.addEventListener('livewire:load', mountOrderRequestApp);
}

export default OrderRequestApp;
