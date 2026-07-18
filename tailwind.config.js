import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
                surface: {
                    DEFAULT:  '#f8fafc',
                    50:      '#f8fafc',
                    100:     '#f1f5f9',
                    200:     '#e2e8f0',
                    300:     '#cbd5e1',
                    400:     '#94a3b8',
                    500:     '#64748b',
                    600:     '#475569',
                    700:     '#334155',
                    800:     '#1e293b',
                    900:     '#0f172a',
                    950:     '#020617',
                },
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
            fontFamily: {
                sans:  ['Inter', 'system-ui', 'sans-serif'],
                mono:  ['JetBrains Mono', 'Fira Code', 'monospace'],
            },
            fontSize: {
                'display':  ['30px', { lineHeight: '38px', letterSpacing: '-0.025em', fontWeight: '800' }],
                'h1':       ['24px', { lineHeight: '32px', letterSpacing: '-0.02em',  fontWeight: '700' }],
                'h2':       ['20px', { lineHeight: '28px', letterSpacing: '-0.01em',  fontWeight: '600' }],
                'h3':       ['16px', { lineHeight: '24px', letterSpacing: '-0.01em',  fontWeight: '600' }],
                'body-lg':  ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'body-md':  ['14px', { lineHeight: '20px', fontWeight: '400' }],
                'body-sm':  ['13px', { lineHeight: '18px', fontWeight: '400' }],
                'label-lg': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '600' }],
                'label-md': ['12px', { lineHeight: '16px', letterSpacing: '0.02em', fontWeight: '600' }],
                'label-sm': ['11px', { lineHeight: '14px', letterSpacing: '0.03em', fontWeight: '500' }],
                'code':     ['13px', { lineHeight: '18px', fontWeight: '400' }],
            },
            boxShadow: {
                'card':   '0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06)',
                'card-hover': '0 10px 25px rgba(0,0,0,0.06), 0 4px 10px rgba(0,0,0,0.04)',
                'glow':   '0 0 20px rgba(99,102,241,0.15)',
                'toast':  '0 10px 40px rgba(0,0,0,0.12)',
            },
            animation: {
                'spin-slow':  'spin 2s linear infinite',
                'fade-in':    'fadeIn 0.4s ease-out',
                'fade-up':    'fadeUp 0.4s ease-out',
                'slide-in':   'slideIn 0.3s ease-out',
                'slide-out':  'slideOut 0.3s ease-in forwards',
                'pulse-dot':  'pulseDot 2s ease-in-out infinite',
            },
            keyframes: {
                fadeIn:   { from: { opacity: 0 }, to: { opacity: 1 } },
                fadeUp:   { from: { opacity: 0, transform: 'translateY(8px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
                slideIn:  { from: { opacity: 0, transform: 'translateX(20px)' }, to: { opacity: 1, transform: 'translateX(0)' } },
                slideOut: { from: { opacity: 1, transform: 'translateX(0)' }, to: { opacity: 0, transform: 'translateX(20px)' } },
                pulseDot: { '0%, 100%': { opacity: 1 }, '50%': { opacity: 0.4 } },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/container-queries'),
    ],
};
