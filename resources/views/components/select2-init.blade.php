@props(['selector' => "select[name='coa_id']"])

<script>
    (function () {
        const selector = {!! json_encode($selector) !!};

        console.log('[select2-init] Starting initialization for selector:', selector);

    let isProgrammaticUpdate = false;

    // Load a script dynamically and return a Promise
        function loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) {
                    return resolve();
                }
                const s = document.createElement('script');
                s.src = src;
                s.async = true;
                s.onload = () => resolve();
                s.onerror = () => reject(new Error('Failed to load ' + src));
                document.head.appendChild(s);
            });
        }

        function loadCss(href) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`link[href="${href}"]`)) {
                    return resolve();
                }
                const l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = href;
                l.onload = () => resolve();
                l.onerror = () => reject(new Error('Failed to load ' + href));
                document.head.appendChild(l);
            });
        }

        async function ensureSelect2Loaded() {
            // If Select2 already present on a working jQuery, do nothing.
            if (window.jQuery && typeof window.jQuery === 'function' && window.jQuery.fn && window.jQuery.fn.select2) {
                return;
            }

            // If jQuery is missing or not a function (some bundles expose an object), load a proper jQuery first.
            if (!window.jQuery || typeof window.jQuery !== 'function') {
                try {
                    await loadScript('https://code.jquery.com/jquery-3.6.0.min.js');
                } catch (e) {
                    console.error('[select2-init] Failed to load jQuery', e);
                    throw e;
                }

                // ensure globals are set by the loaded script
                if (window.jQuery && typeof window.jQuery === 'object' && typeof window.jQuery.default === 'function') {
                    // Some bundlers expose jQuery as a module default; map it to globals
                    window.jQuery = window.jQuery.default;
                    window.$ = window.jQuery;
                }
                // ensure $ alias is available
                if (window.jQuery && typeof window.jQuery === 'function') {
                    window.$ = window.jQuery;
                }
            }

            // Load Select2 assets (css + js)
            try {
                await loadCss('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
                await loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
            } catch (e) {
                console.error('[select2-init] Failed to load Select2 assets', e);
                throw e;
            }

            // After loading, ensure $ alias
            if (window.jQuery && typeof window.jQuery === 'function') {
                window.$ = window.jQuery;
            }
        }

        function findLivewireComponent(el) {
            if (!el) return null;
            let node = el;
            while (node && node.nodeType === Node.ELEMENT_NODE) {
                const wireId = node.getAttribute && node.getAttribute('wire:id');
                if (wireId) {
                    if (window.Livewire && typeof window.Livewire.find === 'function') {
                        return window.Livewire.find(wireId);
                    }
                    break;
                }
                node = node.parentElement;
            }
            return null;
        }

        // Initialize Select2 on the given selector and re-init after Livewire updates
        async function initSelect2For(selectorString, attempt = 0) {
            console.log('[select2-init] initSelect2For called for:', selectorString, 'attempt:', attempt);

            // Ensure jQuery/Select2 are loaded and that window.jQuery is a callable function
            try {
                await ensureSelect2Loaded();
                console.log('[select2-init] Select2 loaded successfully');
            } catch (e) {
                console.warn('[select2-init] Could not load Select2, falling back to native select.', e);
                return;
            }

            // Defensive: normalize jQuery global if some bundler exposed it under default
            if (window.jQuery && typeof window.jQuery !== 'function' && typeof window.jQuery === 'object' && typeof window.jQuery.default === 'function') {
                window.jQuery = window.jQuery.default;
                window.$ = window.jQuery;
            }

            const jq = (typeof window.jQuery === 'function') ? window.jQuery : (typeof window.$ === 'function' ? window.$ : null);
            if (!jq) {
                console.warn('[select2-init] jQuery not available as a function; skipping select2 init.');
                return;
            }

            const $sel = jq(selectorString);
            if (!$sel || !$sel.length) {
                console.log('[select2-init] Element not found:', selectorString, 'length:', $sel ? $sel.length : 'null');
                // retry a few times in case Livewire hasn't rendered the element yet
                if (attempt < 8) {
                    const wait = 150 * (attempt + 1);
                    setTimeout(() => initSelect2For(selectorString, attempt + 1), wait);
                } else {
                    console.warn('[select2-init] Element not found after retries:', selectorString);
                }
                return;
            }

            console.log('[select2-init] Element found:', selectorString, 'initializing Select2');

            // Destroy existing select2 to avoid duplicates
            if ($sel.data('select2')) {
                try { $sel.select2('destroy'); } catch (e) { /* ignore */ }
            }

            // clear custom marker to allow clean re-initialization after Livewire updates
            if ($sel.data('select2-initialized')) {
                $sel.removeData('select2-initialized');
            }
            
            $sel.select2({
                placeholder: '-- Pilih Akun --',
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 0,
                // use body to avoid clipping inside overflowed containers
                dropdownParent: document.body
            });
            $sel.data('select2-initialized', true);

            console.log('[select2-init] Select2 initialized successfully for:', selectorString);

            // CRITICAL: Sync Select2 changes with Livewire wire:model
            // Use delegated handlers on `document` so handlers survive Livewire DOM replacements.
            try {
                // remove any previous delegated handlers for this selector namespace
                jq(document).off('select2:select.select2-init', selectorString);
                jq(document).off('select2:clear.select2-init', selectorString);
                jq(document).off('change.select2-init', selectorString);

                // delegated Select2 select
                jq(document).on('select2:select.select2-init', selectorString, function (e) {
                    if (isProgrammaticUpdate) {
                        console.log('[select2-init] (delegated) programmatic select ignored');
                        return;
                    }
                    console.log('[select2-init] (delegated) select2:select fired for', selectorString);
                    const el = this;
                    const value = el.value;
                    const modelName = el.getAttribute('wire:model') || 'coa_id';
                    const component = findLivewireComponent(el);
                    if (component && typeof component.set === 'function') {
                        console.log('[select2-init] (delegated) Updating Livewire property:', modelName, '=', value);
                        component.set(modelName, value);
                    }
                    // also dispatch native events as fallback
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });

                // delegated Select2 clear
                jq(document).on('select2:clear.select2-init', selectorString, function (e) {
                    if (isProgrammaticUpdate) {
                        console.log('[select2-init] (delegated) programmatic clear ignored');
                        return;
                    }
                    console.log('[select2-init] (delegated) select2:clear fired for', selectorString);
                    const el = this;
                    const value = el.value;
                    const modelName = el.getAttribute('wire:model') || 'coa_id';
                    const component = findLivewireComponent(el);
                    if (component && typeof component.set === 'function') {
                        console.log('[select2-init] (delegated) Updating Livewire property (clear):', modelName, '=', value);
                        component.set(modelName, value);
                    }
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });

                // delegated native change as an extra fallback
                jq(document).on('change.select2-init', selectorString, function (e) {
                    console.log('[select2-init] (delegated) native change detected for', selectorString, 'value=', this.value);
                });
            } catch (e) {
                console.warn('[select2-init] Failed to attach delegated handlers', e);
            }
        }

        function setSelect2Value(selectorString, value) {
            const jq = (typeof window.jQuery === 'function') ? window.jQuery : (typeof window.$ === 'function' ? window.$ : null);
            if (!jq) return;
            const $sel = jq(selectorString);
            if (!$sel.length) return;
            isProgrammaticUpdate = true;
            $sel.val(value ?? '').trigger('change.select2');
            isProgrammaticUpdate = false;
        }

        // Call init immediately if document already loaded, otherwise wait for DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                console.log('[select2-init] DOMContentLoaded fired, initializing');
                setTimeout(() => initSelect2For(selector), 100); // Small delay to ensure DOM is fully ready
            });
        } else {
            console.log('[select2-init] document already ready, initializing immediately');
            setTimeout(() => initSelect2For(selector), 100); // Small delay to ensure DOM is fully ready
        }

        // Re-init after Livewire updates
        function registerLivewireHooks() {
            if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
                return;
            }

            Livewire.hook('message.processed', (message, component) => {
                console.log('[select2-init] Livewire message processed, re-initializing');
                setTimeout(() => initSelect2For(selector), 50);
            });

            document.addEventListener('filament:page:loaded', () => {
                console.log('[select2-init] Filament page loaded, initializing');
                setTimeout(() => initSelect2For(selector), 100);
            });

            if (typeof Livewire.on === 'function') {
                Livewire.on('set-coa-select', (value) => {
                    console.log('[select2-init] Livewire event set-coa-select received with value:', value);
                    setSelect2Value(selector, value);
                });
            }
        }

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            registerLivewireHooks();
        } else {
            document.addEventListener('livewire:load', () => {
                console.log('[select2-init] Livewire loaded, initializing');
                setTimeout(() => initSelect2For(selector), 50);
                registerLivewireHooks();
            });
        }
    })();
</script>
