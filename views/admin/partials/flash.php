<?php if (isset($_SESSION['flash_message'])): ?>
  <?php $flash = (array) $_SESSION['flash_message']; ?>
  <div class="alert alert--<?= htmlspecialchars((string) ($flash['type'] ?? 'info')) ?>">
    <?php if (!empty($flash['errors'])): ?>
      <ul class="alert__list">
        <?php foreach ((array) $flash['errors'] as $error): ?>
          <li class="alert__item"><?= htmlspecialchars((string) $error) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= htmlspecialchars((string) ($flash['text'] ?? '')) ?>
    <?php endif; ?>
  </div>
  <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>
