(function(){
  var form = document.getElementById('dlh-portal-form');
  var submit = document.getElementById('dlh-portal-submit');
  var passwordInput = document.getElementById('dlh_portal_password');
  var toggle = document.getElementById('dlh-portal-toggle-password');

  if (form && submit) {
    form.addEventListener('submit', function(){
      if (form.classList.contains('is-submitting')) {
        return;
      }
      form.classList.add('is-submitting');
      submit.disabled = true;
      submit.textContent = 'Validando...';
    });
  }

  if (toggle && passwordInput) {
    toggle.addEventListener('click', function(){
      var isHidden = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isHidden ? 'text' : 'password');
      toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      toggle.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
      toggle.textContent = isHidden ? 'Ocultar' : 'Mostrar';
    });
  }
})();
