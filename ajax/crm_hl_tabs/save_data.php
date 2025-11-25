<?php
/**
 * AJAX обработчик для сохранения/обновления/удаления данных в Highload-блоках
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

header('Content-Type: application/json');

// Проверка авторизации
if (!$USER->IsAuthorized()) {
    echo json_encode([
        'success' => false,
        'error' => 'Необходима авторизация'
    ]);
    die();
}

// Подключение модулей
if (!Loader::includeModule('highloadblock') || !Loader::includeModule('crm')) {
    echo json_encode([
        'success' => false,
        'error' => 'Не удалось подключить необходимые модули'
    ]);
    die();
}

$request = Application::getInstance()->getContext()->getRequest();

// Получение параметров
$action = $request->getPost('action'); // add, update, delete
$hlBlockId = intval($request->getPost('hlBlockId'));
$itemId = intval($request->getPost('itemId'));
$companyId = intval($request->getPost('companyId'));

// Валидация
if (!$hlBlockId) {
    echo json_encode([
        'success' => false,
        'error' => 'Не указан ID Highload-блока'
    ]);
    die();
}

if (!$companyId && $action !== 'delete') {
    echo json_encode([
        'success' => false,
        'error' => 'Не указан ID компании'
    ]);
    die();
}

// Проверка прав CRM
$crmPerms = new CCrmPerms($USER->GetID());

switch ($action) {
    case 'add':
        if (!$crmPerms->HavePerm('COMPANY', 0, 'WRITE')) {
            echo json_encode([
                'success' => false,
                'error' => 'Недостаточно прав для добавления'
            ]);
            die();
        }
        break;
        
    case 'update':
        if (!$crmPerms->HavePerm('COMPANY', 0, 'WRITE')) {
            echo json_encode([
                'success' => false,
                'error' => 'Недостаточно прав для редактирования'
            ]);
            die();
        }
        break;
        
    case 'delete':
        if (!$crmPerms->HavePerm('COMPANY', 0, 'DELETE')) {
            echo json_encode([
                'success' => false,
                'error' => 'Недостаточно прав для удаления'
            ]);
            die();
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Неизвестное действие'
        ]);
        die();
}

// Получение сущности Highload-блока
$hlblock = HL\HighloadBlockTable::getById($hlBlockId)->fetch();

if (!$hlblock) {
    echo json_encode([
        'success' => false,
        'error' => 'Highload-блок не найден'
    ]);
    die();
}

$entity = HL\HighloadBlockTable::compileEntity($hlblock);
$entityDataClass = $entity->getDataClass();

try {
    switch ($action) {
        case 'add':
            // Получение данных полей
            $fields = [];
            foreach ($request->getPostList()->toArray() as $key => $value) {
                if (strpos($key, 'UF_') === 0) {
                    $fields[$key] = $value;
                }
            }
            
            // Обязательно устанавливаем связь с компанией
            $fields['UF_COMPANY_ID'] = 'CO_' . $companyId;
            
            // Дополнительная валидация через класс прав
            if (class_exists('\CrmHlTabPermissions')) {
                $canAdd = \CrmHlTabPermissions::checkFieldAccess(
                    $USER->GetID(),
                    $hlBlockId,
                    array_keys($fields),
                    'WRITE'
                );
                
                if (!$canAdd) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Недостаточно прав для работы с этими полями'
                    ]);
                    die();
                }
            }
            
            $result = $entityDataClass::add($fields);
            
            if ($result->isSuccess()) {
                echo json_encode([
                    'success' => true,
                    'id' => $result->getId()
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => implode(', ', $result->getErrorMessages())
                ]);
            }
            break;
            
        case 'update':
            if (!$itemId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Не указан ID элемента'
                ]);
                die();
            }
            
            // Получение данных полей
            $fields = [];
            foreach ($request->getPostList()->toArray() as $key => $value) {
                if (strpos($key, 'UF_') === 0 && $key !== 'UF_COMPANY_ID') {
                    $fields[$key] = $value;
                }
            }
            
            // Дополнительная валидация через класс прав
            if (class_exists('\CrmHlTabPermissions')) {
                $canEdit = \CrmHlTabPermissions::checkFieldAccess(
                    $USER->GetID(),
                    $hlBlockId,
                    array_keys($fields),
                    'WRITE'
                );
                
                if (!$canEdit) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Недостаточно прав для редактирования этих полей'
                    ]);
                    die();
                }
            }
            
            $result = $entityDataClass::update($itemId, $fields);
            
            if ($result->isSuccess()) {
                echo json_encode([
                    'success' => true
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => implode(', ', $result->getErrorMessages())
                ]);
            }
            break;
            
        case 'delete':
            if (!$itemId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Не указан ID элемента'
                ]);
                die();
            }
            
            $result = $entityDataClass::delete($itemId);
            
            if ($result->isSuccess()) {
                echo json_encode([
                    'success' => true
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => implode(', ', $result->getErrorMessages())
                ]);
            }
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка: ' . $e->getMessage()
    ]);
}