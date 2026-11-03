<?php

use App\Lang\Lang;

$base = $config['base_url'] ?? '';
$t = $t ?? static fn(string $key, ?string $fallback = null): string => Lang::t($key, $fallback);
?>

<section class="home-hero" style="--home-hero-image: url('<?= htmlspecialchars($base) ?>/img/home-barbell-hero.jpg')">
  <div class="home-hero-veil" aria-hidden="true"></div>
  <div class="home-hero-grain" aria-hidden="true"></div>
  <div class="home-hero-inner">
    <p class="home-hero-meta">
      <time class="home-hero-date" datetime="2026-11-08"><?= htmlspecialchars($config['event_date'] ?? '') ?></time>
      <span class="home-hero-meta-sep" aria-hidden="true"></span>
      <span class="home-hero-place"><?= htmlspecialchars($t('event.place', $config['event_place'] ?? '')) ?></span>
    </p>

    <h1 class="home-hero-title">
      <span class="home-hero-brand-mark" aria-hidden="true"></span>
      <span class="home-hero-brand">
        <span class="home-hero-brand-line">ALASH</span>
        <span class="home-hero-brand-line">GYM</span>
      </span>
    </h1>

    <p class="home-hero-lead"><?= htmlspecialchars($t('home.lead')) ?></p>

    <div class="home-hero-actions">
      <a class="btn-primary home-hero-cta" href="<?= htmlspecialchars($base) ?>/register"><?= htmlspecialchars($t('home.register')) ?></a>
      <a class="home-hero-cta home-hero-cta-live" href="<?= htmlspecialchars($base) ?>/live"><?= htmlspecialchars($t('home.live')) ?></a>
    </div>
  </div>
</section>

<div class="home-body">
  <section class="home-section home-disciplines" aria-labelledby="home-disciplines-title">
    <div class="home-section-inner">
      <div class="home-section-head">
        <h2 class="home-section-title" id="home-disciplines-title"><?= htmlspecialchars($t('home.disciplines')) ?></h2>
        <span class="home-section-count" aria-hidden="true"><?= str_pad((string) count($disciplines), 2, '0', STR_PAD_LEFT) ?></span>
      </div>
      <ol class="home-disciplines-list">
        <?php foreach ($disciplines as $i => $d): ?>
          <li style="--i: <?= (int) $i ?>">
            <span class="home-disciplines-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="home-disciplines-name"><?= htmlspecialchars(Lang::disciplineName($d)) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <section class="home-section home-day" aria-labelledby="home-day-title">
    <div class="home-section-inner">
      <h2 class="home-section-title" id="home-day-title"><?= htmlspecialchars($t('home.day_title')) ?></h2>
      <ol class="home-day-list">
        <li style="--i: 0">
          <span class="home-day-step" aria-hidden="true">01</span>
          <span class="home-day-text"><?= htmlspecialchars($t('home.day_1')) ?></span>
        </li>
        <li style="--i: 1">
          <span class="home-day-step" aria-hidden="true">02</span>
          <span class="home-day-text"><?= htmlspecialchars($t('home.day_2')) ?></span>
        </li>
        <li style="--i: 2">
          <span class="home-day-step" aria-hidden="true">03</span>
          <span class="home-day-text"><?= htmlspecialchars($t('home.day_3')) ?></span>
        </li>
      </ol>
    </div>
  </section>

  <section class="home-section home-join" aria-labelledby="home-join-title">
    <div class="home-section-inner home-join-inner">
      <div class="home-join-copy">
        <h2 class="home-join-title" id="home-join-title"><?= htmlspecialchars($t('home.join_title')) ?></h2>
        <p class="home-join-text"><?= htmlspecialchars($t('home.join_text')) ?></p>
      </div>
      <a class="btn-primary home-join-cta" href="<?= htmlspecialchars($base) ?>/register"><?= htmlspecialchars($t('home.join_cta')) ?></a>
    </div>
  </section>
</div>
