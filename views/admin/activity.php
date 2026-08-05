<?php
// Ustawione jawnie, mimo że analytics.php również to robi - alias nie powinien
// polegać na tym, że dołączany widok pamięta o wykluczeniu z indeksowania.
$noIndex = true;

include __DIR__ . '/analytics.php';
