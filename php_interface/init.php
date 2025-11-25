<?php
/**
 * Файл инициализации для регистрации вкладок CRM и подключения классов
 */

use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

define('CUSTOM_LOG_FILE', '/tmp/tabs.log');

// Функция логирования
function logTab($message) {
    $log = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents(CUSTOM_LOG_FILE, $log, FILE_APPEND);
}

logTab("========== INIT.PHP LOADED ==========");

// Подключение класса управления правами
require_once __DIR__ . '/classes/CrmHlTabPermissions.php';

/**
 * Регистрация вкладок в карточке компании CRM
 */
EventManager::getInstance()->addEventHandler(
    'crm',
    'onEntityDetailsTabsInitialized',
    'addCustomCrmCompanyTabs'
);

function addCustomCrmCompanyTabs($event)
{
    logTab("=== Function addCustomCrmCompanyTabs called ===");
    
    $tabs = $event->getParameter('tabs');
    $entityTypeId = $event->getParameter('entityTypeID');
    $entityId = $event->getParameter('entityID');
    
    logTab("EntityTypeID: " . var_export($entityTypeId, true));
    logTab("EntityID: " . var_export($entityId, true));
    logTab("Expected Company Type: " . CCrmOwnerType::Company);
    
    // Проверяем, что это компания (entityTypeID = 4 для компаний)
    if ($entityTypeId !== CCrmOwnerType::Company) {
        logTab("NOT A COMPANY! Exiting...");
        return;
    }
    
    logTab("This is a COMPANY entity");
    
    global $USER;
    $userId = $USER->GetID();
    logTab("Current User ID: " . $userId);
    
    // Проверка прав доступа
    $hasAccess = CrmHlTabPermissions::checkAccess($userId, 'tab_outlets', 'READ');
    logTab("Access check result for tab_outlets: " . ($hasAccess ? 'YES' : 'NO'));
    
    // Вкладка "Торговые точки"
    if ($hasAccess) {
        logTab("Adding tab_outlets to tabs array");
        $tabs[] = [
            'id' => 'tab_outlets',
            'name' => 'Торговые точки',
            'loader' => [
                'serviceUrl' => '/local/components/custom/crm.company.tab.outlets/ajax.php?action=load',
                'componentData' => [
                    'template' => '.default',
                    'params' => [
                        'COMPANY_ID' => $entityId,
                        'TAB_CODE' => 'tab_outlets',
                    ]
                ]
            ]
        ];
    }
    
    logTab("Total tabs to return: " . count($tabs));
    logTab("Tabs array: " . print_r($tabs, true));
    
    return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, [
		'tabs' => $tabs,
	]);
}

/**
 * Альтернативный способ регистрации через более старый API
 * Используйте этот вариант, если первый не работает в вашей версии Bitrix
 */
/*
AddEventHandler('crm', 'OnAfterCrmCompanyDetailTabShow', 'addCustomCrmCompanyTabsOld');

function addCustomCrmCompanyTabsOld(&$arTabs)
{
    global $USER, $APPLICATION;
    $userId = $USER->GetID();
    
    // Получаем ID компании из запроса
    $companyId = intval($_REQUEST['company_id']);
    
    if (!$companyId) {
        return;
    }
    
    // Вкладка "Торговые точки"
    if (CrmHlTabPermissions::checkAccess($userId, 'tab_outlets', 'READ')) {
        ob_start();
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
        
        $arTabs[] = [
            'DIV' => 'tab_outlets',
            'TAB' => 'Торговые точки',
            'TITLE' => 'Торговые точки компании',
            'CONTENT' => $content,
        ];
    }
}
*/

/**
 * Регистрация обработчиков для автозагрузки классов компонентов
 */
spl_autoload_register(function ($class) {
    $classMap = [
        'CrmCompanyTabBase' => '/local/components/custom/crm.company.tab.base/class.php',
        'CrmCompanyTabOutlets' => '/local/components/custom/crm.company.tab.outlets/class.php',
        'CrmHlTabPermissions' => '/local/php_interface/classes/CrmHlTabPermissions.php',
    ];
    
    if (isset($classMap[$class])) {
        require_once $_SERVER['DOCUMENT_ROOT'] . $classMap[$class];
    }
});

/**
 * Вспомогательная функция для логирования
 * Используется для отладки прав доступа
 */
function logCrmTabAccess($userId, $tabCode, $action, $result)
{
    if (defined('CRM_TAB_DEBUG') && CRM_TAB_DEBUG === true) {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/tmp/crm_tab_access.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $message = sprintf(
            "[%s] User: %d, Tab: %s, Action: %s, Result: %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $tabCode,
            $action,
            $result ? 'ALLOWED' : 'DENIED'
        );
        
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}