import forms from '@tailwindcss/forms'

// Identitas PROCURA: petrol "harbor" (aksi utama) + oranye "pelampung" (perhatian) di atas tinta biru-laut.
// Palet `emerald` sengaja dialiaskan ke `harbor` supaya halaman bawaan (login, pengguna, profil) ikut bertema.
const harbor = {
    50: '#eff8fa', 100: '#d6edf1', 200: '#aedbe3', 300: '#7cc3d0', 400: '#45a4b6',
    500: '#268a9e', 600: '#1a6f83', 700: '#185a6b', 800: '#184a58', 900: '#173f4b', 950: '#0a262f',
}

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{vue,js}'],
    theme: {
        extend: {
            colors: {
                harbor,
                emerald: harbor,
                ink: { 950: '#07111b', 900: '#0b1622', 800: '#122130', 700: '#1b3044', 600: '#2a4560' },
                paper: '#f1f4f6',
            },
            fontFamily: {
                sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
            },
        },
    },
    plugins: [forms],
}
