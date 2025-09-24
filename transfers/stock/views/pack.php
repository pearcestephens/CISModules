<?php
declare(strict_types=1);
$meta = include __DIR__ . '/pack.meta.php';

/**
 * Expect these to be available or passed via query:
 *   $transferId, $outletFrom
 * Fallback to GET if not set (for quick tests)
 */
$transferId = isset($transferId) ? (int)$transferId : (int)($_GET['transfer_id'] ?? 0);
$outletFrom = isset($outletFrom) ? (string)$outletFrom : (string)($_GET['outlet_from'] ?? '');

?>
<input type="hidden" id="transfer_id" value="<?= htmlspecialchars((string)$transferId, ENT_QUOTES) ?>">
<input type="hidden" id="outlet_from" value="<?= htmlspecialchars($outletFrom, ENT_QUOTES) ?>">

<div class="pack-page container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">Packing Transfer #<span id="pack-transfer"><?= (int)$transferId ?: 0 ?></span></h5>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-label-gss">Generate Label (GSS)</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-label-nzpost" disabled>Generate Label (NZPost)</button>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-pack">Save Pack</button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header"><strong>Items</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0" id="tblItems">
              <thead>
                <tr>
                  <th style="width:40%">Product</th>
                  <th style="width:10%">Req</th>
                  <th style="width:15%">Pack Qty</th>
                  <th style="width:15%">Ship Units</th>
                  <th style="width:20%">Weight (g)</th>
                </tr>
              </thead>
              <tbody>
                <!-- Example starter rows (replace with real data render) -->
                <tr data-product-id="02dcd191-ae71-11e8-ed44-095c3a15ce06" data-item-id="">
                  <td><div class="mono">02dcd191-ae71-11e8-ed44-095c3a15ce06</div></td>
                  <td>12</td>
                  <td><input class="form-control form-control-sm qty-input" value="12" min="0" type="number"></td>
                  <td class="ship-units">-</td>
                  <td class="weight-g">-</td>
                </tr>
                <!-- /example -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Notes</strong></div>
        <div class="card-body">
          <textarea class="form-control" id="pack-notes" rows="3" placeholder="Notes..."></textarea>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header"><strong>Parcels (MVP)</strong></div>
        <div class="card-body">
          <div id="parcelList">
            <!-- Minimal parcel builder (add more UI later) -->
            <div class="parcel-row mb-2">
              <div class="d-flex align-items-center gap-2">
                <span class="text-muted">#1</span>
                <input type="number" min="0" class="form-control form-control-sm parcel-weight-input" placeholder="Weight (g)" style="width:140px">
                <button class="btn btn-sm btn-outline-secondary add-row">Add Parcel</button>
              </div>
              <div class="small text-muted mt-1">Items will be auto-linked from table quantities.</div>
            </div>
          </div>
          <div class="mt-2 small text-muted">MVP: tracking/labels handled by carrier integration later.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Summary</strong></div>
        <div class="card-body">
          <div class="d-flex justify-content-between"><span>Parcels</span><span id="sum-parcels">0</span></div>
          <div class="d-flex justify-content-between"><span>Total Weight (g)</span><span id="sum-weight">0</span></div>
        </div>
      </div>
    </div>
  </div>
</div>
