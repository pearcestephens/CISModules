<?php
/**
 * Purchase Orders AJAX Tools
 * Provides jresp(), auth + CSRF gates, and DB helper passthroughs.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$__PO_REQ_ID = bin2hex(random_bytes(8));

// Strictly require full application bootstrap (sessions, config, autoloaders)
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// Fallback: only if DB_* not defined, try legacy config locations
if (!defined('DB_HOST')) {
    $fallback1 = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
    $fallback2 = dirname(__FILE__, 4) . '/config.php';
    if (file_exists($fallback1)) {
        require_once $fallback1;
    } elseif (file_exists($fallback2)) {
        require_once $fallback2;
    }
}

function po_jresp($ok, $payload = [], $code = 200){
    global $__PO_REQ_ID; http_response_code($code);
    $body = ['success'=>(bool)$ok,'request_id'=>$__PO_REQ_ID];
    if ($ok) { $body['data'] = $payload; } else { $body['error'] = is_array($payload)?$payload: ['message'=>(string)$payload]; }
    echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
}

function po_require_login(): int {
    if (!isset($_SESSION['userID']) || (int)$_SESSION['userID'] <= 0) {
        po_jresp(false, ['code'=>'auth_required','message'=>'Login required'], 401);
    }
    return (int)$_SESSION['userID'];
}

function po_verify_csrf(): void {
    $csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = false;
    if (function_exists('verifyCSRFToken')) {
        $valid = verifyCSRFToken($csrf);
    } elseif (!empty($_SESSION['csrf_token'])) {
        $valid = hash_equals((string)$_SESSION['csrf_token'], (string)$csrf);
    }
    if (!$valid) po_jresp(false, ['code'=>'csrf_failed','message'=>'Invalid CSRF'], 400);
}

// Convenience wrappers if project helpers are missing
if (!function_exists('db_escape')) {
    function db_escape(string $s, $con){ return mysqli_real_escape_string($con, $s); }
}

// ---- PDO helper (persistent), mirrors stock_transfers stx_pdo() ----
function po_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!defined('DB_HOST')) {
        throw new RuntimeException('DB config not loaded (DB_HOST not defined)');
    }
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ]);
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return $pdo;
}

// ---- Schema helpers ----
function po_table_exists(PDO $pdo, string $table): bool {
    try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); } catch (Throwable $e) { return false; }
}
function po_has_column(PDO $pdo, string $table, string $col): bool {
    static $cache = [];
    $key = $table.'|'.$col; if (array_key_exists($key,$cache)) return $cache[$key];
    try {
        if (!po_table_exists($pdo,$table)) { return $cache[$key]=false; }
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$col]);
        return $cache[$key] = (bool)$stmt->fetch();
    } catch (Throwable $e) { return $cache[$key]=false; }
}

// ---- Small retry helper for deadlocks ----
function po_retry(callable $fn) {
    $delays = [50, 100, 200]; $last = null;
    foreach ($delays as $d) { try { return $fn(); } catch (Throwable $e) { if (strpos($e->getMessage(),'Deadlock')!==false) { usleep($d*1000); $last=$e; continue; } throw $e; } }
    if ($last) throw $last; return null;
}

// ---- PO event helper ----
function po_insert_event(PDO $pdo, int $poId, string $type, array $data = [], ?int $userId = null): void {
    if (!po_table_exists($pdo, 'po_events')) return; // optional
    $stmt = $pdo->prepare('INSERT INTO po_events (purchase_order_id, event_type, event_data, created_by, created_at) VALUES (?,?,?,?, NOW())');
    $payload = $data ? json_encode($data, JSON_UNESCAPED_SLASHES) : null;
    $stmt->execute([$poId, $type, $payload, $userId]);
}

// ---- Receipt snapshot helpers ----
function po_create_receipt(PDO $pdo, int $poId, string $outletId, bool $isFinal, ?int $userId, array $lines): ?int {
    if (!po_table_exists($pdo, 'po_receipts') || !po_table_exists($pdo, 'po_receipt_items')) return null; // optional
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO po_receipts (purchase_order_id, outlet_id, is_final, created_by, created_at) VALUES (?,?,?,?, NOW())');
        $ins->execute([$poId, $outletId, $isFinal ? 1 : 0, $userId]);
        $rid = (int)$pdo->lastInsertId();
        if ($lines) {
            $li = $pdo->prepare('INSERT INTO po_receipt_items (receipt_id, product_id, expected_qty, received_qty, line_note) VALUES (?,?,?,?,?)');
            foreach ($lines as $ln) {
                $li->execute([$rid, (string)$ln['product_id'], (int)$ln['expected'], (int)$ln['received'], $ln['line_note'] ?? null]);
            }
        }
        $pdo->commit();
        return $rid;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

