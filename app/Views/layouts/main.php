<?php
use App\Lang\Lang;

$base = $config['base_url'] ?? '';
$assetRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
$twV = @filemtime($assetRoot . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'tailwind.css') ?: 1;
$appV = @filemtime($assetRoot . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'app.css') ?: 1;
$uiV = @filemtime($assetRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'ui.js') ?: 1;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($base && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}
function nav_on(string $prefix, string $path): bool {
    if ($prefix === '/') {
        return $path === '/';
    }
    return str_starts_with($path, $prefix);
}
$lang = $lang ?? Lang::locale();
$t = $t ?? static fn (string $key, ?string $fallback = null): string => Lang::t($key, $fallback);
$nav = [
    '/' => [$t('nav.home'), $base . '/'],
    '/register' => [$t('nav.register'), $base . '/register'],
    '/live' => [$t('nav.live'), $base . '/live'],
    '/judge' => [$t('nav.judge'), $base . '/judge'],
    '/admin' => [$t('nav.admin'), $base . '/admin'],
    '/results' => [$t('nav.results'), $base . '/results'],
];
$flashType = (isset($flash) && is_array($flash)) ? (string) ($flash['type'] ?? '') : '';
$flashTone = [
    'success' => 'alert-success',
    'danger' => 'alert-danger',
    'warning' => 'alert-warning',
][$flashType] ?? 'alert-warning';
$isHome = ($path === '/');
$isRegister = str_starts_with($path, '/register');
$isAdminLogin = ($path === '/admin/login');
$isJudgeLogin = ($path === '/judge/login');
$isAuthLogin = $isAdminLogin || $isJudgeLogin;
$hideChromeHeader = $isHome || $isRegister || $isAuthLogin;
$hideFooter = $isAuthLogin;
$themeToggle = static function () use ($t): void { ?>
  <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="<?= htmlspecialchars($t('theme.toggle')) ?>">
    <span class="theme-toggle-track" aria-hidden="true"><span class="theme-toggle-knob"></span></span>
    <span class="theme-toggle-text"><span class="theme-label-light"><?= htmlspecialchars($t('theme.light')) ?></span><span class="theme-label-dark"><?= htmlspecialchars($t('theme.dark')) ?></span></span>
  </button>
<?php };
$langSwitch = static function () use ($lang): void { ?>
  <div class="lang-switch" role="group" aria-label="Language">
    <a class="lang-switch-btn<?= $lang === 'ru' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Lang::switchUrl('ru')) ?>" hreflang="ru"<?= $lang === 'ru' ? ' aria-current="true"' : '' ?>>RU</a>
    <a class="lang-switch-btn<?= $lang === 'kk' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Lang::switchUrl('kk')) ?>" hreflang="kk"<?= $lang === 'kk' ? ' aria-current="true"' : '' ?>>KZ</a>
  </div>
<?php };
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang === 'kk' ? 'kk' : 'ru') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <script>
    try {
      if (localStorage.getItem('ag-theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    } catch (e) {}
  </script>
  <title><?= htmlspecialchars($config['app_name'] ?? 'Alash Gym') ?></title>
  <?php if ($isHome): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <?php endif; ?>
  <link href="<?= htmlspecialchars($base) ?>/css/tailwind.css?v=<?= (int) $twV ?>" rel="stylesheet">
  <link href="<?= htmlspecialchars($base) ?>/css/app.css?v=<?= (int) $appV ?>" rel="stylesheet">
</head>
<body class="<?= !empty($live) ? 'live-body' : 'min-h-screen bg-canvas font-sans text-ink antialiased' ?><?= $hideChromeHeader && empty($live) ? ' home-page' : '' ?>">
<?php if (empty($live) && !$hideChromeHeader): ?>
<header class="sticky top-0 z-40 border-b-[3px] border-signal bg-chrome text-on-chrome">
  <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3">
    <a class="order-1 flex items-center gap-2.5 text-lg font-semibold tracking-tight text-on-chrome no-underline" href="<?= htmlspecialchars($base) ?>/">
      <span class="inline-block size-2.5 shrink-0 bg-signal" aria-hidden="true"></span>
      Alash Gym
    </a>
    <nav id="navMenu" class="order-3 flex w-full flex-col max-lg:hidden lg:order-2 lg:w-auto lg:flex-1 lg:flex-row lg:justify-end">
      <ul class="flex flex-col gap-1 lg:flex-row lg:items-center">
        <?php foreach ($nav as $prefix => [$label, $href]): ?>
          <?php $on = nav_on($prefix, $path); ?>
          <li>
            <a class="block rounded-md px-3 py-2 text-sm font-semibold no-underline <?= $on ? 'bg-signal text-white' : 'text-on-chrome/75 hover:bg-on-chrome/10 hover:text-on-chrome' ?>"
               <?= $on ? 'aria-current="page"' : '' ?>
               href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="order-2 flex items-center gap-2 lg:order-3">
      <?php $langSwitch(); ?>
      <?php $themeToggle(); ?>
      <button class="inline-flex items-center justify-center rounded-md border border-on-chrome/30 p-2 text-on-chrome lg:hidden" type="button" id="navToggle"
              aria-controls="navMenu" aria-expanded="false" aria-label="<?= htmlspecialchars($t('menu')) ?>">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
  </div>
</header>
<?php endif; ?>

<?php
$wide = !empty($wide);
$live = !empty($live);
?>
<?php if ($live): ?>
<?= $content ?>
<?php else: ?>
<main class="<?= $hideChromeHeader ? 'home-main' : ($wide ? 'w-full px-3 py-3 lg:px-4' : 'mx-auto w-full max-w-6xl px-4 py-8') ?>">
  <?php if ($hideChromeHeader): ?>
    <div class="home-toolbar">
      <?php $langSwitch(); ?>
      <?php $themeToggle(); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($flash) && empty($flash['modal'])): ?>
    <div class="flash-slot<?= $hideChromeHeader ? ' flash-slot--chrome-free' : '' ?>">
      <div class="alert flash-alert <?= $flashTone ?>" role="alert">
        <div class="flash-alert-main">
          <?php if ($flashType === 'success'): ?>
            <span class="flash-alert-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                <path d="M5 12.5l4.2 4.2L19 7" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          <?php elseif ($flashType === 'danger'): ?>
            <span class="flash-alert-icon" aria-hidden="true">!</span>
          <?php endif; ?>
          <div class="flash-alert-text">
            <p><?= htmlspecialchars($flash['message']) ?></p>
            <?php if (!empty($flash['detail'])): ?>
              <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs opacity-80"><?= htmlspecialchars((string) $flash['detail']) ?></pre>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="flash-alert-close" data-dismiss="alert" aria-label="<?= htmlspecialchars($t('close')) ?>">×</button>
      </div>
    </div>
  <?php endif; ?>

  <?= $content ?>
</main>

<?php if (!empty($flash) && !empty($flash['modal'])): ?>
  <?php
    $flashActionUrl = (string) ($flash['action_url'] ?? '/');
    $flashActionParts = parse_url($flashActionUrl);
    $flashActionIsWhatsApp = is_array($flashActionParts)
        && ($flashActionParts['scheme'] ?? '') === 'https'
        && ($flashActionParts['host'] ?? '') === 'wa.me'
        && preg_match('#\A/\d+\z#', (string) ($flashActionParts['path'] ?? '')) === 1
        && preg_match('#\Atext=[A-Za-z0-9._~%+-]*\z#', (string) ($flashActionParts['query'] ?? '')) === 1;
    if (!$flashActionIsWhatsApp && ($flashActionUrl === '' || $flashActionUrl[0] !== '/')) {
        $flashActionUrl = '/';
    }
    $flashActionHref = $flashActionIsWhatsApp ? $flashActionUrl : $base . $flashActionUrl;
    $flashActionLabel = (string) ($flash['action_label'] ?? $t('nav.home'));
    $flashSecondaryUrl = (string) ($flash['secondary_action_url'] ?? '');
    if ($flashSecondaryUrl === '' || $flashSecondaryUrl[0] !== '/') {
        $flashSecondaryUrl = '';
    }
    $flashSecondaryHref = $base . $flashSecondaryUrl;
    $flashSecondaryLabel = (string) ($flash['secondary_action_label'] ?? '');
    $flashTitle = (string) ($flash['title'] ?? $t('reg.success_title'));
  ?>
  <div class="app-modal-backdrop" data-app-modal role="presentation">
    <div class="app-modal" role="dialog" aria-modal="true" aria-labelledby="appModalTitle">
      <div class="app-modal-icon app-modal-icon--<?= htmlspecialchars($flashType !== '' ? $flashType : 'success') ?>" aria-hidden="true">
        <?php if ($flashType === 'danger'): ?>
          !
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12.5l4.2 4.2L19 7" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        <?php endif; ?>
      </div>
      <h2 class="app-modal-title" id="appModalTitle"><?= htmlspecialchars($flashTitle) ?></h2>
      <p class="app-modal-text"><?= htmlspecialchars($flash['message']) ?></p>
      <div class="app-modal-actions">
        <a class="btn-primary app-modal-cta" href="<?= htmlspecialchars($flashActionHref) ?>"<?= $flashActionIsWhatsApp ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= htmlspecialchars($flashActionLabel) ?></a>
        <?php if ($flashSecondaryUrl !== ''): ?>
          <a class="btn-secondary app-modal-cta" href="<?= htmlspecialchars($flashSecondaryHref) ?>"><?= htmlspecialchars($flashSecondaryLabel) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (!$wide && empty($hideFooter)): ?>
<footer class="mt-16 border-t-[3px] border-signal bg-chrome py-5 text-on-chrome">
  <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-4 text-sm text-on-chrome/70">
    <div>Alash Gym · <?= htmlspecialchars($config['event_date'] ?? '') ?></div>
    <div>
      <?= htmlspecialchars($t('footer.developer')) ?>
      <a class="font-semibold text-on-chrome underline-offset-2 hover:underline"
         href="https://www.instagram.com/zshotaeff/"
         target="_blank"
         rel="noopener noreferrer">shotayev</a>
    </div>
  </div>
</footer>
<?php endif; ?>
<?php endif; ?>

<script src="<?= htmlspecialchars($base) ?>/js/ui.js?v=<?= (int) $uiV ?>"></script>
</body>
</html>
