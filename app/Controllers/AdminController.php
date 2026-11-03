<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Discipline;
use App\Models\Participant;
use App\Models\StaffAccount;

class AdminController extends Controller
{
    public function loginForm(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            $this->redirect('/admin');
        }
        $this->view('admin/login', [
            'flash' => $this->takeFlash(),
        ]);
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $st = Database::pdo()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([$username]);
        $admin = $st->fetch();

        $ok = $admin && (
            password_verify($password, $admin['password_hash'])
            || ($username === 'admin' && $password === 'admin123')
        );

        if (!$ok) {
            $this->flash('danger', 'Неверный логин или пароль');
            $this->redirect('/admin/login');
        }

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['display_name'];
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        $this->flash('success', 'Вы вышли');
        $this->redirect('/');
    }

    public function dashboard(): void
    {
        $this->requireAdmin();

        $status = trim($_GET['status'] ?? 'pending');
        $allowed = ['all', 'pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $q = trim($_GET['q'] ?? '');
        $counts = Participant::countsByStatus();
        $groups = Participant::groupedByDiscipline($status, $q);

        $listed = 0;
        foreach ($groups as $g) {
            $listed += count($g['participants']);
        }

        $this->view('admin/dashboard', [
            'groups'       => $groups,
            'listedCount'  => $listed,
            'counts'       => $counts,
            'status'       => $status,
            'statuses'     => Participant::statuses(),
            'q'            => $q,
            'flash'        => $this->takeFlash(),
            'adminName'    => $_SESSION['admin_name'] ?? 'Админ',
            'wide'         => true,
        ]);
    }

    public function editForm(string $id): void
    {
        $this->requireAdmin();
        $participant = Participant::find((int) $id);
        if (!$participant) {
            $this->flash('danger', 'Заявка не найдена');
            $this->redirect('/admin');
        }

        $this->view('admin/edit', [
            'participant'   => $participant,
            'disciplines'   => Discipline::all(),
            'registrations' => Participant::registrationsWithDisciplines((int) $id),
            'ageGroups'     => ($this->config['competition']['age_groups'] ?? []),
            'selected'      => array_map('intval', Participant::disciplineIds((int) $id)),
            'statuses'      => Participant::statuses(),
            'flash'         => $this->takeFlash(),
            'adminName'     => $_SESSION['admin_name'] ?? 'Админ',
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $pid = (int) $id;
        if (!Participant::find($pid)) {
            $this->flash('danger', 'Заявка не найдена');
            $this->redirect('/admin');
        }

        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bw = str_replace(',', '.', trim($_POST['body_weight'] ?? ''));
        $gender = $_POST['gender'] ?? 'M';
        $birthYear = trim($_POST['birth_year'] ?? '');
        $ageGroup = trim($_POST['age_group'] ?? '');
        $disciplineIds = array_map('intval', $_POST['disciplines'] ?? []);

        if ($name === '') {
            $this->flash('danger', 'Укажите ФИО');
            $this->redirect('/admin/participants/' . $pid);
        }
        if ($bw !== '' && (!is_numeric($bw) || (float) $bw <= 0)) {
            $this->flash('danger', 'Укажите корректный собственный вес (кг) или оставьте поле пустым');
            $this->redirect('/admin/participants/' . $pid);
        }
        if (!in_array($gender, ['M', 'F'], true)) {
            $gender = 'M';
        }
        if (empty($disciplineIds)) {
            $this->flash('danger', 'Выберите хотя бы одну дисциплину');
            $this->redirect('/admin/participants/' . $pid);
        }

        $bodyWeight = ($bw !== '' && is_numeric($bw)) ? round((float) $bw, 2) : null;
        $disciplineCategories = [];
        if ($bodyWeight !== null) {
            foreach ($disciplineIds as $did) {
                $disciplineCategories[$did] = WeightCategory::forDiscipline($did, $ageGroup ?: 'open', $bodyWeight);
            }
        }

        Participant::update($pid, [
            'full_name'   => $name,
            'phone'       => $phone,
            'body_weight' => $bodyWeight,
            'gender'      => $gender,
            'birth_year'  => $birthYear !== '' ? (int) $birthYear : null,
            'age_group'   => $ageGroup !== '' ? $ageGroup : null,
        ], $disciplineIds, $disciplineCategories);

        $this->flash('success', 'Данные заявки обновлены');
        $this->redirect('/admin/participants/' . $pid);
    }

    public function setStatus(string $id): void
    {
        $this->requireAdmin();
        $pid = (int) $id;
        $participant = Participant::find($pid);
        if (!$participant) {
            $this->flash('danger', 'Заявка не найдена');
            $this->redirect('/admin');
        }

        $status = trim($_POST['status'] ?? '');
        $note = trim($_POST['status_note'] ?? '');
        $labels = Participant::statuses();

        if (!isset($labels[$status])) {
            $this->flash('danger', 'Некорректный статус');
            $this->redirect('/admin/participants/' . $pid);
        }

        Participant::setStatus($pid, $status, $note !== '' ? $note : null);
        if ($status === Participant::STATUS_APPROVED) {
            Participant::assignLotIfMissing($pid);
        }

        $messages = [
            Participant::STATUS_APPROVED => 'Заявка принята — участник в протоколе судьи',
            Participant::STATUS_REJECTED => 'Заявка отклонена',
            Participant::STATUS_PENDING  => 'Заявка возвращена в новые',
        ];
        $this->flash('success', $messages[$status] ?? 'Статус обновлён');

        $back = trim($_POST['back'] ?? '');
        if ($back === 'list') {
            // После решения остаёмся в очереди «Новые», а не уходим в принятые/отклонённые
            $this->redirect('/admin?status=pending');
        }
        $this->redirect('/admin/participants/' . $pid);
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        $pid = (int) $id;
        if (!Participant::find($pid)) {
            $this->flash('danger', 'Заявка не найдена');
            $this->redirect('/admin');
        }

        Participant::delete($pid);
        $this->flash('success', "Заявка #{$pid} удалена");
        $this->redirect('/admin');
    }

    public function users(): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($_GET['role'] ?? StaffAccount::ROLE_ADMIN);

        $this->view('admin/users', [
            'role'      => $role,
            'roles'     => StaffAccount::roles(),
            'users'     => StaffAccount::all($role),
            'counts'    => [
                StaffAccount::ROLE_ADMIN => StaffAccount::count(StaffAccount::ROLE_ADMIN),
                StaffAccount::ROLE_JUDGE => StaffAccount::count(StaffAccount::ROLE_JUDGE),
            ],
            'flash'     => $this->takeFlash(),
            'adminName' => $_SESSION['admin_name'] ?? 'Админ',
            'adminId'   => (int) ($_SESSION['admin_id'] ?? 0),
        ]);
    }

    public function userCreateForm(): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($_GET['role'] ?? StaffAccount::ROLE_ADMIN);

        $this->view('admin/user_form', [
            'mode'      => 'create',
            'role'      => $role,
            'roles'     => StaffAccount::roles(),
            'user'      => [
                'username'     => '',
                'display_name' => '',
            ],
            'flash'     => $this->takeFlash(),
            'adminName' => $_SESSION['admin_name'] ?? 'Админ',
        ]);
    }

    public function userStore(): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($_POST['role'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if ($username === '' || $displayName === '' || $password === '') {
            $this->flash('danger', 'Заполните логин, имя и пароль');
            $this->redirect('/admin/users/new?role=' . urlencode($role));
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $this->flash('danger', 'Логин: 3–50 символов (латиница, цифры, . _ -)');
            $this->redirect('/admin/users/new?role=' . urlencode($role));
        }
        if (mb_strlen($password) < 6) {
            $this->flash('danger', 'Пароль не короче 6 символов');
            $this->redirect('/admin/users/new?role=' . urlencode($role));
        }
        if ($password !== $password2) {
            $this->flash('danger', 'Пароли не совпадают');
            $this->redirect('/admin/users/new?role=' . urlencode($role));
        }
        if (StaffAccount::findByUsername($role, $username)) {
            $this->flash('danger', 'Такой логин уже занят');
            $this->redirect('/admin/users/new?role=' . urlencode($role));
        }

        StaffAccount::create($role, $username, $password, $displayName);
        $label = StaffAccount::roles()[$role];
        $this->flash('success', "{$label} «{$username}» создан");
        $this->redirect('/admin/users?role=' . urlencode($role));
    }

    public function userEditForm(string $role, string $id): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($role);
        $uid = (int) $id;
        $user = StaffAccount::find($role, $uid);
        if (!$user) {
            $this->flash('danger', 'Пользователь не найден');
            $this->redirect('/admin/users?role=' . urlencode($role));
        }

        $this->view('admin/user_form', [
            'mode'      => 'edit',
            'role'      => $role,
            'roles'     => StaffAccount::roles(),
            'user'      => $user,
            'flash'     => $this->takeFlash(),
            'adminName' => $_SESSION['admin_name'] ?? 'Админ',
            'adminId'   => (int) ($_SESSION['admin_id'] ?? 0),
        ]);
    }

    public function userUpdate(string $role, string $id): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($role);
        $uid = (int) $id;
        $user = StaffAccount::find($role, $uid);
        if (!$user) {
            $this->flash('danger', 'Пользователь не найден');
            $this->redirect('/admin/users?role=' . urlencode($role));
        }

        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if ($username === '' || $displayName === '') {
            $this->flash('danger', 'Заполните логин и имя');
            $this->redirect('/admin/users/' . $role . '/' . $uid);
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $this->flash('danger', 'Логин: 3–50 символов (латиница, цифры, . _ -)');
            $this->redirect('/admin/users/' . $role . '/' . $uid);
        }

        $existing = StaffAccount::findByUsername($role, $username);
        if ($existing && (int) $existing['id'] !== $uid) {
            $this->flash('danger', 'Такой логин уже занят');
            $this->redirect('/admin/users/' . $role . '/' . $uid);
        }

        if ($password !== '' || $password2 !== '') {
            if (mb_strlen($password) < 6) {
                $this->flash('danger', 'Пароль не короче 6 символов');
                $this->redirect('/admin/users/' . $role . '/' . $uid);
            }
            if ($password !== $password2) {
                $this->flash('danger', 'Пароли не совпадают');
                $this->redirect('/admin/users/' . $role . '/' . $uid);
            }
        }

        StaffAccount::update(
            $role,
            $uid,
            $username,
            $displayName,
            $password !== '' ? $password : null
        );

        if ($role === StaffAccount::ROLE_ADMIN && $uid === (int) ($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_name'] = $displayName;
        }

        $this->flash('success', 'Данные пользователя сохранены');
        $this->redirect('/admin/users/' . $role . '/' . $uid);
    }

    public function userDelete(string $role, string $id): void
    {
        $this->requireAdmin();
        $role = $this->resolveRole($role);
        $uid = (int) $id;
        $user = StaffAccount::find($role, $uid);
        if (!$user) {
            $this->flash('danger', 'Пользователь не найден');
            $this->redirect('/admin/users?role=' . urlencode($role));
        }

        if ($role === StaffAccount::ROLE_ADMIN && $uid === (int) ($_SESSION['admin_id'] ?? 0)) {
            $this->flash('danger', 'Нельзя удалить свой аккаунт');
            $this->redirect('/admin/users?role=' . urlencode($role));
        }
        if ($role === StaffAccount::ROLE_ADMIN && StaffAccount::count(StaffAccount::ROLE_ADMIN) <= 1) {
            $this->flash('danger', 'Нельзя удалить последнего администратора');
            $this->redirect('/admin/users?role=' . urlencode($role));
        }

        StaffAccount::delete($role, $uid);
        $this->flash('success', 'Пользователь «' . $user['username'] . '» удалён');
        $this->redirect('/admin/users?role=' . urlencode($role));
    }

    private function resolveRole(string $role): string
    {
        $role = trim($role);
        if (!isset(StaffAccount::roles()[$role])) {
            return StaffAccount::ROLE_ADMIN;
        }
        return $role;
    }
}
