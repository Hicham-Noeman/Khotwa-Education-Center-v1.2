<?php
declare(strict_types=1);

// Was a plain .html page, which meant its stylesheet link carried no version and
// browsers kept serving a day-old copy after every change (.htaccess caches CSS
// for a day). Rendered through PHP, khotwa_asset() stamps the file's timestamp on
// the URL, so an edit is picked up on the next load.
require_once __DIR__ . '/src/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Terms and conditions design for Khotwa Education Center.">
  <meta name="theme-color" content="#223F6B">
  <title>Terms and Conditions | Khotwa Education Center</title>
  <?php // The brand face every other page loads. This page was still asking Google
        // for DM Sans / Manrope / Tajawal, which auth.css never names, so its text
        // rendered in a different typeface from the rest of the site. ?>
  <?= khotwa_head_fonts() ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(khotwa_asset('css/auth.css'), ENT_QUOTES) ?>">
</head>
<body class="terms-page">
  <header class="terms-header">
    <a class="auth-brand terms-brand" href="<?= htmlspecialchars(khotwa_url('index.php'), ENT_QUOTES) ?>" aria-label="Khotwa Education Center home">
      <img src="<?= htmlspecialchars(khotwa_url('assets/images/logo-color.svg'), ENT_QUOTES) ?>" alt="Khotwa Education Center" width="148" height="71">
    </a>
    <div>
      <button class="language-switch" type="button" data-language-toggle>
        <span data-language-current>EN</span>
        <i></i>
        <span data-language-label>العربية</span>
      </button>
      <a class="back-link" href="<?= htmlspecialchars(khotwa_url('index.php'), ENT_QUOTES) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
        Back to website
      </a>
      <a class="terms-login" href="<?= htmlspecialchars(khotwa_url('login.php'), ENT_QUOTES) ?>">Log in</a>
    </div>
  </header>

  <main>
    <section class="terms-hero">
      <div class="terms-orbit" aria-hidden="true"></div>
      <span class="section-kicker">Clear expectations, shared trust</span>
      <h1>Terms and<br><em>Conditions.</em></h1>
      <p>A clear design template for how Khotwa Education Center can explain account access, learning services, privacy, and responsible use.</p>
      <div class="terms-meta"><span>Design draft</span><span>Last updated: June 12, 2026</span></div>
    </section>

    <div class="terms-layout">
      <aside class="terms-nav">
        <strong>On this page</strong>
        <a href="#acceptance">01. Acceptance</a>
        <a href="#accounts">02. Accounts</a>
        <a href="#services">03. Learning services</a>
        <a href="#conduct">04. Responsible use</a>
        <a href="#privacy">05. Privacy</a>
        <a href="#changes">06. Changes</a>
        <a href="#contact-terms">07. Contact</a>
      </aside>

      <article class="terms-content">
        <section id="acceptance">
          <span>01</span>
          <div><h2>Acceptance of terms</h2><p>By accessing the Khotwa website or learning portal, users agree to follow these terms and any center policies shared during enrollment. Parents or guardians are responsible for accounts created for learners under the applicable legal age.</p></div>
        </section>
        <section id="accounts">
          <span>02</span>
          <div><h2>Account access</h2><p>Users should provide accurate information, keep login credentials private, and notify Khotwa if they believe an account has been accessed without permission. Account access may be limited when information is incomplete or portal use creates a security concern.</p></div>
        </section>
        <section id="services">
          <span>03</span>
          <div><h2>Learning services</h2><p>Programs, schedules, instructors, resources, and learning plans may change to support student needs and center operations. Specific enrollment, payment, cancellation, and attendance conditions should be provided separately for each program.</p></div>
        </section>
        <section id="conduct">
          <span>04</span>
          <div><h2>Responsible use</h2><p>The portal and its educational materials should be used respectfully and only for their intended learning purpose. Users may not disrupt services, share protected resources without permission, impersonate another person, or attempt to access restricted areas.</p></div>
        </section>
        <section id="privacy">
          <span>05</span>
          <div><h2>Privacy and learner data</h2><p>Khotwa may collect information needed to provide learning services, communicate with families, and report progress. A production version should explain what data is collected, how it is stored, who can access it, and how users may request corrections or deletion.</p></div>
        </section>
        <section id="changes">
          <span>06</span>
          <div><h2>Updates to these terms</h2><p>These terms may be updated when services, regulations, or center policies change. The latest version should always display its effective date, and important changes should be communicated through an appropriate channel.</p></div>
        </section>
        <section id="contact-terms">
          <span>07</span>
          <div><h2>Questions and contact</h2><p>Questions about these terms can be directed to <a href="mailto:khotwacenter.lb@gmail.com">khotwacenter.lb@gmail.com</a> or discussed with the center team during working hours.</p></div>
        </section>
      </article>
    </div>
  </main>

  <footer class="terms-footer">
    <p>&copy; 2026 Khotwa Education Center</p>
    <a href="<?= htmlspecialchars(khotwa_url('login.php'), ENT_QUOTES) ?>">Continue to login <span>&rarr;</span></a>
  </footer>
  <script src="<?= htmlspecialchars(khotwa_asset('js/language.js'), ENT_QUOTES) ?>" defer></script>
</body>
</html>
