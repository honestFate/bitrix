<?php
/**
 * Универсальный AJAX загрузчик для вкладок CRM (с обработкой ошибок)
 * /local/components/custom/crm.company.tab.base/ajax.php
 */

// Включаем отображение ошибок для отладки
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
error_reporting(E_ALL);
ini_set('display_errors', 0); // Отключаем для production
ini_set('log_errors', 1);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
define('NO_AGENT_CHECK', true);

// Функция логирования ошибок
function logError($message, $context = []) {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/crm_tabs_error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $log = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $message;
    if (!empty($context)) {
        $log .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    @file_put_contents($logFile, $log, FILE_APPEND);
}

// Функция для возврата ошибки клиенту
function returnError($message, $code = 500, $details = []) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    $error = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($details)) {
        $error['details'] = $details;
    }
    
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    
    logError($message, $details);
    die();
}

// Обработчик фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("Fatal error: " . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        returnError(
            'Критическая ошибка сервера. Проверьте логи.',
            500,
            [
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        );
    }
});

// Обработчик исключений
set_exception_handler(function($exception) {
    logError("Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    returnError(
        'Ошибка выполнения: ' . $exception->getMessage(),
        500,
        [
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ]
    );
});

try {
    // Подключение Битрикс
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php')) {
        returnError('Битрикс не найден', 500);
    }
    
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');


    
    global $APPLICATION, $USER;
    
    // Логирование (опционально)
    $logEnabled = true;
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/crm_tabs_ajax.log';
    
    function logAjax($message, $data = null) {
        global $logEnabled, $logFile;
        if (!$logEnabled) return;
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $log = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if ($data !== null) {
            $log .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $log .= "\n";
        
        @file_put_contents($logFile, $log, FILE_APPEND);
    }
    
    logAjax("=== AJAX Request Started ===");
    logAjax("Request URI", $_SERVER['REQUEST_URI']);
    logAjax("Request Method", $_SERVER['REQUEST_METHOD']);
    
    // Проверка авторизации
    if (!$USER->IsAuthorized()) {
        returnError('Необходима авторизация', 403);
    }
    
    logAjax("User ID", $USER->GetID());
    
    // Подключение модулей
    if (!Loader::includeModule('crm')) {
        returnError('Модуль CRM не установлен', 500);
    }
    
    if (!Loader::includeModule('highloadblock')) {
        returnError('Модуль Highloadblock не установлен', 500);
    }
    
    logAjax("Modules loaded");
    
    // Получение параметров
    $request = Application::getInstance()->getContext()->getRequest();
    
    // Определяем компонент из разных источников
    $componentName = null;
    $companyId = 0;
    $action = null;
    
    // Попытка 1: из GET/POST параметров
    $componentName = $request->get('component') ?: $request->post('component');
    $companyId = intval($request->get('companyId') ?: $request->post('companyId'));
    $action = $request->get('action') ?: $request->post('action');
    
    // Попытка 2: из $_REQUEST (для старых версий)
    if (!$componentName && isset($_REQUEST['component'])) {
        $componentName = $_REQUEST['component'];
    }
    
    // Попытка 3: из PARAMS (если передается через loader)
    if (!$componentName && isset($_REQUEST['PARAMS']['component'])) {
        $componentName = $_REQUEST['PARAMS']['component'];
    }
    
    if (!$companyId && isset($_REQUEST['PARAMS']['params']['COMPANY_ID'])) {
        $companyId = intval($_REQUEST['PARAMS']['params']['COMPANY_ID']);
    }
    
    // Попытка 4: из URL (парсим путь)
    if (!$componentName) {
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#/crm\.company\.tab\.([^/]+)/ajax\.php#', $uri, $matches)) {
            $componentName = 'crm.company.tab.' . $matches[1];
        }
    }
    
    logAjax("Parsed params", [
        'component' => $componentName,
        'companyId' => $companyId,
        'action' => $action
    ]);
    
    // Валидация
    if (!$componentName) {
        returnError('Не указан компонент', 400, [
            'hint' => 'Передайте параметр component',
            'request_uri' => $_SERVER['REQUEST_URI']
        ]);
    }
    
    // Проверка прав CRM
    $crmPerms = new CCrmPerms($USER->GetID());
    if (!$crmPerms->HavePerm('COMPANY', 0, 'READ')) {
        returnError('Недостаточно прав для доступа к CRM', 403);
    }
    
    logAjax("CRM permissions OK");
    
    // Определяем путь к компоненту
    $componentPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/' . $componentName;
    $componentClass = $componentPath . '/class.php';
    
    if (!file_exists($componentClass)) {
        returnError('Компонент не найден: ' . $componentName, 404, [
            'path' => $componentPath,
            'class_file' => $componentClass
        ]);
    }
    
    logAjax("Component found", $componentPath);
    
    // Подключение и выполнение компонента
    ob_start();
    
    $APPLICATION->IncludeComponent(
        'custom:' . $componentName,
        '.default',
        [
            'COMPANY_ID' => $companyId,
            'TAB_CODE' => $request->get('tabCode') ?: $request->post('tabCode'),
        ],
        false,
        ['HIDE_ICONS' => 'Y']
    );
    
    $content = ob_get_clean();
    
    logAjax("Component executed", ['content_length' => strlen($content)]);
    
    // Проверяем, не вернул ли компонент JSON
    $json = @json_decode($content, true);
    if ($json !== null) {
        // Компонент вернул JSON
        header('Content-Type: application/json; charset=utf-8');
        echo $content;
    } else {
        // Компонент вернул HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
    }
    
    logAjax("Response sent successfully");
    
} catch (Exception $e) {
    logError("Exception in AJAX handler", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    returnError(
        'Ошибка выполнения: ' . $e->getMessage(),
        500,
        [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    );
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');