<?php

return [
    'themes' => [
        'light' => ['label' => 'Light', 'preview' => ['canvas' => '#F3F4F6', 'nav' => '#20252A', 'surface' => '#FFFFFF', 'line' => '#D6DADF']],
        'dark' => ['label' => 'Dark', 'preview' => ['canvas' => '#141618', 'nav' => '#101214', 'surface' => '#202326', 'line' => '#3C4145']],
        'midnight' => ['label' => 'Midnight', 'preview' => ['canvas' => '#131219', 'nav' => '#0E0D13', 'surface' => '#211F28', 'line' => '#45434F']],
        'ocean' => ['label' => 'Ocean', 'preview' => ['canvas' => '#10181B', 'nav' => '#0C1214', 'surface' => '#1D292D', 'line' => '#3E4B4F']],
        'forest' => ['label' => 'Forest', 'preview' => ['canvas' => '#121815', 'nav' => '#0D120F', 'surface' => '#202923', 'line' => '#414B44']],
    ],
    'accents' => [
        'blue' => ['label' => 'Blue', 'swatch' => '#2563EB', 'light' => '#1D4ED8', 'light_hover' => '#1E40AF', 'dark' => '#93C5FD', 'dark_hover' => '#BFDBFE', 'button' => '#1D4ED8', 'button_hover' => '#1E40AF', 'gradient' => 'soft'],
        'teal' => ['label' => 'Teal', 'swatch' => '#0F766E', 'light' => '#0F766E', 'light_hover' => '#115E59', 'dark' => '#5EEAD4', 'dark_hover' => '#99F6E4', 'button' => '#0F766E', 'button_hover' => '#115E59', 'gradient' => 'flow'],
        'violet' => ['label' => 'Violet', 'swatch' => '#6D28D9', 'light' => '#6D28D9', 'light_hover' => '#5B21B6', 'dark' => '#C4B5FD', 'dark_hover' => '#DDD6FE', 'button' => '#6D28D9', 'button_hover' => '#5B21B6', 'gradient' => 'sweep'],
        'green' => ['label' => 'Green', 'swatch' => '#15803D', 'light' => '#15803D', 'light_hover' => '#166534', 'dark' => '#86EFAC', 'dark_hover' => '#BBF7D0', 'button' => '#15803D', 'button_hover' => '#166534', 'gradient' => 'soft'],
        'rose' => ['label' => 'Rose', 'swatch' => '#BE123C', 'light' => '#BE123C', 'light_hover' => '#9F1239', 'dark' => '#FDA4AF', 'dark_hover' => '#FECDD3', 'button' => '#BE123C', 'button_hover' => '#9F1239', 'gradient' => 'flow'],
        'amber' => ['label' => 'Amber', 'swatch' => '#B45309', 'light' => '#92400E', 'light_hover' => '#78350F', 'dark' => '#FCD34D', 'dark_hover' => '#FDE68A', 'button' => '#92400E', 'button_hover' => '#78350F', 'gradient' => 'sweep'],
        'cyan' => ['label' => 'Cyan', 'swatch' => '#0E7490', 'light' => '#0E7490', 'light_hover' => '#155E75', 'dark' => '#67E8F9', 'dark_hover' => '#A5F3FC', 'button' => '#0E7490', 'button_hover' => '#155E75', 'gradient' => 'soft'],
        'indigo' => ['label' => 'Indigo', 'swatch' => '#4338CA', 'light' => '#4338CA', 'light_hover' => '#3730A3', 'dark' => '#A5B4FC', 'dark_hover' => '#C7D2FE', 'button' => '#4338CA', 'button_hover' => '#3730A3', 'gradient' => 'flow'],
        'orange' => ['label' => 'Orange', 'swatch' => '#C2410C', 'light' => '#C2410C', 'light_hover' => '#9A3412', 'dark' => '#FDBA74', 'dark_hover' => '#FED7AA', 'button' => '#C2410C', 'button_hover' => '#9A3412', 'gradient' => 'sweep'],
        'slate' => ['label' => 'Slate', 'swatch' => '#475569', 'light' => '#334155', 'light_hover' => '#1E293B', 'dark' => '#CBD5E1', 'dark_hover' => '#E2E8F0', 'button' => '#334155', 'button_hover' => '#1E293B', 'gradient' => 'soft'],
    ],
    'gradients' => [
        'soft' => 'linear-gradient(110deg, var(--button), var(--button-hover))',
        'flow' => 'linear-gradient(110deg, var(--button-hover), var(--button))',
        'sweep' => 'linear-gradient(110deg, var(--button), var(--button-hover), var(--button))',
    ],
    'density' => [
        'comfortable' => 'Comfortable',
        'compact' => 'Compact',
    ],
    'sidebar' => [
        'standard' => 'Standard',
        'wide' => 'Wide',
    ],
    'defaults' => [
        'theme' => 'light',
        'accent' => 'blue',
        'density' => 'comfortable',
        'sidebar' => 'standard',
        'gradients' => false,
    ],
];
