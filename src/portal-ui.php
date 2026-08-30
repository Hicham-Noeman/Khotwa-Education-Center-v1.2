<?php
declare(strict_types=1);

/*
 * Shared chrome for the signed-in portals.
 *
 * Administration, management, the teacher portal, and the parent portal all wear
 * the same panel. The head of that panel - the wordmark, the language and logout
 * buttons beside it, and the collapse toggle - is rendered here so the four
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
      <div class="sidebar-top-actions">
        <?php // Language and logout live beside the brand; the panel keeps no footer. ?>
        <button
          class="sidebar-top-action sidebar-language-compact"
          type="button"
          title="Switch language"
          aria-label="Switch language"
          data-language-toggle
        ><strong data-language-current>EN</strong></button>
        <a
          class="sidebar-top-action"
          href="<?= htmlspecialchars(khotwa_url('logout.php'), ENT_QUOTES, 'UTF-8') ?>"
          title="Log out"
          aria-label="Log out"
        ><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></a>
      </div>
      <button class="sidebar-toggle" type="button" aria-label="Close navigation panel" aria-controls="admin-sidebar" aria-expanded="true" data-sidebar-toggle>
        <svg class="collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        <svg class="expand-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
    <?php
}
