<?php

declare(strict_types=1);

/**
 * The single source of truth for the installed Sukli codebase version.
 * Nothing else in the app should hardcode a version string — read it via
 * the app_version() / app_version_info() helpers (app/Core/helpers.php).
 *
 * Bump 'version' here on every release. This file ships as part of the
 * codebase (unlike config/installed.php, which the installer generates
 * per-deployment) — every installation on a given release has the same
 * version.php, which is what makes it a meaningful update signal later.
 */
return [
    'version' => '1.0.0',
    'name' => 'Sukli Core',
];
