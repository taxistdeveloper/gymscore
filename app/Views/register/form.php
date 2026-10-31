<?php
use App\Lang\Lang;

$base = $config['base_url'] ?? '';
$old = $old ?? [];
$oldDisc = array_map('intval', $old['disciplines'] ?? []);
$selectedAgeGroup = $old['age_group'] ?? 'juniors';
$oldCategories = $old['discipline_category'] ?? [];
$knownPeople = $knownPeople ?? [];
$ageGroups = $ageGroups ?? [];
$t = $t ?? static fn (string $key, ?string $fallback = null): string => Lang::t($key, $fallback);

$disciplineIcons = [
    'bench'        => '🏋️',
    'bench_folk'   => '⚡',
    'curl_strict'  => '💪',
    'curl_extreme' => '🔥',
    'armwrestling' => '🤼',
];

$disciplineTypes = [
    'bench'        => 'Пауэрлифтинг · Жим штанги лёжа',
    'bench_folk'   => 'Силовая выносливость · 1/2 с/в',
    'curl_strict'  => 'Строгий подъём на бицепс',
    'curl_extreme' => 'Экстремальный подъём на бицепс',
    'armwrestling' => 'Армрестлинг · Борьба на руках',
];
?>

<section class="reg-page">
  <a class="reg-back" href="<?= htmlspecialchars($base) ?>/"><?= htmlspecialchars($t('reg.back')) ?></a>

  <header class="reg-page-header">
    <h1 class="reg-page-title"><?= htmlspecialchars($t('reg.title')) ?></h1>
    <p class="reg-page-lead"><?= htmlspecialchars($t('reg.lead')) ?></p>
  </header>

  <form class="reg-form" id="registerForm" method="post" action="<?= htmlspecialchars($base) ?>/register" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

    <!-- 1. Данные спортсмена (ФИО, Пол, Телефон) -->
    <div class="card card-accent reg-card">
      <div class="card-body">
        <h2 class="reg-card-title"><?= htmlspecialchars($t('reg.participant')) ?></h2>
        <p class="reg-card-hint"><?= htmlspecialchars($t('reg.participant_hint')) ?></p>

        <div class="reg-field" data-name-suggest>
          <label class="field-label" for="full_name"><?= htmlspecialchars($t('reg.full_name')) ?></label>
          <input class="field w-full" id="full_name" name="full_name" required
            autocomplete="off"
            value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
            placeholder="<?= htmlspecialchars($t('reg.full_name_ph')) ?>"
            aria-autocomplete="list"
            aria-controls="nameSuggestList"
            aria-expanded="false">
          <ul class="reg-name-list" id="nameSuggestList" role="listbox" hidden></ul>
          <p class="field-hint reg-name-match" id="nameMatchHint" hidden>
            <?= htmlspecialchars($t('reg.name_match')) ?>
          </p>
        </div>

        <div class="reg-grid">
          <fieldset class="reg-field">
            <legend class="field-label"><?= htmlspecialchars($t('reg.gender')) ?></legend>
            <div class="reg-gender">
              <label class="reg-choice" for="gender_m">
                <input class="check" type="radio" name="gender" id="gender_m" value="M"
                  <?= ($old['gender'] ?? 'M') === 'M' ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($t('reg.gender_m')) ?></span>
              </label>
              <label class="reg-choice" for="gender_f">
                <input class="check" type="radio" name="gender" id="gender_f" value="F"
                  <?= ($old['gender'] ?? '') === 'F' ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($t('reg.gender_f')) ?></span>
              </label>
            </div>
          </fieldset>

          <div class="reg-field">
            <label class="field-label" for="phone"><?= htmlspecialchars($t('reg.phone')) ?></label>
            <input class="field w-full" id="phone" name="phone" type="tel"
              autocomplete="tel" inputmode="tel" maxlength="16"
              value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
              placeholder="+7 700 000 00 00">
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Возрастная категория (Дата рождения) -->
    <div class="card card-accent reg-card">
      <div class="card-body">
        <h2 class="reg-card-title"><?= htmlspecialchars($t('reg.age_group_title', 'Возрастная категория')) ?></h2>
        <p class="reg-card-hint"><?= htmlspecialchars($t('reg.age_group_auto_hint', 'Категория рассчитывается автоматически по дате рождения.')) ?></p>

        <div class="reg-field max-w-md">
          <label class="field-label" for="birth_date"><?= htmlspecialchars($t('reg.birth_date', 'Дата рождения')) ?></label>
          <input class="field w-full" id="birth_date" name="birth_date" type="text"
            autocomplete="bday"
            value="<?= htmlspecialchars($old['birth_date'] ?? $old['birth_year'] ?? '') ?>"
            placeholder="<?= htmlspecialchars($t('reg.birth_date_ph', 'ДД.ММ.ГГГГ или год (например: 2005)')) ?>">
          <input type="hidden" id="birth_year" name="birth_year" value="<?= htmlspecialchars($old['birth_year'] ?? '') ?>">
          <input type="hidden" id="age_group" name="age_group" value="<?= htmlspecialchars($selectedAgeGroup) ?>">

          <div class="mt-2" id="autoAgeGroupRow">
            <div class="reg-age-badge" id="autoAgeGroupBadge" hidden></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Дисциплины (выбираешь дисциплину и внутри выбираешь весовую категорию) -->
    <div class="card card-accent reg-card">
      <div class="card-body">
        <h2 class="reg-card-title"><?= htmlspecialchars($t('reg.disciplines')) ?></h2>
        <p class="reg-card-hint"><?= htmlspecialchars($t('reg.categories_hint', 'Отметьте дисциплины и выберите весовую категорию в каждой из них.')) ?></p>
        <p class="reg-card-hint reg-disciplines-taken-hint" id="disciplinesTakenHint" hidden>
          <?= htmlspecialchars($t('reg.disciplines_taken_hint')) ?>
        </p>

        <div class="reg-disciplines-stack" data-disciplines>
          <?php foreach ($disciplines as $d): ?>
            <?php
              $id = (int) $d['id'];
              $code = (string) ($d['code'] ?? '');
              $icon = $disciplineIcons[$code] ?? '🏆';
              $type = $disciplineTypes[$code] ?? 'Дисциплина';
              $isChecked = in_array($id, $oldDisc, true);
              $initialCat = $oldCategories[$id] ?? '';
            ?>
            <div class="reg-discipline-card<?= $isChecked ? ' is-checked' : '' ?>"
                 id="disciplineCard_<?= $id ?>"
                 data-discipline-id="<?= $id ?>"
                 data-discipline-code="<?= htmlspecialchars($code) ?>">

              <!-- Шапка дисциплины (чекбокс) -->
              <label class="reg-discipline-header" for="d<?= $id ?>">
                <input class="check" type="checkbox"
                  name="disciplines[]" value="<?= $id ?>"
                  id="d<?= $id ?>"
                  <?= $isChecked ? 'checked' : '' ?>>
                <span class="reg-choice-icon" aria-hidden="true"><?= $icon ?></span>
                <span class="reg-choice-info">
                  <span class="reg-choice-name"><?= htmlspecialchars(Lang::disciplineName($d)) ?></span>
                  <span class="reg-choice-sub"><?= htmlspecialchars($type) ?></span>
                </span>
                <span class="reg-choice-cat" data-discipline-cat="<?= $id ?>"></span>
                <span class="reg-choice-taken" hidden><?= htmlspecialchars($t('reg.discipline_taken')) ?></span>
              </label>

              <!-- Выбор весовой категории прямо внутри этой дисциплины -->
              <div class="reg-discipline-body" id="disciplineBody_<?= $id ?>" <?= $isChecked ? '' : 'hidden' ?>>
                <div class="reg-disc-weight-head">
                  <div class="reg-disc-weight-title">
                    <span aria-hidden="true">⚖️</span>
                    <span><?= htmlspecialchars($t('reg.weight_category_for_disc', 'Весовая категория:')) ?></span>
                  </div>
                  <span class="reg-disc-weight-hint" id="discAgeHint_<?= $id ?>"></span>
                </div>

                <div class="reg-disc-weight-list"
                     id="weightList_<?= $id ?>"
                     role="radiogroup"
                     aria-label="Весовая категория для <?= htmlspecialchars(Lang::disciplineName($d)) ?>">
                  <!-- Радио-кнопки категорий генерируются JavaScript на основе возрастной группы -->
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Роскошная сводка по выбранным дисциплинам -->
        <div id="selectedDisciplinesSummary" class="reg-summary-box" hidden>
          <div class="reg-summary-head">
            <div class="reg-summary-left">
              <div class="reg-summary-icon" aria-hidden="true">⚖️</div>
              <div class="reg-summary-text">
                <div class="reg-summary-title"><?= htmlspecialchars($t('reg.weight_categories', 'Зачётные весовые категории')) ?></div>
                <div class="reg-summary-sub"><?= htmlspecialchars($t('reg.weight_categories_sub', 'Категории для выбранных вами дисциплин')) ?></div>
              </div>
            </div>
            <div class="reg-summary-chips" id="summaryAthleteChips"></div>
          </div>
          <div id="categoriesList" class="reg-cat-cards"></div>
        </div>
      </div>
    </div>

    <div class="reg-actions">
      <a href="<?= htmlspecialchars($base) ?>/" class="btn-secondary"><?= htmlspecialchars($t('reg.back')) ?></a>
      <button type="submit" class="btn-primary"><?= htmlspecialchars($t('reg.submit')) ?></button>
    </div>
  </form>
