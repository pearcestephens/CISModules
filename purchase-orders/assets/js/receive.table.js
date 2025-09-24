(function(){
  'use strict';
  var POX = window.POX = window.POX || {};

  var elTable = null;
  var totals = { expected:0, received:0 };

  function render(items){
    if (!elTable) return;
    elTable.innerHTML = '';
    totals.expected = 0; totals.received = 0;
    items.forEach(function(it){
      // it: { image, name, expected, received, status }
      var tr = document.createElement('tr');
      var img = it.image ? '<img src="'+it.image+'" alt="" style="width:48px;height:48px;object-fit:cover">' : '';
      tr.innerHTML = '<td>'+img+'</td>'+
        '<td>'+ (it.name||'') +'</td>'+
        '<td>'+ (it.expected||0) +'</td>'+
        '<td class="text-center">'+ (it.received||0) +'</td>'+
        '<td>'+ (it.status||'') +'</td>'+
        '<td><button class="btn btn-sm btn-outline-secondary" data-act="undo" data-line-id="'+(it.line_id||'')+'">Undo</button></td>';
      elTable.appendChild(tr);
      totals.expected += (it.expected||0);
      totals.received += (it.received||0);
    });
    document.getElementById('total_expected').textContent = String(totals.expected);
    document.getElementById('total_received_display').textContent = String(totals.received);
    var pct = totals.expected>0 ? Math.round((totals.received/totals.expected)*100) : 0;
    document.getElementById('progress_bar').style.width = pct+'%';
    document.getElementById('progress_text').textContent = pct+'% Complete';
    document.getElementById('items_received').textContent = String(totals.received);
    document.getElementById('total_items').textContent = String(totals.expected);
  }

  POX.reloadPO = async function(){
    var wrap = document.querySelector('.po-receive');
    if (!wrap) return;
    var poId = wrap.getAttribute('data-po-id');
    var res = await POX.fetchJSON('po.get_po', { po_id: poId });
    if (res && res.success) {
      render(res.data.items || []);
    } else {
      POX.toast((res && res.error && res.error.message) || 'Failed to load PO', 'danger');
    }
  }

  function init(){
    elTable = document.getElementById('receiving_table').querySelector('tbody');
    POX.reloadPO();
    elTable.addEventListener('click', function(ev){
      var btn = ev.target.closest('button[data-act="undo"]');
      if (!btn) return;
      var lineId = btn.getAttribute('data-line-id');
      POX.fetchJSON('po.undo_item', { line_id: lineId, po_id: document.querySelector('.po-receive').getAttribute('data-po-id') })
        .then(function(r){
          POX.toast(r.success ? 'Undone' : (r.error && r.error.message)||'Undo failed', r.success?'success':'danger');
          if (r.success) POX.reloadPO();
        });
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
