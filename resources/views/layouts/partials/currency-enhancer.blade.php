{{--
    Currency / Rupiah Input Enhancer & Helpers
    Menyediakan:
    - window.formatRupiah(number, withPrefix = true)
    - window.parseRupiah(string)
    - window.setupCurrencyInput(visibleInput, hiddenInputOrCallback)
    - Form submit auto-unmask untuk semua <input data-currency>
    - Alpine.js directive: x-rupiah (bila memakai Alpine)
--}}
<script>
(function() {
    /**
     * Format angka ke format Rupiah (pemisah ribuan titik).
     * Contoh: 150000000 -> "150.000.000" atau "Rp 150.000.000"
     */
    window.formatRupiah = function(value, withPrefix = true) {
        if (value === null || value === undefined || value === '' || isNaN(Number(value))) {
            return withPrefix ? 'Rp 0' : '0';
        }

        const num = Number(value);
        const isNegative = num < 0;
        const absVal = Math.abs(num);

        // Pisahkan bagian integer dan pecahan (jika ada desimal)
        const parts = absVal.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        let formatted = parts.join(',');
        if (isNegative) formatted = '-' + formatted;

        return withPrefix ? 'Rp ' + formatted : formatted;
    };

    /**
     * Parse string Rupiah menjadi angka float/integer murni.
     * Contoh: "Rp 150.000.000" -> 150000000
     * Contoh: "1.500,50" -> 1500.5
     */
    window.parseRupiah = function(str) {
        if (str === null || str === undefined) return 0;
        if (typeof str === 'number') return str;

        const stringVal = String(str).trim();
        if (!stringVal) return 0;

        // Ambil tanda negatif bila ada
        const isNegative = stringVal.startsWith('-');

        // Buang Rp dan spasi
        let clean = stringVal.replace(/[^0-9,\.]/g, '');

        // Standardisasi format Indonesia (titik = ribuan, koma = desimal)
        // Hapus semua titik ribuan
        clean = clean.replace(/\./g, '');
        // Ganti koma pecahan menjadi titik
        clean = clean.replace(/,/g, '.');

        let num = parseFloat(clean);
        if (isNaN(num)) num = 0;
        return isNegative ? -num : num;
    };

    /**
     * Memasang mask Rupiah interaktif pada elemen input text.
     * @param {HTMLInputElement} inputEl - Input visual bertipe text
     * @param {HTMLInputElement|Function} target - Hidden input atau callback(rawValue)
     */
    window.setupCurrencyInput = function(inputEl, target) {
        if (!inputEl || inputEl.dataset.currencyInitialized) return;
        inputEl.dataset.currencyInitialized = 'true';

        function updateTarget(rawVal) {
            if (typeof target === 'function') {
                target(rawVal);
            } else if (target && target.tagName === 'INPUT') {
                target.value = rawVal;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function formatCurrentValue() {
            const raw = window.parseRupiah(inputEl.value);
            inputEl.value = raw > 0 ? window.formatRupiah(raw, false) : (inputEl.value.trim() === '' ? '' : '0');
            updateTarget(raw);
        }

        // Event saat mengetik
        inputEl.addEventListener('input', function() {
            // Simpan posisi cursor
            const raw = window.parseRupiah(inputEl.value);
            updateTarget(raw);
        });

        // Event saat kehilangan fokus (blur): format rapi
        inputEl.addEventListener('blur', function() {
            formatCurrentValue();
        });

        // Event saat fokus: seleksi semua teks untuk kemudahan edit
        inputEl.addEventListener('focus', function() {
            if (inputEl.value === '0') {
                inputEl.value = '';
            }
        });

        // Inisialisasi awal
        if (inputEl.value) {
            formatCurrentValue();
        }
    };

    /**
     * Auto-init semua input ber-atribut `data-currency` di DOM
     */
    window.initAllCurrencyInputs = function(root = document) {
        root.querySelectorAll('input[data-currency]').forEach(function(input) {
            const hiddenName = input.dataset.targetHidden || '';
            let hiddenEl = null;
            if (hiddenName) {
                hiddenEl = input.form ? input.form.querySelector(`input[name="${hiddenName}"]`) : document.querySelector(`input[name="${hiddenName}"]`);
            }
            window.setupCurrencyInput(input, hiddenEl);
        });
    };

    // Global listener: Sebelum form disubmit, pastikan input dengan data-currency mengonversi nilainya
    // jika input tersebut TIDAK memiliki hidden input pendamping
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.querySelectorAll) return;

        form.querySelectorAll('input[data-currency]').forEach(function(input) {
            // Jika input ini adalah form submit payload langsung (tanpa hidden target)
            if (!input.dataset.targetHidden && input.name) {
                input.value = window.parseRupiah(input.value);
            }
        });
    }, true);

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.initAllCurrencyInputs());
    } else {
        window.initAllCurrencyInputs();
    }

    // Integrasi dengan Alpine.js
    document.addEventListener('alpine:init', function() {
        if (window.Alpine) {
            // Directive x-rupiah
            // Penggunaan: <input type="text" x-rupiah="item.unitPrice">
            window.Alpine.directive('rupiah', (el, { expression }, { effect, evaluateLater }) => {
                const getModel = evaluateLater(expression);
                const setModel = evaluateLater(`${expression} = $event.detail`);

                effect(() => {
                    getModel(val => {
                        if (document.activeElement !== el) {
                            el.value = (val !== null && val !== undefined && val !== '' && Number(val) !== 0)
                                ? window.formatRupiah(val, false)
                                : (val === 0 ? '0' : '');
                        }
                    });
                });

                el.addEventListener('input', () => {
                    const raw = window.parseRupiah(el.value);
                    el.dispatchEvent(new CustomEvent('rupiah-sync', { detail: raw }));
                    setModel();
                });

                el.addEventListener('blur', () => {
                    getModel(val => {
                        el.value = (val !== null && val !== undefined && val !== '' && Number(val) !== 0)
                            ? window.formatRupiah(val, false)
                            : (val === 0 ? '0' : '');
                    });
                });

                el.addEventListener('focus', () => {
                    if (el.value === '0') el.value = '';
                });
            });
        }
    });
})();
</script>
