<?php
/**
 * Purchase Orders AJAX Handler (v1)
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 */

declare(strict_types=1);
require_once __DIR__ . '/tools.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        po_jresp(false, ['code' => 'method_not_allowed', 'message' => 'POST required'], 405);
    }
    $uid = po_require_login();
    po_verify_csrf();

    $action = $_POST['action'] ?? null;
    if (!$action) po_jresp(false, ['code' => 'bad_request', 'message' => 'action required'], 400);

    $map = [
        'po.get_po'            => __DIR__ . '/actions/get_po.php',
        'po.search_products'   => __DIR__ . '/actions/search_products.php',
        'po.save_progress'     => __DIR__ . '/actions/save_progress.php',
        'po.undo_item'         => __DIR__ . '/actions/undo_item.php',
        'po.submit_partial'    => __DIR__ . '/actions/submit_partial.php',
        'po.submit_final'      => __DIR__ . '/actions/submit_final.php',
        'po.update_live_stock' => __DIR__ . '/actions/update_live_stock.php',
        'po.unlock'            => __DIR__ . '/actions/unlock.php',
        'po.extend_lock'       => __DIR__ . '/actions/extend_lock.php',
        'po.release_lock'      => __DIR__ . '/actions/release_lock.php',
        'po.upload_evidence'   => __DIR__ . '/actions/upload_evidence.php',
        'po.list_evidence'     => __DIR__ . '/actions/list_evidence.php',
        'po.issue_upload_qr'   => __DIR__ . '/actions/issue_upload_qr.php',
        'po.assign_evidence'   => __DIR__ . '/actions/assign_evidence.php',
        // Admin endpoints
        'admin.list_receipts'  => __DIR__ . '/actions/admin/list_receipts.php',
        'admin.list_events'    => __DIR__ . '/actions/admin/list_events.php',
    'admin.list_inventory_requests' => __DIR__ . '/actions/admin/list_inventory_requests.php',
    'admin.retry_request'  => __DIR__ . '/actions/admin/retry_request.php',
    'admin.force_resend'   => __DIR__ . '/actions/admin/force_resend.php',
    ];

    if (!isset($map[$action]) || !file_exists($map[$action])) {
        po_jresp(false, ['code' => 'unknown_action', 'message' => 'Unknown action: ' . $action], 404);
    }

    $GLOBALS['__po_ctx'] = ['uid'=>$uid, 'request_id'=>$__PO_REQ_ID];
    require $map[$action];
} catch (Throwable $e) {
    error_log('[purchase-orders.ajax]['.$__PO_REQ_ID.'] '.$e->getMessage());
    po_jresp(false, ['code' => 'internal_error', 'message' => 'Unexpected error'], 500);
}
