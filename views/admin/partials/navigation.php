<?php
/**
 * @file navigation.php
 * @description
 * Nawigacja boczna panelu administracyjnego.
 *
 * @version 1.0.0
 */

$activeSection = $activeSection ?? 'dashboard';
// Ukrycie linków to wyłącznie UX — dostęp egzekwują server-side guardy
// (requireAdmin/requireContentManager) w kontrolerach.
$navIsFullAdmin = isset($currentUser) && $currentUser !== null && $currentUser->isAdmin();
$navNewReports = (new \App\Models\QuestionReport())->countNew();
?>

<aside class="admin-sidebar">
  <div class="admin-sidebar__brand">
    <span class="admin-sidebar__eyebrow"><?= $navIsFullAdmin ? 'Admin' : 'Redaktor' ?></span>
    <h2 class="admin-sidebar__title">Panel administracyjny</h2>
  </div>

  <nav class="admin-sidebar__nav" aria-label="Nawigacja panelu CMS">
    <a href="<?= url('admin') ?>" class="admin-sidebar__link <?= $activeSection === 'dashboard' ? 'is-active' : '' ?>">
      <i class="fas fa-gauge-high"></i>
      <span>Pulpit</span>
    </a>
    <?php if ($navIsFullAdmin): ?>
      <a href="<?= url('admin/uzytkownicy') ?>" class="admin-sidebar__link <?= $activeSection === 'users' ? 'is-active' : '' ?>">
        <i class="fas fa-users"></i>
        <span>Użytkownicy</span>
      </a>
      <a href="<?= url('admin/egzaminy') ?>" class="admin-sidebar__link <?= $activeSection === 'exams' ? 'is-active' : '' ?>">
        <i class="fas fa-file-lines"></i>
        <span>Egzaminy</span>
      </a>
    <?php endif; ?>
    <a href="<?= url('admin/pytania') ?>" class="admin-sidebar__link <?= $activeSection === 'questions' ? 'is-active' : '' ?>">
      <i class="fas fa-circle-question"></i>
      <span>Pytania</span>
    </a>
    <a href="<?= url('admin/zgloszenia') ?>" class="admin-sidebar__link <?= $activeSection === 'reports' ? 'is-active' : '' ?>">
      <i class="fas fa-flag"></i>
      <span>Zgłoszenia</span>
      <?php if ($navNewReports > 0): ?>
        <span class="admin-sidebar__count" aria-label="<?= $navNewReports ?> nowych zgłoszeń"><?= $navNewReports ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= url('admin/arkusze') ?>" class="admin-sidebar__link <?= $activeSection === 'sheets' ? 'is-active' : '' ?>">
      <i class="fas fa-file-pdf"></i>
      <span>Arkusze</span>
    </a>
    <?php if ($navIsFullAdmin): ?>
      <a href="<?= url('admin/analytics') ?>" class="admin-sidebar__link <?= $activeSection === 'analytics' ? 'is-active' : '' ?>">
        <i class="fas fa-chart-line"></i>
        <span>Analityka</span>
      </a>
      <a href="<?= url('admin/audyt') ?>" class="admin-sidebar__link <?= $activeSection === 'audit' ? 'is-active' : '' ?>">
        <i class="fas fa-clipboard-list"></i>
        <span>Audyt</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="admin-sidebar__footer">
    <a href="<?= url('/') ?>" class="admin-sidebar__back-link">
      <i class="fas fa-arrow-left"></i>
      <span>Powrót do aplikacji</span>
    </a>
  </div>
</aside>
