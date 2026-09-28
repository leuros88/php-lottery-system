<?php
// TEMPLATE for new languages. Copy to xx.php (e.g. it.php), translate the
// VALUES, keep the KEYS and {placeholders} exactly as in en.php.
// Then register it in includes/lang.php:
//   'xx' => ['label' => 'Native name', 'flag' => '🏳️'],
// Verify with: php -r '...' (see AGENTS.md) or ?lang=xx in the browser.
return require __DIR__ . '/en.php';
