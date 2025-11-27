<?php
/**
 * AJAX обработчик для сохранения данных (обёртка над hl_api.php)
 * /local/ajax/save_data.php
 * 
 * Оставлен для обратной совместимости с существующими компонентами
 */

// Перенаправляем на новый API
$_GET['action'] = $_POST['action'] ?? $_GET['action'] ?? 'get';
$_GET['hlBlockId'] = $_POST['hlBlockId'] ?? $_GET['hlBlockId'] ?? 0;
$_GET['itemId'] = $_POST['itemId'] ?? $_GET['itemId'] ?? 0;
$_GET['companyId'] = $_POST['companyId'] ?? $_GET['companyId'] ?? 0;

require_once __DIR__ . '/hl_api.php';