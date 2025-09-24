(function(){
  'use strict';
  var POX = window.POX = window.POX || {};

  function poId(){
    var wrap = document.querySelector('.po-receive');
    return wrap ? wrap.getAttribute('data-po-id') : '';
  }

  function init(){
    var btnQuick = document.getElementById('btn-quick-save');
    var btnPartial = document.getElementById('btn-submit-partial');
    var btnFinal = document.getElementById('btn-submit-final');

    if (btnQuick) btnQuick.addEventListener('click', function(){
      POX.fetchJSON('po.save_progress', { po_id: poId() }).then(function(r){
        POX.toast(r.success ? 'Saved' : (r.error && r.error.message)||'Save failed', r.success?'success':'danger');
        if (r.success && POX.reloadPO) POX.reloadPO();
      });
    });

    if (btnPartial) btnPartial.addEventListener('click', function(){
      if(!confirm('Submit partial receipt?')) return;
      POX.fetchJSON('po.submit_partial', { po_id: poId() }).then(function(r){
        POX.toast(r.success ? 'Partial saved' : (r.error && r.error.message)||'Partial failed', r.success?'success':'danger');
        if (r.success && POX.reloadPO) POX.reloadPO();
      });
    });

    if (btnFinal) btnFinal.addEventListener('click', function(){
      if(!confirm('Submit final receipt? This will complete the PO.')) return;
      POX.fetchJSON('po.submit_final', { po_id: poId() }).then(function(r){
        if (r.success && r.data && r.data.redirect) {
          window.location.href = r.data.redirect;
        } else {
          POX.toast(r.success ? 'Completed' : (r.error && r.error.message)||'Complete failed', r.success?'success':'danger');
          if (r.success && POX.reloadPO) POX.reloadPO();
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
