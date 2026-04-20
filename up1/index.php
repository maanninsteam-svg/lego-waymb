<?php
/**
 * Raiz /up1/ → primeiro upsell, preservando query string (UTMs do checkout front).
 */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: up1/' . $qs, true, 302);
exit;
