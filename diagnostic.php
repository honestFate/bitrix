<?php
/**
 * Скрипт диагностики вкладок CRM
 * Разместите этот файл в /local/diagnostic.php
 * Откройте в браузере: http://ваш-сайт.ru/local/diagnostic.php
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Highloadblock\HighloadBlockTable;

header('Content-Type: text/html; charset=utf-8');

echo '<html><head><meta charset="utf-8"><title>Диагностика вкладок CRM</title>';
echo '<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
</style></head><body>';

echo '<h1>Диагностика системы вкладок CRM</h1>';

// 1. Проверка существования файла init.php
echo '<h2>1. Проверка файла init.php</h2>';
$initPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
if (file_exists($initPath)) {
    echo '<p class="success">✓ Файл init.php существует: ' . $initPath . '</p>';
    echo '<p class="info">Размер файла: ' . filesize($initPath) . ' байт</p>';
    echo '<p class="info">Права доступа: ' . substr(sprintf('%o', fileperms($initPath)), -4) . '</p>';
} else {
    echo '<p class="error">✗ Файл init.php НЕ найден: ' . $initPath . '</p>';
    echo '<p>Создайте файл или проверьте путь!</p>';
}

// 2. Проверка подключения init.php
echo '<h2>2. Проверка подключения init.php</h2>';
if (function_exists('addCustomCrmCompanyTabs')) {
    echo '<p class="success">✓ Функция addCustomCrmCompanyTabs определена - init.php подключен!</p>';
} else {
    echo '<p class="error">✗ Функция addCustomCrmCompanyTabs НЕ определена</p>';
    echo '<p>Возможные причины:</p>';
    echo '<ul>';
    echo '<li>init.php не подключается автоматически</li>';
    echo '<li>Синтаксическая ошибка в init.php</li>';
    echo '<li>Функция не объявлена в init.php</li>';
    echo '</ul>';
}

// 3. Проверка класса прав доступа
echo '<h2>3. Проверка класса CrmHlTabPermissions</h2>';
if (class_exists('CrmHlTabPermissions')) {
    echo '<p class="success">✓ Класс CrmHlTabPermissions загружен</p>';
} else {
    echo '<p class="error">✗ Класс CrmHlTabPermissions НЕ найден</p>';
    $classPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/CrmHlTabPermissions.php';
    if (file_exists($classPath)) {
        echo '<p class="warning">Файл существует, но класс не загружен. Проверьте синтаксис!</p>';
    } else {
        echo '<p class="error">Файл не существует: ' . $classPath . '</p>';
    }
}

// 4. Проверка модулей
echo '<h2>4. Проверка модулей Bitrix</h2>';
if (CModule::IncludeModule('crm')) {
    echo '<p class="success">✓ Модуль CRM подключен</p>';
} else {
    echo '<p class="error">✗ Модуль CRM не установлен или не активен</p>';
}

if (CModule::IncludeModule('highloadblock')) {
    echo '<p class="success">✓ Модуль Highloadblock подключен</p>';
} else {
    echo '<p class="error">✗ Модуль Highloadblock не установлен или не активен</p>';
}

// 5. Проверка компонентов
echo '<h2>5. Проверка компонентов</h2>';
$baseComponent = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/class.php';
$outletsComponent = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.outlets/class.php';

if (file_exists($baseComponent)) {
    echo '<p class="success">✓ Базовый компонент существует</p>';
} else {
    echo '<p class="error">✗ Базовый компонент не найден: ' . $baseComponent . '</p>';
}

if (file_exists($outletsComponent)) {
    echo '<p class="success">✓ Компонент торговых точек существует</p>';
} else {
    echo '<p class="error">✗ Компонент торговых точек не найден: ' . $outletsComponent . '</p>';
}

// 6. Проверка AJAX обработчика
echo '<h2>6. Проверка AJAX обработчика</h2>';
$ajaxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/ajax/crm_hl_tabs/save_data.php';
if (file_exists($ajaxPath)) {
    echo '<p class="success">✓ AJAX обработчик существует</p>';
} else {
    echo '<p class="error">✗ AJAX обработчик не найден: ' . $ajaxPath . '</p>';
}

// 7. Проверка Highload-блоков
echo '<h2>7. Проверка Highload-блоков</h2>';
if (CModule::IncludeModule('highloadblock')) {

    $rsHl = HighloadBlockTable::getList([
        'select' => ['ID', 'NAME', 'TABLE_NAME'],
        'order' => ['ID' => 'ASC']
    ]);
    
    echo '<p>Доступные Highload-блоки:</p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>ID</th><th>Название</th><th>Таблица</th></tr>';
    
    $hasHl5 = false;
    while ($hl = $rsHl->fetch()) {
        echo '<tr>';
        echo '<td>' . $hl['ID'] . '</td>';
        echo '<td>' . htmlspecialchars($hl['NAME']) . '</td>';
        echo '<td>' . $hl['TABLE_NAME'] . '</td>';
        echo '</tr>';
        
        if ($hl['ID'] == 5) {
            $hasHl5 = true;
        }
    }
    echo '</table>';
    
    if ($hasHl5) {
        echo '<p class="success">✓ Highload-блок с ID=5 найден</p>';
    } else {
        echo '<p class="warning">⚠ Highload-блок с ID=5 НЕ найден. Создайте его или измените ID в компоненте.</p>';
    }
} else {
    echo '<p class="error">Модуль Highloadblock не подключен</p>';
}

// 8. Проверка регистрации обработчиков событий
echo '<h2>8. Проверка регистрации обработчиков</h2>';
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();
$handlers = $eventManager->findEventHandlers('crm', 'onEntityDetailsTabsInitialized');

if (!empty($handlers)) {
    echo '<p class="success">✓ Найдены обработчики события onEntityDetailsTabsInitialized:</p>';
    echo '<pre>';
    print_r($handlers);
    echo '</pre>';
} else {
    echo '<p class="error">✗ Обработчики события onEntityDetailsTabsInitialized НЕ найдены!</p>';
    echo '<p>Это означает, что вкладки не зарегистрированы.</p>';
}

// 9. Проверка прав текущего пользователя
echo '<h2>9. Проверка прав пользователя</h2>';
global $USER;
if ($USER->IsAuthorized()) {
    echo '<p class="success">✓ Пользователь авторизован: ID=' . $USER->GetID() . '</p>';
    
    $userGroups = CUser::GetUserGroup($USER->GetID());
    echo '<p>Группы пользователя: ' . implode(', ', $userGroups) . '</p>';
    
    if (CModule::IncludeModule('crm')) {
        $crmPerms = new CCrmPerms($USER->GetID());
        if ($crmPerms->HavePerm('COMPANY', 0, 'READ')) {
            echo '<p class="success">✓ Есть права на чтение компаний в CRM</p>';
        } else {
            echo '<p class="error">✗ НЕТ прав на чтение компаний в CRM</p>';
        }
    }
} else {
    echo '<p class="error">✗ Пользователь не авторизован</p>';
}

// 10. Тест вызова функции регистрации вкладок
echo '<h2>10. Тест вызова функции регистрации вкладок</h2>';
if (function_exists('addCustomCrmCompanyTabs')) {
    echo '<p>Попытка вызова функции addCustomCrmCompanyTabs...</p>';
    
    // Создаем фейковый event
    $fakeEvent = new \Bitrix\Main\Event('crm', 'onEntityDetailsTabsInitialized', [
        'entityTypeID' => CCrmOwnerType::Company,
        'entityID' => 1
    ]);
    
    try {
        $tabs = addCustomCrmCompanyTabs($fakeEvent);
        if (!empty($tabs)) {
            echo '<p class="success">✓ Функция вернула вкладки:</p>';
            echo '<pre>';
            print_r($tabs);
            echo '</pre>';
        } else {
            echo '<p class="warning">⚠ Функция не вернула вкладки (пустой массив)</p>';
            echo '<p>Возможные причины:</p>';
            echo '<ul>';
            echo '<li>Нет прав доступа к вкладкам</li>';
            echo '<li>Проверка прав блокирует отображение</li>';
            echo '</ul>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Ошибка при вызове функции: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">✗ Функция не определена, тест невозможен</p>';
}

// 11. Рекомендации
echo '<h2>11. Рекомендации по исправлению</h2>';

$errors = [];
if (!file_exists($initPath)) {
    $errors[] = 'Создайте файл /local/php_interface/init.php';
}
if (!function_exists('addCustomCrmCompanyTabs')) {
    $errors[] = 'Проверьте, что в init.php правильно объявлена функция addCustomCrmCompanyTabs';
}
if (!class_exists('CrmHlTabPermissions')) {
    $errors[] = 'Проверьте путь к классу CrmHlTabPermissions и подключите его в init.php';
}
if (!CModule::IncludeModule('crm')) {
    $errors[] = 'Установите модуль CRM';
}
if (!CModule::IncludeModule('highloadblock')) {
    $errors[] = 'Установите модуль Highloadblock';
}

if (!empty($errors)) {
    echo '<ol>';
    foreach ($errors as $error) {
        echo '<li class="error">' . $error . '</li>';
    }
    echo '</ol>';
} else {
    echo '<p class="success">Все основные проверки пройдены! Если вкладка все равно не отображается:</p>';
    echo '<ul>';
    echo '<li>Очистите кеш Bitrix (Настройки → Производительность → Очистить кеш)</li>';
    echo '<li>Проверьте права доступа в crm_tab_permissions.php</li>';
    echo '<li>Попробуйте другой браузер или режим инкогнито</li>';
    echo '<li>Проверьте консоль браузера (F12) на наличие JavaScript ошибок</li>';
    echo '</ul>';
}

echo '</body></html>';