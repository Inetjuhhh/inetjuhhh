import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Anthracite surfaces, from page background up to raised elements
                night: {
                    900: '#16191C',
                    800: '#1E2226',
                    700: '#272C31',
                    600: '#323840',
                    500: '#434A53',
                },
                // Warm off-white text, easier on the eyes than pure white
                cream: {
                    DEFAULT: '#ECE6DC',
                    muted: '#A9A398',
                },
                // Teal, lightened to stay readable on dark backgrounds
                brand: {
                    DEFAULT: '#5BB5AA',
                    dark: '#3E8F86',
                },
                // Terracotta for highlights and categories
                accent: '#E58B63',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            boxShadow: {
                soft: '0 10px 30px -12px rgba(0, 0, 0, 0.6)',
                lift: '0 24px 48px -16px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(91, 181, 170, 0.25)',
            },
            typography: ({ theme }) => ({
                night: {
                    css: {
                        '--tw-prose-body': theme('colors.cream.DEFAULT'),
                        '--tw-prose-headings': theme('colors.cream.DEFAULT'),
                        '--tw-prose-lead': theme('colors.cream.muted'),
                        '--tw-prose-links': theme('colors.brand.DEFAULT'),
                        '--tw-prose-bold': '#FFFFFF',
                        '--tw-prose-counters': theme('colors.accent'),
                        '--tw-prose-bullets': theme('colors.accent'),
                        '--tw-prose-hr': theme('colors.night.500'),
                        '--tw-prose-quotes': theme('colors.cream.DEFAULT'),
                        '--tw-prose-quote-borders': theme('colors.accent'),
                        '--tw-prose-captions': theme('colors.cream.muted'),
                        '--tw-prose-code': theme('colors.cream.DEFAULT'),
                        '--tw-prose-pre-code': theme('colors.cream.DEFAULT'),
                        '--tw-prose-pre-bg': theme('colors.night.900'),
                        '--tw-prose-th-borders': theme('colors.night.500'),
                        '--tw-prose-td-borders': theme('colors.night.600'),
                    },
                },
            }),
        },
    },

    plugins: [forms, typography],
};
