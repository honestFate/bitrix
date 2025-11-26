<?php
/**
 * Файл инициализации для регистрации вкладок CRM
 * /local/php_interface/init.php
 */

use Bitrix\Main\EventManager;

// === НАСТРОЙКА ЛОГИРОВАНИЯ ===
// Определяем путь к лог-файлу для AddMessage2Log
if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/logs/crm_tabs.log');
}

// Создаём директорию для логов если не существует
$logDir = dirname(LOG_FILENAME);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Включить отладку (false на production)
define('CRM_TAB_DEBUG', true);

// Подключение класса управления правами
$permissionsClassPath = __DIR__ . '/classes/CrmHlTabPermissions.php';
if (file_exists($permissionsClassPath)) {
    require_once $permissionsClassPath;
}

/**
 * Регистрация вкладок в карточке компании CRM
 */
EventManager::getInstance()->addEventHandler(
    'crm',
    'onEntityDetailsTabsInitialized',
    'addCustomCrmCompanyTabs'
);

/**
 * Обработчик добавления вкладок
 */
function addCustomCrmCompanyTabs(\Bitrix\Main\Event $event)
{
    AddMessage2Log("=== addCustomCrmCompanyTabs called ===");
    
    $tabs = $event->getParameter('tabs');
    $entityTypeId = $event->getParameter('entityTypeID');
    $entityId = $event->getParameter('entityID');
    
    AddMessage2Log("EntityTypeID: {$entityTypeId}, EntityID: {$entityId}, Expected Company: " . \CCrmOwnerType::Company);
    
    // Проверяем, что это компания (entityTypeID = 4)
    if ($entityTypeId !== \CCrmOwnerType::Company) {
        AddMessage2Log("Not a company, skipping");
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            ['tabs' => $tabs]
        );
    }
    
    global $USER;
    $userId = $USER->GetID();
    
    // Проверка прав доступа
    $hasAccess = true;
    if (class_exists('CrmHlTabPermissions')) {
        $hasAccess = \CrmHlTabPermissions::checkAccess($userId, 'tab_outlets', 'READ');
    }
    
    AddMessage2Log("User: {$userId}, Access for tab_outlets: " . ($hasAccess ? 'YES' : 'NO'));
    
    // Вкладка "Торговые точки"
    if ($hasAccess) {
        $newTab = [
            'id' => 'tab_outlets',
            'name' => 'Торговые точки',
            'loader' => [
                'serviceUrl' => '/local/ajax/crm_tabs_loader.php',
                'componentData' => [
                    'template' => '.default',
                    'signedParameters' => \Bitrix\Main\Component\ParameterSigner::signParameters(
                        'custom:crm.company.tab.outlets',
                        [
                            'COMPANY_ID' => $entityId,
                            'TAB_CODE' => 'tab_outlets',
                        ]
                    ),
                    'params' => [
                        'COMPANY_ID' => $entityId,
                        'TAB_CODE' => 'tab_outlets',
                    ]
                ]
            ]
        ];

        // Вкладка "Договоры"
        $hasContractsAccess = true;
        if (class_exists('CrmHlTabPermissions')) {
            $hasContractsAccess = \CrmHlTabPermissions::checkAccess($userId, 'tab_contracts', 'READ');
        }
        
        if ($hasContractsAccess) {
            $tabs[] = [
                'id' => 'tab_contracts',
                'name' => 'Договоры',
                'loader' => [
                    'serviceUrl' => '/local/ajax/crm_tabs_loader.php',
                    'componentData' => [
                        'template' => '.default',
                        'signedParameters' => \Bitrix\Main\Component\ParameterSigner::signParameters(
                            'custom:crm.company.tab.contracts',
                            [
                                'COMPANY_ID' => $entityId,
                                'TAB_CODE' => 'tab_contracts',
                            ]
                        ),
                        'params' => [
                            'COMPANY_ID' => $entityId,
                            'TAB_CODE' => 'tab_contracts',
                        ]
                    ]
                ]
            ];
            
            AddMessage2Log("Tab contracts added");
        }
        
        $tabs[] = $newTab;
        
        AddMessage2Log("Tab added. ServiceUrl: " . $newTab['loader']['serviceUrl']);
    }
    
    AddMessage2Log("Total tabs: " . count($tabs));
    
    return new \Bitrix\Main\EventResult(
        \Bitrix\Main\EventResult::SUCCESS,
        ['tabs' => $tabs]
    );
}

/**
 * Автозагрузка классов компонентов
 */
spl_autoload_register(function ($class) {
    $classMap = [
        'CrmCompanyTabBase' => '/local/components/custom/crm.company.tab.base/class.php',
        'CrmCompanyTabOutlets' => '/local/components/custom/crm.company.tab.outlets/class.php',
        'CrmHlTabPermissions' => '/local/php_interface/classes/CrmHlTabPermissions.php',
    ];
    
    if (isset($classMap[$class])) {
        $path = $_SERVER['DOCUMENT_ROOT'] . $classMap[$class];
        if (file_exists($path)) {
            require_once $path;
        }
    }
});
