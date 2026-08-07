<?php

/**
 * Generuje responsywne warianty obrazów kart egzaminów (public/images/exams/).
 *
 * Uruchamiane ręcznie, jednorazowo po dodaniu/zmianie źródłowego obrazu karty -
 * wygenerowane pliki commituje się do repo (public/images/exams/ jest śledzone
 * gitem), więc trafiają na produkcję zwykłym `git pull` w deployu, bez potrzeby
 * dodatkowego kroku w deploy.php.
 *
 * Użycie: php scripts/generate_exam_image_variants.php
 */

$widths = [400, 800, 1200];
$quality = 82;

$sourceDir = __DIR__ . '/../public/images/exams';
$sources = [
  'inf02-systemy-i-sieci.webp',
  'inf03-programowanie-webowe.webp',
  'inf04-aplikacje.webp',
];

foreach ($sources as $filename) {
  $sourcePath = $sourceDir . '/' . $filename;

  if (!is_file($sourcePath)) {
    fwrite(STDERR, "Pominięto (brak pliku): $filename\n");
    continue;
  }

  $info = getimagesize($sourcePath);
  if ($info === false) {
    fwrite(STDERR, "Pominięto (nieprawidłowy obraz): $filename\n");
    continue;
  }

  [$origWidth, $origHeight] = $info;
  $baseName = pathinfo($filename, PATHINFO_FILENAME);
  $sourceMtime = filemtime($sourcePath);

  foreach ($widths as $targetWidth) {
    if ($targetWidth >= $origWidth) {
      // Wariant nie mniejszy niż oryginał nic by nie oszczędził.
      continue;
    }

    $targetPath = "$sourceDir/{$baseName}-{$targetWidth}.webp";

    if (is_file($targetPath) && filemtime($targetPath) >= $sourceMtime) {
      echo "Aktualny, pomijam: {$baseName}-{$targetWidth}.webp\n";
      continue;
    }

    $targetHeight = (int) round($targetWidth * $origHeight / $origWidth);

    $source = imagecreatefromwebp($sourcePath);
    if ($source === false) {
      fwrite(STDERR, "Nie udało się wczytać: $filename\n");
      continue;
    }

    $resized = imagescale($source, $targetWidth, $targetHeight, IMG_BICUBIC_FIXED);
    imagedestroy($source);

    if ($resized === false) {
      fwrite(STDERR, "Nie udało się przeskalować: $filename ($targetWidth px)\n");
      continue;
    }

    imagewebp($resized, $targetPath, $quality);
    imagedestroy($resized);

    echo "Wygenerowano: {$baseName}-{$targetWidth}.webp ({$targetWidth}x{$targetHeight})\n";
  }
}
