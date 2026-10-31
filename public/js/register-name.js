(function () {
  var wrap = document.querySelector('[data-name-suggest]');
  var input = document.getElementById('full_name');
  var list = document.getElementById('nameSuggestList');
  var hint = document.getElementById('nameMatchHint');
  var takenHint = document.getElementById('disciplinesTakenHint');
  var people = Array.isArray(window.REGISTER_KNOWN_PEOPLE) ? window.REGISTER_KNOWN_PEOPLE : [];
  var disciplineLabels = document.querySelectorAll('[data-disciplines] [data-discipline-id]');

  if (!wrap || !input || !list) return;

  var active = -1;
  var matches = [];

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/ё/g, 'е')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function surnameOf(fullName) {
    var parts = String(fullName || '').trim().split(/\s+/);
    return parts[0] || '';
  }

  function takenIdsOf(p) {
    if (!p || !Array.isArray(p.discipline_ids)) return [];
    return p.discipline_ids.map(function (id) { return parseInt(id, 10); }).filter(Boolean);
  }

  function applyTakenDisciplines(ids) {
    var taken = {};
    (ids || []).forEach(function (id) { taken[id] = true; });
    var any = false;

    for (var i = 0; i < disciplineLabels.length; i++) {
      var label = disciplineLabels[i];
      var id = parseInt(label.getAttribute('data-discipline-id'), 10);
      var checkbox = label.querySelector('input[type="checkbox"]');
      var badge = label.querySelector('.reg-choice-taken');
      var isTaken = !!taken[id];

      label.classList.toggle('is-taken', isTaken);
      if (checkbox) {
        checkbox.disabled = isTaken;
        if (isTaken) checkbox.checked = false;
      }
      if (badge) badge.hidden = !isTaken;
      if (isTaken) any = true;
    }

    if (takenHint) takenHint.hidden = !any;
  }

  function clearTakenDisciplines() {
    applyTakenDisciplines([]);
  }

  function findMatches(query) {
    var q = norm(query);
    if (q.length < 2 || !people.length) return [];

    var out = [];
    var seen = {};
    for (var i = 0; i < people.length; i++) {
      var p = people[i];
      var full = norm(p.full_name);
      if (seen[full]) continue;
      var sur = norm(surnameOf(p.full_name));
      if (sur.indexOf(q) === 0 || full.indexOf(q) === 0 || full.indexOf(' ' + q) !== -1) {
        seen[full] = true;
        out.push(p);
      }
      if (out.length >= 8) break;
    }
    return out;
  }

  function exactByName(name) {
    var n = norm(name);
    if (!n) return null;
    for (var i = 0; i < people.length; i++) {
      if (norm(people[i].full_name) === n) return people[i];
    }
    return null;
  }

  function exactBySurname(name) {
    var sur = norm(surnameOf(name));
    if (sur.length < 2) return null;
    var found = null;
    for (var i = 0; i < people.length; i++) {
      if (norm(surnameOf(people[i].full_name)) === sur) {
        if (found) return null;
        found = people[i];
      }
    }
    return found;
  }

  function setGender(g) {
    var m = document.getElementById('gender_m');
    var f = document.getElementById('gender_f');
    if (!m || !f) return;
    if (g === 'F') {
      f.checked = true;
      m.checked = false;
    } else {
      m.checked = true;
      f.checked = false;
    }
  }

  function fillPerson(p, opts) {
    opts = opts || {};
    if (!p) return;

    if (opts.setName !== false) {
      input.value = p.full_name;
    }

    var phone = document.getElementById('phone');
    var bw = document.getElementById('body_weight');
    var by = document.getElementById('birth_year');
    var bd = document.getElementById('birth_date');

    if (phone && p.phone) { phone.value = p.phone; phone.dispatchEvent(new Event('input', { bubbles: true })); }
    if (bw && p.body_weight) { bw.value = p.body_weight; bw.dispatchEvent(new Event('input', { bubbles: true })); }
    if (bd && p.birth_year) { bd.value = String(p.birth_year); bd.dispatchEvent(new Event('input', { bubbles: true })); }
    if (by && p.birth_year) { by.value = String(p.birth_year); }
    setGender(p.gender || 'M');
    applyTakenDisciplines(takenIdsOf(p));

    if (hint) hint.hidden = false;
    hideList();
  }

  function hideList() {
    list.hidden = true;
    list.innerHTML = '';
    active = -1;
    matches = [];
    input.setAttribute('aria-expanded', 'false');
  }

  function renderList(items) {
    matches = items;
    active = -1;
    list.innerHTML = '';

    if (!items.length) {
      hideList();
      return;
    }

    for (var i = 0; i < items.length; i++) {
      var li = document.createElement('li');
      li.className = 'reg-name-item';
      li.setAttribute('role', 'option');
      li.setAttribute('data-index', String(i));
      li.textContent = items[i].full_name;
      list.appendChild(li);
    }

    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function highlight(index) {
    var items = list.querySelectorAll('.reg-name-item');
    for (var i = 0; i < items.length; i++) {
      items[i].classList.toggle('is-active', i === index);
    }
    active = index;
  }

  function syncTakenFromName() {
    var exact = exactByName(input.value);
    if (exact) {
      applyTakenDisciplines(takenIdsOf(exact));
      return exact;
    }
    clearTakenDisciplines();
    return null;
  }

  function tryAutoSelect() {
    var exact = exactByName(input.value);
    if (exact) {
      fillPerson(exact, { setName: false });
      return;
    }

    var only = exactBySurname(input.value);
    if (only && norm(input.value) === norm(surnameOf(only.full_name))) {
      fillPerson(only);
    } else {
      clearTakenDisciplines();
      if (hint) hint.hidden = true;
    }
  }

  input.addEventListener('input', function () {
    if (hint) hint.hidden = true;
    syncTakenFromName();
    renderList(findMatches(input.value));
  });

  input.addEventListener('focus', function () {
    if (norm(input.value).length >= 2) {
      renderList(findMatches(input.value));
    }
  });

  input.addEventListener('blur', function () {
    setTimeout(function () {
      hideList();
      tryAutoSelect();
    }, 150);
  });

  input.addEventListener('keydown', function (e) {
    if (list.hidden || !matches.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlight(active < matches.length - 1 ? active + 1 : 0);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlight(active > 0 ? active - 1 : matches.length - 1);
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      fillPerson(matches[active]);
    } else if (e.key === 'Escape') {
      hideList();
    }
  });

  list.addEventListener('mousedown', function (e) {
    var item = e.target.closest('.reg-name-item');
    if (!item) return;
    e.preventDefault();
    var idx = parseInt(item.getAttribute('data-index'), 10);
    if (!isNaN(idx) && matches[idx]) fillPerson(matches[idx]);
  });

  if (norm(input.value).length >= 2) {
    tryAutoSelect();
  }
})();