</section>

<script>
  window.REGISTER_KNOWN_PEOPLE = <?= json_encode($knownPeople, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  window.COMPETITION_CONFIG = <?= json_encode($competitionConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  window.REG_I18N = <?= json_encode([
      'juniors' => $t('reg.age_group_juniors', 'Юноши — юниоры'),
      'open' => $t('reg.age_group_open', 'Открытая (Open)'),
      'age' => $t('reg.age_short', 'Возраст'),
      'group' => $t('reg.age_group', 'Возрастная группа'),
      'switchOpen' => $t('reg.switch_open', 'Выступать в Open →'),
      'switchJuniors' => $t('reg.switch_juniors', '← Вернуть юниоры'),
      'yearError' => $t('reg.error_birth_year', 'Укажите корректный год рождения (от %d до %d)'),
      'born' => Lang::locale() === 'kk' ? 'ж.т.' : 'г.р.',
      'years' => Lang::locale() === 'kk' ? 'жас' : 'лет',
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  window.REGISTER_DISCIPLINES = <?= json_encode(array_map(static fn ($d) => [
      'id'   => (int) $d['id'],
      'code' => (string) ($d['code'] ?? ''),
      'name' => Lang::disciplineName($d),
  ], $disciplines), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  window.OLD_CATEGORIES = <?= json_encode($oldCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/js/phone-mask.js"></script>
<script src="<?= htmlspecialchars($base) ?>/js/register-name.js"></script>
<script>
(function () {
  var form = document.getElementById('registerForm');
  var birthDateInput = document.getElementById('birth_date');
  var birthYearHidden = document.getElementById('birth_year');
  var ageGroupHidden = document.getElementById('age_group');
  var autoAgeBadge = document.getElementById('autoAgeGroupBadge');

  var disciplineCards = document.querySelectorAll('.reg-discipline-card[data-discipline-id]');
  var categoriesSummary = document.getElementById('selectedDisciplinesSummary');
  var categoriesList = document.getElementById('categoriesList');
  var summaryChips = document.getElementById('summaryAthleteChips');

  var cfg = window.COMPETITION_CONFIG || {};
  var disciplines = window.REGISTER_DISCIPLINES || [];
  var oldCategories = window.OLD_CATEGORIES || {};

  var disciplineIcons = {
    bench: '🏋️',
    bench_folk: '⚡',
    curl_strict: '💪',
    curl_extreme: '🔥',
    armwrestling: '🤼'
  };

  // Точные списки категорий из регламента
  var weightCategoriesConfig = {
    powerlifting: {
      juniors: [
        { label: 'до 50 кг', text: '50 кг', range: '(до 50 кг)' },
        { label: 'до 60 кг', text: '60 кг', range: '(до 60 кг)' },
        { label: 'до 67.5 кг', text: '67,5 кг', range: '(до 67,5 кг)' },
        { label: 'до 75 кг', text: '75 кг', range: '(до 75 кг)' },
        { label: 'до 82.5 кг', text: '82,5 кг', range: '(до 82,5 кг)' },
        { label: '82.5+ кг', text: '82,5+ кг', range: '(свыше 82,5 кг)' }
      ],
      open: [
        { label: 'до 60 кг', text: '60 кг', range: '(до 60 кг)' },
        { label: 'до 67.5 кг', text: '67,5 кг', range: '(до 67,5 кг)' },
        { label: 'до 75 кг', text: '75 кг', range: '(до 75 кг)' },
        { label: 'до 82.5 кг', text: '82,5 кг', range: '(до 82,5 кг)' },
        { label: 'до 90 кг', text: '90 кг', range: '(до 90 кг)' },
        { label: 'до 100 кг', text: '100 кг', range: '(до 100 кг)' },
        { label: '100+ кг', text: '100+ кг', range: '(свыше 100 кг)' }
      ]
    },
    armwrestling: {
      juniors: [
        { label: 'до 60 кг', text: '60 кг', range: '(до 60 кг)' },
        { label: 'до 75 кг', text: '75 кг', range: '(до 75 кг)' },
        { label: '80+ кг', text: '80+ кг', range: '(свыше 80 кг)' }
      ],
      open: [
        { label: 'до 70 кг', text: '70 кг', range: '(до 70 кг)' },
        { label: 'до 80 кг', text: '80 кг', range: '(до 80 кг)' },
        { label: '80+ кг', text: '80+ кг', range: '(свыше 80 кг)' }
      ]
    }
  };

  // Хранилище выбранных категорий для каждой дисциплины
  // disciplineId -> categoryLabel
  var selectedCategories = {};
  for (var k in oldCategories) {
    if (oldCategories.hasOwnProperty(k)) {
      selectedCategories[parseInt(k, 10)] = oldCategories[k];
    }
  }

  // Запомненный последний выбор для силовых (чтобы синхронизировать между жимом и бицепсом)
  var lastChosenPowerliftingCat = null;
  var lastChosenArmwrestlingCat = null;

  var currentRenderedAgeGroup = null;
  var manualGroupOverride = null;

  var regText = window.REG_I18N || {};

  function parseBirthYear(val) {
    if (!val) return null;
    val = String(val).trim();
    // 4-значный год, в том числе «1990г»
    var m = val.match(/(19\d{2}|20\d{2})(?!\d)/);
    if (m) return parseInt(m[1], 10);
    // ДД.ММ.ГГ
    var m2 = val.match(/(?:^|\D)(\d{1,2})[./-](\d{1,2})[./-](\d{2})(?!\d)/);
    if (m2) {
      var y2 = parseInt(m2[3], 10);
      return (y2 <= 30) ? 2000 + y2 : 1900 + y2;
    }
    // Ровно 2 цифры (например: 05 или 98)
    if (/^\d{2}$/.test(val)) {
      var yOnly = parseInt(val, 10);
      return (yOnly <= 30) ? 2000 + yOnly : 1900 + yOnly;
    }
    return null;
  }

  function formatAge(age) {
    if (!age && age !== 0) return '';
    if (document.documentElement.lang === 'kk') return age + ' ' + (regText.years || 'жас');
    var mod10 = age % 10;
    var mod100 = age % 100;
    if (mod100 >= 11 && mod100 <= 19) return age + ' лет';
    if (mod10 === 1) return age + ' год';
    if (mod10 >= 2 && mod10 <= 4) return age + ' года';
    return age + ' лет';
  }

  function groupYearBounds(key) {
    var eventYear = cfg.event_year || 2026;
    var g = (cfg.age_groups && cfg.age_groups[key]) ? cfg.age_groups[key] : null;
    if (!g) {
      if (key === 'juniors') return { min: eventYear - 23, max: eventYear - 14 };
      return { min: cfg.min_birth_year || 1926, max: cfg.max_birth_year || 2012 };
    }
    var minAge = g.min_age !== undefined ? g.min_age : 14;
    var maxAge = g.max_age !== undefined ? g.max_age : (key === 'juniors' ? 23 : 100);
    return {
      min: g.min_birth_year !== undefined ? Number(g.min_birth_year) : (eventYear - maxAge),
      max: g.max_birth_year !== undefined ? Number(g.max_birth_year) : (eventYear - minAge)
    };
  }

  function competitionYearBounds() {
    var openBounds = groupYearBounds('open');
    return {
      min: cfg.min_birth_year !== undefined ? Number(cfg.min_birth_year) : openBounds.min,
      max: cfg.max_birth_year !== undefined ? Number(cfg.max_birth_year) : openBounds.max
    };
  }

  function isGroupAllowed(key, birthYear) {
    if (!birthYear) return false;
    var b = groupYearBounds(key);
    return birthYear >= b.min && birthYear <= b.max;
  }

  function yearErrorText() {
    var b = competitionYearBounds();
    var tpl = regText.yearError || 'Укажите корректный год рождения (от %d до %d)';
    return tpl.replace('%d', String(b.min)).replace('%d', String(b.max));
  }

  function resolveAgeGroup(birthYear) {
    if (manualGroupOverride && isGroupAllowed(manualGroupOverride, birthYear)) {
      return manualGroupOverride;
    }
    manualGroupOverride = null;

    if (birthYear) {
      // 2003–2012 — юниоры; 1926–2002 — открытая. Оба входят в 1926–2012.
      if (isGroupAllowed('juniors', birthYear)) return 'juniors';
      if (isGroupAllowed('open', birthYear)) return 'open';
      return '';
    }

    if (ageGroupHidden && ageGroupHidden.value) {
      return ageGroupHidden.value;
    }
    return 'juniors';
  }

  function getSelectedAgeGroup() {
    return currentRenderedAgeGroup || resolveAgeGroup(parseBirthYear(birthDateInput ? birthDateInput.value : ''));
  }

  function setAgeGroup(group) {
    manualGroupOverride = group;
    updateUi();
  }

  // Отрисовка радио-списка категорий для конкретной дисциплины
  function renderWeightListForDiscipline(card) {
    var id = parseInt(card.getAttribute('data-discipline-id'), 10);
    var code = card.getAttribute('data-discipline-code');
    var isArm = (code === 'armwrestling');
    var sportType = isArm ? 'armwrestling' : 'powerlifting';

    var listEl = document.getElementById('weightList_' + id);
    var hintEl = document.getElementById('discAgeHint_' + id);
    if (!listEl) return;

    var ag = getSelectedAgeGroup();
    var catOptions = weightCategoriesConfig[sportType][ag] || weightCategoriesConfig[sportType].open;

    if (hintEl) {
      var groupTitle = (ag === 'juniors') ? (regText.juniors || 'Юноши — юниоры') : (regText.open || 'Открытая (Open)');
      hintEl.textContent = (regText.group || 'Группа') + ': ' + groupTitle;
    }

    var currentChosen = selectedCategories[id] || '';
    // Проверяем, существует ли текущая категория в новой возрастной группе
    var existsInOptions = catOptions.some(function (opt) { return opt.label === currentChosen; });

    // Если не выбрана или не существует в этой группе, но у нас есть синхронизированная:
    if (!existsInOptions) {
      var defaultSync = isArm ? lastChosenArmwrestlingCat : lastChosenPowerliftingCat;
      if (defaultSync && catOptions.some(function (opt) { return opt.label === defaultSync; })) {
        currentChosen = defaultSync;
        selectedCategories[id] = currentChosen;
      } else {
        currentChosen = '';
        delete selectedCategories[id];
      }
    }

    var html = '';
    catOptions.forEach(function (opt, idx) {
      var isChecked = (currentChosen === opt.label);
      var radioId = 'cat_' + id + '_' + idx;

      html += '<label class="reg-weight-radio-item' + (isChecked ? ' is-active' : '') + '" for="' + radioId + '">'
        + '<input type="radio" '
        + '       name="discipline_category[' + id + ']" '
        + '       id="' + radioId + '" '
        + '       value="' + escapeHtml(opt.label) + '" '
        + (isChecked ? 'checked ' : '') + '>'
        + '<span class="reg-weight-radio-text">' + escapeHtml(opt.text) + '</span>'
        + '<span class="reg-weight-radio-range">' + escapeHtml(opt.range) + '</span>'
        + '</label>';
    });

    listEl.innerHTML = html;

    // Слушатели выбора радио-кнопки
    var radios = listEl.querySelectorAll('input[type="radio"]');
    radios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (this.checked) {
          var val = this.value;
          selectedCategories[id] = val;
          card.classList.remove('has-error');

          if (isArm) {
            lastChosenArmwrestlingCat = val;
          } else {
            lastChosenPowerliftingCat = val;
            // Синхронизируем выбор для остальных отмеченных силовых дисциплин, если там еще не выбран другой
            syncPowerliftingCategories(val, id);
          }

          // Обновляем визуальное состояние радио-кнопок в этой дисциплине
          listEl.querySelectorAll('.reg-weight-radio-item').forEach(function (item) {
            var r = item.querySelector('input[type="radio"]');
            item.classList.toggle('is-active', r && r.checked);
          });

          updateUi();
        }
      });
    });
  }

  // Синхронизация выбора категории между силовыми дисциплинами
  function syncPowerliftingCategories(chosenCat, excludeId) {
    disciplineCards.forEach(function (c) {
      var did = parseInt(c.getAttribute('data-discipline-id'), 10);
      var code = c.getAttribute('data-discipline-code');
      if (did === excludeId || code === 'armwrestling') return;

      var cb = c.querySelector('input[type="checkbox"]');
      if (cb && cb.checked) {
        selectedCategories[did] = chosenCat;
        var r = c.querySelector('input[type="radio"][value="' + chosenCat + '"]');
        if (r) {
          r.checked = true;
          c.querySelectorAll('.reg-weight-radio-item').forEach(function (item) {
            var ir = item.querySelector('input[type="radio"]');
            item.classList.toggle('is-active', ir && ir.checked);
          });
        }
      }
    });
  }

  function rebuildAllDisciplineWeightLists() {
    disciplineCards.forEach(function (card) {
      renderWeightListForDiscipline(card);
    });
  }

  function updateUi() {
    var birthYear = parseBirthYear(birthDateInput ? birthDateInput.value : '');
    if (birthYearHidden) {
      birthYearHidden.value = birthYear ? String(birthYear) : '';
    }

    var eventYear = cfg.event_year || 2026;
    var yearBounds = competitionYearBounds();
    var yearOk = !birthYear || (birthYear >= yearBounds.min && birthYear <= yearBounds.max);
    var isJuniorEligible = birthYear ? isGroupAllowed('juniors', birthYear) : false;
    var age = birthYear ? (eventYear - birthYear) : null;

    var targetGroup = yearOk ? resolveAgeGroup(birthYear) : '';

    // Если группа изменилась или это начальная отрисовка — перестраиваем радио-кнопки весовых категорий во всех дисциплинах!
    if (targetGroup && targetGroup !== currentRenderedAgeGroup) {
      currentRenderedAgeGroup = targetGroup;
      if (ageGroupHidden) {
        ageGroupHidden.value = targetGroup;
      }
      rebuildAllDisciplineWeightLists();
    } else if (ageGroupHidden) {
      ageGroupHidden.value = targetGroup;
    }

    var agTitle = (targetGroup === 'juniors')
      ? (regText.juniors || 'Юноши — юниоры')
      : (regText.open || 'Открытая (Open)');

    // Бейдж возраста
    if (autoAgeBadge && birthYear && !yearOk) {
      autoAgeBadge.classList.add('is-error');
      autoAgeBadge.textContent = yearErrorText();
      autoAgeBadge.hidden = false;
    } else if (autoAgeBadge && birthYear && targetGroup) {
      autoAgeBadge.classList.remove('is-error');
      var ageStr = formatAge(age);
      var bornMark = regText.born || 'г.р.';
      var badgeHtml = '🎯 ' + escapeHtml(regText.age || 'Возраст') + ': <strong>' + ageStr + '</strong> (' + birthYear + ' ' + escapeHtml(bornMark) + ') · '
        + escapeHtml(regText.group || 'Группа') + ': <strong>' + escapeHtml(agTitle) + '</strong>';
      if (isJuniorEligible) {
        if (targetGroup === 'juniors') {
          badgeHtml += ' <button type="button" class="reg-badge-btn" data-switch-group="open">' + escapeHtml(regText.switchOpen || 'Выступать в Open →') + '</button>';
        } else {
          badgeHtml += ' <button type="button" class="reg-badge-btn" data-switch-group="juniors">' + escapeHtml(regText.switchJuniors || '← Вернуть юниоры') + '</button>';
        }
      }
      autoAgeBadge.innerHTML = badgeHtml;
      autoAgeBadge.hidden = false;

      var switchBtn = autoAgeBadge.querySelector('[data-switch-group]');
      if (switchBtn) {
        switchBtn.addEventListener('click', function () {
          setAgeGroup(this.getAttribute('data-switch-group'));
        });
      }
    } else if (autoAgeBadge) {
      autoAgeBadge.classList.remove('is-error');
      autoAgeBadge.hidden = true;
    }

    // Обработка карточек дисциплин
    var checkedDiscs = [];
    disciplineCards.forEach(function (card) {
      var id = parseInt(card.getAttribute('data-discipline-id'), 10);
      var code = card.getAttribute('data-discipline-code');
      var cb = card.querySelector('input[type="checkbox"]');
      var bodyEl = document.getElementById('disciplineBody_' + id);
      var catBadge = card.querySelector('.reg-choice-cat');

      var isChecked = cb && cb.checked && !cb.disabled;
      card.classList.toggle('is-checked', isChecked);

      if (bodyEl) {
        bodyEl.hidden = !isChecked;
      }

      var chosenCat = selectedCategories[id] || '';

      if (isChecked) {
        var discObj = disciplines.find(function (d) { return d.id === id; });
        if (discObj) checkedDiscs.push({ disc: discObj, cat: chosenCat });

        if (catBadge) {
          if (chosenCat) {
            catBadge.textContent = '⚡ ' + chosenCat;
            catBadge.classList.remove('is-hint', 'is-pending');
            catBadge.hidden = false;
          } else {
            catBadge.textContent = '⚖️ Выберите категорию';
            catBadge.classList.add('is-hint');
            catBadge.classList.remove('is-pending');
            catBadge.hidden = false;
          }
        }
      } else {
        if (catBadge) {
          catBadge.textContent = '';
          catBadge.hidden = true;
        }
      }
    });

    // Сводка заявки внизу
    if (categoriesSummary && categoriesList) {
      if (checkedDiscs.length > 0) {
        if (summaryChips) {
          var ageNote = age ? ' (' + formatAge(age) + ')' : '';
          var groupLabel = targetGroup ? (agTitle + ageNote) : yearErrorText();
          var chipsHtml = '<span class="reg-chip">🏆 ' + escapeHtml(groupLabel) + '</span>';
          var uniqueCats = [];
          checkedDiscs.forEach(function (item) {
            if (item.cat && uniqueCats.indexOf(item.cat) === -1) uniqueCats.push(item.cat);
          });
          uniqueCats.forEach(function (c) {
            chipsHtml += '<span class="reg-chip">⚖️ ' + escapeHtml(c) + '</span>';
          });
          summaryChips.innerHTML = chipsHtml;
        }

        var html = '';
        checkedDiscs.forEach(function (item) {
          var disc = item.disc;
          var cat = item.cat;
          var isArm = (disc.code === 'armwrestling');
          var icon = disciplineIcons[disc.code] || '🏆';

          html += '<div class="reg-cat-card' + (!cat ? ' is-pending' : '') + '">'
            + '  <div class="reg-cat-left">'
            + '    <div class="reg-cat-title-row">'
            + '      <span class="reg-cat-icon" aria-hidden="true">' + icon + '</span>'
            + '      <div class="reg-cat-name">' + escapeHtml(disc.name) + '</div>'
            + '    </div>'
            + '    <div class="reg-cat-meta">'
            + '      <span class="reg-meta-pill">🏆 ' + escapeHtml(targetGroup ? (agTitle + (age ? ' (' + formatAge(age) + ')' : '')) : yearErrorText()) + '</span>'
            + '      <span class="reg-meta-pill">' + (isArm ? '🤼 Армрестлинг' : '🏋️ Силовые') + '</span>'
            + '    </div>'
            + '  </div>'
            + '  <div class="reg-cat-right">'
            + '    <div class="reg-cat-tag">' + (cat ? 'ЗАЧЁТНАЯ КАТЕГОРИЯ' : 'ТРЕБУЕТСЯ ВЫБОР') + '</div>'
            + '    <div class="reg-cat-pill' + (!cat ? ' is-pending' : '') + '">'
            + (cat ? '<span class="reg-cat-pill-icon" aria-hidden="true">⚡</span> ' + escapeHtml(cat) : '⚠️ Выберите категорию')
            + '    </div>'
            + '  </div>'
            + '</div>';
        });

        categoriesList.innerHTML = html;
        categoriesSummary.hidden = false;
      } else {
        categoriesSummary.hidden = true;
      }
    }
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Слушатели событий
  if (birthDateInput) {
    birthDateInput.addEventListener('input', function () {
      manualGroupOverride = null;
      updateUi();
    });
  }



  disciplineCards.forEach(function (card) {
    var cb = card.querySelector('input[type="checkbox"]');
    if (cb) {
      cb.addEventListener('change', function () {
        var id = parseInt(card.getAttribute('data-discipline-id'), 10);
        var code = card.getAttribute('data-discipline-code');
        var isArm = (code === 'armwrestling');

        if (this.checked) {
          // Если еще нет выбранной категории для этой дисциплины:
          if (!selectedCategories[id]) {
            var syncCat = isArm ? lastChosenArmwrestlingCat : lastChosenPowerliftingCat;
            if (syncCat) {
              selectedCategories[id] = syncCat;
              var r = card.querySelector('input[type="radio"][value="' + syncCat + '"]');
              if (r) {
                r.checked = true;
                card.querySelectorAll('.reg-weight-radio-item').forEach(function (item) {
                  var ir = item.querySelector('input[type="radio"]');
                  item.classList.toggle('is-active', ir && ir.checked);
                });
              }
            }
          }
        }
        updateUi();
      });
    }
  });

  // Валидация перед отправкой формы
  if (form) {
    form.addEventListener('submit', function (e) {
      updateUi();
      var submitYear = parseBirthYear(birthDateInput ? birthDateInput.value : '');
      var submitBounds = competitionYearBounds();
      if (submitYear && (submitYear < submitBounds.min || submitYear > submitBounds.max)) {
        e.preventDefault();
        if (birthDateInput) {
          birthDateInput.focus();
          birthDateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        alert(yearErrorText());
        return;
      }

      var checkedCards = [];
      disciplineCards.forEach(function (card) {
        var cb = card.querySelector('input[type="checkbox"]');
        if (cb && cb.checked && !cb.disabled) {
          checkedCards.push(card);
        }
      });

      if (checkedCards.length === 0) {
        e.preventDefault();
        alert('Пожалуйста, отметьте хотя бы одну дисциплину для участия.');
        return;
      }

      // Проверяем, что для каждой выбранной дисциплины выбрана категория
      for (var i = 0; i < checkedCards.length; i++) {
        var card = checkedCards[i];
        var did = parseInt(card.getAttribute('data-discipline-id'), 10);
        var chosen = selectedCategories[did];

        if (!chosen) {
          e.preventDefault();
          card.classList.add('has-error');
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          var name = card.querySelector('.reg-choice-name') ? card.querySelector('.reg-choice-name').textContent : 'дисциплины';
          alert('Пожалуйста, выберите весовую категорию для: ' + name);
          return;
        }
      }
    });
  }

  // Первоначальное построение и обновление
  updateUi();
})();
</script>
