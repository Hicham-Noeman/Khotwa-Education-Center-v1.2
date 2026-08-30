<?php
declare(strict_types=1);

define('KHOTWA_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/homepage-data.php';

$homepageData = [
    'content' => [],
    'slides' => [],
    'statistics' => [],
    'team' => [],
    'gallery' => [],
    'partners' => [],
    'contacts' => [],
    'reviews' => [],
    'settings' => [],
];
$homepageDataLoaded = false;

try {
    $homepageData = load_homepage_data(getDatabaseConnection());
    $homepageDataLoaded = true;
} catch (Throwable $exception) {
    // The built-in markup remains available when MySQL is temporarily offline.
}

function homepage_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * One stored contact row by its key, or null when the database is unreachable or the
 * row has been switched off.
 *
 * index.js refreshes these from the API on load; reading them here as well means the
 * correct phone number and address are in the HTML as delivered, which is what a
 * search engine indexes and what a visitor without JavaScript sees.
 */
/**
 * Five stars with the filled part clipped to the exact score, so 4.3 reads as 4.3
 * rather than being rounded up to five full stars.
 */
function homepage_star_meter(float $rating, string $extraClass = ''): string
{
    $rating = max(0.0, min(5.0, $rating));
    $percent = round(($rating / 5) * 100, 1);
    $label = rtrim(rtrim(number_format($rating, 1), '0'), '.') . ' out of 5';
    $class = 'star-meter' . ($extraClass === '' ? '' : ' ' . $extraClass);

    return '<span class="' . homepage_e($class) . '" role="img" aria-label="' . homepage_e($label) . '">'
        . '<span class="star-meter-base" aria-hidden="true">★★★★★</span>'
        . '<span class="star-meter-fill" style="width: ' . homepage_e((string) $percent) . '%" aria-hidden="true">'
        . '★★★★★</span>'
        . '</span>';
}

function homepage_contact(array $contacts, string $key): ?array
{
    foreach ($contacts as $row) {
        if (($row['link_key'] ?? '') === $key) {
            return $row;
        }
    }

    return null;
}

function homepage_contact_value(array $contacts, string $key, string $fallback): string
{
    $row = homepage_contact($contacts, $key);

    return $row === null ? $fallback : (string) $row['value_en'];
}

function homepage_contact_url(array $contacts, string $key, string $fallback): string
{
    $row = homepage_contact($contacts, $key);
    $url = $row === null ? '' : (string) ($row['url'] ?? '');

    return $url === '' ? $fallback : $url;
}

// The admissions banner is switched on and off from the admin website workspace.
// When the database is unreachable the banner stays visible, like the rest of the fallback markup.
$admissionsBannerVisible = !$homepageDataLoaded
    || (string) ($homepageData['settings']['admissions_banner_visible'] ?? '1') === '1';
$homepageReviews = $homepageData['reviews'] ?? [];
$homepageContacts = $homepageData['contacts'] ?? [];

// Falling back to the center's real details rather than a placeholder, so a database
// outage cannot put a wrong phone number in front of a visitor.
$contactPhone = homepage_contact_value($homepageContacts, 'primary_phone', '+961 79 42 79 40');
$contactPhoneUrl = homepage_contact_url($homepageContacts, 'primary_phone', 'tel:+96179427940');
$contactAddress = homepage_contact_value($homepageContacts, 'address', 'Tripoli, Lebanon');
$contactHours = homepage_contact_value($homepageContacts, 'opening_hours', 'Mon-Thu & Sat, 3:00-8:00 PM');
$contactMapUrl = homepage_contact_url($homepageContacts, 'google_map', 'https://maps.google.com/?q=Tripoli%2C+Lebanon');
$contactWhatsappUrl = homepage_contact_url($homepageContacts, 'whatsapp', 'https://wa.me/96179427940');

// The hero rating card and the "family satisfaction" counter share one aggregate
// score, taken from the approved parent reviews. With no approved reviews there is no
// score, and the card is left out rather than showing an invented one.
$homepageRatingCount = (int) ($homepageData['metrics']['rating_count'] ?? 0);
$homepageRating = $homepageRatingCount > 0
    ? (float) $homepageData['metrics']['rating_average']
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Khotwa Education Center helps students from Grade 1 till 12 build confidence, skills, and lasting academic progress.">
  <meta name="theme-color" content="#223F6B">
  <title>Khotwa Education Center | Every Step Builds a Future</title>
  <link rel="preload" href="<?= homepage_e(khotwa_url('assets/images/khotwa-hero.webp')) ?>" as="image" type="image/webp" fetchpriority="high">
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= homepage_e(khotwa_asset('css/index.css')) ?>">
</head>
<body>
  <div class="page-loader" aria-hidden="true">
    <div class="loader-mark"><img src="<?= homepage_e(khotwa_url('assets/images/logo-white.svg')) ?>" alt="Khotwa Education Center" width="162" height="54"><i></i></div>
    <p>Building brighter steps</p>
  </div>

  <div class="scroll-progress" aria-hidden="true"><span></span></div>
  <div class="cursor-glow" aria-hidden="true"></div>

  <header class="site-header" id="top">
    <a class="brand" href="#home" aria-label="Khotwa Education Center home">
      <img class="brand-logo brand-logo-white" src="<?= homepage_e(khotwa_url('assets/images/logo-white.svg')) ?>" alt="Khotwa Education Center" width="148" height="71">
      <img class="brand-logo brand-logo-color" src="<?= homepage_e(khotwa_url('assets/images/logo-color.svg')) ?>" alt="Khotwa Education Center" width="148" height="71">
    </a>

    <nav class="desktop-nav" aria-label="Main navigation">
      <a href="#about">About</a>
      <a href="#approach">Approach</a>
      <a href="#programs">Programs</a>
      <a href="#team">Team</a>
      <a href="#gallery">Gallery</a>
      <a href="#faq">FAQ</a>
    </nav>

    <div class="header-actions">
      <button class="language-switch" type="button" data-language-toggle>
        <span data-language-current>EN</span>
        <i></i>
        <span data-language-label>العربية</span>
      </button>
      <a class="header-login" href="<?= homepage_e(khotwa_url('login.php')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="9" cy="8" r="3.5"/>
          <path d="M3.5 20c.5-4 2.4-6 5.5-6s5 2 5.5 6M14 12h7M18 9l3 3-3 3"/>
        </svg>
        <span>Log in</span>
      </a>
      <a class="header-action magnetic" href="#contact">
        <span>Start a conversation</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M5 12h14M13 6l6 6-6 6"/>
        </svg>
      </a>
    </div>

    <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false">
      <span></span><span></span>
    </button>
  </header>

  <div class="mobile-menu" aria-hidden="true">
    <nav aria-label="Mobile navigation">
      <a href="#about"><span>01</span>About</a>
      <a href="#approach"><span>02</span>Approach</a>
      <a href="#programs"><span>03</span>Programs</a>
      <a href="#team"><span>04</span>Team</a>
      <a href="#gallery"><span>05</span>Gallery</a>
      <a href="#faq"><span>06</span>FAQ</a>
      <a class="mobile-terms-link" href="<?= homepage_e(khotwa_url('terms.php')) ?>"><span>07</span>Terms</a>
    </nav>
  </div>

  <main>
    <section class="hero" id="home">
      <div class="hero-media" aria-hidden="true">
        <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-hero.webp')) ?>" alt="" width="1600" height="854" fetchpriority="high">
        <div class="hero-overlay"></div>
        <div class="hero-grid"></div>
      </div>

      <div class="hero-orbit orbit-one" aria-hidden="true"></div>
      <div class="hero-orbit orbit-two" aria-hidden="true"></div>
      <span class="floating-shape shape-orange" aria-hidden="true"></span>
      <span class="floating-shape shape-green" aria-hidden="true"></span>
      <span class="floating-shape shape-pink" aria-hidden="true"></span>

      <div class="hero-content">
        <?php if ($admissionsBannerVisible): ?>
          <div class="eyebrow hero-eyebrow">
            <span class="pulse-dot"></span>
            Admissions are now open
          </div>
        <?php endif; ?>
        <h1>
          <span class="hero-line" data-i18n="heroLineOne">Every step builds</span><br>
          <span class="hero-line"><span data-i18n="heroArticle">a</span> <span class="changing-word" data-i18n-skip data-words="brighter,stronger,wiser">brighter</span> <span data-i18n="heroFuture">future.</span></span>
        </h1>
        <p>Personalized learning, expert guidance, and purposeful practice for students from Grade 1 till 12.</p>
        <div class="hero-actions">
          <a class="button button-primary magnetic" href="#approach">
            See how we teach
            <span class="play-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 7 8 5-8 5V7Z"/></svg>
            </span>
          </a>
          <a class="button button-primary magnetic" href="#programs">
            Explore our programs
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </a>
        </div>
      </div>

      <div class="hero-note glass-card" data-float>
        <div class="note-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3 2.5 8 12 13l9.5-5L12 3Z"/>
            <path d="M6 10.5V16c3.2 2.5 8.8 2.5 12 0v-5.5M21.5 8v6"/>
          </svg>
        </div>
        <div>
          <strong>Grade 1 till 12</strong>
          <span>Support at every stage</span>
        </div>
      </div>

      <?php if ($homepageRating !== null): ?>
      <div class="hero-rating glass-card" data-float>
        <div class="rating-avatars">
          <span>SA</span><span>MK</span><span>JL</span>
        </div>
        <div>
          <strong data-i18n-skip><?= homepage_e(number_format($homepageRating, 1)) ?> <?= homepage_star_meter($homepageRating, 'star-meter-inline') ?></strong>
          <small><?= homepage_e((string) $homepageRatingCount) ?> family <?= $homepageRatingCount === 1 ? 'review' : 'reviews' ?></small>
        </div>
      </div>
      <?php endif; ?>

      <a class="scroll-cue" href="#about" aria-label="Scroll to about section">
        <span>Scroll to discover</span>
        <i></i>
      </a>
    </section>

    <section class="signal-bar" aria-label="Center highlights">
      <div class="signal-track">
        <span><i class="dot orange"></i>Personalized learning</span>
        <span><i class="spark">✦</i>Academic confidence</span>
        <span><i class="dot green"></i>Expert educators</span>
        <span><i class="spark">✦</i>Visible progress</span>
        <span><i class="dot pink"></i>Active learning</span>
        <span><i class="spark">✦</i>Future-ready skills</span>
        <span><i class="dot orange"></i>Personalized learning</span>
        <span><i class="spark">✦</i>Academic confidence</span>
        <span><i class="dot green"></i>Expert educators</span>
        <span><i class="spark">✦</i>Visible progress</span>
        <span><i class="dot pink"></i>Active learning</span>
        <span><i class="spark">✦</i>Future-ready skills</span>
      </div>
    </section>

    <section class="vision-section section" id="about">
      <div class="section-shell">
        <div class="section-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Who we are</span>
            <h2>Learning that moves<br><span>people forward.</span></h2>
          </div>
        </div>

        <div class="vision-grid">
          <article class="story-card story-vision" data-homepage-content="vision" data-reveal data-tilt>
            <span class="card-number">01</span>
            <div class="story-head">
              <div class="story-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                  <circle cx="12" cy="12" r="2.7"/>
                </svg>
              </div>
              <span data-homepage-field="eyebrow">Our vision</span>
            </div>
            <div>
              <h3 data-homepage-field="title">Confident learners. Limitless futures.</h3>
              <p data-homepage-field="description">To shape a generation of curious, capable students who understand how they learn and trust how far they can go.</p>
            </div>
          </article>

          <article class="story-card story-mission" data-homepage-content="mission" data-reveal data-tilt>
            <span class="card-number">02</span>
            <div class="story-head">
              <div class="story-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M21.4 2.6 2.9 9.8l7.9 3.4 3.4 7.9 7.2-18.5Z"/>
                  <path d="M21.4 2.6 10.8 13.2"/>
                </svg>
              </div>
              <span data-homepage-field="eyebrow">Our mission</span>
            </div>
            <div>
              <h3 data-homepage-field="title">Make every learning step count.</h3>
              <p data-homepage-field="description">We combine careful assessment, personalized instruction, purposeful practice, and consistent feedback to turn effort into progress.</p>
            </div>
          </article>

          <figure class="vision-photo vision-slideshow" data-vision-slideshow data-reveal>
            <div class="vision-slides" data-vision-slides>
              <img class="vision-slide is-active" src="<?= homepage_e(khotwa_url('assets/images/khotwa-classroom-gallery.webp')) ?>" alt="Teacher guiding students through a collaborative classroom activity" width="1400" height="933" loading="lazy" decoding="async">
            </div>
            <figcaption data-vision-caption>
              <div class="vision-caption-lead">
                <strong>Human guidance</strong>
                <span>at the center of every lesson</span>
              </div>
              <p class="vision-caption-note">“Khotwa” signifies the beginning of every achievement. We believe that sustainable success is built with confidence and clarity, step by step, through a carefully designed educational journey tailored to each learner’s aspirations.</p>
            </figcaption>
            <div class="vision-slider-controls" data-vision-controls hidden>
              <button type="button" aria-label="Previous slide" data-vision-previous>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
              </button>
              <div class="vision-slider-dots" data-vision-dots></div>
              <button type="button" aria-label="Next slide" data-vision-next>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </button>
            </div>
          </figure>
        </div>
      </div>
    </section>

    <section class="approach-section section" id="approach">
      <div class="approach-glow glow-one" aria-hidden="true"></div>
      <div class="approach-glow glow-two" aria-hidden="true"></div>
      <div class="section-shell">
        <div class="section-intro centered light" data-reveal>
          <span class="eyebrow">Our approach</span>
          <h2>Four steps. One clear path<br><span>to real progress.</span></h2>
          <p>No guesswork. Every learner follows a responsive cycle designed to reveal needs, build understanding, and make growth measurable.</p>
        </div>

        <div class="approach-path" data-reveal>
          <div class="path-line"><span></span></div>

          <article class="approach-step" data-homepage-content="step_discover" data-step="01">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5M8.5 11h5M11 8.5v5"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 01</span>
            <h3 data-homepage-field="title">Discover</h3>
            <p data-homepage-field="description">Student strengths, gaps, learning habits, and goals through focused assessment.</p>
            <?php // Shown only when the step has a video saved in the admin panel. ?>
            <button class="step-watch" type="button" data-step-watch hidden>
              <span class="step-watch-play" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m9 7 8 5-8 5V7Z"/></svg>
              </span>
              <span class="step-watch-label">Watch this step</span>
            </button>
          </article>

          <article class="approach-step" data-homepage-content="step_guide" data-step="02">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 02</span>
            <h3 data-homepage-field="title">Guide</h3>
            <p data-homepage-field="description">Students with targeted support and personalized direction for daily homework.</p>
            <?php // Shown only when the step has a video saved in the admin panel. ?>
            <button class="step-watch" type="button" data-step-watch hidden>
              <span class="step-watch-play" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m9 7 8 5-8 5V7Z"/></svg>
              </span>
              <span class="step-watch-label">Watch this step</span>
            </button>
          </article>

          <article class="approach-step" data-homepage-content="step_build" data-step="03">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/>
                <path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v9"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 03</span>
            <h3 data-homepage-field="title">Build</h3>
            <p data-homepage-field="description">Strong academic foundations through clear explanations and effective routines.</p>
            <?php // Shown only when the step has a video saved in the admin panel. ?>
            <button class="step-watch" type="button" data-step-watch hidden>
              <span class="step-watch-play" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m9 7 8 5-8 5V7Z"/></svg>
              </span>
              <span class="step-watch-label">Watch this step</span>
            </button>
          </article>

          <article class="approach-step" data-homepage-content="step_achieve" data-step="04">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/>
                <path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>
                <path d="M12 14v3m-3 3h6"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 04</span>
            <h3 data-homepage-field="title">Achieve</h3>
            <p data-homepage-field="description">Continuous progress, celebrate key milestones, and reach academic success.</p>
            <?php // Shown only when the step has a video saved in the admin panel. ?>
            <button class="step-watch" type="button" data-step-watch hidden>
              <span class="step-watch-play" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m9 7 8 5-8 5V7Z"/></svg>
              </span>
              <span class="step-watch-label">Watch this step</span>
            </button>
          </article>
        </div>
      </div>
    </section>

    <section class="programs-section section" id="programs">
      <div class="section-shell">
        <div class="section-intro split-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Our programs</span>
            <h2>More ways to<br><span>learn and thrive.</span></h2>
          </div>
          <p>From academic support to practical training and creative activities, every program is designed with a clear purpose and an active learning experience.</p>
        </div>

        <div class="program-grid">
          <article class="program-card program-teaching" data-homepage-content="program_teaching" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Core program</span>
            </div>
            <div class="program-visual">
              <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-classroom-gallery.webp')) ?>" alt="Students working through school subjects with a teacher" width="1400" height="933" loading="lazy" decoding="async">
              <span class="visual-grade">1–12</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Teaching</span>
              <h3 data-homepage-field="title">Academic support from Grade 1 till 12</h3>
              <p data-homepage-field="description">Personalized and small-group learning across core school subjects.</p>
              <ul>
                <li data-homepage-field="point_1">Primary foundations</li>
                <li data-homepage-field="point_2">Middle school support</li>
                <li data-homepage-field="point_3">Grades 10, 11 &amp; 12 preparation</li>
              </ul>
            </div>
          </article>

          <article class="program-card program-training" data-homepage-content="program_training" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Skills program</span>
            </div>
            <div class="program-visual">
              <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-hero.webp')) ?>" alt="A practical skills workshop in progress" width="1600" height="854" loading="lazy" decoding="async">
              <span class="visual-symbol">↗</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Training</span>
              <h3 data-homepage-field="title">Practical skills for learners and educators</h3>
              <p data-homepage-field="description">Focused workshops that turn knowledge into confident action.</p>
              <ul>
                <li data-homepage-field="point_1">Study and learning skills</li>
                <li data-homepage-field="point_2">Teacher development</li>
                <li data-homepage-field="point_3">Digital and communication skills</li>
              </ul>
            </div>
          </article>

          <article class="program-card program-activities" data-homepage-content="program_activities" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Enrichment program</span>
            </div>
            <div class="program-visual">
              <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-stem-gallery.webp')) ?>" alt="Learners taking part in a hands-on STEM activity" width="1400" height="933" loading="lazy" decoding="async">
              <span class="visual-symbol">✦</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Activities</span>
              <h3 data-homepage-field="title">Creative, social, and hands-on experiences</h3>
              <p data-homepage-field="description">Active sessions that spark curiosity and build future-ready abilities.</p>
              <ul>
                <li data-homepage-field="point_1">STEM and maker activities</li>
                <li data-homepage-field="point_2">Arts, reading, and expression</li>
                <li data-homepage-field="point_3">Seasonal clubs and events</li>
              </ul>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="stats-section" aria-label="Quick statistics">
      <div class="stats-pattern" aria-hidden="true"></div>
      <?php
      $statisticItems = $homepageData['statistics'] ?: [
          ['stat_value' => 450, 'suffix' => '+', 'label_en' => 'learners supported'],
          ['stat_value' => 18, 'suffix' => '+', 'label_en' => 'expert educators'],
          ['stat_value' => 92, 'suffix' => '%', 'label_en' => 'family satisfaction'],
          ['stat_value' => 12, 'suffix' => '+', 'label_en' => 'years of experience'],
      ];
      ?>
      <div
        class="section-shell stats-grid"
        style="--stat-count: <?= homepage_e((string) count($statisticItems)) ?>"
        data-homepage-statistics
      >
        <div class="stats-heading" data-reveal>
          <span class="eyebrow">Khotwa in numbers</span>
          <h2>Small steps.<br>Big momentum.</h2>
        </div>
        <?php foreach ($statisticItems as $statistic): ?>
          <div class="stat-item" data-reveal>
            <strong>
              <span data-counter="<?= homepage_e((string) (int) $statistic['stat_value']) ?>">0</span>
              <?php if (!empty($statistic['suffix'])): ?>
                <sup><?= homepage_e((string) $statistic['suffix']) ?></sup>
              <?php endif; ?>
            </strong>
            <p><?= homepage_e((string) $statistic['label_en']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($homepageReviews !== []): ?>
      <?php $reviewCount = count($homepageReviews); ?>
      <section class="reviews-section section" id="reviews">
        <div class="section-shell">
          <div class="section-intro centered" data-reveal>
            <span class="eyebrow dark">Parents’ Voices</span>
            <h2>What parents say<br><span>about Khotwa.</span></h2>
            <?php if ($homepageRating !== null): ?>
              <div class="reviews-score" data-i18n-skip>
                <?= homepage_star_meter($homepageRating) ?>
                <strong><?= homepage_e(number_format($homepageRating, 1)) ?></strong>
                <span class="reviews-score-note">out of 5 &middot; <?= homepage_e((string) $homepageRatingCount) ?> <?= $homepageRatingCount === 1 ? 'review' : 'reviews' ?></span>
              </div>
            <?php endif; ?>
          </div>

          <?php // data-reveal sits on the slider, not the cards: a card scrolled out of
                // view horizontally would never intersect the viewport and stay hidden. ?>
          <div class="review-slider" data-review-slider data-reveal>
            <div
              class="review-track<?= $reviewCount === 1 ? ' is-single' : '' ?>"
              data-review-track
              tabindex="0"
              role="group"
              aria-label="Family reviews"
            >
              <?php foreach ($homepageReviews as $review): ?>
                <?php $rating = max(1, min(5, (int) $review['rating'])); ?>
                <article class="review-card">
                  <?= homepage_star_meter((float) $rating, 'review-stars') ?>
                  <blockquote data-i18n-skip><?= homepage_e((string) $review['review_text']) ?></blockquote>
                  <footer>
                    <?php // The reviewer's own name, then a generic role label. Whether they are
                          // the mother or the father stays internal to the admin panel. ?>
                    <?php // Both spellings ship with the card; the language switch picks one.
                          // Falls back to the Latin name when no Arabic name was given. ?>
                    <strong
                      data-i18n-skip
                      data-review-name-en="<?= homepage_e((string) $review['display_name']) ?>"
                      data-review-name-ar="<?= homepage_e((string) ($review['display_name_ar'] ?: $review['display_name'])) ?>"
                    ><?= homepage_e((string) $review['display_name']) ?></strong>
                    <small>Parents</small>
                  </footer>
                </article>
              <?php endforeach; ?>
            </div>

            <?php // Two cards fit per view on desktop and one on a phone, so paging is
                  // needed past that. The buttons start hidden and the script reveals them
                  // only when the track can actually scroll, which keeps them honest at
                  // every screen width and leaves nothing dead if JavaScript is off. ?>
            <?php if ($reviewCount > 1): ?>
              <div class="review-controls" data-review-controls hidden>
                <button class="review-nav" type="button" data-review-prev aria-label="Previous reviews">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <span class="review-progress" data-review-progress aria-live="polite"></span>
                <button class="review-nav" type="button" data-review-next aria-label="Next reviews">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="team-section section" id="team">
      <div class="section-shell">
        <div class="section-intro centered" data-reveal>
          <span class="eyebrow dark">Meet our team</span>
          <h2>Experts who teach with<br><span>clarity and care.</span></h2>
          <p>Our educators bring subject expertise, thoughtful guidance, and the belief that every learner can make meaningful progress.</p>
        </div>

        <div class="team-grid" data-homepage-team>
          <?php // Every active teacher, straight from the teacher records. Nothing is
                // invented here: with no teachers yet, only the "join us" card shows. ?>
          <?php $teamMembers = $homepageData['team'] ?? []; ?>
          <?php foreach ($teamMembers as $index => $member): ?>
            <?php $portrait = ['one', 'two', 'three'][$index % 3]; ?>
            <?php // The profile panel reads a teacher's details off their own card, so the
                  // markup rendered here and the one index.js rebuilds on a language
                  // switch carry exactly the same set of attributes. ?>
            <article
              class="team-card"
              data-reveal
              tabindex="0"
              role="button"
              data-teacher-experience="<?= homepage_e($member['years_experience'] === null ? '' : (string) (int) $member['years_experience']) ?>"
              data-teacher-levels="<?= homepage_e((string) ($member['education_levels_en'] ?? '')) ?>"
              data-teacher-certifications="<?= homepage_e((string) ($member['certifications_en'] ?? '')) ?>"
              data-teacher-since="<?= homepage_e($member['years_at_center'] === null ? '' : (string) (int) $member['years_at_center']) ?>"
              data-teacher-video="<?= homepage_e((string) ($member['video_url'] ?? '')) ?>"
            >
              <div class="team-portrait portrait-<?= homepage_e($portrait) ?>">
                <?php if (!empty($member['image_path'])): ?>
                  <img
                    class="team-portrait-image"
                    src="<?= homepage_e(khotwa_url((string) $member['image_path'])) ?>"
                    alt="<?= homepage_e((string) $member['name_en']) ?>"
                    loading="lazy"
                    decoding="async"
                  >
                <?php else: ?>
                  <span class="portrait-initials"><?= homepage_e((string) ($member['initials'] ?: 'K')) ?></span>
                  <div class="portrait-shape"></div>
                <?php endif; ?>
              </div>
              <div class="team-info">
                <div>
                  <h3><?= homepage_e((string) $member['name_en']) ?></h3>
                  <p><?= homepage_e((string) $member['role_en']) ?></p>
                </div>
                <?php // Decorative: the card itself is the button, so the arrow says
                      // "clickable" without a word that would need translating. ?>
                <span class="team-open" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
              </div>
              <span class="team-specialty"><?= homepage_e((string) $member['subjects_en']) ?></span>
            </article>
          <?php endforeach; ?>

          <article class="team-card team-join" data-reveal>
            <div class="join-orbit">
              <span>+</span>
            </div>
            <div>
              <span>Grow with us</span>
              <h3>Great educators are always welcome.</h3>
              <a href="mailto:khotwacenter.lb@gmail.com">Join our team <b>↗</b></a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="gallery-section section" id="gallery">
      <div class="section-shell">
        <div class="section-intro split-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Inside Khotwa</span>
            <h2>Learning looks<br><span>good in action.</span></h2>
          </div>
          <p>An inside look at our dynamic learning spaces, crafted to cultivate focus and empower student collaboration.</p>
        </div>

        <div class="gallery-grid" data-homepage-gallery>
          <button class="gallery-item gallery-wide" type="button" data-image="assets/images/khotwa-classroom-gallery.webp" data-caption="Collaborative learning">
            <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-classroom-gallery.webp')) ?>" alt="Students learning together with their teacher" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Collaborative learning</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-tall" type="button" data-image="assets/images/khotwa-stem-gallery.webp" data-caption="Hands-on discovery">
            <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-stem-gallery.webp')) ?>" alt="Students building a project during a STEM activity" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Hands-on discovery</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-crop-one" type="button" data-image="assets/images/khotwa-hero.webp" data-caption="Guided academic support">
            <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-hero.webp')) ?>" alt="Teacher supporting students around a learning table" width="1600" height="854" loading="lazy" decoding="async">
            <span><b>Guided support</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-crop-two" type="button" data-image="assets/images/khotwa-stem-gallery.webp" data-caption="Curiosity at work">
            <img src="<?= homepage_e(khotwa_url('assets/images/khotwa-stem-gallery.webp')) ?>" alt="Young students focused on a classroom project" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Curiosity at work</b><i>View image ↗</i></span>
          </button>
          <div class="gallery-quote" data-reveal>
            <span>“</span>
            <blockquote>Tell me and I forget. Teach me and I remember. Involve me and I learn.</blockquote>
            <cite>Learning through experience</cite>
          </div>
        </div>
      </div>
    </section>

    <?php // Partners come from the admin panel. With none added the whole strip is
          // left out, rather than showing placeholder names. ?>
    <?php $homepagePartners = $homepageData['partners'] ?? []; ?>
    <?php if ($homepagePartners !== []): ?>
    <section class="partners-section" aria-label="Our partners">
      <div class="section-shell">
        <p>Growing through trusted partnerships</p>
        <?php // The strip scrolls on its own, so it needs a window to scroll inside. ?>
        <div class="partner-marquee">
        <div class="partner-logos" data-homepage-partners>
          <?php foreach ($homepagePartners as $index => $partner): ?>
            <?php
            $partnerUrl = (string) ($partner['website_url'] ?? '');
            $partnerName = (string) $partner['name_en'];
            $partnerTag = $partnerUrl === '' ? 'span' : 'a';
            $markName = ['one', 'two', 'three', 'four', 'five'][$index % 5];
            ?>
            <<?= $partnerTag ?>
              <?php if ($partnerUrl !== ''): ?>
                href="<?= homepage_e($partnerUrl) ?>"
                <?= preg_match('/^https?:/i', $partnerUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
              <?php endif; ?>
            >
              <?php if (!empty($partner['logo_path'])): ?>
                <img src="<?= homepage_e(khotwa_url((string) $partner['logo_path'])) ?>" alt="<?= homepage_e($partnerName) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <i class="partner-mark mark-<?= homepage_e($markName) ?>"></i>
              <?php endif; ?>
              <span><?= homepage_e($partnerName) ?></span>
            </<?= $partnerTag ?>>
          <?php endforeach; ?>
        </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="faq-section section" id="faq">
      <div class="section-shell faq-layout">
        <div class="faq-intro" data-reveal>
          <span class="eyebrow dark">Questions, answered</span>
          <h2>Everything you need<br><span>before the first step.</span></h2>
          <p>Still curious? Our team is ready to learn about your goals and recommend the right place to begin.</p>
          <a class="text-link" href="#contact">Ask us anything <span>↗</span></a>
        </div>

        <div class="faq-list" data-reveal>
          <article class="faq-item open">
            <button type="button" aria-expanded="true">
              <span>What grades do you support?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>We support learners from Grade 1 till 12, with age-appropriate programs for foundational learning, school support, and exam preparation.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>How do you decide where a student should begin?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Every journey starts with a conversation and a focused diagnostic assessment. We use the results to build a clear learning plan around the student's current needs and goals.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>Do you offer individual and group sessions?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Yes. Depending on the subject, goal, and learner profile, we offer individual sessions and carefully matched small groups.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>How do families receive progress updates?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Families receive regular feedback on attendance, completed skills, current priorities, and measurable learning progress.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>Are your activities open to students outside the center?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Many workshops, seasonal clubs, and special activities are open to the wider community. Availability may vary by age group and schedule.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="contact-section" id="contact">
      <div class="contact-art" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
      <div class="section-shell contact-inner" data-reveal>
        <div>
          <span class="eyebrow">Your next step</span>
          <?php // The space before "that" is what joins the two halves into one line
                // once the break is dropped on a phone; at the start of a line the
                // browser collapses it away, so the wide layout is unchanged. ?>
          <h2>Let’s build a learning plan<br class="contact-break"> that fits.</h2>
        </div>
        <div class="contact-actions">
          <a class="button button-light magnetic" href="<?= homepage_e($contactWhatsappUrl) ?>" data-contact-link="whatsapp" target="_blank" rel="noopener noreferrer">
            Contact us now
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="section-shell">
      <div class="footer-main">
        <div class="footer-brand">
          <a class="brand brand-light" href="#home">
            <img class="brand-logo" src="<?= homepage_e(khotwa_url('assets/images/logo-white.svg')) ?>" alt="Khotwa Education Center" width="148" height="71">
          </a>
          <p>One step at a time, toward stronger skills, greater confidence, and a future full of possibility.</p>
          <?php
          $socialTypes = homepage_social_link_types();
          $socialLinks = array_values(array_filter(
              $homepageData['contacts'],
              static fn (array $row): bool => in_array($row['link_type'], $socialTypes, true)
          ));
          if ($socialLinks === []) {
              $socialLinks = array_map(
                  static fn (string $type): array => ['link_type' => $type, 'label_en' => ucfirst($type), 'url' => '#'],
                  $socialTypes
              );
          }
          ?>
          <div class="social-links" aria-label="Social media links" data-homepage-socials>
            <?php foreach ($socialLinks as $social): ?>
              <?php $socialUrl = (string) ($social['url'] ?? '') ?: '#'; ?>
              <a
                href="<?= homepage_e($socialUrl) ?>"
                aria-label="<?= homepage_e((string) $social['label_en']) ?>"
                data-social-type="<?= homepage_e((string) $social['link_type']) ?>"
                <?= preg_match('/^https?:/i', $socialUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
              ><?= homepage_social_icon((string) $social['link_type']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="footer-links">
          <div class="footer-explore">
            <h3>Explore</h3>
            <a href="#about">About us</a>
            <a href="#approach">Our approach</a>
            <a href="#programs">Programs</a>
            <a href="#team">Our team</a>
          </div>
          <div class="footer-programs">
            <h3>Programs</h3>
            <a href="#programs">Core Program</a>
            <a href="#programs">Skills Program</a>
            <a href="#programs">Enrichment Program</a>
          </div>
          <div class="footer-visit">
            <h3>Visit</h3>
            <a href="mailto:khotwacenter.lb@gmail.com" data-contact-link="primary_email">khotwacenter.lb@gmail.com</a>
            <a href="<?= homepage_e($contactPhoneUrl) ?>" data-contact-link="primary_phone"><?= homepage_e($contactPhone) ?></a>
            <a href="<?= homepage_e($contactMapUrl) ?>" data-contact-link="google_map" target="_blank" rel="noopener noreferrer"><?= homepage_e($contactAddress) ?></a>
            <p data-contact-value="opening_hours"><?= homepage_e($contactHours) ?></p>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© <span id="year"></span> Khotwa Education Center. All rights reserved.</p>
        <div><a class="footer-login" href="<?= homepage_e(khotwa_url('login.php')) ?>">Log in</a><a href="<?= homepage_e(khotwa_url('terms.php')) ?>">Terms</a></div>
        <a href="#home">Back to top ↑</a>
      </div>
    </div>
  </footer>

  <div class="lightbox" role="dialog" aria-modal="true" aria-label="Gallery image" aria-hidden="true">
    <button class="lightbox-close" type="button" aria-label="Close gallery">×</button>
    <figure>
      <img src="" alt="">
      <figcaption></figcaption>
    </figure>
  </div>

  <?php // The step video player. It sits in the middle of the screen over a dimmed
        // page, and the iframe is only given a source while it is open. ?>
  <div class="video-modal" role="dialog" aria-modal="true" aria-label="Step video" aria-hidden="true">
    <div class="video-modal-inner">
      <div class="video-modal-head">
        <span class="video-modal-step" data-video-step></span>
        <h2 class="video-modal-title" data-video-title></h2>
      </div>
      <button class="video-modal-close" type="button" aria-label="Close video">&times;</button>
      <div class="video-modal-frame">
        <iframe
          data-video-frame
          src=""
          title="Step video"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen
        ></iframe>
      </div>
    </div>
  </div>

  <div class="teacher-modal" role="dialog" aria-modal="true" aria-label="Teacher profile" aria-hidden="true">
    <div class="teacher-modal-inner">
      <button class="teacher-modal-close" type="button" aria-label="Close teacher profile">×</button>
      <?php // Who this is, read as one unit: the portrait, then the name with the
            // subjects they teach directly under it. ?>
      <div class="teacher-modal-header">
        <div class="teacher-modal-portrait">
          <span class="teacher-modal-initials"></span>
          <img class="teacher-modal-photo" src="" alt="" loading="lazy">
        </div>
        <div class="teacher-modal-heading">
          <h2 class="teacher-modal-name"></h2>
          <p class="teacher-modal-subjects"></p>
        </div>
      </div>
      <div class="teacher-modal-body">
        <?php // Filled in from the card that was opened. Every row whose value is blank
              // is dropped, so a half-filled teacher record still reads as a finished
              // profile instead of a list of empty labels. ?>
        <dl class="teacher-modal-facts">
          <div data-teacher-fact="levels">
            <dt>Levels taught</dt>
            <dd></dd>
          </div>
          <div data-teacher-fact="experience">
            <dt>Years of experience</dt>
            <dd></dd>
          </div>
          <div data-teacher-fact="certifications">
            <dt>Certifications</dt>
            <dd></dd>
          </div>
          <div data-teacher-fact="since">
            <dt>At the center for</dt>
            <dd></dd>
          </div>
        </dl>
        <div class="teacher-modal-actions">
          <a
            class="teacher-modal-video button"
            href="#"
            target="_blank"
            rel="noopener noreferrer"
          >
            Watch the introduction
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 7 8 5-8 5V7Z"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.KhotwaHomepageData = <?= $homepageDataLoaded
        ? json_encode(
            $homepageData,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        )
        : 'null' ?>;
  </script>
  <script src="<?= homepage_e(khotwa_asset('js/language.js')) ?>" defer></script>
  <script src="<?= homepage_e(khotwa_asset('js/index.js')) ?>" defer></script>
</body>
</html>
