<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\ArmMatch;
use App\Models\Attempt;
use App\Models\BarLoader;
use App\Models\Discipline;
use App\Models\FolkBench;
use App\Models\Participant;
use App\Models\PlatformQueue;
use App\Models\PlatformState;
use App\Models\WeightCategory;

class JudgeController extends Controller
{
    public function loginForm(): void
    {
        if (!empty($_SESSION['judge_id'])) {
            $this->redirect('/judge');
        }
        $this->view('judge/login', [
            'flash' => $this->takeFlash(),
        ]);
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $st = Database::pdo()->prepare('SELECT * FROM judges WHERE username = ?');
        $st->execute([$username]);
        $judge = $st->fetch();

        // Демо: judge / judge123 (или любой хэш password_verify)
        $ok = $judge && (
            password_verify($password, $judge['password_hash'])
            || ($username === 'judge' && $password === 'judge123')
        );

        if (!$ok) {
            $this->flash('danger', 'Неверный логин или пароль');
            $this->redirect('/judge/login');
        }

        $_SESSION['judge_id'] = $judge['id'];
        $_SESSION['judge_name'] = $judge['display_name'];
        $this->redirect('/judge');
    }

    public function logout(): void
    {
        unset($_SESSION['judge_id'], $_SESSION['judge_name']);
        $this->flash('success', 'Вы вышли');
        $this->redirect('/');
    }

    public function dashboard(): void
    {
        $this->requireJudge();
        $this->platform();
    }

