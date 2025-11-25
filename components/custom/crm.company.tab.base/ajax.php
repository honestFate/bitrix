<?php
/**
 * Универсальный AJAX загрузчик для вкладок CRM
 * /local/components/custom/crm.company.tab.base/ajax.php
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('NO_AGENT_CHECK', true);

// Подключение Битрикс
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Битрикс не найден'], JSON_UNESCAPED_UNICODE);
    die();
}

require_once $prologPath;

/**
 * Функция для возврата ошибки клиенту
 */
function returnAjaxError($message, $code = 500, $details = []) {
    AddMessage2Log("CRM Tab AJAX Error: {$message} | Details: " . json_encode($details, JSON_UNESCAPED_UNICODE), 'crm_tabs');
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    $error = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($details) && defined('CRM_TAB_DEBUG') && CRM_TAB_DEBUG) {
        $error['details'] = $details;
    }
    
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    die();
}

// Обработчик фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        AddMessage2Log("CRM Tab Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}", 'crm_tabs');
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Критическая ошибка сервера',
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Обработчик исключений
set_exception_handler(function($exception) {
    AddMessage2Log("CRM Tab Exception: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'crm_tabs');
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка выполнения: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    die();
});

try {
    global $APPLICATION, $USER;
    
    AddMessage2Log("=== CRM Tab AJAX Request ===", 'crm_tabs');
    AddMessage2Log("URI: {$_SERVER['REQUEST_URI']}", 'crm_tabs');
    AddMessage2Log("Method: {$_SERVER['REQUEST_METHOD']}", 'crm_tabs');
    
    if (!$USER->IsAuthorized()) {
        returnAjaxError('Необходима авторизация', 403);
    }
    
    AddMessage2Log("User ID: {$USER->GetID()}", 'crm_tabs');
    
    if (!Loader::includeModule('crm')) {
        returnAjaxError('Модуль CRM не установлен', 500);
    }
    
    if (!Loader::includeModule('highloadblock')) {
        returnAjaxError('Модуль Highloadblock не установлен', 500);
    }
    
    $request = Application::getInstance()->getContext()->getRequest();
    
    $componentName = null;
    $companyId = 0;
    $tabCode = '';
    
    // 1. Прямые GET/POST параметры
    $componentName = $request->get('component') ?: $request->getPost('component');
    $companyId = intval($request->get('companyId') ?: $request->getPost('companyId'));
    $tabCode = $request->get('tabCode') ?: $request->getPost('tabCode');
    
    // 2. Параметры из Bitrix loader (формат PARAMS)
    $loaderParams = $request->get('PARAMS') ?: $request->getPost('PARAMS');
    if (is_array($loaderParams)) {
        if (!$componentName && !empty($loaderParams['signedParameters'])) {
            // Расшифровка подписанных параметров Bitrix
            $signedParams = \Bitrix\Main\Component\ParameterSigner::unsignParameters(
                $loaderParams['signedParameters'],
                $loaderParams['component'] ?? ''
            );
            if (!empty($signedParams['COMPANY_ID'])) {
                $companyId = intval($signedParams['COMPANY_ID']);
            }
            if (!empty($signedParams['TAB_CODE'])) {
                $tabCode = $signedParams['TAB_CODE'];
            }
        }
        
        // Простые параметры из loader
        if (!empty($loaderParams['params']['COMPANY_ID'])) {
            $companyId = intval($loaderParams['params']['COMPANY_ID']);
        }
        if (!empty($loaderParams['params']['TAB_CODE'])) {
            $tabCode = $loaderParams['params']['TAB_CODE'];
        }
    }
    
    // 3. JSON body (для fetch запросов)
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            if (!$componentName && !empty($jsonData['component'])) {
                $componentName = $jsonData['component'];
            }
            if (!$companyId && !empty($jsonData['companyId'])) {
                $companyId = intval($jsonData['companyId']);
            }
            if (!$companyId && !empty($jsonData['PARAMS']['params']['COMPANY_ID'])) {
                $companyId = intval($jsonData['PARAMS']['params']['COMPANY_ID']);
            }
            if (!$tabCode && !empty($jsonData['tabCode'])) {
                $tabCode = $jsonData['tabCode'];
            }
        }
    }
    
    // 4. Определение компонента из URL
    if (!$componentName) {
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#/crm\.company\.tab\.([^/]+)/ajax\.php#', $uri, $matches)) {
            $componentName = 'crm.company.tab.' . $matches[1];
        }
    }
    
    // 5. Fallback на $_REQUEST
    if (!$componentName && isset($_REQUEST['component'])) {
        $componentName = $_REQUEST['component'];
    }
    if (!$companyId && isset($_REQUEST['COMPANY_ID'])) {
        $companyId = intval($_REQUEST['COMPANY_ID']);
    }
    
    AddMessage2Log("Parsed: component={$componentName}, companyId={$companyId}, tabCode={$tabCode}", 'crm_tabs');
    
    // Валидация компонента
    if (!$componentName) {
        returnAjaxError('Не указан компонент', 400, [
            'hint' => 'Передайте параметр component'
        ]);
    }
    
    // Валидация companyId
    if (!$companyId) {
        returnAjaxError('Не указан ID компании', 400, [
            'hint' => 'Передайте параметр companyId или COMPANY_ID'
        ]);
    }
    
    // Проверка прав CRM
    $crmPerms = new CCrmPerms($USER->GetID());
    if (!$crmPerms->HavePerm('COMPANY', BX_CRM_PERM_NONE, 'READ')) {
        returnAjaxError('Недостаточно прав для доступа к CRM', 403);
    }
    
    // Определяем путь к компоненту
    $componentPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/' . $componentName;
    $componentClass = $componentPath . '/class.php';
    
    if (!file_exists($componentClass)) {
        returnAjaxError('Компонент не найден: ' . $componentName, 404);
    }
    
    AddMessage2Log("Including component: {$componentName}", 'crm_tabs');
    
    // Выполнение компонента
    ob_start();
    
    $APPLICATION->IncludeComponent(
        'custom:' . $componentName,
        '.default',
        [
            'COMPANY_ID' => $companyId,
            'TAB_CODE' => $tabCode ?: str_replace('crm.company.', '', $componentName),
        ],
        false,
        ['HIDE_ICONS' => 'Y']
    );
    
    $content = ob_get_clean();
    
    AddMessage2Log("Component output length: " . strlen($content), 'crm_tabs');
    
    // Проверяем тип ответа
    $json = @json_decode($content, true);
    if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
        header('Content-Type: application/json; charset=utf-8');
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }
    
    echo $content;
    
} catch (\Exception $e) {
    AddMessage2Log("CRM Tab Exception: {$e->getMessage()}", 'crm_tabs');
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    returnAjaxError('Ошибка: ' . $e->getMessage(), 500);
}