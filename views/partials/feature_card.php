<?php
/**
 * Shared feature card used by the home feature grid and project overview.
 *
 * @var array{
 *   icon: string,
 *   title: string,
 *   text: string,
 *   details?: string,
 *   status?: string,
 *   href?: string,
 *   cta?: string,
 *   action?: string,
 *   actions?: array<int, array{href: string, label: string}>
 * } $feature
 */

$featureAction = $feature['action'] ?? 'modal';
$featureHref = $feature['href'] ?? '#';
$featureIcon = $feature['icon'] ?? 'fa-circle-info';
$featureActions = isset($feature['actions'])
  ? htmlspecialchars(json_encode($feature['actions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
  : null;
$featureClasses = 'feature-card';
if (isset($feature['status'])) {
  $featureClasses .= ' feature-card--soon';
}
?>

<article
  class="<?= $featureClasses ?>"
  data-feature-card
  data-feature-action="<?= htmlspecialchars($featureAction) ?>"
  data-feature-href="<?= htmlspecialchars($featureHref) ?>"
  data-feature-title="<?= htmlspecialchars($feature['title']) ?>"
  data-feature-icon="fas <?= htmlspecialchars($featureIcon) ?>"
  data-feature-cta="<?= htmlspecialchars($feature['cta'] ?? 'Przejdź dalej') ?>"
  <?php if ($featureActions): ?>data-feature-actions="<?= $featureActions ?>"<?php endif; ?>
  tabindex="0">
  <div class="feature-card__header">
    <i class="feature-card__icon fas <?= htmlspecialchars($featureIcon) ?>"></i>
    <div class="feature-card__title-group">
      <h3 class="feature-card__title"><?= htmlspecialchars($feature['title']) ?></h3>
      <?php if (isset($feature['status'])): ?>
        <span class="soon-badge"><?= htmlspecialchars($feature['status']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <p class="feature-card__text"><?= htmlspecialchars($feature['text']) ?></p>
  <div class="feature-card__details">
    <p><?= htmlspecialchars($feature['details'] ?? $feature['text']) ?></p>
  </div>
</article>
