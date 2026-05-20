<?php
/**
 * @file navigation.php
 * @description
 * Nawigacja boczna panelu administracyjnego.
 *
 * @version 1.0.0
 */

$activeSection = $activeSection ?? 'dashboard';
?>

<aside class="admin-sidebar">
  <div class="admin-sidebar__brand">
    <span class="admin-sidebar__eyebrow">Admin</span>
    <h2 class="admin-sidebar__title">Admin Panel</h2>
  </div>

  <nav class="admin-sidebar__nav" aria-label="Nawigacja panelu CMS">
    <a href="<?= url('admin') ?>" class="admin-sidebar__link <?= $activeSection === 'dashboard' ? 'is-active' : '' ?>">
      <i class="fas fa-gauge-high"></i>
      <span>Pulpit</span>
    </a>
    <a href="<?= url('admin/uzytkownicy') ?>" class="admin-sidebar__link <?= $activeSection === 'users' ? 'is-active' : '' ?>">
      <i class="fas fa-users"></i>
      <span>Użytkownicy</span>
    </a>
    <a href="<?= url('admin/egzaminy') ?>" class="admin-sidebar__link <?= $activeSection === 'exams' ? 'is-active' : '' ?>">
      <i class="fas fa-file-lines"></i>
      <span>Egzaminy</span>
    </a>
    <a href="<?= url('admin/pytania') ?>" class="admin-sidebar__link <?= $activeSection === 'questions' ? 'is-active' : '' ?>">
      <i class="fas fa-circle-question"></i>
      <span>Pytania</span>
    </a>
    <a href="<?= url('admin/analytics') ?>" class="admin-sidebar__link <?= $activeSection === 'analytics' ? 'is-active' : '' ?>">
      <i class="fas fa-chart-line"></i>
      <span>Analityka</span>
    </a>
    <a href="<?= url('admin/audyt') ?>" class="admin-sidebar__link <?= $activeSection === 'audit' ? 'is-active' : '' ?>">
      <i class="fas fa-clipboard-list"></i>
      <span>Audyt</span>
    </a>
  </nav>

  <div class="admin-sidebar__footer">
    <a href="<?= url('/') ?>" class="admin-sidebar__back-link">
      <i class="fas fa-arrow-left"></i>
      <span>Powrót do aplikacji</span>
    </a>
  </div>
</aside>
