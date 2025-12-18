<?php
/**
 * REST API для Highload-блоков с поддержкой токенов и batch-операций
 * /local/ajax/hl_api.php
 * 
 * Расширенная версия с поддержкой:
 * - Множественной загрузки (batch_add, batch_update)
 * - Нового HL-блока "Расчёт портфеля" (ID 7)
 * 
 * @version 2.0
 */

// Composer autoload для brick/money
$composerAutoload = $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Brick\Money\Money;
use Brick\Money\Currency;
use Brick\Math\RoundingMode;

if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/logs/hl_api.log');
}

// Создаём директорию для логов
$logDir = dirname(LOG_FILENAME);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
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
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

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
    
    public function validateMoney($field, $value, $fieldName = ''): bool
    {
        $fieldName = $fieldName ?: $field;
        
        if (empty($value) && $value !== '0' && $value !== 0) {
            return true;
        }
        
        $normalizedValue = str_replace(',', '.', trim($value));
        
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $normalizedValue)) {
            $this->addError($field, "{$fieldName}: неверный формат суммы. Ожидается число, например: 100.50 или -500,00");
            return false;
        }
        
        return true;
    }
    
    /**
     * Валидация денежного значения для хранения в копейках
     * Принимает: "15000.50", "15000,50", "15 000.50", 1500050 (уже копейки)
     */
    public function validateMoneyMinor($field, $value, $fieldName = ''): bool
    {
        $fieldName = $fieldName ?: $field;
        
        if (empty($value) && $value !== '0' && $value !== 0) {
            return true;
        }
        
        // Если уже integer - это копейки
        if (is_int($value)) {
            return true;
        }
        
        // Очистка от пробелов и замена запятой на точку
        $normalizedValue = str_replace([' ', ','], ['', '.'], trim($value));
        
        // Проверяем формат: целое число (копейки) или десятичное (рубли)
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $normalizedValue)) {
            $this->addError($field, "{$fieldName}: неверный формат суммы. Ожидается: 15000.50 (рубли) или 1500050 (копейки)");
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
    private $allowedHlBlocks = [5, 6, 7]; // Добавлен блок 7 - Расчёт портфеля
    
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
                'batch_add' => true,
                'batch_update' => true,
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
                'UF_DEBT' => ['required' => false, 'type' => 'money', 'currency' => 'RUB'],
            ],
            'permissions' => [
                'read' => true,
                'add' => true,
                'update' => true,
                'delete' => false,
                'batch_add' => true,
                'batch_update' => true,
            ]
        ],
        7 => [
            'name' => 'Расчёт портфеля',
            'fields' => [
                'UF_PERIOD' => ['required' => true, 'type' => 'datetime'],
                'UF_CONTRACTOR' => ['required' => true, 'type' => 'string'],
                'UF_CONTRACTOR_ID' => ['required' => false, 'type' => 'crm_company'],
                'UF_CONTRACT' => ['required' => false, 'type' => 'string'],
                'UF_CONTRACT_ID' => ['required' => false, 'type' => 'hlblock'],
                'UF_DOCUMENT' => ['required' => true, 'type' => 'string'],
                'UF_ORGANIZATION' => ['required' => false, 'type' => 'string'],
                'UF_ORGANIZATION_ID' => ['required' => false, 'type' => 'crm_company'],
                'UF_DEPARTMENT' => ['required' => false, 'type' => 'string'],
                'UF_DEPARTMENT_ID' => ['required' => false, 'type' => 'iblock_section'],
                'UF_MANAGER' => ['required' => false, 'type' => 'string'],
                'UF_MANAGER_ID' => ['required' => false, 'type' => 'employee'],
                'UF_SUPERVISOR' => ['required' => false, 'type' => 'string'],
                'UF_SUPERVISOR_ID' => ['required' => false, 'type' => 'employee'],
                'UF_AGENT' => ['required' => false, 'type' => 'string'],
                'UF_AGENT_ID' => ['required' => false, 'type' => 'employee'],
                'UF_DIRECTION_HEAD' => ['required' => false, 'type' => 'string'],
                'UF_DIRECTION_HEAD_ID' => ['required' => false, 'type' => 'employee'],
                // Денежные поля - хранятся в копейках (integer), конвертация через brick/money
                'UF_DOC_SUM' => ['required' => true, 'type' => 'money_minor'],
                'UF_CREDIT_SUM' => ['required' => true, 'type' => 'money_minor'],
                'UF_OVERDUE_CREDIT_SUM' => ['required' => false, 'type' => 'money_minor'],
                'UF_AGENT_DEDUCTION' => ['required' => false, 'type' => 'money_minor'],
                'UF_SUPERVISOR_DEDUCTION' => ['required' => false, 'type' => 'money_minor'],
                'UF_MANAGER_DEDUCTION' => ['required' => false, 'type' => 'money_minor'],
                'UF_DIRECTION_HEAD_DEDUCTION' => ['required' => false, 'type' => 'money_minor'],
                'UF_SHIPMENT_DATE' => ['required' => false, 'type' => 'date'],
                'UF_PAYMENT_DATE' => ['required' => false, 'type' => 'date'],
                'UF_DOC_DATE' => ['required' => false, 'type' => 'datetime'],
                'UF_DEBT_DEPTH' => ['required' => false, 'type' => 'integer'],
                'UF_CREDIT_LIMIT' => ['required' => false, 'type' => 'money_minor'],
                'UF_PAYMENT_DELAY' => ['required' => false, 'type' => 'integer'],
                'UF_IS_COLLATERAL' => ['required' => false, 'type' => 'boolean'],
                'UF_CONTRACT_TYPE' => ['required' => false, 'type' => 'string'],
            ],
            'permissions' => [
                'read' => true,
                'add' => true,
                'update' => true,
                'delete' => true,
                'batch_add' => true,
                'batch_update' => true,
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
            
            $skipCompanyCheck = $this->getParam($request, 'skipCompanyValidation', false);
            // Пропуск проверки работает только если: параметр true И токен разрешает
            if ($skipCompanyCheck && !empty($this->tokenData['skip_company_validation'])) {
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
                case 'batch_add':
                    $this->actionBatchAdd($request, $hlBlockId);
                    break;
                case 'batch_update':
                    $this->actionBatchUpdate($request, $hlBlockId);
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
            'batch_add' => 'batch_add',
            'batch_update' => 'batch_update',
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
        if (in_array($action, ['add', 'update', 'batch_add', 'batch_update'])) {
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
                case 'money':
                    $this->validator->validateMoney($fieldCode, $value, $fieldCode);
                    break;
                case 'money_minor':
                    $this->validator->validateMoneyMinor($fieldCode, $value, $fieldCode);
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
            
            if ($value === null || $value === '') {
                continue;
            }
            
            switch ($fieldConfig['type'] ?? 'string') {
                case 'boolean':
                    $boolValue = $value === true || $value === 1 || $value === '1' 
                        || strtolower($value) === 'да' || strtolower($value) === 'yes';
                    $prepared[$fieldCode] = $boolValue ? 1 : 0;
                    break;
                case 'integer':
                    $prepared[$fieldCode] = intval($value);
                    break;
                case 'double':
                    $normalizedValue = str_replace([',', ' '], ['.', ''], trim($value));
                    $prepared[$fieldCode] = floatval($normalizedValue);
                    break;
                case 'date':
                    if (!empty($value)) {
                        $prepared[$fieldCode] = $this->prepareDate($value);
                    }
                    break;
                case 'datetime':
                    if (!empty($value)) {
                        $prepared[$fieldCode] = $this->prepareDateTime($value);
                    }
                    break;
                case 'string':
                    $prepared[$fieldCode] = trim($value);
                    break;
                case 'crm_company':
                    if (preg_match('/^CO_(\d+)$/', $value, $matches)) {
                        $prepared[$fieldCode] = $matches[1];
                    } else {
                        $prepared[$fieldCode] = intval($value);
                    }
                    break;
                case 'money':
                    $prepared[$fieldCode] = $this->prepareMoney($value, $fieldConfig['currency'] ?? 'RUB');
                    break;
                case 'money_minor':
                    // Хранение в копейках через brick/money
                    $prepared[$fieldCode] = $this->prepareMoneyMinor($value);
                    break;
                case 'employee':
                case 'hlblock':
                case 'iblock_section':
                    $prepared[$fieldCode] = intval($value);
                    break;
                default:
                    $prepared[$fieldCode] = $value;
            }
        }
        
        return $prepared;
    }
    
    private function prepareMoney($value, $currency = 'RUB'): string
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return '';
        }
        
        $normalizedValue = str_replace([',', ' '], ['.', ''], trim($value));
        $floatValue = floatval($normalizedValue);
        $formattedValue = number_format($floatValue, 2, '.', '');
        
        return $formattedValue . '|' . $currency;
    }
    
    /**
     * Конвертация денежного значения в копейки (minor units) через brick/money
     * Принимает: "15000.50" (рубли), "15000,50", "15 000.50", 1500050 (уже копейки)
     * Возвращает: int (копейки)
     */
    private function prepareMoneyMinor($value): int
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return 0;
        }
        
        // Если уже integer - считаем что это копейки
        if (is_int($value)) {
            return $value;
        }
        
        // Очистка: убираем пробелы, заменяем запятую на точку
        $normalizedValue = str_replace([' ', ','], ['', '.'], trim($value));
        
        // Если целое число без точки и больше 100 - скорее всего уже копейки
        if (ctype_digit(ltrim($normalizedValue, '-')) && abs((int)$normalizedValue) >= 100) {
            // Проверяем: если это похоже на копейки (большое число без дробной части)
            // Но это может быть и 100 рублей ровно. Для однозначности:
            // - если передано как string без точки - считаем копейками
            // - если передано с точкой - считаем рублями
            return (int)$normalizedValue;
        }
        
        try {
            // Определяем, есть ли дробная часть
            if (strpos($normalizedValue, '.') !== false) {
                // Это рубли с копейками - конвертируем через brick/money
                $money = Money::of($normalizedValue, 'RUB', roundingMode: RoundingMode::HALF_UP);
                return $money->getMinorAmount()->toInt();
            } else {
                // Целое число - если маленькое, считаем рублями, если большое - копейками
                $intValue = (int)$normalizedValue;
                // Эвристика: если число < 1000000 и передано без точки, считаем копейками
                // Это покрывает суммы до 10000 рублей как копейки
                // Для больших сумм используйте явно дробный формат "1000000.00"
                return $intValue;
            }
        } catch (\Exception $e) {
            AddMessage2Log("[MONEY_MINOR] Error parsing value '{$value}': " . $e->getMessage(), 'hl_api');
            // Fallback: пробуем как float и умножаем на 100
            $floatValue = (float)$normalizedValue;
            return (int)round($floatValue * 100);
        }
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
    
    private function prepareDateTime($value)
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        
        $formats = ['d.m.Y H:i:s', 'Y-m-d H:i:s', 'd.m.Y G:i:s', 'd.m.Y', 'Y-m-d'];
        
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed !== false) {
                return DateTime::createFromPhp($parsed);
            }
        }
        
        return null;
    }
    
    private function actionGet($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        
        $companyId = $this->getParam($request, 'companyId');
        $itemId = intval($this->getParam($request, 'itemId', 0));
        $limit = min(intval($this->getParam($request, 'limit', 50)), 1000);
        $offset = max(intval($this->getParam($request, 'offset', 0)), 0);
        
        $filter = [];
        
        if (!empty($companyId)) {
            $normalizedCompanyId = null;
            if (preg_match('/^CO_(\d+)$/', $companyId, $matches)) {
                $normalizedCompanyId = intval($matches[1]);
            } elseif (is_numeric($companyId)) {
                $normalizedCompanyId = intval($companyId);
            }
            
            if ($normalizedCompanyId && $normalizedCompanyId > 0) {
                $filter['UF_COMPANY_ID'] = $normalizedCompanyId;
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
        $config = $this->hlConfig[$hlBlockId]['fields'] ?? [];
        
        foreach ($item as $key => $value) {
            if ($key === 'ID') continue;
            
            $fieldType = $config[$key]['type'] ?? null;
            
            if ($value instanceof Date || $value instanceof DateTime) {
                $output[$key] = $value->format('d.m.Y H:i:s');
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
            } elseif ($fieldType === 'money' && !empty($value)) {
                $output[$key] = $this->parseMoneyForOutput($value);
            } elseif ($fieldType === 'money_minor') {
                // Конвертация из копеек в рубли через brick/money
                $output[$key] = $this->parseMoneyMinorForOutput($value);
            } elseif ($fieldType === 'boolean') {
                $output[$key] = (bool)$value;
            } else {
                $output[$key] = $value;
            }
        }
        
        return $output;
    }
    
    private function parseMoneyForOutput($value): array
    {
        if (is_array($value)) {
            return [
                'value' => floatval($value['VALUE'] ?? $value['SUM'] ?? 0),
                'currency' => $value['CURRENCY'] ?? 'RUB',
                'formatted' => number_format(floatval($value['VALUE'] ?? $value['SUM'] ?? 0), 2, '.', ' ') . ' ₽',
            ];
        }
        
        if (is_string($value) && strpos($value, '|') !== false) {
            list($amount, $currency) = explode('|', $value, 2);
            $floatAmount = floatval($amount);
            
            return [
                'value' => $floatAmount,
                'currency' => $currency ?: 'RUB',
                'formatted' => number_format($floatAmount, 2, '.', ' ') . ' ₽',
            ];
        }
        
        $floatAmount = floatval($value);
        return [
            'value' => $floatAmount,
            'currency' => 'RUB',
            'formatted' => number_format($floatAmount, 2, '.', ' ') . ' ₽',
        ];
    }
    
    /**
     * Конвертация из копеек (minor units) в рубли через brick/money
     * @param int|null $minorAmount Сумма в копейках
     * @return array
     */
    private function parseMoneyMinorForOutput($minorAmount): array
    {
        if (empty($minorAmount) && $minorAmount !== 0) {
            return [
                'minor' => 0,
                'value' => '0.00',
                'currency' => 'RUB',
                'formatted' => '0,00 ₽',
            ];
        }
        
        $intAmount = (int)$minorAmount;
        
        try {
            $money = Money::ofMinor($intAmount, 'RUB');
            $decimalValue = $money->getAmount()->toScale(2);
            
            return [
                'minor' => $intAmount,
                'value' => (string)$decimalValue,
                'currency' => 'RUB',
                'formatted' => number_format((float)(string)$decimalValue, 2, ',', ' ') . ' ₽',
            ];
        } catch (\Exception $e) {
            // Fallback без brick/money
            $rubles = $intAmount / 100;
            return [
                'minor' => $intAmount,
                'value' => number_format($rubles, 2, '.', ''),
                'currency' => 'RUB',
                'formatted' => number_format($rubles, 2, ',', ' ') . ' ₽',
            ];
        }
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
    
    /**
     * Массовое добавление записей
     */
    private function actionBatchAdd($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $items = $this->getParam($request, 'items');
        
        if (!is_array($items) || empty($items)) {
            $this->sendError('Параметр items должен быть непустым массивом', 400);
        }
        
        $maxBatchSize = 1000;
        if (count($items) > $maxBatchSize) {
            $this->sendError("Максимальный размер пакета: {$maxBatchSize} записей", 400);
        }
        
        AddMessage2Log("[BATCH_ADD] Items count: " . count($items) . ", HL Block: {$hlBlockId}", 'hl_api');
        
        $results = [
            'success' => [],
            'errors' => [],
        ];
        
        $connection = Application::getConnection();
        $connection->startTransaction();
        
        try {
            foreach ($items as $index => $itemData) {
                $this->validator->clearErrors();
                
                if (!$this->validateFields($hlBlockId, $itemData, false)) {
                    $results['errors'][] = [
                        'index' => $index,
                        'errors' => $this->validator->getErrors(),
                    ];
                    continue;
                }
                
                $preparedFields = $this->prepareFields($hlBlockId, $itemData);
                $result = $entityClass::add($preparedFields);
                
                if ($result->isSuccess()) {
                    $results['success'][] = [
                        'index' => $index,
                        'id' => $result->getId(),
                    ];
                } else {
                    $results['errors'][] = [
                        'index' => $index,
                        'errors' => $result->getErrorMessages(),
                    ];
                }
            }
            
            $allOrNothing = $this->getParam($request, 'all_or_nothing', false);
            if ($allOrNothing && !empty($results['errors'])) {
                $connection->rollbackTransaction();
                $this->sendError('Транзакция отменена из-за ошибок', 400, $results);
            }
            
            $connection->commitTransaction();
            
            $this->logAction('batch_add', $hlBlockId, 0, ['count' => count($results['success'])]);
            
            $this->sendSuccess([
                'added' => count($results['success']),
                'failed' => count($results['errors']),
                'results' => $results,
            ]);
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            AddMessage2Log("[BATCH_ADD] Exception: " . $e->getMessage(), 'hl_api');
            $this->sendError('Ошибка при массовом добавлении: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Массовое обновление записей
     */
    private function actionBatchUpdate($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $items = $this->getParam($request, 'items');
        
        if (!is_array($items) || empty($items)) {
            $this->sendError('Параметр items должен быть непустым массивом', 400);
        }
        
        AddMessage2Log("[BATCH_UPDATE] Items count: " . count($items) . ", HL Block: {$hlBlockId}", 'hl_api');
        
        $results = [
            'success' => [],
            'errors' => [],
        ];
        
        foreach ($items as $index => $itemData) {
            $itemId = intval($itemData['id'] ?? $itemData['ID'] ?? $itemData['itemId'] ?? 0);
            
            if (!$itemId) {
                $results['errors'][] = [
                    'index' => $index,
                    'error' => 'Не указан ID записи',
                ];
                continue;
            }
            
            $existing = $entityClass::getById($itemId)->fetch();
            if (!$existing) {
                $results['errors'][] = [
                    'index' => $index,
                    'id' => $itemId,
                    'error' => 'Запись не найдена',
                ];
                continue;
            }
            
            unset($itemData['id'], $itemData['ID'], $itemData['itemId']);
            
            $this->validator->clearErrors();
            if (!$this->validateFields($hlBlockId, $itemData, true)) {
                $results['errors'][] = [
                    'index' => $index,
                    'id' => $itemId,
                    'errors' => $this->validator->getErrors(),
                ];
                continue;
            }
            
            $preparedFields = $this->prepareFields($hlBlockId, $itemData);
            $result = $entityClass::update($itemId, $preparedFields);
            
            if ($result->isSuccess()) {
                $results['success'][] = ['index' => $index, 'id' => $itemId];
            } else {
                $results['errors'][] = [
                    'index' => $index,
                    'id' => $itemId,
                    'errors' => $result->getErrorMessages(),
                ];
            }
        }
        
        $this->logAction('batch_update', $hlBlockId, 0, ['count' => count($results['success'])]);
        
        $this->sendSuccess([
            'updated' => count($results['success']),
            'failed' => count($results['errors']),
            'results' => $results,
        ]);
    }
    
    private function actionUpdate($request, $hlBlockId): void
    {
        $entityClass = $this->getEntityClass($hlBlockId);
        $itemId = intval(
            $this->getParam($request, 'id', 0) 
            ?: $this->getParam($request, 'ID', 0) 
            ?: $this->getParam($request, 'itemId', 0)
        );
        
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
        $itemId = intval(
            $this->getParam($request, 'id', 0) 
            ?: $this->getParam($request, 'ID', 0) 
            ?: $this->getParam($request, 'itemId', 0)
        );
        
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
        
        if (empty($fields['UF_COMPANY_ID'])) {
            $companyId = $this->getParam($request, 'companyId');
            if (!empty($companyId)) {
                $fields['UF_COMPANY_ID'] = $companyId;
                AddMessage2Log("[COLLECT] Auto-filled UF_COMPANY_ID from companyId: {$companyId}", 'hl_api');
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