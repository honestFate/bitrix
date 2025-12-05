<?php
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;

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

// Конвертируем из Y-m-d в d.m.Y для Bitrix
$dateFromBx = date('d.m.Y', strtotime($dateFrom)) . ' 00:00:00';
$dateToBx = date('d.m.Y', strtotime($dateTo)) . ' 23:59:59';

$activities = [];

$filter = [
    'RESPONSIBLE_ID' => $userId,
    '>=START_TIME' => $dateFromBx,
    '<=START_TIME' => $dateToBx,
    'COMPLETED' => 'N'
];

$res = \CCrmActivity::GetList(
    ['START_TIME' => 'ASC'],
    $filter,
    false,
    false,
    ['ID', 'SUBJECT', 'START_TIME', 'END_TIME', 'TYPE_ID', 'OWNER_ID', 'OWNER_TYPE_ID']
);

$typeNames = [
    1 => 'Звонок входящий',
    2 => 'Встреча', 
    3 => 'Задача',
    4 => 'Письмо',
    5 => 'Звонок исходящий',
    6 => 'Дело'
];

while ($row = $res->Fetch()) {
    $startTimestamp = strtotime($row['START_TIME']);
    $endTimestamp = strtotime($row['END_TIME']);
    
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
        'dateFrom' => date('d.m.Y', $startTimestamp),
        'dateTo' => date('d.m.Y', $endTimestamp),
        'timeFrom' => date('H:i', $startTimestamp),
        'timeTo' => date('H:i', $endTimestamp),
        'timestampFrom' => $startTimestamp,
        'timestampTo' => $endTimestamp,
        'ownerId' => (int)$row['OWNER_ID'],
        'ownerType' => $ownerType,
        'isAllDay' => (date('H:i', $startTimestamp) === '00:00' && date('H:i', $endTimestamp) === '00:00'),
    ];
}

echo json_encode($activities, JSON_UNESCAPED_UNICODE);