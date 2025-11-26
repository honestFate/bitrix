<?php
// /local/components/custom/crm.company.tab.contracts/ajax.php

$_REQUEST['component'] = 'crm.company.tab.contracts';
$_GET['component'] = 'crm.company.tab.contracts';
$_POST['component'] = 'crm.company.tab.contracts';

$universalAjaxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/ajax.php';

if (!file_exists($universalAjaxPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Базовый AJAX обработчик не найден',
    ], JSON_UNESCAPED_UNICODE);
    die();
}

require_once $universalAjaxPath;