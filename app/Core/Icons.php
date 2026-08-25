<?php

declare(strict_types=1);

namespace Sukli\Core;

/**
 * Small inline stroke-icon set so the UI has zero external font/icon
 * dependency (no CDN needed for shared hosting, works offline).
 */
class Icons
{
    private const PATHS = [
        'dashboard' => 'M3 3h7v9H3V3zm11 0h7v5h-7V3zM3 15h7v6H3v-6zm11-3h7v9h-7v-9z',
        'pos' => 'M3 6h18l-1.5 9.5a2 2 0 0 1-2 1.5H6.5a2 2 0 0 1-2-1.5L3 6zm0 0-.7-2.5A1 1 0 0 0 1.3 3H0M8 10h8M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
        'inventory' => 'M3 7l9-4 9 4-9 4-9-4zm0 0v10l9 4 9-4V7M12 11v10',
        'income' => 'M12 2v20M17 6.5c0-2-2-3-5-3s-5 1.2-5 3 2 2.7 5 3 5 1.3 5 3.2-2 3.3-5 3.3-5-1-5-3',
        'expense' => 'M4 19h16M4 19V9l6-5 6 5v10M4 19h16',
        'eload' => 'M13 2 3 14h7l-1 8 11-14h-7l1-6z',
        'gcash' => 'M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm0 5v10m3.5-7.2c0-1.5-1.6-2.3-3.5-2.3S8.5 8.3 8.5 9.8s1.6 2.1 3.5 2.4 3.5 1 3.5 2.5-1.6 2.3-3.5 2.3-3.5-.8-3.5-2.3',
        'utang' => 'M4 4h16v12H8l-4 4V4zM8 9h8M8 12h5',
        'customers' => 'M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10.5 10v-2a4 4 0 0 0-3-3.87M15 2.13A4 4 0 0 1 15 9.87',
        'suppliers' => 'M3 3h13l3 5v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3zm13 0v5h5M8 21v-6h4v6',
        'users' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'reports' => 'M4 20V10m6 10V4m6 16v-7',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8-3a7.9 7.9 0 0 0-.2-1.8l2.1-1.6-2-3.4-2.5 1a8 8 0 0 0-3.1-1.8L14 2h-4l-.3 2.4A8 8 0 0 0 6.6 6.2l-2.5-1-2 3.4 2.1 1.6A7.9 7.9 0 0 0 4 12c0 .6.1 1.2.2 1.8l-2.1 1.6 2 3.4 2.5-1a8 8 0 0 0 3.1 1.8L10 22h4l.3-2.4a8 8 0 0 0 3.1-1.8l2.5 1 2-3.4-2.1-1.6c.1-.6.2-1.2.2-1.8z',
        'audit' => 'M9 2h6l1 3h4v16H4V5h4l1-3zm3 6v6m-3-3h6',
        'bell' => 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
        'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm10 2-4.35-4.35',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'menu' => 'M3 6h18M3 12h18M3 18h18',
        'more' => 'M5 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
        'plus' => 'M12 5v14M5 12h14',
        'edit' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z',
        'trash' => 'M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16z',
        'archive' => 'M21 8v13H3V8M1 3h22v5H1V3zm9 8h4',
        'barcode' => 'M3 5v14M7 5v14M11 5v14h2V5zM17 5v14M21 5v14',
        'alert' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
        'wallet' => 'M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3M3 7v11a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4M3 7l3-4h9M17 13h.01',
        'cash' => 'M2 6h20v12H2V6zm10 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM6 6v12M18 6v12',
        'chevron-right' => 'm9 18 6-6-6-6',
        'x' => 'M18 6 6 18M6 6l12 12',
        'check' => 'M20 6 9 17l-5-5',
        'paperclip' => 'M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l8.57-8.57a4 4 0 0 1 5.66 5.66l-8.58 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48',
    ];

    public static function svg(string $name, int $size = 18): string
    {
        $d = self::PATHS[$name] ?? self::PATHS['more'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="' . $d . '"/></svg>';
    }
}
