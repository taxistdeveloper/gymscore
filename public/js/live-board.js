(function () {
  var root = document.getElementById('liveBoard');
  if (!root) return;

  var base = root.getAttribute('data-base') || '';
  var disciplineId = root.getAttribute('data-discipline') || '';
  var discPick = document.getElementById('liveDiscipline');
  var pulse = document.getElementById('livePulse');
  var lastPayload = '';

  function fmtWeight(n, scoring, asBarKg) {
    var x = Number(n);
    if (!isFinite(x)) return '—';
    if (asBarKg || scoring === 'weight') return x.toFixed(1);
    return String(Math.round(x));
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function findChip(el) {
    while (el && el !== discPick) {
      if (el.className && (' ' + el.className + ' ').indexOf(' live-disc-chip ') !== -1) {
        return el;
      }
      el = el.parentNode;
    }
    return null;
  }

  function setDisciplineUrl(id) {
    try {
      var href = window.location.href;
      var hash = '';
      var hashPos = href.indexOf('#');
      if (hashPos !== -1) {
        hash = href.substring(hashPos);
        href = href.substring(0, hashPos);
      }
      var qPos = href.indexOf('?');
      var path = qPos === -1 ? href : href.substring(0, qPos);
      var query = qPos === -1 ? '' : href.substring(qPos + 1);
      var parts = query ? query.split('&') : [];
      var out = [];
      var replaced = false;
      for (var i = 0; i < parts.length; i++) {
        if (!parts[i]) continue;
        if (parts[i].indexOf('discipline=') === 0) {
          out.push('discipline=' + encodeURIComponent(id));
          replaced = true;
        } else {
          out.push(parts[i]);
        }
      }
      if (!replaced) out.push('discipline=' + encodeURIComponent(id));
      var next = path + '?' + out.join('&') + hash;
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', next);
      }
    } catch (e) {}
  }

  function getJson(url, onOk, onErr) {
    if (typeof fetch === 'function') {
      fetch(url, { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(onOk)
        .catch(onErr || function () {});
      return;
    }
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            onOk(JSON.parse(xhr.responseText));
          } catch (e) {
            if (onErr) onErr(e);
          }
        } else if (onErr) {
          onErr();
        }
      };
      xhr.onerror = function () { if (onErr) onErr(); };
      xhr.send(null);
    } catch (e) {
      if (onErr) onErr(e);
    }
  }

  function renderBar(bar) {
    var wrap = document.getElementById('liveBar');
    var visual = document.getElementById('liveBarVisual');
    var perSide = document.getElementById('livePerSide');
    if (!wrap || !visual) return;

    if (!bar || !bar.plates) {
      wrap.hidden = true;
      wrap.style.display = 'none';
      visual.innerHTML = '';
      return;
    }

    wrap.hidden = false;
    wrap.style.display = '';
    if (perSide) {
      perSide.textContent = 'На сторону: ' + Number(bar.per_side).toFixed(2) + ' кг';
    }

    function platesHtml(list, reverse) {
      var items = reverse ? list.slice().reverse() : list;
      var html = '';
      for (var i = 0; i < items.length; i++) {
        var p = items[i];
        var dark = p.dark ? 'color:#111;' : '';
        html += '<span class="plate" style="background:' + esc(p.color) + ';' + dark + '">'
          + esc(p.label) + '</span>';
      }
      return html;
    }

    visual.innerHTML =
      '<div class="platform-bar-side-plates">' + platesHtml(bar.plates, true) + '</div>' +
      '<div class="platform-bar-sleeve"></div>' +
      '<div class="platform-bar-shaft"></div>' +
      '<div class="platform-bar-sleeve"></div>' +
      '<div class="platform-bar-side-plates">' + platesHtml(bar.plates, false) + '</div>';
  }

  function fighterMeta(f) {
    if (!f) return '';
    var parts = [Number(f.body_weight).toFixed(2) + ' кг'];
    if (f.wins != null) parts.push(f.wins + ' побед');
    if (f.lot_number) parts.push('#' + f.lot_number);
    return parts.join(' · ');
  }

  function setHidden(el, hide) {
    if (!el) return;
    el.hidden = !!hide;
    el.style.display = hide ? 'none' : '';
  }

  function renderReps(last, isFolkMode) {
    var block = document.getElementById('liveRepsBlock');
    var repsEl = document.getElementById('liveReps');
    var labelEl = document.getElementById('liveRepsLabel');
    var whoEl = document.getElementById('liveRepsWho');
    if (!block || !repsEl) return;

    if (!isFolkMode) {
      setHidden(block, true);
      return;
    }

    setHidden(block, false);
    if (labelEl) labelEl.textContent = 'раз';
    if (last && last.reps != null) {
      repsEl.textContent = String(last.reps);
      if (whoEl) {
        whoEl.textContent = (last.is_valid === false ? 'незачёт · ' : '')
          + (last.full_name || '');
      }
    } else {
      repsEl.textContent = '—';
      if (whoEl) whoEl.textContent = 'ожидание результата';
    }
  }

  function render(data) {
    var d = data.discipline || {};
    var cur = data.current;
    var match = data.match;
    var last = data.last_result || null;
    var unit = d.unit || 'кг';
    var scoring = d.scoring_type || 'weight';
    var isFolk = !!d.is_folk;
    var isReps = scoring === 'reps' || isFolk;
    var isArm = scoring === 'arm';
    var barUnit = d.display_unit || (isFolk ? 'кг' : unit);
    var maxA = d.attempts_allowed || 3;
    var armEl = document.getElementById('liveArm');
    var weightBlock = document.getElementById('liveWeightBlock');
    var metrics = document.getElementById('liveMetrics');
    var nameEl = document.getElementById('liveName');

    if (metrics) {
      if (metrics.classList && metrics.classList.toggle) {
        metrics.classList.toggle('is-reps', isReps && !isArm);
      } else {
        metrics.className = (metrics.className || '').replace(/\bis-reps\b/g, '');
        if (isReps && !isArm) metrics.className += ' is-reps';
      }
    }

    if (isArm && match) {
      document.getElementById('liveAttempt').textContent =
        'Раунд ' + match.round_no + ' · бой ' + match.match_no;
      var metaArm = [];
      if (d.name) metaArm.push(d.name);
      if (match.category) metaArm.push(match.category);
      document.getElementById('liveMeta').textContent = metaArm.length ? metaArm.join(' · ') : '—';
      setHidden(nameEl, true);
      document.getElementById('liveSub').textContent = '';
      if (armEl) {
        setHidden(armEl, false);
        document.getElementById('liveArmLeftName').textContent = match.left.full_name;
        document.getElementById('liveArmLeftMeta').textContent = fighterMeta(match.left);
        if (match.right) {
          document.getElementById('liveArmRightName').textContent = match.right.full_name;
          document.getElementById('liveArmRightMeta').textContent = fighterMeta(match.right);
        } else {
          document.getElementById('liveArmRightName').textContent = 'BYE';
          document.getElementById('liveArmRightMeta').textContent = 'проход без боя';
        }
      }
      setHidden(weightBlock, true);
      renderReps(null, false);
      renderBar(null);
    } else {
      setHidden(armEl, true);
      setHidden(nameEl, false);
      setHidden(weightBlock, false);

      document.getElementById('liveAttempt').textContent = cur
        ? (isFolk
            ? ('Народный жим · ½ с/в')
            : ('Попытка ' + cur.attempt_no + ' из ' + maxA))
        : (isArm ? 'Стол свободен' : 'Помост свободен');

      var metaParts = [];
      if (d.name) metaParts.push(d.name);
      if (cur && cur.category) metaParts.push(cur.category);
      document.getElementById('liveMeta').textContent = metaParts.length ? metaParts.join(' · ') : '—';

      nameEl.textContent = cur
        ? cur.full_name
        : (isArm ? 'Ожидание боя…' : 'Ожидание помоста…');

      var sub = [];
      if (cur) {
        sub.push('С/вес: ' + Number(cur.body_weight).toFixed(2) + ' кг');
        if (isFolk) {
          sub.push('штанга ' + fmtWeight(cur.declared_weight, 'weight', true) + ' кг (½ с/в)');
        }
        if (cur.lot_number) sub.push('Жребий #' + cur.lot_number);
        if (cur.deferred) sub.push('ещё не на помосте');
      } else if (last && isReps) {
        sub.push('Последний результат: ' + last.full_name + ' — ' + last.reps + ' раз');
      }
      document.getElementById('liveSub').textContent = sub.join(' · ');

      var weightEl = document.getElementById('liveWeight');
      var weightLabel = document.getElementById('liveWeightLabel');
      if (weightEl) {
        if (isReps) {
          weightEl.innerHTML = cur
            ? (esc(fmtWeight(cur.declared_weight, 'weight', true)) + ' <span>кг</span>')
            : (last && last.bar_weight != null
                ? (esc(fmtWeight(last.bar_weight, 'weight', true)) + ' <span>кг</span>')
                : '—');
          if (weightLabel) {
            weightLabel.textContent = isFolk ? 'Вес на штанге (½ с/в)' : 'Вес на штанге';
          }
          renderReps(last, true);
        } else {
          weightEl.innerHTML = cur
            ? (esc(fmtWeight(cur.declared_weight, scoring, false)) + ' <span>' + esc(barUnit) + '</span>')
            : '—';
          if (weightLabel) weightLabel.textContent = 'Вес на штанге';
          renderReps(null, false);
        }
      }

      renderBar(data.bar);
    }

    var q = document.getElementById('liveQueue');
    var upcoming = data.upcoming || [];
    if (!upcoming.length) {
      q.innerHTML = '<li class="live-empty">' + (isArm ? 'Нет следующих боёв' : 'Очередь пуста') + '</li>';
    } else {
      var qHtml = '';
      for (var ui = 0; ui < upcoming.length; ui++) {
        var item = upcoming[ui];
        var meta = item.is_arm
          ? ('Раунд ' + (item.round_no || item.attempt_no)
            + (item.category ? ' · ' + item.category : ''))
          : ('Попытка #' + item.attempt_no
            + (item.lot_number ? ' · #' + item.lot_number : '')
            + (item.deferred ? ' · не пришёл' : ''));
        var right = item.is_arm
          ? ''
          : ('<span class="live-queue-w">'
            + esc(fmtWeight(item.declared_weight, isFolk ? 'weight' : scoring, isFolk))
            + ' ' + esc(isFolk ? 'кг' : barUnit)
            + '</span>');
        qHtml += '<li class="live-queue-item' + (ui === 0 ? ' is-next' : '') + (item.deferred ? ' is-deferred' : '') + '">'
          + '<div>'
          + '<div class="live-queue-name">'
          + (ui === 0 ? '<span class="platform-pill platform-pill--next">далее</span> ' : '')
          + esc(item.full_name)
          + '</div>'
          + '<div class="live-queue-meta">' + esc(meta) + '</div></div>'
          + right
          + '</li>';
      }
      q.innerHTML = qHtml;
    }

    var rankHead = document.getElementById('liveRankUnit');
    if (!rankHead) {
      var ths = document.querySelectorAll('#liveRank thead th');
      if (ths && ths.length) rankHead = ths[ths.length - 1];
    }
    if (rankHead) {
      rankHead.textContent = isArm ? 'Побед' : (isReps ? 'Раз' : 'Рез.');
    }

    var tbody = document.querySelector('#liveRank tbody');
    var ranking = data.ranking || [];
    if (!ranking.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="live-empty">Пока нет результатов</td></tr>';
    } else {
      var rHtml = '';
      for (var ri = 0; ri < ranking.length; ri++) {
        var row = ranking[ri];
        var pc = row.place <= 3 ? (' place-' + row.place) : '';
        var res = fmtWeight(row.best_result, isReps ? 'reps' : scoring);
        if (isReps) {
          res = res + ' раз';
        } else if (unit) {
          res = res + ' ' + unit;
        }
        rHtml += '<tr>'
          + '<td class="' + pc.replace(/^\s+/, '') + '">' + row.place + '</td>'
          + '<td>' + esc(row.full_name) + '</td>'
          + '<td>' + esc(res) + '</td>'
          + '</tr>';
      }
      tbody.innerHTML = rHtml;
    }
  }

  function tickPulse(ok) {
    if (!pulse) return;
    var cls = pulse.className || '';
    cls = cls.replace(/\bis-ok\b/g, '').replace(/\bis-err\b/g, '').replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
    pulse.className = cls + (ok ? ' is-ok' : ' is-err');
  }

  function applyData(data) {
    if (!data || !data.ok) {
      tickPulse(false);
      return;
    }
    var raw = JSON.stringify(data);
    if (raw !== lastPayload) {
      lastPayload = raw;
      render(data);
    }
    tickPulse(true);
  }

  function fetchData() {
    var url = base + '/live/data?discipline=' + encodeURIComponent(disciplineId)
      + '&_=' + (new Date().getTime());
    getJson(url, applyData, function () { tickPulse(false); });
  }

  if (discPick) {
    discPick.onclick = function (e) {
      var btn = findChip(e.target || e.srcElement);
      if (!btn) return;
      var id = btn.getAttribute('data-id');
      if (!id || id === String(disciplineId)) return;

      disciplineId = id;
      var chips = discPick.querySelectorAll('.live-disc-chip');
      for (var i = 0; i < chips.length; i++) {
        var on = chips[i].getAttribute('data-id') === id;
        var c = chips[i].className || '';
        c = c.replace(/\bis-active\b/g, '').replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
        chips[i].className = on ? (c + ' is-active') : c;
        chips[i].setAttribute('aria-selected', on ? 'true' : 'false');
      }

      setDisciplineUrl(disciplineId);
      lastPayload = '';
      fetchData();
    };
  }

  // Initial payload from server (works even if polling fails on TV)
  var boot = document.getElementById('liveBoot');
  if (boot && boot.textContent) {
    try {
      applyData(JSON.parse(boot.textContent));
    } catch (e) {}
  }

  fetchData();
  setInterval(fetchData, 2000);
})();
