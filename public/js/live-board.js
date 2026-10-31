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

  function renderBar(bar) {
    var wrap = document.getElementById('liveBar');
    var visual = document.getElementById('liveBarVisual');
    var perSide = document.getElementById('livePerSide');
    if (!wrap || !visual) return;

    if (!bar || !bar.plates) {
      wrap.hidden = true;
      visual.innerHTML = '';
      return;
    }

    wrap.hidden = false;
    if (perSide) {
      perSide.textContent = 'На сторону: ' + Number(bar.per_side).toFixed(2) + ' кг';
    }

    function platesHtml(list, reverse) {
      var items = reverse ? list.slice().reverse() : list;
      return items.map(function (p) {
        var dark = p.dark ? 'color:#111;' : '';
        return '<span class="plate" style="--plate-color:' + esc(p.color) + ';' + dark + '">'
          + esc(p.label) + '</span>';
      }).join('');
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

  function renderReps(last, isFolkMode) {
    var block = document.getElementById('liveRepsBlock');
    var repsEl = document.getElementById('liveReps');
    var labelEl = document.getElementById('liveRepsLabel');
    var whoEl = document.getElementById('liveRepsWho');
    if (!block || !repsEl) return;

    if (!isFolkMode) {
      block.hidden = true;
      return;
    }

    block.hidden = false;
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
      metrics.classList.toggle('is-reps', isReps && !isArm);
    }

    if (isArm && match) {
      document.getElementById('liveAttempt').textContent =
        'Раунд ' + match.round_no + ' · бой ' + match.match_no;
      document.getElementById('liveMeta').textContent = [
        d.name || '',
        match.category || ''
      ].filter(Boolean).join(' · ') || '—';
      if (nameEl) nameEl.hidden = true;
      document.getElementById('liveSub').textContent = '';
      if (armEl) {
        armEl.hidden = false;
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
      if (weightBlock) weightBlock.hidden = true;
      renderReps(null, false);
      renderBar(null);
    } else {
      if (armEl) armEl.hidden = true;
      if (nameEl) nameEl.hidden = false;
      if (weightBlock) weightBlock.hidden = false;

      document.getElementById('liveAttempt').textContent = cur
        ? (isFolk
            ? ('Народный жим · ½ с/в')
            : (isReps
                ? ('Попытка ' + cur.attempt_no + ' из ' + maxA)
                : ('Попытка ' + cur.attempt_no + ' из ' + maxA)))
        : (isArm ? 'Стол свободен' : 'Помост свободен');

      document.getElementById('liveMeta').textContent = [
        d.name || '',
        cur && cur.category ? cur.category : ''
      ].filter(Boolean).join(' · ') || '—';

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
          // Для народного / reps крупно — сколько раз, вес штанги рядом мельче
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
      q.innerHTML = upcoming.map(function (item, i) {
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
        return '<li class="live-queue-item' + (i === 0 ? ' is-next' : '') + (item.deferred ? ' is-deferred' : '') + '">'
          + '<div>'
          + '<div class="live-queue-name">'
          + (i === 0 ? '<span class="platform-pill platform-pill--next">далее</span> ' : '')
          + esc(item.full_name)
          + '</div>'
          + '<div class="live-queue-meta">' + esc(meta) + '</div></div>'
          + right
          + '</li>';
      }).join('');
    }

    var rankHead = document.getElementById('liveRankUnit')
      || document.querySelector('#liveRank thead th:last-child');
    if (rankHead) {
      rankHead.textContent = isArm ? 'Побед' : (isReps ? 'Раз' : 'Рез.');
    }

    var tbody = document.querySelector('#liveRank tbody');
    var ranking = data.ranking || [];
    if (!ranking.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="live-empty">Пока нет результатов</td></tr>';
    } else {
      tbody.innerHTML = ranking.map(function (row) {
        var pc = row.place <= 3 ? (' place-' + row.place) : '';
        var res = fmtWeight(row.best_result, isReps ? 'reps' : scoring);
        if (isReps) {
          res = res + ' раз';
        } else if (unit) {
          res = res + ' ' + unit;
        }
        return '<tr>'
          + '<td class="' + pc.trim() + '">' + row.place + '</td>'
          + '<td>' + esc(row.full_name) + '</td>'
          + '<td>' + esc(res) + '</td>'
          + '</tr>';
      }).join('');
    }
  }

  function tickPulse(ok) {
    if (!pulse) return;
    pulse.classList.toggle('is-ok', !!ok);
    pulse.classList.toggle('is-err', !ok);
  }

  function fetchData() {
    var url = base + '/live/data?discipline=' + encodeURIComponent(disciplineId);
    fetch(url, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
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
      })
      .catch(function () {
        tickPulse(false);
      });
  }

  if (discPick) {
    discPick.addEventListener('click', function (e) {
      var btn = e.target.closest('.live-disc-chip');
      if (!btn || !discPick.contains(btn)) return;
      var id = btn.getAttribute('data-id');
      if (!id || id === String(disciplineId)) return;

      disciplineId = id;
      discPick.querySelectorAll('.live-disc-chip').forEach(function (chip) {
        var on = chip.getAttribute('data-id') === id;
        chip.classList.toggle('is-active', on);
        chip.setAttribute('aria-selected', on ? 'true' : 'false');
      });

      var u = new URL(window.location.href);
      u.searchParams.set('discipline', disciplineId);
      window.history.replaceState({}, '', u.toString());
      lastPayload = '';
      fetchData();
    });
  }

  fetchData();
  setInterval(fetchData, 2000);
})();
