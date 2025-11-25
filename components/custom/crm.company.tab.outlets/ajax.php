<?php
/**
 * AJAX загрузчик для вкладки "Торговые точки"
 * /local/components/custom/crm.company.tab.outlets/ajax.php
 */

// Устанавливаем компонент
$_REQUEST['component'] = 'crm.company.tab.outlets';
$_GET['component'] = 'crm.company.tab.outlets';
$_POST['component'] = 'crm.company.tab.outlets';

// Путь к универсальному обработчику
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