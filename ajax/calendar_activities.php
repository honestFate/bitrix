<?php
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('crm')) {
    die(json_encode(['error' => 'CRM module not loaded']));
}

$userId = $GLOBALS['USER']->GetID();
if (!$userId) {
    die(json_encode(['error' => 'Not authorized']));
}

$dateFrom = $_REQUEST['date_from'] ?? date('Y-m-d');
$dateTo = $_REQUEST['date_to'] ?? date('Y-m-d');

$activities = [];

// Получаем дела через старый API (более надёжно)
$filter = [
    'RESPONSIBLE_ID' => $userId,
    '>=START_TIME' => $dateFrom . ' 00:00:00',
    '<=START_TIME' => $dateTo . ' 23:59:59',
    'COMPLETED' => 'N'
];

$res = \CCrmActivity::GetList(
    ['START_TIME' => 'ASC'],
    $filter,
    false,
    false,
    ['ID', 'SUBJECT', 'START_TIME', 'END_TIME', 'TYPE_ID', 'OWNER_ID', 'OWNER_TYPE_ID', 'DESCRIPTION']
);

$typeNames = [
    1 => 'Звонок',
    2 => 'Встреча', 
    3 => 'Задача',
    4 => 'Письмо',
    5 => 'Звонок',
    6 => 'Дело'
];

while ($row = $res->Fetch()) {
    $startTime = new DateTime($row['START_TIME']);
    $endTime = new DateTime($row['END_TIME']);
    
    // Определяем тип сущности для ссылки
    $ownerTypeMap = [
        1 => 'lead',
        2 => 'deal', 
        3 => 'contact',
        4 => 'company'
    ];
    $ownerType = $ownerTypeMap[$row['OWNER_TYPE_ID']] ?? 'deal';
    
    $activities[] = [
        'id' => (int)$row['ID'],
        'title' => $row['SUBJECT'],
        'type' => $typeNames[$row['TYPE_ID']] ?? 'Дело',
        'typeId' => (int)$row['TYPE_ID'],
        'dateFrom' => $startTime->format('d.m.Y'),
        'dateTo' => $endTime->format('d.m.Y'),
        'timeFrom' => $startTime->format('H:i'),
        'timeTo' => $endTime->format('H:i'),
        'timestampFrom' => $startTime->getTimestamp(),
        'timestampTo' => $endTime->getTimestamp(),
        'ownerId' => (int)$row['OWNER_ID'],
        'ownerType' => $ownerType,
        'isAllDay' => ($startTime->format('H:i') === '00:00' && $endTime->format('H:i') === '00:00'),
    ];
}

echo json_encode($activities);