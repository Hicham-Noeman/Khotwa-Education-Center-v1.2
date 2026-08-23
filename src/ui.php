<?php
declare(strict_types=1);

/*
 * Shared UI partials.
 */

/**
 * Renders status messages as transient toasts.
 *
 * A banner at the top of the page stays until the next navigation, which reads as
 * clutter long after the action finished. These float above the page instead and
 * dismiss themselves; errors linger longer and can also be closed by hand.
 *
 * @param array<int, array{type?: string, text?: string|null}> $toasts
 */
function render_toasts(array $toasts): void
{
    $toasts = array_values(array_filter(
        $toasts,
        static fn (array $toast): bool => trim((string) ($toast['text'] ?? '')) !== ''
    ));

    if ($toasts === []) {
        return;
    }
    ?>
    <div class="toast-stack" data-toast-stack>
      <?php foreach ($toasts as $toast): ?>
        <?php $isError = ($toast['type'] ?? 'success') === 'error'; ?>
        <div
          class="toast <?= $isError ? 'toast-error' : 'toast-success' ?>"
          data-toast
          data-toast-timeout="<?= $isError ? '9000' : '4500' ?>"
          role="<?= $isError ? 'alert' : 'status' ?>"
        >
          <span class="toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <?php if ($isError): ?>
                <circle cx="12" cy="12" r="9"/><path d="M12 7.6v5M12 16.2h.01"/>
              <?php else: ?>
                <circle cx="12" cy="12" r="9"/><path d="m8.3 12.4 2.6 2.6 4.8-5.4"/>
              <?php endif; ?>
            </svg>
          </span>
          <p><?= e((string) $toast['text']) ?></p>
          <button class="toast-close" type="button" data-toast-close aria-label="Dismiss">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/></svg>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}
