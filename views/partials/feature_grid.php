<?php

/**
 * Reużywalny komponent siatki funkcji Zdajnika (karty + modal ze szczegółami).
 * Używany zarówno na stronie głównej (home.php), jak i na stronie o projekcie
 * (about_project.php), żeby obie strony prezentowały te same możliwości w ten sam
 * sposób (DRY) - patrz `partials/data/hero_features.php`.
 *
 * @var array<int, array{icon:string,title:string,description:string,details?:string,status?:string}> $features
 * @var string|null $featureGridTag Znacznik HTML kontenera siatki (domyślnie 'div')
 * @var string|null $featureGridClass Dodatkowa klasa BEM dopisywana do kontenera
 * @var string|null $featureGridAriaLabel Etykieta ARIA dla kontenera
 */

$featureGridTag = $featureGridTag ?? 'div';
$featureGridClasses = trim('hero-feature-grid ' . ($featureGridClass ?? ''));
$featureGridAriaLabel = $featureGridAriaLabel ?? 'Możliwości Zdajnika';
?>
<<?= $featureGridTag ?> class="<?= htmlspecialchars($featureGridClasses) ?>" aria-label="<?= htmlspecialchars($featureGridAriaLabel) ?>">
  <?php foreach ($features as $feature): ?>
    <?php if (isset($feature['status'])): ?>
      <div class="hero-feature-card hero-feature-card--soon" aria-disabled="true">
        <span class="hero-feature-card__badge"><?= htmlspecialchars($feature['status']) ?></span>
        <span class="hero-feature-card__icon"><i class="<?= htmlspecialchars($feature['icon']) ?>"></i></span>
        <span class="hero-feature-card__title"><?= htmlspecialchars($feature['title']) ?></span>
        <span class="hero-feature-card__description"><?= htmlspecialchars($feature['description']) ?></span>
      </div>
    <?php else: ?>
      <button type="button" class="hero-feature-card" data-feature-card
        data-feature-title="<?= htmlspecialchars($feature['title']) ?>"
        data-feature-icon="<?= htmlspecialchars($feature['icon']) ?>">
        <span class="hero-feature-card__icon"><i class="<?= htmlspecialchars($feature['icon']) ?>"></i></span>
        <span class="hero-feature-card__title"><?= htmlspecialchars($feature['title']) ?></span>
        <span class="hero-feature-card__description"><?= htmlspecialchars($feature['description']) ?></span>
        <span class="hero-feature-card__details" hidden><?= htmlspecialchars($feature['details'] ?? $feature['description']) ?></span>
      </button>
    <?php endif; ?>
  <?php endforeach; ?>
</<?= $featureGridTag ?>>

<div class="hero-feature-modal" data-feature-modal hidden>
  <div class="hero-feature-modal__backdrop" data-feature-modal-close></div>
  <div class="hero-feature-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="hero-feature-modal-title"
    tabindex="-1">
    <button type="button" class="hero-feature-modal__close" data-feature-modal-close aria-label="Zamknij opis funkcji">
      <i class="fas fa-xmark"></i>
    </button>
    <div class="hero-feature-modal__icon" data-feature-modal-icon aria-hidden="true"></div>
    <p class="hero-feature-modal__eyebrow">Funkcja Zdajnika</p>
    <h2 class="hero-feature-modal__title" id="hero-feature-modal-title" data-feature-modal-title></h2>
    <p class="hero-feature-modal__text" data-feature-modal-text></p>
  </div>
</div>
