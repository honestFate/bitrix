<?php
/**
 * AJAX endpoint для получения CRM активностей для календаря Bitrix24
 * Расположение: /local/ajax/calendar_activities.php
 * 
 * Параметры:
 *   date_from - начало периода (YYYY-MM-DD)
 *   date_to   - конец периода (YYYY-MM-DD)
 *   debug     - режим отладки (1)
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Проверка модуля CRM
if (!Loader::includeModule('crm')) {
    http_response_code(500);
    die(json_encode(['error' => 'CRM module not loaded']));
}

// Проверка авторизации
$userId = $GLOBALS['USER']->GetID();
if (!$userId) {
    http_response_code(401);
    die(json_encode(['error' => 'Not authorized']));
}

// Получение параметров дат
$dateFrom = $_REQUEST['date_from'] ?? date('Y-m-d');
$dateTo = $_REQUEST['date_to'] ?? date('Y-m-d');

// Валидация формата дат (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']));
}

// Конвертируем даты для фильтра Bitrix (формат DD.MM.YYYY HH:MM:SS)
$dateFromBx = date('d.m.Y', strtotime($dateFrom)) . ' 00:00:00';
$dateToBx = date('d.m.Y', strtotime($dateTo)) . ' 23:59:59';

$activities = [];

// Фильтр для активностей
$filter = [
    'RESPONSIBLE_ID' => $userId,
    '>=START_TIME' => $dateFromBx,
    '<=START_TIME' => $dateToBx,
    'COMPLETED' => 'N'
];

// Получаем активности
$res = \CCrmActivity::GetList(
    ['START_TIME' => 'ASC'],
    $filter,
    false,
    false,
    [
        'ID', 
        'SUBJECT', 
        'START_TIME', 
        'END_TIME', 
        'TYPE_ID', 
        'OWNER_ID', 
        'OWNER_TYPE_ID',
        'DESCRIPTION',
        'PRIORITY'
    ]
);

// Маппинг типов активностей
$typeNames = [
    1 => 'Звонок входящий',
    2 => 'Встреча', 
    3 => 'Задача',
    4 => 'Письмо',
    5 => 'Звонок исходящий',
    6 => 'Дело'
];

// Маппинг типов владельцев
$ownerTypeMap = [
    1 => 'lead',
    2 => 'deal', 
    3 => 'contact',
    4 => 'company'
];

while ($row = $res->Fetch()) {
    // Парсим время
    $startTimestamp = strtotime($row['START_TIME']);
    $endTimestamp = strtotime($row['END_TIME']);
    
    // Если не удалось распарсить, пропускаем
    if (!$startTimestamp) continue;
    
    // Если время окончания некорректно, делаем +1 час
    if (!$endTimestamp || $endTimestamp <= $startTimestamp) {
        $endTimestamp = $startTimestamp + 3600;
    }
    
    // Определяем тип владельца
    $ownerType = $ownerTypeMap[$row['OWNER_TYPE_ID']] ?? 'deal';
    
    // Определяем название типа активности
    $typeName = $typeNames[$row['TYPE_ID']] ?? 'Дело';
    
    // Проверяем на событие на весь день
    $isAllDay = (
        date('H:i', $startTimestamp) === '00:00' && 
        date('H:i', $endTimestamp) === '00:00'
    );
    
    $activities[] = [
        'id' => (int)$row['ID'],
        'title' => $row['SUBJECT'] ?: $typeName,
        'type' => $typeName,
        'typeId' => (int)$row['TYPE_ID'],
        // Формат DD.MM.YYYY для JS
        'dateFrom' => date('d.m.Y', $startTimestamp),
        'dateTo' => date('d.m.Y', $endTimestamp),
        // Формат HH:MM для JS
        'timeFrom' => date('H:i', $startTimestamp),
        'timeTo' => date('H:i', $endTimestamp),
        // Владелец
        'ownerId' => (int)$row['OWNER_ID'],
        'ownerType' => $ownerType,
        // Дополнительно
        'isAllDay' => $isAllDay,
        'description' => mb_substr($row['DESCRIPTION'] ?? '', 0, 200)
    ];
}

// Режим отладки
if (!empty($_REQUEST['debug'])) {
    echo json_encode([
        'debug' => [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateFromBx' => $dateFromBx,
            'dateToBx' => $dateToBx,
            'userId' => $userId,
            'count' => count($activities)
        ],
        'data' => $activities
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode($activities, JSON_UNESCAPED_UNICODE);
}