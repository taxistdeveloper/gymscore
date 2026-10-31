(function () {
  var input = document.getElementById('phone');
  if (!input) return;

  function digits(value) {
    var d = String(value || '').replace(/\D/g, '');
    if (d.startsWith('8')) d = '7' + d.slice(1);
    if (!d.startsWith('7') && d.length) d = '7' + d;
    return d.slice(0, 11);
  }

  function format(value) {
    var d = digits(value);
    if (!d.length) return '';

    var out = '+7';
    if (d.length > 1) out += ' ' + d.slice(1, 4);
    if (d.length > 4) out += ' ' + d.slice(4, 7);
    if (d.length > 7) out += ' ' + d.slice(7, 9);
    if (d.length > 9) out += ' ' + d.slice(9, 11);
    return out;
  }

  function apply() {
    var start = input.selectionStart;
    var before = input.value.length;
    input.value = format(input.value);
    var after = input.value.length;
    if (document.activeElement === input && start != null) {
      var pos = Math.max(0, start + (after - before));
      input.setSelectionRange(pos, pos);
    }
  }

  input.addEventListener('input', apply);
  input.addEventListener('focus', function () {
    if (!input.value.trim()) input.value = '+7 ';
  });
  input.addEventListener('blur', function () {
    if (digits(input.value).length <= 1) input.value = '';
  });

  if (input.value) apply();
})();
