<?php
/**
 * REST API для Highload-блоков с поддержкой токенов
 * /local/ajax/hl_api.php
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Type\Date;

if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/logs/hl_api.log');
}

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);
define('STOP_STATISTICS', true);
define('BX_SECURITY_SESSION_READONLY', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, X-API-Token, Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    die();
}

class ApiTokenAuth
{
    private static $tokens = null;
    
    private static function loadTokens(): array
    {
        if (self::$tokens !== null) {
            return self::$tokens;
        }
        
        $configFile = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/hl_api_tokens.php';
        
        if (file_exists($configFile)) {
            self::$tokens = include $configFile;
        } else {
            self::$tokens = [];
            AddMessage2Log("[AUTH] Config file not found: {$configFile}", 'hl_api');
        }
        
        return self::$tokens;
    }
    
    public static function getTokenFromRequest(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? $_SERVER['HTTP_X_AUTHORIZATION']
            ?? '';
        
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        
        $apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? null;
        if ($apiToken) {
            return trim($apiToken);
        }
        
        $request = Application::getInstance()->getContext()->getRequest();
        $token = $request->get('token') ?? $request->getPost('token');
        if ($token) {
            return trim($token);
        }
        
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (isset($jsonData['token'])) {
                return trim($jsonData['token']);
            }
        }
        
        return null;
    }
    
    public static function validate(string $token): ?array
    {
        $tokens = self::loadTokens();
        
        if (isset($tokens[$token])) {
            AddMessage2Log("[AUTH] Token valid: " . $tokens[$token]['name'], 'hl_api');
            return $tokens[$token];
        }
        
        AddMessage2Log("[AUTH] Token NOT found", 'hl_api');
        return null;
    }
}

class HLApiValidator
{
    private $errors = [];
    private $skipCompanyExistenceCheck = false;
    
    public function getErrors(): array { return $this->errors; }
    public function hasErrors(): bool { return !empty($this->errors); }
    public function addError(string $field, string $message): void { $this->errors[$field] = $message; }
    public function clearErrors(): void { $this->errors = []; }
    
    public function setSkipCompanyExistenceCheck(bool $skip): void
    {
        $this->skipCompanyExistenceCheck = $skip;
    }
    
    public function validateCompanyId($value): bool
    {
        if (empty($value)) {
            $this->addError('UF_COMPANY_ID', 'ID компании обязателен');
            return false;
        }
        
        // Поддерживаем оба формата: "CO_123" и просто "123"
        $companyId = null;
        if (preg_match('/^CO_(\d+)$/', $value, $matches)) {
            $companyId = intval($matches[1]);
        } elseif (is_numeric($value)) {
            $companyId = intval($value);
        } else {
            $this->addError('UF_COMPANY_ID', 'Неверный формат ID компании. Ожидается: число или CO_{id}');
            return false;
        }
        
        if ($companyId <= 0) {
            $this->addError('UF_COMPANY_ID', 'ID компании должен быть положительным числом');
            return false;
        }
        
        if ($this->skipCompanyExistenceCheck) {
            AddMessage2Log("[VALIDATOR] Skipping company existence check for: {$value}", 'hl_api');
            return true;
        }
        
        if (!Loader::includeModule('crm')) {
            $this->addError('UF_COMPANY_ID', 'Модуль CRM не загружен');
            return false;
        }
        
        // Используем GetListEx для надёжной проверки
        $dbResult = \CCrmCompany::GetListEx(
            [],
            ['ID' => $companyId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            ['nTopCount' => 1],
            ['ID', 'TITLE']
        );
        
        $company = $dbResult ? $dbResult->Fetch() : null;
        
        AddMessage2Log("[VALIDATOR] Company check for ID {$companyId}: " . 
            ($company ? "FOUND (TITLE: {$company['TITLE']})" : 'NOT FOUND'), 'hl_api');
        
        if (!$company) {
            $this->addError('UF_COMPANY_ID', "Компания с ID {$companyId} не найдена");
            return false;
        }
        
        return true;
    }
    
    public function validateDateRange($dateStart, $dateEnd): bool
    {
        if (empty($dateStart) || empty($dateEnd)) {
            return true;
        }
        
        $startTimestamp = $this->parseDate($dateStart);
        $endTimestamp = $this->parseDate($dateEnd);
        
        if ($startTimestamp === false) {
            $this->addError('UF_DATE_START', 'Неверный формат даты начала');
            return false;
        }
        
        if ($endTimestamp === false) {
            $this->addError('UF_DATE_END', 'Неверный формат даты окончания');
            return false;
        }
        
        if ($endTimestamp <= $startTimestamp) {
            $this->addError('UF_DATE_END', 'Дата окончания должна быть позже даты начала');
            return false;
        }
        
        return true;
    }
    
    private function parseDate($date)
    {
        if ($date instanceof Date) {
            return $date->getTimestamp();
        }
        
        if (is_string($date)) {
            $formats = ['d.m.Y', 'Y-m-d', 'd/m/Y', 'd.m.Y H:i:s', 'Y-m-d H:i:s'];
            foreach ($formats as $format) {
                $parsed = \DateTime::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed->getTimestamp();
                }
            }
        }
        
        return false;
    }
    
    public function validateNonNegative($field, $value, $fieldName = ''): bool
    {
        $fieldName = $fieldName ?: $field;
        
        if (!is_numeric($value)) {
            $this->addError($field, "{$fieldName} должно быть числом");
            return false;
        }
        
        if (intval($value) < 0) {
            $this->addError($field, "{$fieldName} не может быть отрицательным");
            return false;
        }
        
        return true;
    }
    
    public function validatePdfFile($fileId): bool
    {
        if (empty($fileId)) {
            return true;
        }
        
        $fileArray = \CFile::GetFileArray($fileId);
        
        if (!$fileArray) {
            $this->addError('UF_CONTRACT_FILE', 'Файл не найден');
            return false;
        }
        
        $extension = strtolower(pathinfo($fileArray['ORIGINAL_NAME'] ?: $fileArray['FILE_NAME'], PATHINFO_EXTENSION));
        
        if ($extension !== 'pdf') {
            $this->addError('UF_CONTRACT_FILE', 'Допустимый формат файла: PDF');
            return false;
        }
        
        return true;
    }
    
    public function validateRequired($field, $value, $fieldName = ''): bool
    {
        $fieldName = $fieldName ?: $field;
        
        if (empty($value) && $value !== 0 && $value !== '0') {
            $this->addError($field, "Поле '{$fieldName}' обязательно для заполнения");
            return false;
        }
        
        return true;
    }
    
    public function validateStringLength($field, $value, $maxLength, $fieldName = ''): bool
    {
        $fieldName = $fieldName ?: $field;
        
        if (mb_strlen($value) > $maxLength) {
            $this->addError($field, "Поле '{$fieldName}' не должно превышать {$maxLength} символов");
            return false;
        }
        
        return true;
    }
}

class HLApiHandler
{
    private $validator;
    private $userId;
    private $tokenData = null;
    private $authMethod = 'session';
    private $allowedHlBlocks = [5, 6];
    
    private $hlConfig = [
        5 => [
            'name' => 'Торговые точки',
            'fields' => [
                'UF_ADDRESS' => ['required' => true, 'type' => 'string', 'maxLength' => 255],
                'UF_COMPANY_ID' => ['required' => true, 'type' => 'crm_company'],
            ],
            'permissions' => [
                'read' => true,
                'add' => true,
                'update' => true,
                'delete' => true,
            ]
        ],
        6 => [
            'name' => 'Договоры',
            'fields' => [
                'UF_ACTIVE' => ['required' => false, 'type' => 'boolean'],
                'UF_NAME' => ['required' => true, 'type' => 'string', 'maxLength' => 255],
                'UF_COMPANY_ID' => ['required' => true, 'type' => 'crm_company'],
                'UF_CONTRACT_FILE' => ['required' => false, 'type' => 'file_pdf'],
                'UF_CREDIT_LIMIT' => ['required' => true, 'type' => 'integer', 'min' => 0],
                'UF_PAYMENT_DELAY' => ['required' => true, 'type' => 'integer', 'min' => 0],
                'UF_DATE_START' => ['required' => false, 'type' => 'date'],
                'UF_DATE_END' => ['required' => false, 'type' => 'date'],
            ],
            'permissions' => [
                'read' => true,
                'add' => true,
                'update' => true,
                'delete' => false,
            ]
        ],
    ];
    
    public function __construct()
    {
        $this->validator = new HLApiValidator();
    }
    
    public function handleRequest(): void
    {
        try {
            AddMessage2Log("=== API Request ===", 'hl_api');
            
            if (!$this->checkAuth()) {
                $this->sendError('Необходима авторизация', 401);
            }
            
            if (!Loader::includeModule('crm') || !Loader::includeModule('highloadblock')) {
                $this->sendError('Необходимые модули не установлены', 500);
            }
            
            $request = Application::getInstance()->getContext()->getRequest();
            
            $action = $this->getParam($request, 'action', 'get');
            $hlBlockId = intval($this->getParam($request, 'hlBlockId', 0));
            
            // Опция для пропуска проверки существования компании
            $skipCompanyCheck = $this->getParam($request, 'skipCompanyValidation', false);
            if ($skipCompanyCheck) {
                $this->validator->setSkipCompanyExistenceCheck(true);
            }
            
            AddMessage2Log("Action: {$action}, hlBlockId: {$hlBlockId}", 'hl_api');
            
            if (!$this->isHlBlockAllowed($hlBlockId)) {
                $this->sendError('Указан недопустимый Highload-блок', 400);
            }
            
            if (!$this->checkPermissions($action, $hlBlockId)) {
                $this->sendError('Недостаточно прав для выполнения операции', 403);
            }
            
            switch ($action) {
                case 'get':
                    $this->actionGet($request, $hlBlockId);
                    break;
                case 'add':
                    $this->actionAdd($request, $hlBlockId);
                    break;
                case 'update':
                    $this->actionUpdate($request, $hlBlockId);
                    break;
                case 'delete':
                    $this->actionDelete($request, $hlBlockId);
                    break;
                default:
                    $this->sendError('Неизвестное действие: ' . $action, 400);
            }
            
        } catch (\Exception $e) {
            AddMessage2Log('Exception: ' . $e->getMessage(), 'hl_api');
            $this->sendError('Внутренняя ошибка сервера', 500);
        }
    }
    
    private function checkAuth(): bool
    {
        $token = ApiTokenAuth::getTokenFromRequest();
        
        if ($token) {
            $tokenData = ApiTokenAuth::validate($token);
            
            if ($tokenData) {
                $this->tokenData = $tokenData;
                $this->userId = $tokenData['user_id'];
                $this->authMethod = 'token';
                
                if (!empty($tokenData['skip_company_validation'])) {
                    $this->validator->setSkipCompanyExistenceCheck(true);
                }
                
                return true;
            }
            
            return false;
        }
        
        global $USER;
        
        if ($USER->IsAuthorized()) {
            $this->userId = $USER->GetID();
            $this->authMethod = 'session';
            return true;
        }
        
        return false;
    }
    
    private function isHlBlockAllowed($hlBlockId): bool
    {
        if ($this->authMethod === 'token' && $this->tokenData) {
            $allowed = $this->tokenData['allowed_hl_blocks'] ?? [];
            return in_array($hlBlockId, $allowed);
        }
        
        return in_array($hlBlockId, $this->allowedHlBlocks);
    }
    
    private function checkPermissions($action, $hlBlockId): bool
    {
        $actionMap = [
            'get' => 'read',
            'add' => 'add',
            'update' => 'update',
            'delete' => 'delete',
        ];
        
        $permAction = $actionMap[$action] ?? 'read';
        
        if ($this->authMethod === 'token' && $this->tokenData) {
            $permissions = $this->tokenData['permissions'] ?? [];
            if (!in_array($permAction, $permissions)) {
                return false;
            }
        }
        
        if (!($this->hlConfig[$hlBlockId]['permissions'][$permAction] ?? false)) {
            return false;
        }
        
        if ($this->authMethod === 'session') {
            return $this->checkCrmPermissions($action);
        }
        
        return true;
    }
    
    private function checkCrmPermissions($action): bool
    {
        global $USER;
        
        if ($USER->IsAdmin()) {
            return true;
        }
        
        $crmPerms = new \CCrmPerms($this->userId);
        
        $crmAction = 'READ';
        if (in_array($action, ['add', 'update'])) {
            $crmAction = 'WRITE';
        } elseif ($action === 'delete') {
            $crmAction = 'DELETE';
        }
        
        $permType = $crmPerms->GetPermType('COMPANY', $crmAction);
        return $permType !== BX_CRM_PERM_NONE;
    }
    
    private function getParam($request, $key, $default = null)
    {
        $value = $request->get($key) ?? $request->getPost($key);
        
        if ($value === null) {
            static $jsonData = null;
            if ($jsonData === null) {
                $rawInput = file_get_contents('php://input');
                $jsonData = !empty($rawInput) ? (json_decode($rawInput, true) ?: []) : [];
            }
            $value = $jsonData[$key] ?? null;
        }
        
        return $value ?? $default;
    }
    
    private function getEntityClass($hlBlockId)
    {
        $hlblock = HL\HighloadBlockTable::getById($hlBlockId)->fetch();
        
        if (!$hlblock) {
            $this->sendError('Highload-блок не найден', 404);
        }
        
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        return $entity->getDataClass();
    }
    
    private function validateFields($hlBlockId, $fields, $isUpdate = false): bool
    {
        $this->validator->clearErrors();
        $config = $this->hlConfig[$hlBlockId]['fields'] ?? [];
        
        foreach ($config as $fieldCode => $fieldConfig) {
            $value = $fields[$fieldCode] ?? null;
            
            if (!$isUpdate && ($fieldConfig['required'] ?? false)) {
                if (!$this->validator->validateRequired($fieldCode, $value, $fieldCode)) {
                    continue;
                }
            }
            
            if (empty($value) && $value !== 0 && $value !== '0') {
                continue;
            }
            
            switch ($fieldConfig['type'] ?? 'string') {
                case 'crm_company':
                    $this->validator->validateCompanyId($value);
                    break;
                case 'string':
                    if (isset($fieldConfig['maxLength'])) {
                        $this->validator->validateStringLength($fieldCode, $value, $fieldConfig['maxLength']);
                    }
                    break;
                case 'integer':
                    if (isset($fieldConfig['min']) && $fieldConfig['min'] >= 0) {
                        $this->validator->validateNonNegative($fieldCode, $value, $fieldCode);
                    }
                    break;
                case 'file_pdf':
                    $this->validator->validatePdfFile($value);
                    break;
            }
        }
        
        if ($hlBlockId === 6) {
            $dateStart = $fields['UF_DATE_START'] ?? null;
            $dateEnd = $fields['UF_DATE_END'] ?? null;
            $this->validator->validateDateRange($dateStart, $dateEnd);
        }
        
        return !$this->validator->hasErrors();
    }
    
    private function prepareFields($hlBlockId, $fields): array
    {
        $config = $this->hlConfig[$hlBlockId]['fields'] ?? [];
        $prepared = [];
        
        foreach ($config as $fieldCode => $fieldConfig) {
            if (!array_key_exists($fieldCode, $fields)) {
                continue;
            }
            
            $value = $fields[$fieldCode];
            
            switch ($fieldConfig['type'] ?? 'string') {
                case 'boolean':
                    $prepared[$fieldCode] = $value ? 1 : 0;
                    break;
                case 'integer':
                    $prepared[$fieldCode] = intval($value);
                    break;
                case 'date':
                    if (!empty($value)) {
                        $prepared[$fieldCode] = $this->prepareDate($value);
                    }
                    break;
                case 'string':
                    $prepared[$fieldCode] = trim($value);
                    break;
                case 'crm_company':
                    // Сохраняем только числовой ID без префикса CO_
                    if (preg_match('/^CO_(\d+)$/', $value, $matches)) {
                        $prepared[$fieldCode] = $matches[1];
                    } else {
                        $prepared[$fieldCode] = intval($value);
                    }
                    break;
                default:
                    $prepared[$fieldCode] = $value;
            }
        }
        
        return $prepared;
    }
    
    private function prepareDate($value)
    {
        if ($value instanceof Date) {
            return $value;
        }
        
        $formats = ['d.m.Y', 'Y-m-d', 'd/m/Y'];
        
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed !== false) {
                return Date::createFromPhp($parsed);
            }
        }
        
        return null;
    }
    
    private function actionGet($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        
        $companyId = $this->getParam($request, 'companyId');
        $itemId = intval($this->getParam($request, 'itemId', 0));
        $limit = min(intval($this->getParam($request, 'limit', 50)), 100);
        $offset = max(intval($this->getParam($request, 'offset', 0)), 0);
        
        $filter = [];
        
        if (!empty($companyId)) {
            // Поддерживаем оба формата, но в базе хранится только число
            if (preg_match('/^CO_(\d+)$/', $companyId, $matches)) {
                $filter['UF_COMPANY_ID'] = $matches[1];
            } else {
                $filter['UF_COMPANY_ID'] = intval($companyId);
            }
        }
        
        if ($itemId > 0) {
            $filter['ID'] = $itemId;
        }
        
        $result = $entityClass::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
        ]);
        
        $items = [];
        while ($row = $result->fetch()) {
            $items[] = $this->prepareItemForOutput($hlBlockId, $row);
        }
        
        $countResult = $entityClass::getList([
            'select' => ['CNT'],
            'filter' => $filter,
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ])->fetch();
        
        $this->sendSuccess([
            'items' => $items,
            'total' => intval($countResult['CNT'] ?? count($items)),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    private function prepareItemForOutput($hlBlockId, $item): array
    {
        $output = ['ID' => $item['ID']];
        
        foreach ($item as $key => $value) {
            if ($key === 'ID') continue;
            
            if ($value instanceof Date) {
                $output[$key] = $value->format('d.m.Y');
            } elseif ($key === 'UF_CONTRACT_FILE' && !empty($value)) {
                $fileArray = \CFile::GetFileArray($value);
                if ($fileArray) {
                    $output[$key] = [
                        'ID' => $value,
                        'NAME' => $fileArray['ORIGINAL_NAME'] ?: $fileArray['FILE_NAME'],
                        'SIZE' => \CFile::FormatSize($fileArray['FILE_SIZE']),
                        'SRC' => $fileArray['SRC'],
                    ];
                }
            } else {
                $output[$key] = $value;
            }
        }
        
        return $output;
    }
    
    private function actionAdd($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $fields = $this->collectFields($request, $hlBlockId);
        
        AddMessage2Log("[ADD] Fields: " . json_encode($fields, JSON_UNESCAPED_UNICODE), 'hl_api');
        
        if (!$this->validateFields($hlBlockId, $fields, false)) {
            $this->sendError('Ошибка валидации', 400, $this->validator->getErrors());
        }
        
        $preparedFields = $this->prepareFields($hlBlockId, $fields);
        $result = $entityClass::add($preparedFields);
        
        if ($result->isSuccess()) {
            $this->logAction('add', $hlBlockId, $result->getId(), $preparedFields);
            $this->sendSuccess(['id' => $result->getId(), 'message' => 'Элемент успешно добавлен']);
        } else {
            $this->sendError('Ошибка при добавлении', 400, ['errors' => $result->getErrorMessages()]);
        }
    }
    
    private function actionUpdate($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $itemId = intval($this->getParam($request, 'itemId', 0));
        
        if (!$itemId) {
            $this->sendError('Не указан ID элемента', 400);
        }
        
        $existing = $entityClass::getById($itemId)->fetch();
        if (!$existing) {
            $this->sendError('Элемент не найден', 404);
        }
        
        $fields = $this->collectFields($request, $hlBlockId);
        
        if (empty($fields)) {
            $this->sendError('Нет данных для обновления', 400);
        }
        
        if (!$this->validateFields($hlBlockId, $fields, true)) {
            $this->sendError('Ошибка валидации', 400, $this->validator->getErrors());
        }
        
        if ($hlBlockId === 6) {
            $dateStart = $fields['UF_DATE_START'] ?? $existing['UF_DATE_START'];
            $dateEnd = $fields['UF_DATE_END'] ?? $existing['UF_DATE_END'];
            
            $this->validator->clearErrors();
            if (!$this->validator->validateDateRange($dateStart, $dateEnd)) {
                $this->sendError('Ошибка валидации', 400, $this->validator->getErrors());
            }
        }
        
        $preparedFields = $this->prepareFields($hlBlockId, $fields);
        $result = $entityClass::update($itemId, $preparedFields);
        
        if ($result->isSuccess()) {
            $this->logAction('update', $hlBlockId, $itemId, $preparedFields);
            $this->sendSuccess(['id' => $itemId, 'message' => 'Данные успешно обновлены']);
        } else {
            $this->sendError('Ошибка при обновлении', 400, ['errors' => $result->getErrorMessages()]);
        }
    }
    
    private function actionDelete($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $itemId = intval($this->getParam($request, 'itemId', 0));
        
        if (!$itemId) {
            $this->sendError('Не указан ID элемента', 400);
        }
        
        $existing = $entityClass::getById($itemId)->fetch();
        if (!$existing) {
            $this->sendError('Элемент не найден', 404);
        }
        
        $result = $entityClass::delete($itemId);
        
        if ($result->isSuccess()) {
            $this->logAction('delete', $hlBlockId, $itemId, []);
            $this->sendSuccess(['message' => 'Элемент успешно удален']);
        } else {
            $this->sendError('Ошибка при удалении', 400, ['errors' => $result->getErrorMessages()]);
        }
    }
    
    private function collectFields($request, $hlBlockId): array
    {
        $fields = [];
        $config = $this->hlConfig[$hlBlockId]['fields'] ?? [];
        
        foreach ($config as $fieldCode => $fieldConfig) {
            $value = $this->getParam($request, $fieldCode);
            if ($value !== null) {
                $fields[$fieldCode] = $value;
            }
        }
        
        return $fields;
    }
    
    private function logAction($action, $hlBlockId, $itemId, $fields): void
    {
        $authInfo = $this->authMethod === 'token' 
            ? "token:{$this->tokenData['name']}" 
            : "session";
        
        $message = sprintf(
            "[%s] User %d (%s): %s on HL_%d, item %d | %s",
            date('Y-m-d H:i:s'),
            $this->userId,
            $authInfo,
            strtoupper($action),
            $hlBlockId,
            $itemId,
            json_encode($fields, JSON_UNESCAPED_UNICODE)
        );
        
        AddMessage2Log($message, 'hl_api');
    }
    
    private function sendSuccess($data): void
    {
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    private function sendError($message, $code = 400, $details = []): void
    {
        http_response_code($code);
        $response = ['success' => false, 'error' => $message];
        if (!empty($details)) {
            $response['details'] = $details;
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        die();
    }
}

$handler = new HLApiHandler();
$handler->handleRequest();