<?php
/**
 * AJAX загрузчик для вкладки "Торговые точки"
 * Делегирует работу универсальному обработчику
 * 
 * /local/components/custom/crm.company.tab.outlets/ajax.php
 */

// Устанавливаем компонент по умолчанию
if (!isset($_REQUEST['component'])) {
    $_REQUEST['component'] = 'crm.company.tab.outlets';
}
if (!isset($_GET['component'])) {
    $_GET['component'] = 'crm.company.tab.outlets';
}
if (!isset($_POST['component'])) {
    $_POST['component'] = 'crm.company.tab.outlets';
}

// Проверяем существование универсального обработчика
$universalAjaxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/ajax.php';

if (!file_exists($universalAjaxPath)) {
    // Если универсального обработчика нет - выводим ошибку
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Универсальный AJAX обработчик не найден',
        'expected_path' => $universalAjaxPath,
        'hint' => 'Создайте файл /local/components/custom/crm.company.tab.base/ajax.php'
    ], JSON_UNESCAPED_UNICODE);
    die();
}

// Делегируем работу универсальному обработчику
require_once $universalAjaxPath;