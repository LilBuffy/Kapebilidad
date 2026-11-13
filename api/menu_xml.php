<?php
require_once __DIR__ . '/../includes/menu_xml.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

try {
    echo menuXmlString(getDB(), true);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Menu is unavailable.</error>';
}
