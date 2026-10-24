// Minimal Tailwind configuration to ensure content paths include Blade and Livewire
// Project uses ESM (see package.json "type": "module") so export default is used.
import animate from 'tailwindcss-animate';
import plugin from 'tailwindcss/plugin';

export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,vue,ts,jsx,tsx}',
        './resources/css/**/*.css',
        './app/Http/Livewire/**/*.php',
        './app/Livewire/**/*.php',
        './app/Filament/**/*.php',
        './vendor/filament/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    safelist: [
        {
            pattern: /^(bg|text|border|from|to|via)-primary-(50|100|200|300|400|500|600|700|800|900|950)$/,
        },
        {
            pattern: /^(bg|text|border|from|to|via)-primary-(50|100|200|300|400|500|600|700|800|900|950)\/(0|5|10|20|25|30|40|50|60|70|75|80|90|95|100)$/,
        },
    ],
    theme: {
        extend: {
            // Nama skala Tailwind v4 yang sudah dipakai di banyak view Blade; proyek ini memakai Tailwind v3,
            // jadi didefinisikan di sini (nilainya sama dengan default v4) agar kelasnya benar-benar terbentuk.
            boxShadow: {
                '2xs': '0 1px rgb(0 0 0 / 0.05)',
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
            backdropBlur: {
                xs: '4px',
            },
            spacing: {
                '0.2': '0.05rem',
                '4.5': '1.125rem',
            },
            borderWidth: {
                3: '3px',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
            },
            colors: {
                surface: '#f6f7f8',
                brand: {
                    50: '#f0fdf4',
                    100: '#ecfdf3',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                    950: '#052e16',
                },
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                    950: '#172554',
                },
            },
        },
    },
    plugins: [
        // animate-in / fade-in / zoom-in / slide-in-from-* (dipakai modal & panel; mengikuti konvensi shadcn/ui)
        animate,
        // Sembunyikan scrollbar pada bilah tab/filter yang bisa di-scroll horizontal (tetap bisa di-scroll)
        plugin(function ({ addUtilities }) {
            addUtilities({
                '.scrollbar-none, .no-scrollbar, .scrollbar-hide': {
                    '-ms-overflow-style': 'none',
                    'scrollbar-width': 'none',
                },
                '.scrollbar-none::-webkit-scrollbar, .no-scrollbar::-webkit-scrollbar, .scrollbar-hide::-webkit-scrollbar': {
                    display: 'none',
                },
            });
        }),
    ],
};
