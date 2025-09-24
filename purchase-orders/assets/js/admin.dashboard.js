/* https://staff.vapeshed.co.nz/modules/purchase-orders/assets/js/admin.dashboard.js */
(function () {
  const csrf = document.querySelector('meta[name="csrf"]').content;
  const H = 'https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php';

  function post(form) {
    const data = new URLSearchParams();
    Object.entries(form).forEach(([k,v])=>{ if (v!==undefined && v!==null) data.append(k, String(v)); });
    data.append('csrf', csrf);
    return fetch(H, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:String(data) })
      .then(r=>r.json());
  }

  const state = { rcp:{page:1,size:20}, evt:{page:1,size:20}, q:{page:1,size:20} };
  const q = sel => document.querySelector(sel);
  const tbody = sel => q(sel).querySelector('tbody');

  function applyFilter() {
    state.po = parseInt(q('#po-filter-id').value || '0', 10) || 0;
    loadReceipts(); loadEvents();
  }

  function loadReceipts() {
    post({ action:'admin.list_receipts', po_id: state.po || 0, page: state.rcp.page, size: state.rcp.size })
      .then(j=>{
        if (!j.success) throw new Error(j.error && j.error.message || 'error');
        const rows = j.data.rows||[]; const tb = tbody('#tbl-receipts'); tb.innerHTML='';
        rows.forEach(r=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${r.receipt_id}</td><td>${r.purchase_order_id}</td><td>${r.outlet_id||''}</td><td>${r.is_final? 'Yes':'No'}</td><td>${r.items||0}</td><td>${r.created_by||''}</td><td>${r.created_at||''}</td>`;
          tb.appendChild(tr);
        });
        q('#rcp-page').textContent = `${j.data.page} / ${Math.max(1, Math.ceil(j.data.total / state.rcp.size))}`;
        q('#receipts-meta').textContent = `${j.data.total} total`;
      }).catch(()=>{});
  }

  function loadEvents() {
    post({ action:'admin.list_events', po_id: state.po || 0, page: state.evt.page, size: state.evt.size })
      .then(j=>{
        if (!j.success) throw new Error(j.error && j.error.message || 'error');
        const rows = j.data.rows||[]; const tb = tbody('#tbl-events'); tb.innerHTML='';
        rows.forEach(r=>{
          const tr = document.createElement('tr');
          let data = r.event_data; try { data = data ? JSON.stringify(JSON.parse(data), null, 0) : ''; } catch(e) {}
          tr.innerHTML = `<td>${r.event_id}</td><td>${r.purchase_order_id}</td><td>${r.event_type}</td><td class="text-monospace small">${(data||'').slice(0,140)}</td><td>${r.created_by||''}</td><td>${r.created_at||''}</td>`;
          tb.appendChild(tr);
        });
        q('#evt-page').textContent = `${j.data.page} / ${Math.max(1, Math.ceil(j.data.total / state.evt.size))}`;
        q('#events-meta').textContent = `${j.data.total} total`;
      }).catch(()=>{});
  }

  function loadQueue() {
    const status = q('#queue-status').value; const outlet = q('#queue-outlet').value;
    post({ action:'admin.list_inventory_requests', status, outlet_id: outlet, page: state.q.page, size: state.q.size })
      .then(j=>{
        if (!j.success) throw new Error(j.error && j.error.message || 'error');
        const rows = j.data.rows||[]; const tb = tbody('#tbl-queue'); tb.innerHTML='';
        rows.forEach(r=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${r.request_id}</td><td>${r.outlet_id}</td><td>${r.product_id}</td><td>${r.delta}</td><td>${r.status}</td><td>${r.reason||''}</td><td>${r.requested_at||''}</td>
            <td>
              <button class="btn btn-xs btn-outline-secondary mr-1" data-act="retry" data-id="${r.request_id}">Retry</button>
              <button class="btn btn-xs btn-outline-primary" data-act="force" data-id="${r.request_id}">Force</button>
            </td>`;
          tb.appendChild(tr);
        });
        q('#q-page').textContent = `${j.data.page} / ${Math.max(1, Math.ceil(j.data.total / state.q.size))}`;
        q('#queue-meta').textContent = `${j.data.total} total`;
      }).catch(()=>{});
  }

  function onQueueAction(e){
    const btn = e.target.closest('button'); if (!btn) return;
    const id = btn.getAttribute('data-id'); const act = btn.getAttribute('data-act');
    if (!id) return;
    const action = act==='retry' ? 'admin.retry_request' : 'admin.force_resend';
    post({ action, request_id: id }).then(()=> loadQueue());
  }

  function refreshEvidence(){
    const po = parseInt(q('#ev-po-id').value||'0',10)||0; if(!po) return;
    post({ action:'po.list_evidence', po_id: po }).then(j=>{
      if (!j.success) return;
      const tb = tbody('#tbl-evidence'); tb.innerHTML='';
      (j.data.rows||[]).forEach(r=>{
        const tr = document.createElement('tr');
        const href = r.file_path;
        tr.innerHTML = `<td>${r.id}</td><td><a href="${href}" target="_blank" rel="noopener">${href}</a></td><td>${r.evidence_type||''}</td><td>${r.uploaded_by||''}</td><td>${r.uploaded_at||''}</td>`;
        tb.appendChild(tr);
      });
    });
  }

  function onUpload(ev){
    ev.preventDefault();
    const po = parseInt(q('#ev-po-id').value||'0',10)||0; if(!po) return alert('Enter PO ID');
    const file = q('#ev-file').files[0]; if(!file) return alert('Choose a file');
    const fd = new FormData();
    fd.append('action','po.upload_evidence');
    fd.append('csrf', csrf);
    fd.append('po_id', String(po));
    fd.append('evidence_type', q('#ev-type').value);
    fd.append('description', q('#ev-desc').value);
    fd.append('file', file);
    fetch(H, { method:'POST', body: fd }).then(r=>r.json()).then(j=>{
      if(!j.success) return alert(j.error && j.error.message || 'Upload failed');
      q('#ev-file').value = '';
      refreshEvidence();
    });
  }

  // Bindings
  q('#btn-apply-filter').addEventListener('click', applyFilter);
  q('#btn-refresh-receipts').addEventListener('click', loadReceipts);
  q('#btn-refresh-events').addEventListener('click', loadEvents);
  q('#btn-refresh-queue').addEventListener('click', loadQueue);
  q('#tbl-queue').addEventListener('click', onQueueAction);
  q('#evidence-form').addEventListener('submit', onUpload);
  q('#btn-refresh-evidence').addEventListener('click', refreshEvidence);

  // Pager bindings
  q('#rcp-prev').addEventListener('click', ()=>{ state.rcp.page=Math.max(1,state.rcp.page-1); loadReceipts(); });
  q('#rcp-next').addEventListener('click', ()=>{ state.rcp.page=state.rcp.page+1; loadReceipts(); });
  q('#evt-prev').addEventListener('click', ()=>{ state.evt.page=Math.max(1,state.evt.page-1); loadEvents(); });
  q('#evt-next').addEventListener('click', ()=>{ state.evt.page=state.evt.page+1; loadEvents(); });
  q('#q-prev').addEventListener('click', ()=>{ state.q.page=Math.max(1,state.q.page-1); loadQueue(); });
  q('#q-next').addEventListener('click', ()=>{ state.q.page=state.q.page+1; loadQueue(); });

  // Initial load
  loadReceipts(); loadEvents(); loadQueue();
})();
