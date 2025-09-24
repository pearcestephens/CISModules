<?php
declare(strict_types=1);

namespace CIS\Transfers\Stock;

/**
 * PackHelper
 * - cartonization + label generation (MVP now, couriers soon)
 * - robust ID resolution and safe DB writes
 *
 * Assumptions:
 * - db(): PDO connection (from /core/error.php bootstrap)
 * - transfer_* tables exist as per your schema
 * - MVP writes: transfer_shipments, transfer_parcels, transfer_parcel_items, transfer_audit_log, transfer_logs
 */

class PackHelper
{
    /** Calculate how many ship units (cartons/packs/etc) for a requested qty. */
    public function calculateShipUnits(int $productId, int $qty): array
    {
        // Try your DB function/metadata first; fallback to 1:1 units
        $unitG = $this->resolveUnitWeightG($productId);
        $shipUnits = max(1, (int)$qty); // TODO: replace with real pack rules if present
        return [
            'ship_units' => $shipUnits,
            'unit_g'     => $unitG,
            'weight_g'   => $shipUnits * $unitG,
        ];
    }

    /** Resolve weight per unit (g) from vend product, category, else default 100g. */
    public function resolveUnitWeightG(int $productId): int
    {
        $pdo = db();
        // Try product weight
        $q = $pdo->prepare("SELECT IFNULL(ROUND(vp.weight_grams),0) AS wg FROM vend_products vp WHERE vp.id = :pid LIMIT 1");
        $q->execute([':pid' => $productId]);
        $wg = (int)($q->fetchColumn() ?: 0);
        if ($wg > 0) return $wg;

        // Try category fallback (example category weight table)
        $q = $pdo->prepare("
            SELECT IFNULL(ROUND(cw.default_weight_g),0) AS wg
            FROM product_classification_unified pcu
            JOIN category_weights cw ON cw.category_id = pcu.category_id
            WHERE pcu.product_id = :pid LIMIT 1
        ");
        $q->execute([':pid' => $productId]);
        $wg = (int)($q->fetchColumn() ?: 0);
        if ($wg > 0) return $wg;

        // Default
        return 100;
    }

    /**
     * Validate a parcel plan against this transfer's items.
     * Returns attachable (resolved items) and unknown (won’t map).
     */
    public function validateParcelPlan(int $transferId, array $plan): array
    {
        $map = $this->loadTransferItemMap($transferId); // by item_id and by product_id
        $attachable = [];
        $unknown = [];
        $parcels = (array)($plan['parcels'] ?? []);

        foreach ($parcels as $pi => $parcel) {
            $items = (array)($parcel['items'] ?? []);
            foreach ($items as $line) {
                $qty = (int)($line['qty'] ?? 0);
                $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                $pid = isset($line['product_id']) ? (int)$line['product_id'] : null;

                $resolved = $this->resolveTransferItemId($transferId, $iid, $pid, $map);
                if ($resolved) {
                    $attachable[] = ['parcel_index' => $pi, 'item_id' => $resolved, 'qty' => $qty];
                } else {
                    $unknown[] = ['parcel_index' => $pi, 'item_id' => $iid, 'product_id' => $pid, 'qty' => $qty];
                }
            }
        }

        return [
            'attachable' => $attachable,
            'unknown'    => $unknown,
            'notes'      => [
                'parcels'        => count($parcels),
                'lines_ok'       => count($attachable),
                'lines_unknown'  => count($unknown),
            ],
        ];
    }

    /**
     * Auto-attach fallback:
     * If any parcel has no items[] (or items omitted), fill with ALL transfer_items,
     * using calculated ship units per product.
     */
    public function autoAttachIfEmpty(int $transferId, array $plan): array
    {
        $parcels = (array)($plan['parcels'] ?? []);
        if (!$parcels) {
            // Create a single dummy parcel if none provided; weight will be recomputed below
            $parcels = [['weight_g' => 0, 'items' => []]];
        }

        $needsAuto = false;
        foreach ($parcels as $p) {
            if (!isset($p['items']) || !(array)$p['items']) {
                $needsAuto = true;
                break;
            }
        }
        if (!$needsAuto) {
            return ['parcels' => $parcels];
        }

        // Load all transfer_items
        $items = $this->listItems($transferId);

        // Build a single items set (could be spread over multiple parcels later when caps exist)
        $autoItems = [];
        $totalWeight = 0;
        foreach ($items as $it) {
            $pid   = (int)$it['product_id'];
            $qty   = (int)($it['requested_qty'] ?? 0);
            if ($qty <= 0) continue;

            $calc  = $this->calculateShipUnits($pid, $qty);
            $su    = (int)$calc['ship_units'];
            $unitG = (int)$calc['unit_g'];

            $autoItems[] = [
                'item_id'    => (int)$it['id'],
                'product_id' => $pid,
                'qty'        => $su,
            ];
            $totalWeight += ($su * $unitG);
        }

        // Put everything into the first parcel and keep others unchanged
        $parcels[0]['items']    = $autoItems;
        $parcels[0]['weight_g'] = (int)($parcels[0]['weight_g'] ?? 0);
        if ($parcels[0]['weight_g'] <= 0) {
            $parcels[0]['weight_g'] = max(100, $totalWeight); // ensure non-zero weight
        }

        return ['parcels' => $parcels];
    }

    /**
     * MVP label writer: persists shipment, parcels, and parcel items; writes audit + logs.
     * Returns: { ok, shipment_id, parcels:[{id, box_number, weight_kg, items_count}], skipped:[] }
     */
    public function generateLabelMvp(int $transferId, string $carrier, array $plan): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $shipmentId = $this->createShipment($transferId, $carrier, 'MVP');
            $parcelsOut = [];
            $skipped    = [];

            $map = $this->loadTransferItemMap($transferId);

            $idx = 0;
            foreach ((array)$plan['parcels'] as $parcel) {
                $idx++;
                $weightG = (int)($parcel['weight_g'] ?? 0);
                $parcelId = $this->addParcel($shipmentId, $idx, $weightG);

                $items = (array)($parcel['items'] ?? []);
                $count = 0;
                foreach ($items as $line) {
                    $qty = (int)($line['qty'] ?? 0);
                    $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                    $pid = isset($line['product_id']) ? (int)$line['product_id'] : null;

                    $resolved = $this->resolveTransferItemId($transferId, $iid, $pid, $map);
                    if (!$resolved || $qty <= 0) {
                        $skipped[] = ['box' => $idx, 'item_id' => $iid, 'product_id' => $pid, 'qty' => $qty];
                        continue;
                    }
                    $this->attachItemToParcel($parcelId, $resolved, $qty);
                    $count += $qty;
                }

                $parcelsOut[] = [
                    'id'         => $parcelId,
                    'box_number' => $idx,
                    'weight_kg'  => round($weightG / 1000, 3),
                    'items_count'=> $count,
                ];
            }

            $this->audit($transferId, 'mvp_label_created', [
                'shipment_id' => $shipmentId,
                'parcels'     => count($parcelsOut),
                'skipped'     => count($skipped),
            ]);
            $this->log($transferId, "MVP label created. shipment_id={$shipmentId} parcels=" . count($parcelsOut));

            $pdo->commit();

            return [
                'ok'          => true,
                'shipment_id' => $shipmentId,
                'parcels'     => $parcelsOut,
                'skipped'     => $skipped,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Failed to generate MVP label'];
        }
    }

