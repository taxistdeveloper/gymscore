(function () {
  var root = document.querySelector('[data-platform]');
  var cfg = window.PLATFORM || {};
  var lights = Array.prototype.slice.call(document.querySelectorAll('.platform-light'));
  var verdictEl = document.getElementById('lightsVerdict');
  var verdictText = document.getElementById('verdictText');
  var resultInput = document.getElementById('liftResult');
  var confirmBtn = document.getElementById('btnConfirm');
  var liftForm = document.getElementById('liftForm');

  function applyLightValue(card, value) {
    card.setAttribute('data-value', value || '');
    card.classList.toggle('is-white', value === 'white');
    card.classList.toggle('is-red', value === 'red');
    card.querySelectorAll('.platform-choice').forEach(function (btn) {
      var on = btn.getAttribute('data-choice') === value;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function countLights() {
    var white = 0;
    var red = 0;
    var unset = 0;
    lights.forEach(function (card) {
      var v = card.getAttribute('data-value') || '';
      if (v === 'white') white += 1;
      else if (v === 'red') red += 1;
      else unset += 1;
    });
    return { white: white, red: red, unset: unset };
  }

  function updateVerdict() {
    if (!confirmBtn || !resultInput) return;
    var c = countLights();
    var ready = c.unset === 0;
    var good = c.white >= 2;
    var result = ready ? (good ? 'good' : 'bad') : '';

    resultInput.value = result;
    confirmBtn.disabled = !ready;

    if (verdictEl) {
      verdictEl.classList.remove('is-good', 'is-bad', 'is-wait');
      if (!ready) {
        verdictEl.classList.add('is-wait');
        if (verdictText) {
          verdictText.textContent = 'Выберите сигналы: ещё ' + c.unset
            + (c.unset === 1 ? ' судья' : ' судей');
        }
        confirmBtn.textContent = 'Сначала выберите 3 сигнала';
        confirmBtn.classList.remove('is-good', 'is-bad');
      } else if (good) {
        verdictEl.classList.add('is-good');
        if (verdictText) {
          verdictText.textContent = c.white + ' белых · ' + c.red + ' красных → Зачёт';
        }
        confirmBtn.textContent = '✓ Зафиксировать: Зачёт';
        confirmBtn.classList.add('is-good');
        confirmBtn.classList.remove('is-bad');
      } else {
        verdictEl.classList.add('is-bad');
        if (verdictText) {
          verdictText.textContent = c.white + ' белых · ' + c.red + ' красных → Незачёт';
        }
        confirmBtn.textContent = '✕ Зафиксировать: Незачёт';
        confirmBtn.classList.add('is-bad');
        confirmBtn.classList.remove('is-good');
      }
    }
  }

  function setLights(mode) {
    lights.forEach(function (card) {
      applyLightValue(card, mode === 'white' || mode === 'red' ? mode : '');
    });
    updateVerdict();
  }

  lights.forEach(function (card) {
    card.querySelectorAll('.platform-choice').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var choice = btn.getAttribute('data-choice');
        var current = card.getAttribute('data-value');
        // повторный клик снимает выбор
        applyLightValue(card, current === choice ? '' : choice);
        updateVerdict();
      });
    });
  });
  updateVerdict();

  if (liftForm) {
    liftForm.addEventListener('submit', function (e) {
      // Весовой помост: нужны 3 сигнала. Повторы (народный жим) — без сигналов.
      if (resultInput) {
        updateVerdict();
        if (!resultInput.value) {
          e.preventDefault();
          return;
        }
      }
      if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('is-busy');
      }
    });
  }

  // —— Weight steppers ——
  function bumpInput(input, delta) {
    if (!input) return;
    var step = parseFloat(input.getAttribute('step') || '2.5') || 2.5;
    var cur = parseFloat(String(input.value).replace(',', '.')) || 0;
    var next = Math.max(0, Math.round((cur + delta) * 100) / 100);
    if (step >= 0.5) {
      next = Math.round(next / step) * step;
      next = Math.round(next * 10) / 10;
    }
    input.value = String(next);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  document.querySelectorAll('.platform-step, .platform-preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var delta = parseFloat(btn.getAttribute('data-delta') || '0');
      var targetId = btn.getAttribute('data-target');
      var input = targetId
        ? document.getElementById(targetId)
        : (btn.closest('form') && btn.closest('form').querySelector('input[name="weight"]'));
      bumpInput(input, delta);
    });
  });

  // —— Timer ——
  var display = document.getElementById('platformTimer');
  var startBtn = document.getElementById('timerStart');
  var pauseBtn = document.getElementById('timerPause');
  var resetBtn = document.getElementById('timerReset');
  var autoBox = document.getElementById('timerAutostart');
  var TOTAL = 60;
  var remaining = TOTAL;
  var timerId = null;
  var athleteKey = root ? root.getAttribute('data-athlete-key') : '';

  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.connect(g);
      g.connect(ctx.destination);
      o.frequency.value = 880;
      g.gain.value = 0.05;
      o.start();
      setTimeout(function () {
        o.stop();
        ctx.close();
      }, 220);
    } catch (e) { /* ignore */ }
  }

  function render() {
    if (!display) return;
    display.textContent = String(remaining);
    display.classList.toggle('is-low', remaining <= 10 && remaining > 0);
    display.classList.toggle('is-zero', remaining === 0);
  }

  function stop() {
    if (timerId) {
      clearInterval(timerId);
      timerId = null;
    }
  }

  function start() {
    if (!display || timerId) return;
    if (remaining === 0) remaining = TOTAL;
    timerId = setInterval(function () {
      if (remaining <= 0) {
        stop();
        remaining = 0;
        render();
        beep();
        return;
      }
      remaining -= 1;
      render();
    }, 1000);
  }

  function reset(andStart) {
    stop();
    remaining = TOTAL;
    render();
    setLights(null);
    if (andStart) start();
  }

  if (startBtn) startBtn.addEventListener('click', start);
  if (pauseBtn) pauseBtn.addEventListener('click', stop);
  if (resetBtn) resetBtn.addEventListener('click', function () { reset(false); });

  if (autoBox) {
    try {
      var saved = localStorage.getItem('platform_autostart');
      if (saved !== null) autoBox.checked = saved === '1';
    } catch (e) { /* ignore */ }
    autoBox.addEventListener('change', function () {
      try { localStorage.setItem('platform_autostart', autoBox.checked ? '1' : '0'); } catch (e) { /* ignore */ }
    });
  }

  if (display && athleteKey) {
    var prevKey = null;
    try { prevKey = sessionStorage.getItem('platform_athlete'); } catch (e) { /* ignore */ }
    var changed = prevKey !== athleteKey;
    try { sessionStorage.setItem('platform_athlete', athleteKey); } catch (e) { /* ignore */ }
    if (changed) {
      reset(!!(autoBox && autoBox.checked));
    } else {
      render();
    }
  } else if (display) {
    render();
  }

  var curQ = document.getElementById('queueCurrent');
  if (curQ && curQ.scrollIntoView) {
    curQ.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  var orderQuick = document.getElementById('order_weight_quick');
  if (orderQuick) {
    setTimeout(function () {
      orderQuick.focus();
      orderQuick.select();
    }, 50);
  }

  var orderModal = document.getElementById('orderModal');
  var orderBtn = document.getElementById('btnOrder');
  if (orderBtn && orderModal && orderModal.showModal) {
    orderBtn.addEventListener('click', function () {
      orderModal.showModal();
      var weight = document.getElementById('order_weight');
      if (weight) {
        weight.focus();
        if (weight.select) weight.select();
      }
    });
  }
  if (orderModal) {
    orderModal.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
      btn.addEventListener('click', function () { orderModal.close(); });
    });
    orderModal.addEventListener('click', function (e) {
      if (e.target === orderModal) orderModal.close();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (orderModal && orderModal.open) return;
    var tag = (e.target && e.target.tagName) || '';
    var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target.isContentEditable;
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    if (typing) {
      if (e.key === 'Escape' && orderQuick) {
        e.preventDefault();
        var skip = document.querySelector('.platform-order-banner-actions a');
        if (skip && skip.href) window.location.href = skip.href;
      }
      return;
    }

    var k = e.key;
    if (k === ' ' || k === 'Spacebar') {
      e.preventDefault();
      if (timerId) stop();
      else start();
      return;
    }
    if (k === '1' || k === '2' || k === '3') {
      e.preventDefault();
      var idx = parseInt(k, 10) - 1;
      var card = lights[idx];
      if (!card) return;
      var cur = card.getAttribute('data-value') || '';
      var next = cur === 'white' ? 'red' : (cur === 'red' ? '' : 'white');
      applyLightValue(card, next);
      updateVerdict();
      return;
    }
    if (k === 'Enter') {
      e.preventDefault();
      if (confirmBtn && !confirmBtn.disabled) confirmBtn.click();
      return;
    }
    if (k === 'o' || k === 'O') {
      e.preventDefault();
      var orderBtn = document.getElementById('btnOrder');
      if (orderBtn) orderBtn.click();
      return;
    }
    if (k === 'ArrowLeft' && cfg.prevUrl) {
      e.preventDefault();
      window.location.href = cfg.prevUrl;
      return;
    }
    if (k === 'ArrowRight' && cfg.nextUrl) {
      e.preventDefault();
      window.location.href = cfg.nextUrl;
    }
  });
})();
