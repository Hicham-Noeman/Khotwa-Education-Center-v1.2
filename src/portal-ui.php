<?php
declare(strict_types=1);

/*
 * Shared chrome for the signed-in portals.
 *
 * Administration, management, the teacher portal, and the parent portal all wear
 * the same panel. The head of that panel - the wordmark and the collapse toggle -
 * and its foot - the language and logout buttons - are rendered here so the four
 * portals cannot drift apart again: each page keeps only its own navigation list.
 */

require_once __DIR__ . '/paths.php';

/**
 * The top block of a portal sidebar: brand, language, logout, collapse toggle.
 *
 * $subline is the one word under the wordmark that says which portal this is
 * ("Administration", "Teacher", ...).
 */
function portal_sidebar_top(string $brandHref, string $brandLabel, string $subline): void
{
    ?>
    <div class="sidebar-top">
      <a class="admin-brand" href="<?= htmlspecialchars($brandHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8') ?>">
        <?php // The logo carries the name, so the wordmark replaces the "Khotwa" text. ?>
        <span class="admin-brand-mark admin-brand-mark-logo">
          <img
            class="admin-brand-logo"
            src="<?= htmlspecialchars(khotwa_asset('images/logo-white.svg'), ENT_QUOTES, 'UTF-8') ?>"
            alt="Khotwa"
            width="148"
            height="71"
          >
        </span>
        <span class="admin-brand-copy"><small><?= htmlspecialchars($subline, ENT_QUOTES, 'UTF-8') ?></small></span>
      </a>
      <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
        <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
    <?php
}

/**
 * Language and logout, as the pair of pills the public site wears in its header.
 *
 * Drawn twice over: in the bar on a phone, where the panel has no room for
 * them, and at the foot of the panel on a desktop, where there is no bar. Only
 * one of the two is ever on screen. The long language label stays in the markup
 * for the script to write into, undrawn, exactly as it is on the public site.
 */
function portal_session_controls(): void
{
    ?>
    <button
      class="portal-language"
      type="button"
      title="Switch language"
      aria-label="Switch language"
      data-language-toggle
    ><span data-language-current>EN</span><i></i><span data-language-label>العربية</span></button>
    <a class="portal-logout" href="<?= htmlspecialchars(khotwa_url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
      <span>Log out</span>
    </a>
    <?php
}

/**
 * The bar across the top of a portal on a phone.
 *
 * The navigation panel slides away at this size, and a bare button floating over
 * the page had nothing to anchor it. This gives it the same footing as the bar
 * on the public site: the wordmark at the leading edge, the panel button at the
 * trailing one, on a pale blurred strip pinned to the top of the screen.
 *
 * Hidden above the drawer breakpoint, where the panel is always on show and the
 * wordmark already sits at the head of it.
 */
function portal_mobile_topbar(string $brandHref, string $brandLabel): void
{
    ?>
    <header class="portal-topbar">
      <a class="portal-topbar-brand" href="<?= htmlspecialchars($brandHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8') ?>">
        <?php // Both cuts, as on the public site: the colour one against the pale
              // strip, the white one once the panel opens behind it. ?>
        <img
          class="portal-topbar-logo portal-topbar-logo-color"
          src="<?= htmlspecialchars(khotwa_asset('images/logo-color.svg'), ENT_QUOTES, 'UTF-8') ?>"
          alt="Khotwa"
          width="148"
          height="71"
        >
        <img
          class="portal-topbar-logo portal-topbar-logo-white"
          src="<?= htmlspecialchars(khotwa_asset('images/logo-white.svg'), ENT_QUOTES, 'UTF-8') ?>"
          alt=""
          aria-hidden="true"
          width="148"
          height="71"
        >
      </a>
      <div class="portal-topbar-actions">
        <?php portal_session_controls(); ?>
        <?php // Two glyphs, one swapped for the other while the panel is open. ?>
        <button class="mobile-panel-toggle" type="button" aria-label="Open navigation panel" aria-controls="admin-sidebar" aria-expanded="false" data-mobile-sidebar-toggle>
          <svg class="panel-open-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
          <svg class="panel-close-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
    </header>
    <?php
}

/**
 * The foot of a portal sidebar: language and logout.
 *
 * These sit at the bottom of the panel, below the navigation and away from the
 * brand, so the two ways of leaving a screen are together and out of the path
 * of the links people actually use. Called last inside the <aside>, where it
 * lands in the third row the sidebar grid already reserves.
 */
function portal_sidebar_bottom(): void
{
    ?>
    <div class="sidebar-footer">
      <?php portal_session_controls(); ?>
    </div>
    <?php
}