    /** Real courier path (stub): wire GSS / NZPost helpers here and persist results similarly. */
    public function generateLabel(int $transferId, string $carrier, array $plan): array
    {
        // For now, delegate to MVP to keep environments simple
        return $this->generateLabelMvp($transferId, $carrier, $plan);
    }

    /** Add a simple pack note */
    public function addPackNote(int $transferId, string $notes): void
    {
        if ($notes === '') return;
        $pdo = db();
        $q = $pdo->prepare("INSERT INTO transfer_notes (transfer_id, note, created_at) VALUES (:t, :n, NOW())");
        $q->execute([':t' => $transferId, ':n' => $notes]);
        $this->audit($transferId, 'pack_note_added', ['len' => strlen($notes)]);
    }

    /** Return item rows for grid rendering */
    public function listItems(int $transferId): array
    {
        $pdo = db();
        $q = $pdo->prepare("
            SELECT 
              ti.id,
              ti.product_id,
              ti.request_qty AS requested_qty,
              COALESCE(vp.sku, '') AS sku,
              COALESCE(vp.name, '') AS name
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.transfer_id = :t
            ORDER BY ti.id ASC
        ");
        $q->execute([':t' => $transferId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Decorate with unit weight & suggested ship units
        foreach ($rows as &$r) {
            $pid  = (int)$r['product_id'];
            $qty  = (int)$r['requested_qty'];
            $calc = $this->calculateShipUnits($pid, $qty);
            $r['unit_g']               = $calc['unit_g'];
            $r['suggested_ship_units'] = $calc['ship_units'];
        }
        return $rows;
    }

    /**
     * Fetch latest shipment + parcels for transfer
     * Output: { shipment_id:int|null, parcels:[{id, box_number, weight_kg, items_count}] }
     */
    public function getParcels(int $transferId): array
    {
        $pdo = db();
        $q = $pdo->prepare("
            SELECT ts.id
            FROM transfer_shipments ts
            WHERE ts.transfer_id = :t
            ORDER BY ts.id DESC
            LIMIT 1
        ");
        $q->execute([':t' => $transferId]);
        $shipmentId = $q->fetchColumn();
        if (!$shipmentId) {
            return ['shipment_id' => null, 'parcels' => []];
        }

        $q = $pdo->prepare("
            SELECT 
              tp.id, tp.box_number, tp.weight_g,
              (SELECT COALESCE(SUM(tpi.qty),0) FROM transfer_parcel_items tpi WHERE tpi.parcel_id = tp.id) AS items_count
            FROM transfer_parcels tp
            WHERE tp.shipment_id = :s
            ORDER BY tp.box_number ASC
        ");
        $q->execute([':s' => (int)$shipmentId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int)$r['id'],
                'box_number' => (int)$r['box_number'],
                'weight_kg'  => round(((int)$r['weight_g']) / 1000, 3),
                'items_count'=> (int)$r['items_count'],
            ];
        }
        return ['shipment_id' => (int)$shipmentId, 'parcels' => $out];
    }

    /** Create shipment row and return id */
    public function createShipment(int $transferId, string $carrier, string $mode): int
    {
        $pdo = db();
        $q = $pdo->prepare("
            INSERT INTO transfer_shipments (transfer_id, carrier, mode, created_at)
            VALUES (:t, :c, :m, NOW())
        ");
        $q->execute([':t' => $transferId, ':c' => $carrier, ':m' => $mode]);
        return (int)$pdo->lastInsertId();
    }

    /** Add parcel row and return id */
    public function addParcel(int $shipmentId, int $boxNumber, int $weightG): int
    {
        $pdo = db();
        $q = $pdo->prepare("
            INSERT INTO transfer_parcels (shipment_id, box_number, weight_g, created_at)
            VALUES (:s, :b, :w, NOW())
        ");
        $q->execute([':s' => $shipmentId, ':b' => $boxNumber, ':w' => $weightG]);
        return (int)$pdo->lastInsertId();
    }

    /** Attach an item to a parcel */
    public function attachItemToParcel(int $parcelId, int $transferItemId, int $qty): void
    {
        $pdo = db();
        $q = $pdo->prepare("
            INSERT INTO transfer_parcel_items (parcel_id, transfer_item_id, qty)
            VALUES (:p, :i, :q)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
        ");
        $q->execute([':p' => $parcelId, ':i' => $transferItemId, ':q' => $qty]);
    }

    /**
     * Resolve transfer_item.id by either provided item_id or product_id.
     * Accepts a preloaded map to avoid repeated queries.
     */
    public function resolveTransferItemId(int $transferId, ?int $itemId, ?int $productId, ?array $preMap = null): ?int
    {
        $map = $preMap ?? $this->loadTransferItemMap($transferId);
        if ($itemId && isset($map['by_item'][$itemId])) {
            return $itemId;
        }
        if ($productId && isset($map['by_product'][$productId])) {
            return (int)$map['by_product'][$productId];
        }
        return null;
    }

    /** Build a map of transfer_items for fast lookups */
    public function loadTransferItemMap(int $transferId): array
    {
        $pdo = db();
        $q = $pdo->prepare("SELECT id, product_id FROM transfer_items WHERE transfer_id = :t");
        $q->execute([':t' => $transferId]);

        $byItem = [];
        $byProduct = [];
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['id'];
            $pid = (int)$r['product_id'];
            $byItem[$id] = $pid;
            $byProduct[$pid] = $id;
        }
        return ['by_item' => $byItem, 'by_product' => $byProduct];
    }

    /** Audit + logs */
    public function audit(int $transferId, string $event, array $meta = []): void
    {
        $pdo = db();
        $q = $pdo->prepare("
            INSERT INTO transfer_audit_log (transfer_id, event, meta_json, created_at)
            VALUES (:t, :e, :m, NOW())
        ");
        $q->execute([':t' => $transferId, ':e' => $event, ':m' => json_encode($meta, JSON_UNESCAPED_SLASHES)]);
    }

    public function log(int $transferId, string $message): void
    {
        $pdo = db();
        $q = $pdo->prepare("
            INSERT INTO transfer_logs (transfer_id, message, created_at)
            VALUES (:t, :m, NOW())
        ");
        $q->execute([':t' => $transferId, ':m' => $message]);
    }
}
