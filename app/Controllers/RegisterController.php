<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Lang\Lang;
use App\Models\Discipline;
use App\Models\Participant;
use App\Models\WeightCategory;

class RegisterController extends Controller
{
    public function form(): void
    {
        $competition = WeightCategory::competitionConfig($this->config['competition'] ?? null);
        $ageGroups = $competition['age_groups'];
        $weightCategories = $competition['weight_categories'] ?? WeightCategory::defaultRules();

        $this->view('register/form', [
            'disciplines'       => Discipline::all(),
            'ageGroups'         => $ageGroups,
            'weightCategories'  => $weightCategories,
            'competitionConfig' => $competition,
            'knownPeople'       => Participant::forRegisterSuggest(),
            'flash'             => $this->takeFlash(),
            'old'               => $_SESSION['old'] ?? [],
        ]);
        unset($_SESSION['old']);
    }

    public function store(): void
    {
        // 1. CSRF защита
        if (!$this->validateCsrf()) {
            $this->flash('danger', Lang::t('reg.error_csrf', 'Ошибка безопасности (недействительный токен). Обновите страницу и попробуйте снова.'));
            $this->redirect('/register');
        }

        $competition = WeightCategory::competitionConfig($this->config['competition'] ?? null);
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bw = str_replace(',', '.', trim($_POST['body_weight'] ?? ''));
        $gender = $_POST['gender'] ?? 'M';
        $birthDateRaw = trim($_POST['birth_date'] ?? '');
        $birthYearRaw = trim($_POST['birth_year'] ?? '');
        $ageGroupKey = trim($_POST['age_group'] ?? '');
        $disciplineIds = array_map('intval', $_POST['disciplines'] ?? []);

        $_SESSION['old'] = $_POST;

        // 2. Валидация ФИО
        if ($name === '') {
            $this->flash('danger', Lang::t('reg.error_name', 'Укажите ФИО'));
            $this->redirect('/register');
        }

        // 3. Собственный вес — теперь необязателен (участник выбирает весовую категорию)
        $bodyWeight = ($bw !== '' && is_numeric($bw)) ? round((float) $bw, 2) : null;

        // 4. Валидация даты / года рождения (открытая группа: 1926–2012)
        $minBirthYear = (int) ($competition['min_birth_year'] ?? 1926);
        $maxBirthYear = (int) ($competition['max_birth_year'] ?? 2012);
        $birthYear = WeightCategory::parseBirthYear($birthDateRaw);
        if ($birthYear === null) {
            $birthYear = WeightCategory::parseBirthYear($birthYearRaw);
        }

        $birthEntered = $birthDateRaw !== '' || $birthYearRaw !== '';
        if ($birthEntered && ($birthYear === null || $birthYear < $minBirthYear || $birthYear > $maxBirthYear)) {
            $this->flash(
                'danger',
                sprintf(
                    Lang::t('reg.error_birth_year', 'Укажите корректный год рождения (от %d до %d)'),
                    $minBirthYear,
                    $maxBirthYear
                )
            );
            $this->redirect('/register');
        }

        // 5. Возрастная группа: явный выбор, если год подходит, иначе первая допустимая.
        // Год 1926–2012 всегда попадает хотя бы в открытую группу.
        $ageGroups = $competition['age_groups'] ?? [];
        $ageGroupKey = WeightCategory::resolveAgeGroup($ageGroupKey, $birthYear, $competition);

        if ($birthYear !== null && !WeightCategory::isAgeGroupAllowed($ageGroupKey, $birthYear, $competition)) {
            $bounds = WeightCategory::ageGroupBounds($ageGroupKey, $competition);
            $groupName = $ageGroups[$ageGroupKey]['name'] ?? $ageGroupKey;
            $this->flash(
                'danger',
                sprintf(
                    Lang::t('reg.error_age_group_ineligible', 'Возрастная группа «%s» недоступна для этого года рождения. Допустимые годы: %d–%d.'),
                    $groupName,
                    $bounds['min'],
                    $bounds['max']
                )
            );
            $this->redirect('/register');
        }

        if (!in_array($gender, ['M', 'F'], true)) {
            $gender = 'M';
        }

        // 6. Проверка допустимости дисциплин
        $allDisciplines = Discipline::all();
        $validDiscMap = [];
        foreach ($allDisciplines as $d) {
            $validDiscMap[(int) $d['id']] = $d;
        }

        $disciplineIds = array_values(array_unique(array_filter(
            $disciplineIds,
            static fn (int $id): bool => isset($validDiscMap[$id])
        )));

        if (empty($disciplineIds)) {
            $this->flash('danger', Lang::t('reg.error_disciplines'));
            $this->redirect('/register');
        }

        // 7. Проверка повторной регистрации
        $taken = Participant::takenDisciplineIdsByFullName($name);
        $takenMap = array_fill_keys($taken, true);
        $available = array_values(array_filter(
            $disciplineIds,
            static fn (int $id): bool => !isset($takenMap[$id])
        ));

        if (empty($available)) {
            $this->flash('danger', Lang::t('reg.error_already_registered'));
            $this->redirect('/register');
        }

        // 8. Определение весовых категорий для выбранных дисциплин
        $postedCategories = $_POST['discipline_category'] ?? [];
        $fallbackPower = trim($_POST['weight_category_powerlifting'] ?? $_POST['weight_categories']['powerlifting'] ?? $_POST['weight_category'] ?? '');
        $fallbackArm = trim($_POST['weight_category_armwrestling'] ?? $_POST['weight_categories']['armwrestling'] ?? '');

        $disciplineCategories = [];
        $disciplineDisplayList = [];
        foreach ($available as $did) {
            $d = $validDiscMap[$did];
            $code = (string) ($d['code'] ?? '');
            $isArm = ($code === 'armwrestling');

            $rawCat = trim($postedCategories[$did] ?? ($isArm ? $fallbackArm : $fallbackPower));
            $normCat = WeightCategory::normalizeLabel($rawCat, $d, $ageGroupKey, $competition);

            if ($normCat === null || $normCat === '') {
                $dName = Lang::disciplineName($d);
                $this->flash(
                    'danger',
                    sprintf(Lang::t('reg.error_select_weight_for_disc', 'Пожалуйста, выберите весовую категорию для дисциплины «%s»'), $dName)
                );
                $this->redirect('/register');
            }

            $disciplineCategories[$did] = $normCat;
            $dName = Lang::disciplineName($d);
            $disciplineDisplayList[] = "{$dName} ({$normCat})";
        }

        // 9. Сохранение участника и категорий в базе данных
        try {
            $id = Participant::create([
                'full_name'       => $name,
                'phone'           => $phone,
                'body_weight'     => $bodyWeight,
                'gender'          => $gender,
                'birth_year'      => $birthYear,
                'age_group'       => $ageGroupKey,
                'status'          => Participant::STATUS_PENDING,
                'weight_category' => reset($disciplineCategories) ?: null,
            ], $available, $disciplineCategories);
        } catch (\Throwable $e) {
            $this->flash(
                'danger',
                Lang::t('reg.error_save', 'Не удалось сохранить заявку. Попробуйте ещё раз или обратитесь к организатору.')
            );
            $this->redirect('/register');
        }

        unset($_SESSION['old']);

        // 10. WhatsApp-сообщение менеджеру
        $date = (string) ($this->config['event_date'] ?? '08.11.2026');
        $place = Lang::t('event.place', (string) ($this->config['event_place'] ?? ''));
        $managerPhone = preg_replace('/\D+/', '', (string) ($this->config['manager_whatsapp'] ?? '87474844387')) ?? '';
        if (strlen($managerPhone) === 11 && str_starts_with($managerPhone, '8')) {
            $managerPhone = '7' . substr($managerPhone, 1);
        }

        $ageGroupName = $ageGroups[$ageGroupKey]['name'] ?? $ageGroupKey;
        $uniqueCats = array_values(array_unique($disciplineCategories));

        $managerMessage = implode("\n", [
            Lang::t('reg.whatsapp_message_title'),
            Lang::t('reg.whatsapp_id') . ': #' . $id,
            Lang::t('reg.whatsapp_name') . ': ' . $name,
            Lang::t('reg.whatsapp_gender') . ': ' . Lang::t($gender === 'F' ? 'reg.gender_f' : 'reg.gender_m'),
            Lang::t('reg.whatsapp_weight_category', 'Весовая категория') . ': ' . implode(', ', $uniqueCats),
            Lang::t('reg.whatsapp_age_group', 'Возрастная группа') . ': ' . $ageGroupName,
            Lang::t('reg.whatsapp_phone') . ': ' . ($phone !== '' ? $phone : Lang::t('reg.whatsapp_not_specified')),
            Lang::t('reg.whatsapp_birth_year') . ': ' . ($birthDateRaw !== '' ? $birthDateRaw : ($birthYear ? (string) $birthYear : Lang::t('reg.whatsapp_not_specified'))),
            Lang::t('reg.whatsapp_disciplines') . ': ' . implode(', ', $disciplineDisplayList),
            sprintf(Lang::t('reg.whatsapp_event'), $date),
            Lang::t('reg.whatsapp_place') . ': ' . $place,
        ]);

        $whatsappUrl = 'https://wa.me/' . $managerPhone . '?text=' . rawurlencode($managerMessage);
        $this->flash(
            'success',
            sprintf(Lang::t('reg.success_body'), $date, $place),
            [
                'modal'                  => true,
                'title'                  => Lang::t('reg.success_title'),
                'action_url'             => $whatsappUrl,
                'action_label'           => Lang::t('reg.whatsapp_send'),
                'secondary_action_url'   => '/',
                'secondary_action_label' => Lang::t('reg.success_home'),
            ]
        );
        $this->redirect('/register');
    }
}
