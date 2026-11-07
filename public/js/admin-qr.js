(function () {
  var input = document.getElementById('qrUrl');
  var canvas = document.getElementById('qrCanvas');
  var hint = document.getElementById('qrHint');
  var btnDownload = document.getElementById('qrDownload');
  var btnPrint = document.getElementById('qrPrint');
  var btnCopy = document.getElementById('qrCopy');
  if (!input || !canvas || typeof QRCode === 'undefined') return;

  var timer = null;
  var lastUrl = '';

  function setBusy(ok) {
    if (btnDownload) btnDownload.disabled = !ok;
    if (btnPrint) btnPrint.disabled = !ok;
    if (btnCopy) btnCopy.disabled = !ok;
  }

  function clearCanvas() {
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  }

  function render(url) {
    url = String(url || '').trim();
    if (!url) {
      lastUrl = '';
      clearCanvas();
      if (hint) hint.textContent = 'Вставьте ссылку — QR обновится сам';
      setBusy(false);
      return;
    }

    QRCode.toCanvas(
      canvas,
      url,
      {
        width: 320,
        margin: 2,
        color: { dark: '#0a0a0a', light: '#ffffff' },
        errorCorrectionLevel: 'M',
      },
      function (err) {
        if (err) {
          lastUrl = '';
          clearCanvas();
          if (hint) hint.textContent = 'Не удалось построить QR. Проверьте ссылку.';
          setBusy(false);
          return;
        }
        lastUrl = url;
        if (hint) hint.textContent = url;
        setBusy(true);
      }
    );
  }

  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      render(input.value);
    }, 180);
  }

  input.addEventListener('input', schedule);
  input.addEventListener('change', schedule);

  document.querySelectorAll('[data-qr-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      input.value = btn.getAttribute('data-qr-preset') || '';
      render(input.value);
      input.focus();
    });
  });

  if (btnDownload) {
    btnDownload.addEventListener('click', function () {
      if (!lastUrl) return;
      var a = document.createElement('a');
      a.href = canvas.toDataURL('image/png');
      a.download = 'qr-code.png';
      document.body.appendChild(a);
      a.click();
      a.remove();
    });
  }

  if (btnPrint) {
    btnPrint.addEventListener('click', function () {
      if (!lastUrl) return;
      var dataUrl = canvas.toDataURL('image/png');
      var win = window.open('', '_blank');
      if (!win) return;
      win.document.write(
        '<!DOCTYPE html><html><head><title>QR</title>' +
          '<style>html,body{margin:0;min-height:100%;display:flex;align-items:center;justify-content:center;background:#fff;}' +
          'img{width:min(80vw,420px);height:auto;}@media print{img{width:70mm;}}</style></head><body>' +
          '<img src="' +
          dataUrl +
          '" alt="QR">' +
          '<script>window.onload=function(){window.focus();window.print();}<\/script>' +
          '</body></html>'
      );
      win.document.close();
    });
  }

  if (btnCopy) {
    btnCopy.addEventListener('click', function () {
      if (!lastUrl) return;
      var done = function () {
        var prev = btnCopy.textContent;
        btnCopy.textContent = 'Скопировано';
        setTimeout(function () {
          btnCopy.textContent = prev;
        }, 1200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(lastUrl).then(done).catch(function () {
          input.select();
          document.execCommand('copy');
          done();
        });
      } else {
        input.select();
        document.execCommand('copy');
        done();
      }
    });
  }

  render(input.value);
})();
