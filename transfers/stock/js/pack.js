(function(){
  // -------- CSRF ----------
  function csrf(){ return (window.CIS_CSRF || ''); }

  // -------- Small DOM utils ----------
  function qs(sel, root){ return (root || document).querySelector(sel); }
  function qsa(sel, root){ return (root || document).querySelectorAll(sel); }
  function toInt(v, d){ var n = parseInt(v, 10); return isNaN(n) ? (d||0) : n; }

  // -------- Network helpers (FormData, stays compatible with your PHP) ----------
  async function postForm(action, params){
    const fd = new FormData();
    // legacy key your server expects
    fd.append('ajax_action', action);
    // body token
    fd.append('csrf', csrf());

    if (params && typeof params === 'object'){
      for (var k in params){
        if (!Object.prototype.hasOwnProperty.call(params, k)) continue;
        // Stringify objects (e.g., parcel_plan) so PHP can json_decode
        const v = params[k];
        fd.append(k, (v && typeof v === 'object') ? JSON.stringify(v) : v);
      }
    }

    const res = await fetch('/modules/transfers/stock/ajax/handler.php', {
      method:'POST',
      headers: { 'X-CSRF-Token': csrf() }, // header token
      body: fd,
      credentials:'same-origin'
    });

    // Try to parse JSON; surface HTTP errors with message
    let j = null;
    try { j = await res.json(); } catch(e){ /* fall through */ }
    if (!res.ok) {
      const msg = (j && (j.error || j.message)) || ('HTTP ' + res.status);
      throw new Error(msg);
    }
    return j || { ok:false, error:'Invalid JSON response' };
  }

  // -------- Row calc ----------
  async function calcRow(row){
    try {
      const productId = row.getAttribute('data-product-id') || '';
      const qty = toInt(qs('.qty-input', row)?.value || '0', 0);
      const j = await postForm('calculate_ship_units', { product_id: productId, qty: qty });
      if (j && j.ok){
        qs('.ship-units', row).textContent = j.ship_units;
        qs('.weight-g', row).textContent   = j.weight_g;
      } else {
        qs('.ship-units', row).textContent = 'ERR';
        qs('.weight-g', row).textContent   = 'ERR';
      }
    } catch (e){
      qs('.ship-units', row).textContent = 'ERR';
      qs('.weight-g', row).textContent   = 'ERR';
      console.error('calcRow error:', e);
    } finally {
      refreshSummary();
    }
  }

  // -------- Summary ----------
  function refreshSummary(){
    const parcels = qsa('#parcelList .parcel-row').length;
    let totalWeight = 0;
    qsa('#tblItems tbody tr').forEach(tr=>{
      totalWeight += toInt(qs('.weight-g', tr)?.textContent || '0', 0);
    });
    const sp = qs('#sum-parcels'); if (sp) sp.textContent = parcels;
    const sw = qs('#sum-weight');  if (sw) sw.textContent = totalWeight;
  }

  // -------- Build a parcel plan from the UI ----------
  function buildParcelPlan(){
    // MVP: each parcel row contributes a weight; attach all table rows with their ship units
    const parcels = [];
    const rows = qsa('#tblItems tbody tr');

    qsa('#parcelList .parcel-row').forEach((pRow)=>{
      const weightG = toInt(qs('.parcel-weight-input', pRow)?.value || '0', 0);
      const items = [];
      rows.forEach(tr=>{
        const itemId    = tr.getAttribute('data-item-id') || '';
        const productId = tr.getAttribute('data-product-id') || '';
        const shipUnits = toInt(qs('.ship-units', tr)?.textContent || '0', 0);
        if (shipUnits > 0){
          items.push({
            // Prefer item_id if available; server also supports product_id mapping
            item_id: itemId ? toInt(itemId, 0) : undefined,
            product_id: productId ? toInt(productId, 0) : undefined,
            qty: shipUnits
          });
        }
      });
      parcels.push({ weight_g: weightG, items: items });
    });

    return { parcels: parcels };
  }

  // -------- New helpers: validate + readback ----------
  async function validateParcelPlan(transferId, plan){
    return postForm('validate_parcel_plan', {
      transfer_id: transferId,
      parcel_plan: plan // will be JSON.stringified inside postForm
    });
  }

  async function loadParcels(transferId){
    return postForm('get_parcels', { transfer_id: transferId });
  }

  // Optional: render the right-hand “Parcels” pane if present
  function renderParcelsPane(data){
    const pane = qs('#parcelsPane');
    if (!pane || !data) return;
    const { shipment_id, parcels } = data;
    let html = '';
    html += '<div class="parcels-header">';
    html += '<div><strong>Shipment:</strong> ' + (shipment_id ?? '—') + '</div>';
    html += '<div><strong>Parcels:</strong> ' + (parcels?.length || 0) + '</div>';
    html += '</div>';
    html += '<div class="parcels-list">';
    (parcels || []).forEach(p=>{
      html += '<div class="parcel-card">';
      html +=   '<div>#' + p.box_number + '</div>';
      html +=   '<div>Weight: ' + p.weight_kg + ' kg</div>';
      html +=   '<div>Items: ' + p.items_count + '</div>';
      html += '</div>';
    });
    html += '</div>';
    pane.innerHTML = html;
  }

  // -------- Generate labels (merged: validation + readback) ----------
  let inFlight = false; // prevent double submits
  async function generateLabel(carrier){
    if (inFlight) return;
    inFlight = true;
    const btns = qsa('#btn-label-gss, #btn-label-nzpost, #btn-save-pack');
    btns.forEach(b => b && (b.disabled = true));

    try {
      const transferId = toInt(qs('#transfer_id')?.value || '0', 0);
      const outletFrom = qs('#outlet_from')?.value || '';
      const plan = buildParcelPlan();

      // 1) Pre-validate so UI can highlight unknowns if needed
      const v = await validateParcelPlan(transferId, plan);
      if (!v || !v.ok){
        alert('Validation failed: ' + (v?.error || 'Unexpected error'));
        return;
      }
      if (Array.isArray(v.unknown) && v.unknown.length > 0){
        // Optional: visually mark unknown items/rows here
        console.warn('Unknown lines in plan:', v.unknown);
        // Continue or abort based on your policy; for now we continue.
      }

      // 2) Create labels (server supports auto-attach if items[] omitted/empty)
      const j = await postForm('generate_label', {
        transfer_id: transferId,
        carrier: carrier,
        outlet_from: outletFrom,
        parcel_plan: plan
      });

      if (!j || !j.ok){
        alert('Label failed: ' + (j?.message || j?.error || 'error'));
        return;
      }

      // 3) Readback to populate right-hand parcels panel
      const r = await loadParcels(transferId);
      if (r && r.ok){
        renderParcelsPane({ shipment_id: r.shipment_id, parcels: r.parcels });
      }

      alert('Shipment created (MVP). ID: ' + (j.shipment_id || '?'));
    } catch (e){
      console.error('generateLabel error:', e);
      alert('Label failed: ' + (e?.message || 'error'));
    } finally {
      inFlight = false;
      btns.forEach(b => b && (b.disabled = false));
    }
  }

  // -------- Events ----------
  document.addEventListener('change', function(e){
    const t = e.target;
    if (t.matches('.qty-input')){
      const tr = t.closest('tr'); if (tr) calcRow(tr);
    }
  });

  document.addEventListener('click', function(e){
    const t = e.target;

    if (t.matches('#btn-save-pack')){
      e.preventDefault();
      const transferId = toInt(qs('#transfer_id')?.value || '0', 0);
      postForm('save_pack', {
        transfer_id: transferId,
        notes: (qs('#pack-notes')?.value || '')
      }).then(j=>{
        alert(j.ok ? 'Saved' : ('Save failed: ' + (j.error||'error')));
      }).catch(err=>{
        alert('Save failed: ' + (err?.message || 'error'));
      });
    }

    if (t.matches('#btn-label-gss')) {
      e.preventDefault();
      generateLabel('gosweetspot');
    }
    if (t.matches('#btn-label-nzpost')) {
      e.preventDefault();
      generateLabel('nzpost');
    }

    if (t.matches('.add-row')){
      e.preventDefault();
      const tpl = qs('#parcelList .parcel-row');
      if (!tpl) return;
      const clone = tpl.cloneNode(true);
      const w = qs('.parcel-weight-input', clone);
      if (w) w.value = '';
      qs('#parcelList')?.appendChild(clone);
      refreshSummary();
    }
  });

  // -------- Init ----------
  qsa('#tblItems tbody tr').forEach(calcRow);
  refreshSummary();
})();
