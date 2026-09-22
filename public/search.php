<?php
/**
 * Public Search Route Alias
 * Forwards requests to the central discovery and search module.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
redirect('client/search.php' . $queryString);
