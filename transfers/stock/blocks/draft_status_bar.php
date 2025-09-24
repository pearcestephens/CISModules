<?php
/**
 * blocks/draft_status_bar.php
 * Professional toolbar: draft indicator, save/restore/discard/autosave, options with add products/print
 */
?>
<section class="card mb-2">
  <div class="card-body py-2 d-flex align-items-center flex-wrap" style="gap:8px;">
  <div class="badge badge-secondary">Draft: <span id="stx-draft-indicator">Off</span></div>
  <span id="stx-save-status" class="small text-muted">Not saved</span>
    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
      <div class="btn-group btn-group-sm" role="group" aria-label="Save actions">
        <button class="btn btn-primary" id="stx-save"><span class="d-none d-sm-inline">Save now</span> <span class="text-monospace small">(Ctrl+S)</span></button>
        <button class="btn btn-outline-secondary" id="stx-restore">Restore</button>
        <button class="btn btn-outline-danger" id="stx-discard">Discard</button>
      </div>
      <div class="custom-control custom-checkbox small">
        <input type="checkbox" class="custom-control-input" id="stx-autosave">
        <label class="custom-control-label" for="stx-autosave">Autosave</label>
      </div>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Options</button>
        <div class="dropdown-menu dropdown-menu-right">
          <a class="dropdown-item" href="#" id="stx-add-products-open">Add Products</a>
          <a class="dropdown-item" href="#" id="stx-print">Print</a>
        </div>
      </div>
    </div>
  </div>
</section>
