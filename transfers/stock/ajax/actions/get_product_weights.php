<?php
declare(strict_types=1);

// Given transfer_id, return a map of product_id => weight_kg (float)
// Tries vend_products.weight_grams, falls back to product attributes table patterns when present.

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  if (!function_exists('cis_pdo')) { jresp(false, 'DB unavailable', 500); }
  $pdo = cis_pdo();

  // Gather product IDs for this transfer from canonical/legacy lines
  $pids = [];
  $collect = function(array $rows) use (&$pids){ foreach ($rows as $r){ $id = (string)($r['pid'] ?? $r['product_id'] ?? ''); if ($id !== '') $pids[$id] = true; } };

  // transfer_items
  try {
    $st = $pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM transfer_items WHERE transfer_id = :tid OR stock_transfer_id = :tid OR parent_transfer_id = :tid');
    $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) { /* ignore */ }
  // stock_transfer_lines
  if (empty($pids)) {
    try { $st = $pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM stock_transfer_lines WHERE transfer_id = :tid OR stock_transfer_id = :tid OR parent_transfer_id = :tid'); $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable $e) { /* ignore */ }
  }
  if (empty($pids)) jresp(true, ['weights'=>[]]);

  $ids = array_keys($pids);

  // Query vend_products for weight; prefer grams
  $weights = [];
  try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, COALESCE(weight_grams, weight, 0) AS w FROM vend_products WHERE id IN ($in)");
    $st->execute($ids);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)){
      $g = (float)($row['w'] ?? 0);
      $kg = $g > 10 ? ($g/1000.0) : $g; // if stored already in kg, leave; if grams, convert (assume >10 means grams)
      $weights[(string)$row['id']] = max(0.0, $kg);
    }
  } catch (Throwable $e) { /* ignore */ }

  // Optional: attributes table variants (best-effort)
  if (empty($weights)){
    try {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $st = $pdo->prepare("SELECT product_id, COALESCE(weight_kg, weight, 0) AS w FROM product_attributes WHERE product_id IN ($in)");
      $st->execute($ids);
      while ($row = $st->fetch(PDO::FETCH_ASSOC)){
        $kg = (float)($row['w'] ?? 0);
        $weights[(string)$row['product_id']] = max(0.0, $kg);
      }
    } catch (Throwable $e) { /* ignore */ }
  }

  jresp(true, ['weights'=>$weights]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
