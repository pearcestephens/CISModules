<?php

declare(strict_types=1);

/**
 * /modules/transfers/stock/ajax/handler.php
 * JSON-only endpoint for pack workflow.
 *
 * Actions:
 *   - calculate_ship_units
 *   - validate_parcel_plan
 *   - generate_label           (MVP or real courier; auto-attach fallback)
 *   - save_pack
 *   - list_items               (grid loader)
 *   - get_parcels              (post-label readback)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
require_once dirname(__DIR__, 3) . '/core/error.php';
require_once dirname(__DIR__, 3) . '/core/security.php';
require_once dirname(__DIR__, 3) . '/core/csrf.php';
require_once dirname(__DIR__, 3) . '/core/middleware/kernel.php';   // ← add this
require_once dirname(__DIR__) . '/lib/PackHelper.php';


header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$start = microtime(true);

/** Basic JSON responder */
function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read JSON with safe fallback */
function read_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Minimal auth: CSRF for browser; optional API-key for CLI tests */
function gate_request(): void
{
    $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $bypassKey    = getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123';

    // If API key matches, skip CSRF (for controlled CLI/tests only).
    if ($apiKeyHeader && hash_equals($bypassKey, $apiKeyHeader)) {
        return;
    }

    // Otherwise enforce CSRF for browser-originated POSTs.
    if (!cis_csrf_or_json_400()) {
        // cis_csrf_or_json_400() already emitted JSON 400
        exit;
    }
}



try {

    // Build middleware pipeline (kernel.php already required above)
    $pipe = mw_pipeline([
        mw_trace(),
        mw_security_headers(),
        mw_json_or_form_normalizer(),
        mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
        mw_validate_content_type(['application/json', 'multipart/form-data', 'application/x-www-form-urlencoded']),
        mw_content_length_limit(1024 * 1024), // 1MB
        mw_rate_limit('transfers.stock.handler', 120, 60),
        // mw_enforce_auth(), // enable when ready
        // mw_idempotency(),  // enable later for create actions
    ]);

    $ctx = $pipe([]);              // run middleware
    $in  = $ctx['input'];          // normalized input (JSON or FormData)
    $hdr = $ctx['headers'];        // normalized headers (lowercase)
    $perf = [];
    $helper = new \CIS\Transfers\Stock\PackHelper();

    $action = trim((string)($in['action'] ?? ''));
    if ($action === '') {
        json_out(['ok' => false, 'error' => 'Missing action'], 400);
    }


    switch ($action) {
        /**
         * calculate_ship_units
         * Input: { product_id:int, qty:int }
         * Output: { ok:true, ship_units:int, unit_g:int, weight_g:int }
         */
        case 'calculate_ship_units': {
                $productId = (int)($in['product_id'] ?? 0);
                $qty       = (int)($in['qty'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    json_out(['ok' => false, 'error' => 'product_id and qty required'], 400);
                }
                $res = $helper->calculateShipUnits($productId, $qty);
                json_out(['ok' => true] + $res);
            }

        /**
             * validate_parcel_plan
             * Input: { transfer_id:int, parcel_plan:{ parcels:[{weight_g:int, items:[{item_id?|product_id, qty:int}]}] } }
             * Output: { ok:true, attachable:[...], unknown:[...], notes:{...} }
             */
        case 'validate_parcel_plan': {
                $transferId = (int)($in['transfer_id'] ?? 0);
                $plan       = $in['parcel_plan'] ?? null;
                if ($transferId <= 0 || !is_array($plan)) {
                    json_out(['ok' => false, 'error' => 'transfer_id and parcel_plan required'], 400);
                }
                $out = $helper->validateParcelPlan($transferId, $plan);
                json_out(['ok' => true] + $out);
            }

        /**
             * generate_label
             * Input: {
             *   transfer_id:int,
             *   carrier?:string,
             *   parcel_plan?:{ parcels:[{weight_g:int, items?:[{item_id?|product_id, qty:int}]}] }
             * }
             * Behavior:
             *   - If items omitted or empty for any parcel, server auto-attaches all transfer_items using calculated ship units.
             * Output: { ok:true, shipment_id:int, parcels:[...], skipped:[...] }
             */
        case 'generate_label': {
                $transferId = (int)($in['transfer_id'] ?? 0);
                if ($transferId <= 0) {
                    json_out(['ok' => false, 'error' => 'transfer_id required'], 400);
                }
                $carrier = trim((string)($in['carrier'] ?? 'MVP'));
                $planRaw = $in['parcel_plan'] ?? ['parcels' => []];

                // Auto-attach fallback
                $plan = $helper->autoAttachIfEmpty($transferId, is_array($planRaw) ? $planRaw : ['parcels' => []]);

                $useReal = (getenv('COURIERS_ENABLED') === '1');
                $res = $useReal
                    ? $helper->generateLabel($transferId, $carrier, $plan)
                    : $helper->generateLabelMvp($transferId, $carrier, $plan);

                // Persist response for same Idempotency-Key (only if mw_idempotency is enabled)
                if (!empty($hdr['idempotency-key'])) {
                    mw_idem_store($ctx, $res);
                }

                json_out($res['ok'] ? $res : ['ok' => false] + $res, $res['ok'] ? 200 : 422);
            }


        /**
             * save_pack
             * Input: { transfer_id:int, notes?:string }
             */
        case 'save_pack': {
                $transferId = (int)($in['transfer_id'] ?? 0);
                $notes      = (string)($in['notes'] ?? '');
                if ($transferId <= 0) {
                    json_out(['ok' => false, 'error' => 'transfer_id required'], 400);
                }
                $helper->addPackNote($transferId, $notes);
                json_out(['ok' => true]);
            }

        /**
             * list_items
             * Input: { transfer_id:int }
             * Output: { ok:true, items:[{id, product_id, sku, name, requested_qty, unit_g, suggested_ship_units}] }
             */
        case 'list_items': {
                $transferId = (int)($in['transfer_id'] ?? 0);
                if ($transferId <= 0) {
                    json_out(['ok' => false, 'error' => 'transfer_id required'], 400);
                }
                $items = $helper->listItems($transferId);
                json_out(['ok' => true, 'items' => $items]);
            }

        /**
             * get_parcels
             * Input: { transfer_id:int }
             * Output: { ok:true, shipment_id:int|null, parcels:[{id, box_number, weight_kg, items_count}] }
             */
        case 'get_parcels': {
                $transferId = (int)($in['transfer_id'] ?? 0);
                if ($transferId <= 0) {
                    json_out(['ok' => false, 'error' => 'transfer_id required'], 400);
                }
                $out = $helper->getParcels($transferId);
                json_out(['ok' => true] + $out);
            }

        default:
            json_out(['ok' => false, 'error' => 'Unknown action'], 404);
    }
} catch (\Throwable $e) {
    // /core/error.php should already capture; we still send a clean JSON
    json_out([
        'ok'    => false,
        'error' => 'Unhandled exception',
        'hint'  => 'See server logs for details.',
    ], 500);
} finally {
    // Optional: lightweight perf sample
    if (function_exists('cis_profile_flush')) {
        cis_profile_flush(['endpoint' => 'transfers.stock.handler', 'ms' => (int)((microtime(true) - $start) * 1000)]);
    }
}
