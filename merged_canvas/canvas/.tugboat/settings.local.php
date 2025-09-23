<?php
// phpcs:ignoreFile

// Add tugboat URLs to the Drupal trusted host patterns.
$settings['trusted_host_patterns'] = ['\.tugboatqa\.com$'];

// Set memory_limit to unlimited for CLI operations.
if (PHP_SAPI === 'cli') {
  ini_set('memory_limit', '-1');
}

// Allow hidden modules to be installed.
$settings['extension_discovery_scan_tests'] = TRUE;
