/* pack.init.js (stock path) */
(function(){
  const root = document.querySelector('.stx-outgoing'); if(!root) return;
  function u(url){ return 'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/js/' + url; }
  function ensureTable(){ if (ensureTable._p) return ensureTable._p; ensureTable._p = STX.lazy(u('items.table.js')); return ensureTable._p; }
  function bind(){
    root.addEventListener('click', async (e)=>{ const b=e.target.closest('[data-action="pack-goods"]'); if(!b) return; e.preventDefault(); await ensureTable(); STXPack.packGoods(); });
  root.addEventListener('click', async (e)=>{ const b=e.target.closest('[data-action="send-transfer"]'); if(!b) return; e.preventDefault(); STXPack.sendTransfer(false); });
    root.addEventListener('click', async (e)=>{ const b=e.target.closest('[data-action="force-send"]'); if(!b) return; e.preventDefault(); STXPack.sendTransfer(true); });
  root.addEventListener('click', async (e)=>{ const b=e.target.closest('[data-action="mark-ready"]'); if(!b) return; e.preventDefault(); try{ const tid=document.getElementById('transferID')?.value||''; await STX.fetchJSON('mark_ready', { transfer_id: tid }); STX.toast({ type:'success', text:'Marked as Ready' }); }catch(err){ STX.toast({ type:'error', text: err.message||'Failed to mark ready' }); } });
    function toggleTracking(){ const val = root.querySelector('input[name="delivery-mode"]:checked')?.value; const sec = document.getElementById('tracking-section'); if(!sec) return; sec.style.display = (val === 'courier') ? '' : 'none'; }
    root.addEventListener('change', (e)=>{ if(e.target.matches('[data-action="toggle-tracking"], input[name="delivery-mode"]')) toggleTracking(); });
    document.addEventListener('DOMContentLoaded', toggleTracking);
  root.addEventListener('input', (e)=>{ if(e.target.closest('[data-behavior="counted-input"]')){ ensureTable(); try{ window.STXPrinter?.recalcDueToItemsChange(); }catch(_){} } });
  root.addEventListener('click', (e)=>{ if(e.target.closest('[data-action="remove-product"],[data-action="fill-all-planned"]')){ ensureTable(); try{ window.STXPrinter?.recalcDueToItemsChange(); }catch(_){} } });
    root.addEventListener('click', async (e)=>{ const a=e.target.closest('[data-tab]'); if(!a) return; const name=a.getAttribute('data-tab'); if(name==='nzpost'){ await STX.lazy(u('shipping.np.js')); } else if(name==='gss'){ await STX.lazy(u('shipping.gss.js')); } else if(name==='manual'){ await STX.lazy(u('shipping.manual.js')); } else if(name==='history'){ await STX.lazy(u('history.js')); } });
    try { if (window.STXPrinter){ const csrf=(document.querySelector('meta[name="csrf-token"]')?.content) || (root.querySelector('input[name="csrf"]')?.value) || ''; const ajax=(document.querySelector('meta[name="stx-ajax"]')?.content) || (root.querySelector('input[name="stx-ajax"]')?.value) || undefined; const tid=document.getElementById('transferID')?.value || ''; window.STXPrinter.init({ transferId: tid, csrf: csrf, ajaxUrl: ajax }); } } catch (e) {}

    // Draft/status toolbar wiring
    (function draftToolbar(){
      const tid = document.getElementById('transferID')?.value || '';
      const saveBtn = document.getElementById('stx-save');
      const restoreBtn = document.getElementById('stx-restore');
      const discardBtn = document.getElementById('stx-discard');
      const autosaveCk = document.getElementById('stx-autosave');
      const draftInd = document.getElementById('stx-draft-indicator');
      const saveStatus = document.getElementById('stx-save-status');
      let autosaveTimer = null;

      function setStatus(text, type){ if(saveStatus){ saveStatus.textContent = text; saveStatus.classList.remove('text-danger','text-success','text-muted'); saveStatus.classList.add(type==='error'?'text-danger':(type==='ok'?'text-success':'text-muted')); } }
      function setDraft(on){ if(draftInd){ draftInd.textContent = on ? 'On' : 'Off'; draftInd.parentElement?.classList.toggle('badge-success', !!on); draftInd.parentElement?.classList.toggle('badge-secondary', !on); } }

      async function saveNow(){ try { setStatus('Saving...', ''); const payload = { transfer_id: tid, items: JSON.stringify(STXPack.collectItems()) }; const res = await STX.fetchJSON('finalize_pack', payload); setStatus('Saved', 'ok'); STX.toast({ type:'success', text:'Saved changes' }); setDraft(true); } catch(err){ setStatus('Save failed', 'error'); STX.toast({ type:'error', text: err.message || 'Save failed' }); } }
      async function restore(){ try { setStatus('Restoring...', ''); const res = await STX.fetchJSON('get_status', { transfer_id: tid }); const draft = !!res?.data?.is_draft; setDraft(draft); setStatus('Restored', 'ok'); STX.toast({ type:'success', text:'Restored status' }); } catch(err){ setStatus('Restore failed', 'error'); STX.toast({ type:'error', text: err.message || 'Restore failed' }); } }
      async function discard(){ try { setStatus('Discarding...', ''); const res = await STX.fetchJSON('cancel_transfer', { transfer_id: tid, simulate: 1 }); setStatus('Discarded', 'ok'); setDraft(false); STX.toast({ type:'success', text:'Draft discarded (simulation)' }); } catch(err){ setStatus('Discard failed', 'error'); STX.toast({ type:'error', text: err.message || 'Discard failed' }); } }

      saveBtn?.addEventListener('click', (e)=>{ e.preventDefault(); saveNow(); });
      restoreBtn?.addEventListener('click', (e)=>{ e.preventDefault(); restore(); });
      discardBtn?.addEventListener('click', (e)=>{ e.preventDefault(); discard(); });

      // Ctrl+S
      document.addEventListener('keydown', (e)=>{ if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase()==='s'){ e.preventDefault(); saveNow(); }});

      // Autosave toggle
      autosaveCk?.addEventListener('change', ()=>{
        if (autosaveCk.checked){ setStatus('Autosave On', ''); autosaveTimer = setInterval(()=> saveNow(), 15000); }
        else { setStatus('Autosave Off', ''); if (autosaveTimer){ clearInterval(autosaveTimer); autosaveTimer = null; } }
      });

      // Initial status fetch
      restore();
    })();

    // Options: Add Products modal
  root.addEventListener('click', (e)=>{ const open = e.target.closest('#stx-add-products-open'); if(!open) return; e.preventDefault(); $('#stx-add-products').modal('show'); });
    // Clear search
    document.getElementById('stx-add-clear')?.addEventListener('click', ()=>{ const r=document.getElementById('stx-add-results'); const i=document.getElementById('stx-add-search'); if(i) i.value=''; if(r){ r.innerHTML='<tr><td colspan="5" class="text-center text-muted py-4">Ready to search<br><small>Start typing to find products...</small></td></tr>'; }});

    // Product search wiring
  const searchInput = document.getElementById('stx-add-search');
  const resultsTbody = document.getElementById('stx-add-results');
  const moreBtn = document.getElementById('stx-add-more');
  const inStockOnly = document.getElementById('stx-add-instock');
  const tid = document.getElementById('transferID')?.value || '';
  let searchTimer = null;
  function cssEscape(v){ try{ return (window.CSS && CSS.escape) ? CSS.escape(v) : String(v).replace(/[^a-zA-Z0-9_-]/g,'\\$&'); }catch(_){ return String(v).replace(/[^a-zA-Z0-9_-]/g,'\\$&'); } }
  function escHtml(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
  function highlight(text, q){ try{ if(!q) return escHtml(text); const rx = new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig'); return escHtml(text).replace(rx,'<mark class="stx-hit">$1</mark>'); }catch(_){ return escHtml(text);} }
  let currentLimit = 50; let lastQuery = ''; let lastSeq = 0;
    function renderResults(items, q){
      if (!resultsTbody) return;
      if (!Array.isArray(items) || items.length===0){
        resultsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No results</td></tr>';
        if (moreBtn) { moreBtn.disabled = true; }
        return;
      }
      resultsTbody.innerHTML = items.map(it=>{
        const pid = String(it.product_id||'');
        const name = String(it.product_name||'');
        const sku = String(it.sku||'');
        const stock = Number(it.stock||0);
        const price = Number(it.price||0).toFixed(2);
        return `<tr>
          <td><input type="checkbox" class="stx-add-select" data-pid="${escHtml(pid)}" data-name="${escHtml(name)}" data-stock="${stock}"></td>
          <td><div><strong>${highlight(name, q)}</strong></div><div class="text-muted small">SKU: ${highlight(sku, q)}</div></td>
          <td>${stock}</td>
          <td>$${price}</td>
          <td><button type="button" class="btn btn-sm btn-primary stx-add-insert" data-pid="${escHtml(pid)}" data-name="${escHtml(name)}" data-stock="${stock}"><i class="fa fa-plus"></i> Add</button></td>
        </tr>`;
      }).join('');
      // Mark first row as active by default for keyboard Enter
      const first = resultsTbody.querySelector('tr'); if (first){ first.classList.add('is-active'); }
      if (moreBtn) { moreBtn.disabled = (items.length < currentLimit); }
    }
    async function doSearch(q){
      try{
        const seq = ++lastSeq; lastQuery = q; const limit = currentLimit; const instock = !!inStockOnly?.checked;
        const res = await STX.fetchJSON('search_products', { transfer_id: tid, q, limit });
        if (seq !== lastSeq) return; // stale
        const items = Array.isArray(res?.data?.items)? res.data.items : [];
        const filtered = instock ? items.filter(r=> Number(r.stock||0) > 0) : items;
        renderResults(filtered, q);
      }catch(err){
        resultsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Search failed</td></tr>';
        if (moreBtn) { moreBtn.disabled = true; }
      }
    }
    if (searchInput){
      searchInput.addEventListener('input', ()=>{
        const q = (searchInput.value||'').trim();
        clearTimeout(searchTimer);
        if (q.length < 2){ renderResults([]); return; }
        searchTimer = setTimeout(()=> doSearch(q), 250);
      });
      // Enter to add active or first
      searchInput.addEventListener('keydown', (e)=>{
        if (e.key === 'Enter'){
          const active = resultsTbody?.querySelector('tr.is-active .stx-add-insert');
          const first = resultsTbody?.querySelector('tr .stx-add-insert');
          const btn = active || first; if(btn){ e.preventDefault(); btn.click(); }
        }
        if (e.key === 'Escape'){ (document.getElementById('stx-add-clear')||{}).click?.(); }
      });
    }
    // Select All toggle in header
    document.getElementById('stx-add-select-all')?.addEventListener('change', (e)=>{
      const on = !!e.target.checked; resultsTbody?.querySelectorAll('.stx-add-select')?.forEach(cb=> cb.checked = on);
    });

    // Keyboard navigation in results
    function moveActive(delta){
      const rows = Array.from(resultsTbody?.querySelectorAll('tr')||[]);
      if (!rows.length) return;
      let idx = rows.findIndex(r=> r.classList.contains('is-active'));
      if (idx<0) idx = 0; else idx = Math.max(0, Math.min(rows.length-1, idx + delta));
      rows.forEach(r=> r.classList.remove('is-active'));
      const row = rows[idx]; row.classList.add('is-active'); row.scrollIntoView({block:'nearest'});
    }
    resultsTbody?.addEventListener('keydown', (e)=>{
      if (e.key==='ArrowDown'){ e.preventDefault(); moveActive(1); }
      if (e.key==='ArrowUp'){ e.preventDefault(); moveActive(-1); }
      if (e.key==='Enter'){ e.preventDefault(); const btn = resultsTbody.querySelector('tr.is-active .stx-add-insert'); if(btn) btn.click(); }
      if (e.key===' '){ const cb = resultsTbody.querySelector('tr.is-active .stx-add-select'); if(cb){ e.preventDefault(); cb.checked = !cb.checked; }}
    });
    resultsTbody?.addEventListener('mouseover', (e)=>{ const tr=e.target.closest('tr'); if(tr){ resultsTbody.querySelectorAll('tr').forEach(r=>r.classList.remove('is-active')); tr.classList.add('is-active'); }});

    // Insert single row
    root.addEventListener('click', (e)=>{
      const btn = e.target.closest('.stx-add-insert'); if(!btn) return;
      e.preventDefault();
      const pid = btn.getAttribute('data-pid');
      const name = btn.getAttribute('data-name');
      const stock = parseInt(btn.getAttribute('data-stock')||'0',10);
      const ok = insertRow(pid, name, stock);
      if (!ok){ highlightExisting(pid); STX.toast?.({ type:'info', text:'Already in list' }); return; }
      btn.disabled = true; btn.classList.remove('btn-primary'); btn.classList.add('btn-outline-secondary'); btn.innerHTML = '<i class="fa fa-check"></i> Added';
      try{ window.STXPrinter?.recalcDueToItemsChange(); }catch(_){ }
    });
    // Insert all selected
    root.addEventListener('click', (e)=>{
      const addAll = e.target.closest('#stx-add-insert-selected'); if(!addAll) return; e.preventDefault();
      const cbs = Array.from(resultsTbody?.querySelectorAll('.stx-add-select:checked')||[]);
      let added = 0, dup = 0;
      cbs.forEach(cb=>{
        const pid = cb.getAttribute('data-pid');
        const name = cb.getAttribute('data-name');
        const stock = parseInt(cb.getAttribute('data-stock')||'0',10);
        if (!insertRow(pid, name, stock)){ dup++; highlightExisting(pid); } else { added++; }
      });
      if (added>0) STX.toast?.({ type:'success', text:`Added ${added} product(s)` });
      if (dup>0) STX.toast?.({ type:'info', text:`${dup} duplicate(s) skipped` });
      if (added>0){ try{ window.STXPrinter?.recalcDueToItemsChange(); }catch(_){} }
    });

    // In-stock only toggle triggers re-render of last results
    inStockOnly?.addEventListener('change', ()=>{ const q=(searchInput?.value||'').trim(); if(q.length>=2){ doSearch(q); }});
    // Load more results (increase limit up to 200)
    moreBtn?.addEventListener('click', ()=>{ currentLimit = Math.min(200, currentLimit + 50); const q=(searchInput?.value||'').trim(); if(q.length>=2){ doSearch(q); }});

    function highlightExisting(pid){
      const tbody = document.getElementById('productSearchBody'); if(!tbody) return;
  const existing = tbody.querySelector(`.productID[value="${cssEscape(pid)}"]`);
      const tr = existing?.closest('tr'); if(!tr) return;
      tr.classList.add('stx-flash'); setTimeout(()=> tr.classList.remove('stx-flash'), 1500);
      tr.scrollIntoView({ block:'nearest', behavior:'smooth' });
      const inp = tr.querySelector('[data-behavior="counted-input"]'); if (inp) inp.focus();
    }

    function insertRow(pid, name, stock){
      const tbody = document.getElementById('productSearchBody'); if(!tbody) return;
      // Duplicate detection
  if (tbody.querySelector(`.productID[value="${cssEscape(pid)}"]`)) return false;
      const from = tbody.querySelector('tr td:nth-child(6)')?.textContent || 'Source';
      const to   = tbody.querySelector('tr td:nth-child(7)')?.textContent || 'Destination';
      const idx  = tbody.querySelectorAll('tr').length + 1;
      const tidForCounter = document.getElementById('transferID')?.value || '0';
      const tr = document.createElement('tr');
      tr.setAttribute('data-inventory', String(stock));
      tr.setAttribute('data-planned', '0');
      tr.innerHTML = `
        <td class='text-center align-middle'>
          <button type='button' class='btn btn-link p-0' aria-label='Remove product' data-action="remove-product">
            <i class='fa fa-times text-danger' aria-hidden='true'></i><span class='sr-only'> Remove</span>
          </button>
          <input type='hidden' class='productID' value='${STX.escapeHtml(pid)}'>
        </td>
        <td>${STX.escapeHtml(name)} <span class="badge badge-info">Added</span></td>
        <td class='inv'>${stock}</td>
        <td class='planned'>0</td>
        <td class='counted-td'>
          <label class='sr-only' for='counted-${STX.escapeHtml(pid)}'>Counted Qty</label>
          <input id='counted-${STX.escapeHtml(pid)}' class="form-control form-control-sm d-inline-block" type='number' min='0' max='${stock}' value='' style='width:6em;' data-behavior="counted-input">
          <span class='counted-print-value d-none'>0</span>
        </td>
        <td>${STX.escapeHtml(from)}</td>
        <td>${STX.escapeHtml(to)}</td>
        <td><span class='id-counter'>${STX.escapeHtml(String(tidForCounter))}-${idx}</span></td>`;
      tbody.appendChild(tr);
      const initial = parseInt(document.getElementById('stx-add-initial-counted')?.value||'',10);
      if (!isNaN(initial) && initial>=0){ const inp = tr.querySelector('[data-behavior="counted-input"]'); if (inp){ inp.value = String(Math.min(initial, stock)); inp.dispatchEvent(new Event('input', { bubbles:true })); } }
      ensureTable().then(()=>{ if (window.STXPack) { /* recalc handled by items.table.js on input */ } });
      return true;
    }

    // Printer config: toggle tabs by availability
    (async function initPrinters(){
      try{
        const cfg = await STX.fetchJSON('get_printers_config', {});
        const hasNZ = !!cfg?.data?.has_nzpost; const hasGSS = !!cfg?.data?.has_gss; const def = cfg?.data?.default || 'none';
        const nav = root.querySelector('.stx-tabs'); if (!nav) return;
        if (!hasNZ) nav.querySelector('[data-tab="nzpost"]')?.classList.add('disabled');
        if (!hasGSS) nav.querySelector('[data-tab="gss"]')?.classList.add('disabled');
        const first = def === 'nzpost' ? 'nzpost' : (def === 'gss' ? 'gss' : 'manual');
        const target = nav.querySelector(`[data-tab="${first}"]`); if (target){ target.click?.(); }
      }catch(e){}
    })();
  }
  document.addEventListener('DOMContentLoaded', bind);
})();
