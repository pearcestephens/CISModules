<?php
declare(strict_types=1);

/**
 * CIS Telemetry — System-wide page view logging dispatcher.
 * - Invoked by modules/CIS_TEMPLATE.php once per page render (GET).
 * - Routes to module-specific sinks (e.g., Transfers) and optional security service plugin.
 */

if (!function_exists('cis_log_page_view')) {
    function cis_log_page_view(string $module, string $view, array $ctx = []): void {
        try {
            if (!isset($_SESSION)) session_start();
            $uid   = (int)($_SESSION['userID'] ?? 0);
            $role  = (string)($_SESSION['role'] ?? ($_SESSION['userRole'] ?? 'user'));
            $uri   = $_SERVER['REQUEST_URI']     ?? '';
            $ref   = $_SERVER['HTTP_REFERER']    ?? '';
            $ip    = $_SERVER['REMOTE_ADDR']     ?? '';
            $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sid   = session_id();
            $ctx2  = array_merge([
                'module' => $module,
                'view'   => $view,
                'actor_user_id' => $uid,
                'actor_role'    => $role,
                'uri' => $uri,
                'referer' => $ref,
                'ip' => $ip,
                'user_agent' => $ua,
                'session_id' => $sid,
            ], $ctx);

            // Optional: security service integration if available
            $secFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/services/security/page_view.php';
            if (is_file($secFile)) {
                require_once $secFile;
                if (function_exists('sec_page_view_log')) {
                    try { sec_page_view_log($ctx2); } catch (\Throwable $e) { /* soft-fail */ }
                }
            }

            // Module-specific sinks
            if (strpos($module, 'transfers') === 0) {
                // Route to Transfers page view logger, if present
                $stxLogger = $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/core/Logger.php';
                if (is_file($stxLogger)) {
                    require_once $stxLogger;
                    if (function_exists('stx_log_page_view')) {
                        $transferId = (int)($_GET['transfer'] ?? 0);
                        stx_log_page_view([
                            'view' => $view,
                            'transfer_id' => $transferId ?: ($ctx2['transfer_id'] ?? null),
                            'actor_user_id' => $uid,
                            'actor_role' => $role,
                        ]);
                    }
                }
            }

            // Purchase Orders module sink (if present)
            if (strpos($module, 'purchase-orders') === 0 || $module === 'purchase-orders') {
                $poLogger = $_SERVER['DOCUMENT_ROOT'] . '/modules/purchase-orders/core/Logger.php';
                if (is_file($poLogger)) {
                    require_once $poLogger;
                    if (function_exists('po_log_page_view')) {
                        po_log_page_view([
                            'view' => $view,
                            'actor_user_id' => $uid,
                            'actor_role' => $role,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[cis_log_page_view] '.$e->getMessage());
        }
    }
}

if (!function_exists('cis_log_page_perf')) {
    /**
     * Log page performance (server-side render time, memory) via security service and module sinks.
     */
    function cis_log_page_perf(string $module, string $view, int $ms, array $ctx = []): void {
        try {
            $payload = array_merge([
                'module' => $module,
                'view' => $view,
                'ms' => $ms,
                'peak_memory' => memory_get_peak_usage(true),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ], $ctx);
            // Security service hook (optional)
            $secFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/services/security/page_perf.php';
            if (is_file($secFile)) {
                require_once $secFile;
                if (function_exists('sec_page_perf_log')) {
                    try { sec_page_perf_log($payload); } catch (\Throwable $e) { /* soft-fail */ }
                }
            }
            // Module-specific sinks
            if (strpos($module, 'transfers') === 0) {
                $stxLogger = $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/core/Logger.php';
                if (is_file($stxLogger)) {
                    require_once $stxLogger;
                    if (function_exists('stx_log_page_perf')) {
                        $transferId = (int)($_GET['transfer'] ?? 0);
                        stx_log_page_perf([
                            'view' => $view,
                            'ms' => $ms,
                            'transfer_id' => $transferId ?: ($ctx['transfer_id'] ?? null),
                        ]);
                    }
                }
            }
            if (strpos($module, 'purchase-orders') === 0 || $module === 'purchase-orders') {
                $poLogger = $_SERVER['DOCUMENT_ROOT'] . '/modules/purchase-orders/core/Logger.php';
                if (is_file($poLogger)) {
                    require_once $poLogger;
                    if (function_exists('po_log_page_perf')) {
                        po_log_page_perf([
                            'view' => $view,
                            'ms' => $ms,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[cis_log_page_perf] '.$e->getMessage());
        }
    }
}

if (!function_exists('cis_profiler_start')) {
    /**
     * Start page profiling and register shutdown hook to log duration.
     */
    function cis_profiler_start(string $module, string $view, array $ctx = []): void {
        try {
            $GLOBALS['__cis_prof_start'] = microtime(true);
            $GLOBALS['__cis_prof_mod'] = $module;
            $GLOBALS['__cis_prof_view'] = $view;
            $GLOBALS['__cis_prof_ctx'] = $ctx;
            register_shutdown_function(static function () {
                try {
                    $start = (float)($GLOBALS['__cis_prof_start'] ?? microtime(true));
                    $ms = (int)round((microtime(true) - $start) * 1000);
                    $m = (string)($GLOBALS['__cis_prof_mod'] ?? '');
                    $v = (string)($GLOBALS['__cis_prof_view'] ?? '');
                    $c = (array)($GLOBALS['__cis_prof_ctx'] ?? []);
                    if ($m !== '' && $v !== '' && function_exists('cis_log_page_perf')) {
                        cis_log_page_perf($m, $v, $ms, $c);
                    }
                } catch (\Throwable $e) {
                    error_log('[cis_profiler_shutdown] '.$e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            error_log('[cis_profiler_start] '.$e->getMessage());
        }
    }
}
