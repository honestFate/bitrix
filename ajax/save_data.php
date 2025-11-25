<?php
/**
 * AJAX обработчик для сохранения данных в Highload-блоки
 * /local/ajax/crm_hl_tabs/save_data.php
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Highloadblock as HL;

// Определяем лог-файл
if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/logs/crm_tabs.log');
}

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('NO_AGENT_CHECK', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Возврат JSON ответа
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die();
}

/**
 * Возврат ошибки
 */
function jsonError($message, $code = 400) {
    AddMessage2Log("CRM HL Save Error: {$message}", 'crm_tabs');
    jsonResponse(['success' => false, 'error' => $message], $code);
}

try {
    global $USER;
    
    // Проверка авторизации
    if (!$USER->IsAuthorized()) {
        jsonError('Необходима авторизация', 403);
    }
    
    // Подключение модулей
    if (!Loader::includeModule('crm')) {
        jsonError('Модуль CRM не установлен', 500);
    }
    
    if (!Loader::includeModule('highloadblock')) {
        jsonError('Модуль Highloadblock не установлен', 500);
    }
    
    $request = Application::getInstance()->getContext()->getRequest();
    
    // Получение параметров
    $action = $request->getPost('action') ?: $request->get('action');
    $hlBlockId = intval($request->getPost('hlBlockId') ?: $request->get('hlBlockId'));
    $companyId = intval($request->getPost('companyId') ?: $request->get('companyId'));
    $itemId = intval($request->getPost('itemId') ?: $request->get('itemId'));
    
    AddMessage2Log("Save request: action={$action}, hlBlockId={$hlBlockId}, companyId={$companyId}, itemId={$itemId}", 'crm_tabs');
    
    if (!$hlBlockId) {
        jsonError('Не указан ID Highload-блока');
    }
    
    // Проверка прав CRM
    $userId = $USER->GetID();
    $crmPerms = new CCrmPerms($userId);
    
    $requiredAction = ($action === 'delete') ? 'DELETE' : 'WRITE';
    $permType = $crmPerms->GetPermType('COMPANY', $requiredAction);
    
    if ($permType === BX_CRM_PERM_NONE && !$USER->IsAdmin()) {
        jsonError('Недостаточно прав для выполнения операции', 403);
    }
    
    // Получение сущности HL-блока
    $hlblock = HL\HighloadBlockTable::getById($hlBlockId)->fetch();
    if (!$hlblock) {
        jsonError('Highload-блок не найден');
    }
    
    $entity = HL\HighloadBlockTable::compileEntity($hlblock);
    $entityDataClass = $entity->getDataClass();
    
    switch ($action) {
        case 'add':
            if (!$companyId) {
                jsonError('Не указан ID компании');
            }
            
            // Собираем данные полей
            $fields = ['UF_COMPANY_ID' => $companyId];
            foreach ($request->getPostList() as $key => $value) {
                if (strpos($key, 'UF_') === 0 && $key !== 'UF_COMPANY_ID') {
                    $fields[$key] = trim($value);
                }
            }
            
            $result = $entityDataClass::add($fields);
            
            if ($result->isSuccess()) {
                AddMessage2Log("Item added: ID={$result->getId()}", 'crm_tabs');
                jsonResponse([
                    'success' => true,
                    'id' => $result->getId(),
                    'message' => 'Элемент успешно добавлен'
                ]);
            } else {
                jsonError('Ошибка при добавлении: ' . implode(', ', $result->getErrorMessages()));
            }
            break;
            
        case 'update':
            if (!$itemId) {
                jsonError('Не указан ID элемента');
            }
            
            // Собираем данные полей
            $fields = [];
            foreach ($request->getPostList() as $key => $value) {
                if (strpos($key, 'UF_') === 0 && $key !== 'UF_COMPANY_ID') {
                    $fields[$key] = trim($value);
                }
            }
            
            if (empty($fields)) {
                jsonError('Нет данных для обновления');
            }
            
            $result = $entityDataClass::update($itemId, $fields);
            
            if ($result->isSuccess()) {
                AddMessage2Log("Item updated: ID={$itemId}", 'crm_tabs');
                jsonResponse([
                    'success' => true,
                    'id' => $itemId,
                    'message' => 'Данные успешно сохранены'
                ]);
            } else {
                jsonError('Ошибка при сохранении: ' . implode(', ', $result->getErrorMessages()));
            }
            break;
            
        case 'delete':
            if (!$itemId) {
                jsonError('Не указан ID элемента');
            }
            
            $result = $entityDataClass::delete($itemId);
            
            if ($result->isSuccess()) {
                AddMessage2Log("Item deleted: ID={$itemId}", 'crm_tabs');
                jsonResponse([
                    'success' => true,
                    'message' => 'Элемент успешно удален'
                ]);
            } else {
                jsonError('Ошибка при удалении: ' . implode(', ', $result->getErrorMessages()));
            }
            break;
            
        default:
            jsonError('Неизвестное действие: ' . $action);
    }
    
} catch (\Exception $e) {
    AddMessage2Log("Save exception: " . $e->getMessage(), 'crm_tabs');
    jsonError('Ошибка: ' . $e->getMessage(), 500);
}
