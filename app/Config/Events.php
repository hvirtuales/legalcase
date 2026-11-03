<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

// TEMPORAL (perfilado): con BENCH=1 en el entorno, cada petición deja en
// writable/bench.log cuántas consultas hizo, cuáles se repiten y qué tardó.
if (getenv('BENCH')) {
    Events::on('DBQuery', static function ($query): void {
        $GLOBALS['__benchQ'][] = [(string) $query, (float) $query->getDuration()];
    });

    Events::on('post_system', static function (): void {
        $rows = $GLOBALS['__benchQ'] ?? [];
        $sql  = 0.0;
        $seen = [];
        foreach ($rows as [$q, $d]) {
            $sql += $d;
            $norm = preg_replace('/\d+/', 'N', preg_replace('/\s+/', ' ', $q));
            $seen[$norm] = ($seen[$norm] ?? 0) + 1;
        }
        arsort($seen);

        $out = sprintf(
            "\n### %s  ·  %d consultas  ·  %.0f ms SQL  ·  %.0f ms total\n",
            uri_string() ?: '/',
            count($rows),
            $sql * 1000,
            (microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000
        );
        foreach (array_slice($seen, 0, 12, true) as $q => $n) {
            $out .= sprintf("  %3d×  %s\n", $n, substr($q, 0, 150));
        }

        file_put_contents(WRITEPATH . 'bench.log', $out, FILE_APPEND);
    });
}

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});