    public function platform(): void
    {
        $this->requireJudge();

        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));
        $discipline = Discipline::find($disciplineId) ?? $disciplines[0] ?? null;

        if ($discipline && ($discipline['scoring_type'] ?? '') === 'arm') {
            $this->redirect('/judge/arm');
        }

        $queue = $discipline
            ? PlatformQueue::forDiscipline((int) $discipline['id'])
            : [];

        $currentKey = trim($_GET['current'] ?? '');
        $current = null;
        if ($queue) {
            if ($currentKey !== '') {
                foreach ($queue as $item) {
                    $key = $item['participant_id'] . '-' . $item['attempt_no'];
                    if ($key === $currentKey) {
                        $current = $item;
                        break;
                    }
                }
            }
            $current = $current ?? $queue[0];
        }

        if ($discipline) {
            PlatformState::setCurrent(
                (int) $discipline['id'],
                $current['queue_key'] ?? null
            );
        }

        $bar = null;
        $category = null;
        $attemptsAllowed = (int) ($discipline['attempts_allowed'] ?? 3);
        $isFolk = FolkBench::isFolk($discipline);
        if ($current && (
            ($discipline['scoring_type'] ?? '') === 'weight' || $isFolk
        )) {
            $bar = BarLoader::forWeight((float) $current['declared_weight']);
        }
        if ($current) {
            $category = $current['weight_category']
                ?: WeightCategory::forDiscipline(
                    $discipline,
                    (string) ($current['age_group'] ?? 'open'),
                    (float) $current['body_weight']
                );
        }

        $orderPid = (int) ($_GET['order_pid'] ?? 0);
        $orderAttempt = (int) ($_GET['order_attempt'] ?? 0);
        $orderBase = str_replace(',', '.', trim((string) ($_GET['order_base'] ?? '')));
        $orderName = '';
        if ($orderPid > 0) {
            $op = Participant::find($orderPid);
            $orderName = $op['full_name'] ?? '';
        }

        $this->view('judge/platform', [
            'disciplines'      => $disciplines,
            'discipline'       => $discipline,
            'queue'            => $queue,
            'current'          => $current,
            'bar'              => $bar,
            'category'         => $category,
            'attemptsAllowed'  => $attemptsAllowed,
            'isFolk'           => $isFolk,
            'orderPid'         => $orderPid,
            'orderAttempt'     => $orderAttempt,
            'orderBase'        => is_numeric($orderBase) ? (float) $orderBase : null,
            'orderName'        => $orderName,
            'flash'            => $this->takeFlash(),
            'judgeName'        => $_SESSION['judge_name'] ?? 'Судья',
            'wide'             => true,
        ]);
    }

    public function protocol(): void
    {
        $this->requireJudge();

        $disciplines = Discipline::all();
        $disciplineId = (int) ($_GET['discipline'] ?? ($disciplines[0]['id'] ?? 0));
        $discipline = Discipline::find($disciplineId) ?? $disciplines[0] ?? null;

        // Армрестлинг — своя таблица мест/боёв + модалка судейства
        if ($discipline && ($discipline['scoring_type'] ?? '') === 'arm') {
            $board = ArmMatch::boardData((int) $discipline['id']);
            $this->view('judge/protocol_arm', [
                'disciplines'  => $disciplines,
                'discipline'   => $discipline,
                'armProtocol'  => ArmMatch::buildProtocol((int) $discipline['id']),
                'byCategory'   => $board['byCategory'],
                'wins'         => $board['wins'],
                'flash'        => $this->takeFlash(),
                'judgeName'    => $_SESSION['judge_name'] ?? 'Судья',
                'wide'         => true,
            ]);
            return;
        }

        $participants = $discipline
            ? Participant::registeredFor((int) $discipline['id'])
            : [];

        $attemptsMap = [];
        if ($discipline) {
            foreach (Attempt::allForDiscipline((int) $discipline['id']) as $a) {
                $attemptsMap[$a['participant_id']][$a['attempt_no']] = $a;
            }
        }

        $queue = $discipline
            ? PlatformQueue::forDiscipline((int) $discipline['id'])
            : [];

        $requestedKey = trim($_GET['current'] ?? '');
        $storedKey = $discipline
            ? PlatformState::getCurrent((int) $discipline['id'])
            : null;

        $current = null;
        if ($queue) {
            $wantKey = $requestedKey !== '' ? $requestedKey : (string) ($storedKey ?? '');
            if ($wantKey !== '') {
                foreach ($queue as $item) {
                    $key = (string) ($item['queue_key'] ?? ((int) $item['participant_id'] . '-' . (int) $item['attempt_no']));
                    if ($key === $wantKey) {
                        $current = $item;
                        break;
                    }
                }
            }
            $current = $current ?? $queue[0];
        }

        if ($discipline) {
            PlatformState::setCurrent(
                (int) $discipline['id'],
                $current['queue_key'] ?? (
                    $current
                        ? ((int) $current['participant_id'] . '-' . (int) $current['attempt_no'])
                        : null
                )
            );
        }

        $bar = null;
        $category = null;
        $attemptsAllowed = (int) ($discipline['attempts_allowed'] ?? 3);
        $isFolk = FolkBench::isFolk($discipline);
        if ($current && (
            ($discipline['scoring_type'] ?? '') === 'weight' || $isFolk
        )) {
            $bar = BarLoader::forWeight((float) $current['declared_weight']);
        }
        if ($current) {
            $category = $current['weight_category']
                ?: WeightCategory::forDiscipline(
                    $discipline,
                    (string) ($current['age_group'] ?? 'open'),
                    (float) $current['body_weight']
                );
        }

        $orderPid = (int) ($_GET['order_pid'] ?? 0);
        $orderAttempt = (int) ($_GET['order_attempt'] ?? 0);
        $orderBase = str_replace(',', '.', trim((string) ($_GET['order_base'] ?? '')));
        $orderName = '';
        if ($orderPid > 0) {
            $op = Participant::find($orderPid);
            $orderName = $op['full_name'] ?? '';
        }

        $protocolData = $discipline
            ? \App\Models\PowerProtocol::buildProtocol($discipline, $participants, $attemptsMap, $queue, $current)
            : ['categories' => [], 'total_athletes' => 0, 'judged_attempts' => 0];

        $records = \App\Models\PowerProtocol::records($discipline['name_ru'] ?? 'Любители Жим штанги лежа без экипировки');
        $normatives = \App\Models\PowerProtocol::normatives($discipline['code'] ?? 'bench');

        $this->view('judge/dashboard', [
            'disciplines'     => $disciplines,
            'discipline'      => $discipline,
            'participants'    => $participants,
            'attemptsMap'     => $attemptsMap,
            'protocolData'    => $protocolData,
            'records'         => $records,
            'normatives'      => $normatives,
            'queue'           => $queue,
            'current'         => $current,
            'bar'             => $bar,
            'category'        => $category,
            'attemptsAllowed' => $attemptsAllowed,
            'isFolk'          => $isFolk,
            'orderPid'        => $orderPid,
            'orderAttempt'    => $orderAttempt,
            'orderBase'       => is_numeric($orderBase) ? (float) $orderBase : null,
            'orderName'       => $orderName,
            'flash'           => $this->takeFlash(),
            'judgeName'       => $_SESSION['judge_name'] ?? 'Судья',
            'wide'            => true,
        ]);
    }

    private function judgeBackUrl(int $disciplineId): string
    {
        $from = trim($_POST['from'] ?? $_GET['from'] ?? '');
        if ($from === 'protocol') {
            return '/judge/protocol?discipline=' . $disciplineId;
        }
        return '/judge?discipline=' . $disciplineId;
    }

    public function saveAttempt(): void
    {
        $this->requireJudge();

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || isset($_POST['ajax']);

        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $disciplineId  = (int) ($_POST['discipline_id'] ?? 0);
        $attemptNo     = (int) ($_POST['attempt_no'] ?? 1);
        $rawValue      = str_replace(',', '.', trim($_POST['value'] ?? ''));
        $isValid       = isset($_POST['is_valid']) && ((string) $_POST['is_valid'] === '1' || $_POST['is_valid'] === 1 || $_POST['is_valid'] === true);
        $from          = trim($_POST['from'] ?? '');
        $action        = trim($_POST['action'] ?? '');

        $discipline = Discipline::find($disciplineId);
        if (!$discipline || !$participantId) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Некорректные данные']);
                exit;
            }
            $this->flash('danger', 'Некорректные данные');
            $this->redirect('/judge');
        }

        if ($action === 'clear') {
            Attempt::delete($participantId, $disciplineId, $attemptNo);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'             => true,
                    'action'         => 'cleared',
                    'participant_id' => $participantId,
                    'discipline_id'  => $disciplineId,
                    'attempt_no'     => $attemptNo,
                ]);
                exit;
            }
            $this->flash('success', 'Попытка очищена');
            $this->redirect('/judge/protocol?discipline=' . $disciplineId);
        }

        $value = null;
        if ($rawValue !== '' && is_numeric($rawValue)) {
            $value = round((float) $rawValue, 2);
        }

        if (!$isValid && $value === null) {
            $value = 0;
        }

        Attempt::upsert($participantId, $disciplineId, $attemptNo, $value, $isValid, $value);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'             => true,
                'participant_id' => $participantId,
                'discipline_id'  => $disciplineId,
                'attempt_no'     => $attemptNo,
                'value'          => $value,
                'value_fmt'      => $value !== null ? str_replace('.', ',', (string) $value) : '',
                'is_valid'       => $isValid,
            ]);
            exit;
        }

        $this->flash('success', 'Попытка сохранена');
        if ($from === 'platform') {
            $this->redirect('/judge?discipline=' . $disciplineId);
        }
        $this->redirect('/judge/protocol?discipline=' . $disciplineId);
    }

    public function judgeLift(): void
    {
        $this->requireJudge();

        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $disciplineId  = (int) ($_POST['discipline_id'] ?? 0);
        $attemptNo     = (int) ($_POST['attempt_no'] ?? 1);
        $rawWeight     = str_replace(',', '.', trim($_POST['weight'] ?? ''));
        $rawReps       = str_replace(',', '.', trim($_POST['reps'] ?? ''));
        $result        = trim($_POST['result'] ?? '');
        $mode          = trim($_POST['mode'] ?? 'weight');

        $back = $this->judgeBackUrl($disciplineId);
        $discipline = Discipline::find($disciplineId);

        if (!$participantId || !$discipline) {
            $this->flash('danger', 'Некорректные данные попытки');
            $this->redirect($back);
        }

        // Повторы (народный жим): value = кол-во подъёмов
        if ($mode === 'reps' || ($discipline['scoring_type'] ?? '') === 'reps') {
            if ($rawReps === '' || !is_numeric($rawReps) || (float) $rawReps < 0) {
                $this->flash('danger', 'Укажите количество повторений');
                $this->redirect($back);
            }
            $barWeight = null;
            if (is_numeric($rawWeight) && (float) $rawWeight > 0) {
                $barWeight = (float) $rawWeight;
            } elseif (FolkBench::isFolk($discipline)) {
                $p = Participant::find($participantId);
                $barWeight = FolkBench::barWeight((float) ($p['body_weight'] ?? 0));
            }
            $reps = (float) $rawReps;
            Attempt::judgeReps(
                $participantId,
                $disciplineId,
                $attemptNo,
                $reps,
                $barWeight,
                $reps > 0
            );
            PlatformQueue::clearDeferForJudged($disciplineId, $participantId, $attemptNo);
            $this->flash(
                'success',
                'Поднял: ' . (int) $reps . ' раз'
                . ($barWeight !== null
                    ? ' · штанга ' . number_format($barWeight, 1, '.', '') . ' кг'
                    : '')
            );
            $this->redirect($back . (str_contains($back, 'protocol') ? '&open_stage=1' : ''));
        }

        if (!is_numeric($rawWeight) || (float) $rawWeight <= 0) {
            $this->flash('danger', 'Некорректные данные попытки');
            $this->redirect($back);
        }
        if (!in_array($result, ['good', 'bad'], true)) {
            $this->flash('danger', 'Укажите зачёт или незачёт');
            $this->redirect($back);
        }

        $weight = (float) $rawWeight;
        Attempt::judge(
            $participantId,
            $disciplineId,
            $attemptNo,
            $weight,
            $result === 'good'
        );
        PlatformQueue::clearDeferForJudged($disciplineId, $participantId, $attemptNo);

        $label = $result === 'good' ? 'Зачёт (белая)' : 'Незачёт (красная)';
        $max = (int) ($discipline['attempts_allowed'] ?? 3);
        $next = $attemptNo + 1;

        if ($next <= $max) {
            $this->flash('success', $label . ' · закажите вес на попытку #' . $next);
            $this->redirect(
                $back
                . '&order_pid=' . $participantId
                . '&order_attempt=' . $next
                . '&order_base=' . rawurlencode(number_format($weight, 1, '.', ''))
                . '&open_stage=1'
            );
        }

        $this->flash('success', $label . ' · попытка ' . $attemptNo . ' (последняя)');
        $this->redirect($back . (str_contains($back, 'protocol') ? '&open_stage=1' : ''));
    }

    public function deferLift(): void
    {
        $this->requireJudge();

        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $disciplineId  = (int) ($_POST['discipline_id'] ?? 0);
        $attemptNo     = (int) ($_POST['attempt_no'] ?? 1);
        $action        = trim($_POST['action'] ?? 'defer');

        $back = $this->judgeBackUrl($disciplineId);
        if (!$participantId || !$disciplineId) {
            $this->flash('danger', 'Некорректные данные');
            $this->redirect($back);
        }

        $key = $participantId . '-' . $attemptNo;
        $participant = Participant::find($participantId);
        $name = $participant['full_name'] ?? ('#' . $participantId);

        $stageQs = str_contains($back, 'protocol') ? '&open_stage=1' : '';

        if ($action === 'undefer') {
            PlatformQueue::undefer($disciplineId, $key);
            $this->flash('success', $name . ' снова в очереди');
            $this->redirect($back . '&current=' . urlencode($key) . $stageQs);
        }

        PlatformQueue::defer($disciplineId, $key);
        $this->flash('warning', $name . ' — ещё не пришёл, вызван следующий');
        $this->redirect($back . $stageQs);
    }

    public function orderAttempt(): void
    {
        $this->requireJudge();

        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $disciplineId  = (int) ($_POST['discipline_id'] ?? 0);
        $attemptNo     = (int) ($_POST['attempt_no'] ?? 0);
        $rawWeight     = str_replace(',', '.', trim($_POST['weight'] ?? ''));

        $back = $this->judgeBackUrl($disciplineId);

        $discipline = Discipline::find($disciplineId);
        $max = (int) ($discipline['attempts_allowed'] ?? 3);
        if (!$participantId || !$discipline || $attemptNo < 2 || $attemptNo > $max) {
            $this->flash('danger', 'Некорректный номер попытки');
            $this->redirect($back);
        }
        if (!is_numeric($rawWeight) || (float) $rawWeight <= 0) {
            $this->flash('danger', 'Укажите вес на следующую попытку');
            $this->redirect($back);
        }

        $prev = Attempt::find($participantId, $disciplineId, $attemptNo);
        $hadDeclared = $prev
            && $prev['declared_weight'] !== null
            && $prev['declared_weight'] !== ''
            && (float) $prev['declared_weight'] > 0
            && ($prev['value'] === null || $prev['value'] === '');

        // Нельзя менять вес уже зафиксированной попытки
        if ($prev && $prev['value'] !== null && $prev['value'] !== '') {
            $this->flash('danger', 'Попытка #' . $attemptNo . ' уже зафиксирована');
            $this->redirect($back . (str_contains($back, 'protocol') ? '&open_stage=1' : ''));
        }

        Attempt::declareWeight($participantId, $disciplineId, $attemptNo, (float) $rawWeight);

        $weightLabel = number_format((float) $rawWeight, 1, '.', '') . ' кг';
        $isLast = $attemptNo >= $max;
        $isBiceps = in_array((string) ($discipline['code'] ?? ''), ['curl_strict', 'curl_extreme'], true);
        $verb = ($hadDeclared && $isLast && $isBiceps) ? 'Перезаказан' : 'Заказан';

        $this->flash('success', $verb . ' вес на попытку #' . $attemptNo . ': ' . $weightLabel);

        $stageQs = str_contains($back, 'protocol') ? '&open_stage=1' : '';
        // После перезаказа последнего подхода остаёмся на этом атлете
        if ($hadDeclared && $isLast) {
            $this->redirect(
                $back
                . '&current=' . urlencode($participantId . '-' . $attemptNo)
                . $stageQs
            );
        }
        $this->redirect($back . $stageQs);
    }

    public function arm(): void
    {
        $this->requireJudge();

        $discipline = ArmMatch::discipline();
        if (!$discipline) {
            $this->flash('danger', 'Дисциплина армрестлинга не найдена');
            $this->redirect('/judge');
        }

        // Судейство — модалка на протоколе
        $this->redirect('/judge/protocol?discipline=' . (int) $discipline['id'] . '&open_arm=1');
    }

    private function armBackUrl(): string
    {
        $discipline = ArmMatch::discipline();
        $did = (int) ($discipline['id'] ?? 0);
        if (($_POST['return'] ?? '') === 'protocol' && $did > 0) {
            return '/judge/protocol?discipline=' . $did . '&open_arm=1';
        }
        return $did > 0
            ? '/judge/protocol?discipline=' . $did . '&open_arm=1'
            : '/judge';
    }

    public function armGenerate(): void
    {
        $this->requireJudge();

        $discipline = ArmMatch::discipline();
        $category = trim($_POST['category'] ?? '');
        if (!$discipline || $category === '') {
            $this->flash('danger', 'Укажите категорию');
            $this->redirect($this->armBackUrl());
        }

        try {
            $result = ArmMatch::generatePairs((int) $discipline['id'], $category);
            $msg = 'Раунд ' . $result['round'] . ': ' . $result['created'] . ' пар';
            if ($result['byes'] > 0) {
                $msg .= ' (bye: ' . $result['byes'] . ')';
            }
            $this->flash('success', $msg . ' · ' . $category);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect($this->armBackUrl());
    }

    public function armWinner(): void
    {
        $this->requireJudge();

        $matchId = (int) ($_POST['match_id'] ?? 0);
        $winnerId = (int) ($_POST['winner_id'] ?? 0);
        $action = trim($_POST['action'] ?? 'win');

        try {
            if ($action === 'clear') {
                ArmMatch::clearResult($matchId);
                $this->flash('warning', 'Результат боя сброшен');
            } else {
                ArmMatch::setWinner($matchId, $winnerId);
                ArmMatch::setCurrent($matchId);
                $p = Participant::find($winnerId);
                $this->flash('success', 'Победитель: ' . ($p['full_name'] ?? ('#' . $winnerId)));
            }
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect($this->armBackUrl());
    }

    public function armCurrent(): void
    {
        $this->requireJudge();

        $matchId = (int) ($_POST['match_id'] ?? 0);
        try {
            ArmMatch::setCurrent($matchId);
            $this->flash('success', 'Бой на табло');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect($this->armBackUrl());
    }

    public function weighInForm(): void
    {
        $this->requireJudge();

        $filter = trim($_GET['filter'] ?? 'pending');
        if (!in_array($filter, ['pending', 'done', 'all'], true)) {
            $filter = 'pending';
        }
        $q = trim($_GET['q'] ?? '');
        $disciplineId = (int) ($_GET['discipline'] ?? 0);
        $discipline = $disciplineId > 0 ? Discipline::find($disciplineId) : null;
        if (!$discipline) {
            $disciplineId = 0;
        }

        $disciplines = Participant::weighInDisciplineCounts();
        foreach ($disciplines as &$row) {
            $row['count'] = match ($filter) {
                'pending' => $row['pending'],
                'done'    => $row['done'],
                default   => $row['count'],
            };
        }
        unset($row);

        $this->view('judge/weigh_in', [
            'participants' => $disciplineId > 0 || $q !== ''
                ? Participant::forWeighIn(
                    $filter,
                    $q,
                    $disciplineId > 0 ? $disciplineId : null
                )
                : [],
            'disciplines'  => $disciplines,
            'discipline'   => $discipline,
            'counts'       => Participant::weighInCounts($disciplineId > 0 ? $disciplineId : null),
            'filter'       => $filter,
            'q'            => $q,
            'flash'        => $this->takeFlash(),
            'judgeName'    => $_SESSION['judge_name'] ?? 'Судья',
            'wide'         => true,
        ]);
    }

    public function weighInCard(string $id): void
    {
        $this->requireJudge();

        $participant = Participant::find((int) $id);
        if (!$participant || ($participant['status'] ?? '') !== Participant::STATUS_APPROVED) {
            $this->flash('danger', 'Участник не найден или заявка не принята');
            $this->redirect('/judge/weigh-in');
        }

        $regs = Participant::registrationsWithDisciplines((int) $id);
        $filter = trim($_GET['filter'] ?? 'pending');
        $q = trim($_GET['q'] ?? '');
        $disciplineId = (int) ($_GET['discipline'] ?? 0);

        if ($disciplineId > 0) {
            foreach ($regs as $reg) {
                if ((int) $reg['discipline_id'] === $disciplineId
                    && trim((string) ($reg['weight_category'] ?? '')) !== ''
                ) {
                    $participant['discipline_weight_category'] = $reg['weight_category'];
                    break;
                }
            }
        }

        $this->view('judge/weigh_in_card', [
            'participant'  => $participant,
            'registrations'=> $regs,
            'disciplines'  => Discipline::all(),
            'filter'       => $filter,
            'q'            => $q,
            'disciplineId' => $disciplineId,
            'flash'        => $this->takeFlash(),
            'judgeName'    => $_SESSION['judge_name'] ?? 'Судья',
        ]);
    }

    public function weighIn(): void
    {
        $this->requireJudge();

        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $bw = str_replace(',', '.', trim($_POST['body_weight'] ?? ''));
        $disciplineId = (int) ($_POST['discipline_id'] ?? 0);
        $listDisciplineId = (int) ($_POST['list_discipline'] ?? 0);
        $from = trim($_POST['from'] ?? '');
        $filter = trim($_POST['filter'] ?? 'pending');
        $q = trim($_POST['q'] ?? '');
        $note = trim($_POST['weigh_note'] ?? '');

        $listBack = '/judge/weigh-in?filter=' . urlencode($filter)
            . ($listDisciplineId > 0 ? '&discipline=' . $listDisciplineId : '')
            . ($q !== '' ? '&q=' . urlencode($q) : '');
        $cardBack = '/judge/weigh-in/' . $participantId
            . '?filter=' . urlencode($filter)
            . ($listDisciplineId > 0 ? '&discipline=' . $listDisciplineId : '')
            . ($q !== '' ? '&q=' . urlencode($q) : '');
        $back = $from === 'card' ? $cardBack
            : ($from === 'weigh-in' ? $listBack : '/judge?discipline=' . $disciplineId);

        $participant = Participant::find($participantId);
        if (!$participant || ($participant['status'] ?? '') !== Participant::STATUS_APPROVED) {
            $this->flash('danger', 'Участник не найден или заявка не принята');
            $this->redirect($from === 'card' || $from === 'weigh-in' ? $listBack : $back);
        }

        if (!is_numeric($bw) || (float) $bw <= 0) {
            $this->flash('danger', 'Укажите точный собственный вес');
            $this->redirect($back);
        }

        $disciplineIds = array_values(array_unique(array_map(
            'intval',
            $_POST['disciplines'] ?? []
        )));
        if ($disciplineIds === []) {
            $this->flash('danger', 'Выберите хотя бы одну дисциплину');
            $this->redirect($back);
        }

        $validIds = [];
        foreach (Discipline::all() as $d) {
            $validIds[(int) $d['id']] = true;
        }
        $disciplineIds = array_values(array_filter(
            $disciplineIds,
            static fn (int $did): bool => isset($validIds[$did])
        ));
        if ($disciplineIds === []) {
            $this->flash('danger', 'Выберите хотя бы одну дисциплину');
            $this->redirect($back);
        }

        $openings = [];
        $rawOpenings = $_POST['opening'] ?? [];
        if (is_array($rawOpenings)) {
            foreach ($rawOpenings as $did => $raw) {
                $did = (int) $did;
                if (!in_array($did, $disciplineIds, true)) {
                    continue;
                }
                $raw = str_replace(',', '.', trim((string) $raw));
                if ($raw === '') {
                    $openings[$did] = null;
                    continue;
                }
                if (!is_numeric($raw) || (float) $raw < 0) {
                    $this->flash('danger', 'Некорректный стартовый вес');
                    $this->redirect($back);
                }
                $openings[$did] = (float) $raw;
            }
        }

        try {
            Participant::saveWeighIn(
                $participantId,
                (float) $bw,
                $note !== '' ? $note : null,
                $openings,
                $disciplineIds
            );
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
            $this->redirect($back);
        }

        $updatedP = Participant::find($participantId) ?: $participant;
        $weightCategory = Participant::weighCategoryLabel($updatedP);
        $name = $participant['full_name'];
        $weight = number_format((float) $bw, 2, '.', '');
        $this->flash('success', "В протоколе: {$name} — {$weight} кг · {$weightCategory}");
        $this->redirect($listBack);
    }
}
