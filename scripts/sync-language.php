<?php
/**
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2026 The PHP Group                                |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 3.01 of the PHP license,      |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available through the world-wide-web at the following url:           |
 * | https://www.php.net/license/3_01.txt.                                |
 * | If you did not receive a copy of the PHP license and are unable to   |
 * | obtain it through the world-wide-web, please send a note to          |
 * | license@php.net, so we can mail you a copy immediately.              |
 * +----------------------------------------------------------------------+
 * | Authors: Jordi Kroon <jordikroon@php.net>                            |
 * +----------------------------------------------------------------------+
 */

/**
 * The extension documentation refers to types, classes, interfaces,
 * exceptions and constants that are documented in php/doc-en, not here.
 * language/ holds one summary page per such item, carrying the same xml:id
 * as doc-en so that PhD resolves the references locally, the synopsis block
 * copied verbatim from doc-en, a hand-written one-line description, and a
 * link to the full page in the core manual.
 *
 * Inputs
 *   language/manifest.json   what to mirror and the hand-written summaries
 *   <doc-en>                 a checkout of php/doc-en (../doc-en or ../en)
 *
 * Outputs
 *   language/**\/*.xml             regenerated; files not produced are deleted
 *   entities/entities.manual.ent   missing url.php.manual.* entities added
 *   language/sync.lock             doc-en commit the pages were last synced from
 *
 * Usage
 *   php scripts/sync-language.php [--doc-en=PATH] [--check] [--help]
 *   --check   write nothing; report what would change and exit 1 if anything
 *
 * Exit status: 0 on success (or in sync with --check), 1 on any error or
 * when --check found pending changes.
 */

declare(strict_types=1);

use DocExtensions\SyncLanguage\Cli\Application;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    $prefix = 'DocExtensions\\SyncLanguage\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/sync-language/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

exit(Application::withArguments($argv ?? [], dirname(__DIR__))->run());
