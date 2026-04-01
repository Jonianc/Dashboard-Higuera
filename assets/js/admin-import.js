(function(){
  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function(){
    var input = document.getElementById('dlh-rentabilidad-file');
    var feedback = document.getElementById('dlh-rent-file-feedback');
    if(!input || !feedback) return;
    var strong = feedback.querySelector('strong');
    var span = feedback.querySelector('span');
    var labels = (window.dlhImportPage && window.dlhImportPage.labels) || {};

    input.addEventListener('change', function(){
      var file = input.files && input.files[0] ? input.files[0] : null;
      if(!file){
        if(strong) strong.textContent = labels.noFile || 'Ningún archivo seleccionado';
        if(span) span.textContent = labels.chooseFile || 'Selecciona un CSV o XLSX para rentabilidad.';
        return;
      }
      if(strong) strong.textContent = file.name;
      if(span) span.textContent = (labels.selected || 'Archivo listo para validar y activar') + ' · ' + formatBytes(file.size);
    });
  });

  function formatBytes(bytes){
    if(!bytes || bytes <= 0) return '0 B';
    var units = ['B','KB','MB','GB'];
    var i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    var value = bytes / Math.pow(1024, i);
    return value.toFixed(value >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
  }
})();
