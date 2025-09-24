/* items.table.js — table interactions (remove row, fill-all-planned, totals) */
(function(){
  const root = document.querySelector('.stx-outgoing'); if (!root) return;
  const tbody = document.getElementById('productSearchBody');
  const plannedEl = document.getElementById('plannedTotal');
  const countedEl = document.getElementById('countedTotal');
  const diffEl = document.getElementById('diffTotal');

  function num(v){ const n = parseFloat(String(v||'').replace(/[^0-9.-]/g,'')); return isNaN(n)?0:n; }
  function text(el){ return (el && (el.textContent||'').trim()) || '0'; }

  function recalc(){
    if (!tbody) return;
    let plannedTot = 0, countedTot = 0;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const planned = num(text(tr.querySelector('.planned')));
      const inp = tr.querySelector('[data-behavior="counted-input"]');
      const counted = num(inp && inp.value);
      plannedTot += planned;
      countedTot += counted;
      const pv = tr.querySelector('.counted-print-value'); if (pv) pv.textContent = String(counted);
    });
    const diff = countedTot - plannedTot;
    if (plannedEl) plannedEl.textContent = String(plannedTot);
    if (countedEl) countedEl.textContent = String(countedTot);
    if (diffEl){ diffEl.textContent = String(diff); diffEl.classList.toggle('text-success', diff === 0); diffEl.classList.toggle('text-danger', diff !== 0); }
    try { window.STXPrinter && window.STXPrinter.recalcDueToItemsChange && window.STXPrinter.recalcDueToItemsChange(); } catch(_){ }
  }

	function removeRow(btn){ const tr = btn.closest('tr'); if (!tr) return; 
		const name = (tr.children[1]?.textContent||'this item').trim();
		const ok = window.confirm('Remove "'+name+'" from this pack view? This does not delete the transfer line in the database.');
		if (!ok) return; tr.remove(); recalc(); }

  function fillAllPlanned(){ if (!tbody) return; tbody.querySelectorAll('tr').forEach(tr=>{
    const planned = num(text(tr.querySelector('.planned')));
    const inv = num(tr.getAttribute('data-inventory'));
    const target = (isFinite(inv) && inv>0) ? Math.min(planned, inv) : planned;
    const inp = tr.querySelector('[data-behavior="counted-input"]'); if (inp){ inp.value = String(target); inp.dispatchEvent(new Event('input', { bubbles:true })); }
  }); recalc(); }

  // Delegated events
  root.addEventListener('click', (e)=>{
    const del = e.target.closest('[data-action="remove-product"]'); if (del){ e.preventDefault(); removeRow(del); return; }
    const fill = e.target.closest('[data-action="fill-all-planned"]'); if (fill){ e.preventDefault(); fillAllPlanned(); return; }
  });
  root.addEventListener('input', (e)=>{ if (e.target.closest('[data-behavior="counted-input"]')) recalc(); });

  // Initial calc and simple observer for row changes
  document.addEventListener('DOMContentLoaded', recalc);
  if (window.MutationObserver){ const mo = new MutationObserver(()=>recalc()); mo.observe(tbody||document.body, { childList:true, subtree:false }); }

  window.STXTable = { recalc };
})();
/* items.table.js (stock path, migrated) */
(function(){
	const root = document.querySelector('.stx-outgoing'); if(!root) return;
	const table = root.querySelector('.stx-table') || document;
	function recalc(){
		let planned=0, counted=0;
		table.querySelectorAll('tbody tr').forEach(tr=>{
			const p = parseFloat(tr.getAttribute('data-planned')||'0')||0; planned+=p;
			const inp = tr.querySelector('[data-behavior="counted-input"]');
			const c = parseFloat(inp?.value||'0')||0; counted+=c;
		});
		const pEl = root.querySelector('#plannedTotal'); const cEl = root.querySelector('#countedTotal');
		if (pEl) pEl.textContent = planned.toFixed(0);
		if (cEl) cEl.textContent = counted.toFixed(0);
		const d = planned - counted; const dEl = root.querySelector('#diffTotal'); if (dEl){ dEl.textContent = d.toFixed(0); dEl.classList.toggle('text-danger', d!==0); dEl.classList.toggle('text-success', d===0); }
	}

	const STORAGE_KEY = 'stx-pack-draft-' + (document.getElementById('transferID')?.value || '');

	function serializeDraft(){
		const rows=[];
		table.querySelectorAll('tbody tr').forEach(tr=>{
			const pid = tr.querySelector('.productID')?.value||'';
			const planned = parseFloat(tr.getAttribute('data-planned')||'0')||0;
			const counted = parseFloat(tr.querySelector('[data-behavior="counted-input"]')?.value||'0')||0;
			if (pid) rows.push({ pid, planned, counted });
		});
		return { rows };
	}

	function applyDraft(d){
		if (!d || !Array.isArray(d.rows)) return;
		d.rows.forEach(item=>{
			const tr = table.querySelector('tbody tr .productID[value="'+CSS.escape(item.pid)+'"]')?.closest('tr');
			if (!tr) return;
			const inp = tr.querySelector('[data-behavior="counted-input"]');
			if (inp){ inp.value = String(item.counted||0); }
		});
		recalc();
	}

	function setDraftIndicator(on){
		const el = document.getElementById('stx-draft-indicator'); if (el) el.textContent = on ? 'On' : 'Off';
	}

	function saveDraft(){ try{ localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeDraft())); setDraftIndicator(true); }catch(e){} }
	function clearDraft(){ try{ localStorage.removeItem(STORAGE_KEY); setDraftIndicator(false); }catch(e){} }
	function restoreDraft(){ try{ const d=JSON.parse(localStorage.getItem(STORAGE_KEY)||'null'); if(d){ applyDraft(d); } }catch(e){} }

	table.addEventListener('input', (e)=>{ if(e.target.closest('[data-behavior="counted-input"]')) recalc(); });
	table.addEventListener('click', (e)=>{
		const rm = e.target.closest('[data-action="remove-product"]'); if(!rm) return;
		e.preventDefault(); const tr = rm.closest('tr'); if(tr){ tr.remove(); recalc(); }
	});
	table.addEventListener('click', (e)=>{
		const fill = e.target.closest('[data-action="fill-planned"]'); if(!fill) return;
		e.preventDefault(); const tr = fill.closest('tr');
		const inp = tr?.querySelector('[data-behavior="counted-input"]');
		const planned = tr ? parseFloat(tr.getAttribute('data-planned')||'0')||0 : 0;
		if (inp) { inp.value = planned; recalc(); }
	});

	// Global fill planned button
	document.addEventListener('click', (e)=>{
		const btn = e.target.closest('[data-action="fill-all-planned"]'); if(!btn) return;
		e.preventDefault();
		table.querySelectorAll('tbody tr').forEach(tr=>{
			const planned = parseFloat(tr.getAttribute('data-planned')||'0')||0;
			const inp = tr.querySelector('[data-behavior="counted-input"]');
			if (inp) inp.value = String(planned);
		});
		recalc();
		if (document.getElementById('stx-autosave')?.checked) saveDraft();
	});

	// Save/Restore/Discard/Autosave
	document.getElementById('stx-save')?.addEventListener('click', ()=> saveDraft());
	document.getElementById('stx-restore')?.addEventListener('click', ()=> restoreDraft());
	document.getElementById('stx-discard')?.addEventListener('click', ()=> { clearDraft(); restoreDraft(); });
	document.getElementById('stx-autosave')?.addEventListener('change', ()=>{ if (document.getElementById('stx-autosave').checked) saveDraft(); });
	document.addEventListener('keydown', (e)=>{ if ((e.ctrlKey||e.metaKey) && (e.key==='s' || e.key==='S')){ e.preventDefault(); saveDraft(); }});

	document.addEventListener('DOMContentLoaded', recalc);
	// Initialize indicator and attempt restore
	(function initDraft(){ try{ const has = !!localStorage.getItem(STORAGE_KEY); setDraftIndicator(has); if (has) restoreDraft(); }catch(e){} })();
})();
