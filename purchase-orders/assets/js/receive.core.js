(function(){
  'use strict';
  var POX = window.POX = window.POX || {};

  POX.csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content || (window.CSRF_TOKEN||'');
  POX.ajaxUrl = 'https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php';

  POX.fetchJSON = async function(action, body){
    var form = new FormData();
    form.append('action', action);
    form.append('csrf', POX.csrf);
    Object.keys(body||{}).forEach(function(k){
      form.append(k, typeof body[k] === 'object' ? JSON.stringify(body[k]) : body[k]);
    });
    var res = await fetch(POX.ajaxUrl, { method: 'POST', credentials: 'include', body: form });
    return res.json();
  };

  POX.toast = function(msg, type){
    console.log('[toast]', type||'info', msg);
  };
})();
