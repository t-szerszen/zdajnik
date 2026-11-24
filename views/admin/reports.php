<?php
/**
 * @file reports.php
 * @description
 * Lista zgłoszeń błędów w pytaniach (panel CMS) z filtrami i akcjami statusu.
 */
$noIndex = true;
$pageTitle = $pageTitle ?? 'Zgłoszenia błędów | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Zgłoszenia błędów w pytaniach.';
$filters = $filters ?? [];
$reports = $reports ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'total' => 0];
$categories = $categories ?? [];
$statuses = $statuses ?? [];
$contexts = $contexts ?? [];

$statusTone = ['new' => 'warning', 'resolved' => 'success', 'rejected' => 'neutral'];
$buildUrl = static function (array $overrides = []) use ($filters): string {
  // status '' (Wszystkie) musi przetrwać w URL-u - brak parametru oznacza domyślnie "nowe"
  $params = array_merge($filters, ['page' => 1], $overrides);
  $params = array_filter($params, static fn($value, $key) => $key === 'status' || ($value !== null && $value !== ''), ARRAY_FILTER_USE_BOTH);
  return url('admin/zgloszenia') . '?' . http_build_query($params);
};
$currentUrl = $buildUrl(['page' => (int) ($pagination['page'] ?? 1)]);
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : '';
$excerpt = static function (?string $markdown): string {
  $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $markdown)));
  $plain = preg_replace('/[`*_#>]+/u', '', $plain);
  return mb_strlen($plain) > 220 ? mb_substr($plain, 0, 220) . '…' : $plain;
};
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>
<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>
  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Zgłoszenia błędów</span></h1>
        <p class="page-header__text">Błędy w pytaniach zauważone przez uczniów.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'reports'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>
          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/zgloszenia') ?>">
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><?php foreach ($statuses as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Kategoria</span><select name="category"><option value="">Wszystkie</option><?php foreach ($categories as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['category'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>ID pytania</span><input type="number" min="1" name="question_id" value="<?= htmlspecialchars((string) ($filters['question_id'] ?? '')) ?>"></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/zgloszenia') ?>">Wyczyść</a></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-flag"></i><span>Zgłoszenia</span></div>
              <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>

            <?php if (empty($reports)): ?>
              <div class="admin-empty">Brak zgłoszeń spełniających kryteria.</div>
            <?php else: ?>
              <div class="admin-audit">
                <?php foreach ($reports as $report): ?>
                  <?php
                  $questionId = (int) $report['question_id'];
                  $reporter = $report['user_id']
                    ? (trim(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')) ?: (string) $report['email'])
                    : 'Gość';
                  $isOutdated = $report['reported_version_num'] !== null
                    && $report['current_version_num'] !== null
                    && (int) $report['reported_version_num'] < (int) $report['current_version_num'];
                  ?>
                  <article class="admin-audit__item admin-report">
                    <div class="admin-audit__top">
                      <div>
                        <strong>#<?= (int) $report['id'] ?> · <?= htmlspecialchars($categories[$report['category']] ?? $report['category']) ?></strong>
                        <small class="admin-report__meta">
                          <a href="<?= url('admin/pytania/' . $questionId) ?>">Pytanie <?= $questionId ?></a>
                          · <?= htmlspecialchars((string) $report['exam_code']) ?>
                          <?php if ($report['reported_version_num'] !== null): ?>· wersja <?= (int) $report['reported_version_num'] ?><?php endif; ?>
                          · <?= htmlspecialchars($reporter) ?>
                          <?php if (!empty($report['context'])): ?>· <?= htmlspecialchars($contexts[$report['context']] ?? $report['context']) ?><?php endif; ?>
                          · <?= $date($report['created_at']) ?>
                        </small>
                      </div>
                      <div class="admin-audit__meta">
                        <?php if ($isOutdated): ?>
                          <span class="admin-status admin-status--accent" title="Od czasu zgłoszenia pytanie zostało edytowane">Jest nowsza wersja (<?= (int) $report['current_version_num'] ?>)</span>
                        <?php endif; ?>
                        <?php if ((int) $report['open_for_question'] > 1): ?>
                          <a class="admin-status admin-status--danger" href="<?= htmlspecialchars($buildUrl(['question_id' => $questionId, 'status' => 'new'])) ?>"><?= (int) $report['open_for_question'] ?> otwarte do pytania</a>
                        <?php endif; ?>
                        <span class="admin-status admin-status--<?= $statusTone[$report['status']] ?? 'neutral' ?>"><?= htmlspecialchars($statuses[$report['status']] ?? $report['status']) ?></span>
                      </div>
                    </div>

                    <p class="admin-report__question"><?= htmlspecialchars($excerpt($report['question_text'] ?? '')) ?></p>

                    <?php if (!empty($report['message'])): ?>
                      <blockquote class="admin-report__message"><?= nl2br(htmlspecialchars((string) $report['message'])) ?></blockquote>
                    <?php endif; ?>

                    <div class="admin-report__footer">
                      <?php if ($report['status'] !== 'new' && !empty($report['resolved_at'])): ?>
                        <small class="admin-report__meta">
                          Rozpatrzone <?= $date($report['resolved_at']) ?>
                          <?php if (!empty($report['resolver_first_name'])): ?>przez <?= htmlspecialchars(trim($report['resolver_first_name'] . ' ' . ($report['resolver_last_name'] ?? ''))) ?><?php endif; ?>
                        </small>
                      <?php endif; ?>
                      <div class="admin-actions">
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/' . $questionId . '/edytuj') ?>"><i class="fas fa-pen"></i> Edytuj pytanie</a>
                        <?php foreach (($report['status'] === 'new' ? ['resolved' => 'Poprawione', 'rejected' => 'Odrzuć'] : ['new' => 'Przywróć']) as $targetStatus => $label): ?>
                          <form method="POST" action="<?= url('admin/zgloszenia/' . (int) $report['id'] . '/status') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="status" value="<?= $targetStatus ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl) ?>">
                            <button type="submit" class="btn btn--sm <?= $targetStatus === 'resolved' ? 'btn--primary' : 'btn--secondary' ?>"><?= $label ?></button>
                          </form>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination"><span class="admin-pagination__status">Strona <?= (int) $pagination['page'] ?> / <?= (int) $pagination['totalPages'] ?></span><div class="admin-actions"><?php if ($pagination['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] - 1])) ?>">Poprzednia</a><?php endif; ?><?php if ($pagination['page'] < $pagination['totalPages']): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] + 1])) ?>">Następna</a><?php endif; ?></div></div>
            <?php endif; ?>
          </section>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
