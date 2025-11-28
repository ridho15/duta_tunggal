// Minimal Tailwind configuration to ensure content paths include Blade and Livewire
// Project uses ESM (see package.json "type": "module") so export default is used.
export default {
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
            colors: {
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
    plugins: [],
};
