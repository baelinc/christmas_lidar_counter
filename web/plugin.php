<?php
/* The page (ui.php) calls "plugin.php?page=ajax.php&nopage=1&action=..."; answer it with ajax.php. */
if (($_GET['page'] ?? '') !== 'ajax.php') { http_response_code(404); exit; }
require __DIR__ . '/ajax.php';
