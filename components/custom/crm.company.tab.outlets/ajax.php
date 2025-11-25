<?php
/**
 * AJAX загрузчик компонента для вкладки CRM
 * /local/components/custom/crm.company.tab.outlets/ajax.php
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

global $APPLICATION, $USER;

// Логирование
$logFile = '/tmp/tabs.log';
$log = "[" . date('Y-m-d H:i:s') . "] ajax.php called\n";
file_put_contents($logFile, $log, FILE_APPEND);

// Проверка авторизации
if (!$USER->IsAuthorized()) {
    echo 'Необходима авторизация';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] User not authorized\n", FILE_APPEND);
    die();
}

// Получение параметров
$action = $_REQUEST['action'] ?? '';
$companyId = intval($_REQUEST['PARAMS']['params']['COMPANY_ID'] ?? 0);

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Action: $action, CompanyID: $companyId\n", FILE_APPEND);

if (!$companyId) {
    print_r($_REQUEST);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: No company ID\n", FILE_APPEND);
    die();
}

// Подключение модулей
use Bitrix\Main\Loader;

if (!Loader::includeModule('crm')) {
    echo 'Модуль CRM не установлен';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: CRM module not loaded\n", FILE_APPEND);
    die();
}

if (!Loader::includeModule('highloadblock')) {
    echo 'Модуль Highloadblock не установлен';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: Highloadblock module not loaded\n", FILE_APPEND);
    die();
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Modules loaded, including component...\n", FILE_APPEND);

// Подключение компонента
ob_start();

try {
    $APPLICATION->IncludeComponent(
        'custom:crm.company.tab.outlets',
        '.default',
        [
            'COMPANY_ID' => $companyId,
            'TAB_CODE' => 'tab_outlets',
        ],
        false
    );
    
    $content = ob_get_clean();
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Component included successfully. Content length: " . strlen($content) . "\n", FILE_APPEND);
    
    echo $content;
    
} catch (Exception $e) {
    $error = ob_get_clean();
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Output buffer: " . $error . "\n", FILE_APPEND);
    echo 'Ошибка загрузки компонента: ' . $e->getMessage();
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');