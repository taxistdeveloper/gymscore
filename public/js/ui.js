(function () {
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMenu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('max-lg:hidden') === false;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function closeDeployModal(modal) {
    if (!modal) return;
    modal.classList.add('hidden');
    var input = modal.querySelector('#deploySshPassword');
    if (input) input.value = '';
  }

  function openDeployModal(modal) {
    if (!modal) return;
    modal.classList.remove('hidden');
    var input = modal.querySelector('#deploySshPassword');
    if (input) {
      setTimeout(function () { input.focus(); }, 20);
    }
  }

  document.addEventListener('click', function (e) {
    var dismiss = e.target.closest('[data-dismiss="alert"]');
    if (dismiss) {
      var alert = dismiss.closest('[role="alert"]');
      if (alert) {
        var slot = alert.closest('.flash-slot');
        (slot || alert).remove();
      }
      return;
    }

    var openBtn = e.target.closest('[data-deploy-open]');
    if (openBtn) {
      openDeployModal(document.querySelector('[data-deploy-modal]'));
      return;
    }

    var closeBtn = e.target.closest('[data-deploy-close]');
    if (closeBtn) {
      closeDeployModal(closeBtn.closest('[data-deploy-modal]'));
      return;
    }

    var modal = e.target.closest('[data-app-modal]');
    if (modal && e.target === modal) {
      if (modal.hasAttribute('data-deploy-modal')) {
        closeDeployModal(modal);
      } else {
        modal.remove();
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var deployModal = document.querySelector('[data-deploy-modal]:not(.hidden)');
    if (deployModal) {
      closeDeployModal(deployModal);
      return;
    }
    var openModal = document.querySelector('[data-app-modal]');
    if (openModal) openModal.remove();
  });

  function themeIsDark() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  function syncThemeButtons() {
    var dark = themeIsDark();
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
  }

  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = themeIsDark() ? 'light' : 'dark';
      if (next === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else document.documentElement.removeAttribute('data-theme');
      try { localStorage.setItem('ag-theme', next); } catch (e) {}
      syncThemeButtons();
    });
  });

  syncThemeButtons();
})();
