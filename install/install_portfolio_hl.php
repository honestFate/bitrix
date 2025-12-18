<?php
/**
 * Скрипт установки Highload-блока "Расчёт портфеля"
 * Запуск: php -f /local/install/install_portfolio_hl.php
 * или через браузер (под админом)
 * 
 * @version 1.0
 */

// Определяем лог-файл
if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'] . '/local/logs/hl_install.log');
}

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: realpath(__DIR__ . '/../..');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity\Base;

// Проверка прав
global $USER;
if (php_sapi_name() !== 'cli' && (!$USER || !$USER->IsAdmin())) {
    die('Access denied. Admin rights required.');
}

AddMessage2Log("=== Starting Portfolio HL Block Installation ===", 'hl_install');

// Подключение модулей
if (!Loader::includeModule('highloadblock')) {
    AddMessage2Log("ERROR: Highloadblock module not found", 'hl_install');
    die('Highloadblock module not installed');
}

if (!Loader::includeModule('crm')) {
    AddMessage2Log("WARNING: CRM module not found", 'hl_install');
}

/**
 * Конфигурация Highload-блока
 */
$hlBlockConfig = [
    'NAME' => 'PortfolioCalculation',
    'TABLE_NAME' => 'hl_portfolio_calculation',
];

/**
 * Конфигурация полей
 * Структура: код => [тип, название, обязательное, настройки]
 */
$fieldsConfig = [
    // Период расчета
    'UF_PERIOD' => [
        'type' => 'datetime',
        'label' => 'Период расчета',
        'required' => false,
    ],
    
    // Контрагент - строка + привязка к CRM компании
    'UF_CONTRACTOR' => [
        'type' => 'string',
        'label' => 'Контрагент (наименование)',
        'required' => false,
    ],
    'UF_CONTRACTOR_ID' => [
        'type' => 'crm',
        'label' => 'Контрагент (CRM)',
        'required' => false,
        'settings' => ['LEAD' => 'N', 'CONTACT' => 'N', 'COMPANY' => 'Y', 'DEAL' => 'N'],
    ],
    
    // Договор - строка + привязка к HL-блоку 6
    'UF_CONTRACT' => [
        'type' => 'string',
        'label' => 'Договор (наименование)',
        'required' => false,
    ],
    'UF_CONTRACT_ID' => [
        'type' => 'hlblock',
        'label' => 'Договор (HL)',
        'required' => false,
        'settings' => ['HLBLOCK_ID' => 6],
    ],
    
    // Документ расчета
    'UF_DOCUMENT' => [
        'type' => 'string',
        'label' => 'Документ расчета',
        'required' => false,
    ],
    
    // Организация - строка + привязка к CRM компании
    'UF_ORGANIZATION' => [
        'type' => 'string',
        'label' => 'Организация (наименование)',
        'required' => false,
    ],
    'UF_ORGANIZATION_ID' => [
        'type' => 'crm',
        'label' => 'Организация (CRM)',
        'required' => false,
        'settings' => ['LEAD' => 'N', 'CONTACT' => 'N', 'COMPANY' => 'Y', 'DEAL' => 'N'],
    ],
    
    // Подразделение - строка + привязка к оргструктуре
    'UF_DEPARTMENT' => [
        'type' => 'string',
        'label' => 'Подразделение (наименование)',
        'required' => false,
    ],
    'UF_DEPARTMENT_ID' => [
        'type' => 'iblock_section',
        'label' => 'Подразделение (оргструктура)',
        'required' => false,
        'settings' => ['IBLOCK_TYPE_ID' => 'structure', 'IBLOCK_ID' => 0], // IBLOCK_ID будет определен автоматически
    ],
    
    // Руководитель - строка + привязка к сотруднику
    'UF_MANAGER' => [
        'type' => 'string',
        'label' => 'Руководитель (ФИО)',
        'required' => false,
    ],
    'UF_MANAGER_ID' => [
        'type' => 'employee',
        'label' => 'Руководитель (сотрудник)',
        'required' => false,
    ],
    
    // Супервайзер - строка + привязка к сотруднику
    'UF_SUPERVISOR' => [
        'type' => 'string',
        'label' => 'Супервайзер (ФИО)',
        'required' => false,
    ],
    'UF_SUPERVISOR_ID' => [
        'type' => 'employee',
        'label' => 'Супервайзер (сотрудник)',
        'required' => false,
    ],
    
    // Агент - строка + привязка к сотруднику
    'UF_AGENT' => [
        'type' => 'string',
        'label' => 'Агент (ФИО)',
        'required' => false,
    ],
    'UF_AGENT_ID' => [
        'type' => 'employee',
        'label' => 'Агент (сотрудник)',
        'required' => false,
    ],
    
    // Руководитель направления - строка + привязка к сотруднику
    'UF_DIRECTION_HEAD' => [
        'type' => 'string',
        'label' => 'Руководитель направления (ФИО)',
        'required' => false,
    ],
    'UF_DIRECTION_HEAD_ID' => [
        'type' => 'employee',
        'label' => 'Руководитель направления (сотрудник)',
        'required' => false,
    ],
    
    // Финансовые поля - суммы в копейках (integer)
    // Хранение: рубли * 100 + копейки (например, 15000.50 руб = 1500050)
    'UF_DOC_SUM' => [
        'type' => 'integer',
        'label' => 'Сумма документа (коп.)',
        'required' => false,
    ],
    'UF_CREDIT_SUM' => [
        'type' => 'integer',
        'label' => 'Сумма товарного кредита (коп.)',
        'required' => false,
    ],
    'UF_OVERDUE_CREDIT_SUM' => [
        'type' => 'integer',
        'label' => 'Сумма просроченного товарного кредита (коп.)',
        'required' => false,
    ],
    'UF_AGENT_DEDUCTION' => [
        'type' => 'integer',
        'label' => 'Сумма удержания агент (коп.)',
        'required' => false,
    ],
    'UF_SUPERVISOR_DEDUCTION' => [
        'type' => 'integer',
        'label' => 'Сумма удержания супервайзер (коп.)',
        'required' => false,
    ],
    'UF_MANAGER_DEDUCTION' => [
        'type' => 'integer',
        'label' => 'Сумма удержания руководитель (коп.)',
        'required' => false,
    ],
    'UF_DIRECTION_HEAD_DEDUCTION' => [
        'type' => 'integer',
        'label' => 'Сумма удержания руководитель направления (коп.)',
        'required' => false,
    ],
    
    // Даты
    'UF_SHIPMENT_DATE' => [
        'type' => 'date',
        'label' => 'Дата отгрузки',
        'required' => false,
    ],
    'UF_PAYMENT_DATE' => [
        'type' => 'date',
        'label' => 'Дата платежа',
        'required' => false,
    ],
    'UF_DOC_DATE' => [
        'type' => 'datetime',
        'label' => 'Дата документа',
        'required' => false,
    ],
    
    // Числовые поля
    'UF_DEBT_DEPTH' => [
        'type' => 'integer',
        'label' => 'Глубина задолженности (дни)',
        'required' => false,
    ],
    'UF_CREDIT_LIMIT' => [
        'type' => 'integer',
        'label' => 'Кредитный лимит (коп.)',
        'required' => false,
    ],
    'UF_PAYMENT_DELAY' => [
        'type' => 'integer',
        'label' => 'Отсрочка платежа (дни)',
        'required' => false,
    ],
    
    // Прочие поля
    'UF_IS_COLLATERAL' => [
        'type' => 'boolean',
        'label' => 'Это договор обеспечения',
        'required' => false,
    ],
    'UF_CONTRACT_TYPE' => [
        'type' => 'string',
        'label' => 'Вид договора',
        'required' => false,
    ],
];

/**
 * Получение ID инфоблока оргструктуры
 */
function getStructureIblockId(): int
{
    if (!Loader::includeModule('iblock')) {
        return 0;
    }
    
    $res = \CIBlock::GetList([], ['TYPE' => 'structure', 'CODE' => 'departments']);
    if ($iblock = $res->Fetch()) {
        return (int)$iblock['ID'];
    }
    
    // Пробуем найти по названию
    $res = \CIBlock::GetList([], ['TYPE' => 'structure', 'NAME' => '%подразделен%']);
    if ($iblock = $res->Fetch()) {
        return (int)$iblock['ID'];
    }
    
    return 0;
}

/**
 * Создание Highload-блока
 */
function createHlBlock(array $config): ?int
{
    // Проверяем, существует ли уже
    $existing = HL\HighloadBlockTable::getList([
        'filter' => ['NAME' => $config['NAME']]
    ])->fetch();
    
    if ($existing) {
        AddMessage2Log("HL Block '{$config['NAME']}' already exists with ID: {$existing['ID']}", 'hl_install');
        return (int)$existing['ID'];
    }
    
    $result = HL\HighloadBlockTable::add($config);
    
    if ($result->isSuccess()) {
        $hlBlockId = $result->getId();
        AddMessage2Log("Created HL Block '{$config['NAME']}' with ID: {$hlBlockId}", 'hl_install');
        return $hlBlockId;
    } else {
        AddMessage2Log("ERROR creating HL Block: " . implode(', ', $result->getErrorMessages()), 'hl_install');
        return null;
    }
}

/**
 * Создание пользовательского поля
 */
function createUserField(int $hlBlockId, string $fieldCode, array $fieldConfig): bool
{
    $entityId = 'HLBLOCK_' . $hlBlockId;
    
    // Проверяем существование поля
    $existingField = \CUserTypeEntity::GetList([], [
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => $fieldCode,
    ])->Fetch();
    
    if ($existingField) {
        AddMessage2Log("Field {$fieldCode} already exists", 'hl_install');
        return true;
    }
    
    $userTypeEntity = new \CUserTypeEntity();
    
    // Базовые настройки поля
    $arFields = [
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => $fieldCode,
        'USER_TYPE_ID' => mapFieldType($fieldConfig['type']),
        'SORT' => 100,
        'MULTIPLE' => 'N',
        'MANDATORY' => $fieldConfig['required'] ? 'Y' : 'N',
        'SHOW_FILTER' => 'E',
        'SHOW_IN_LIST' => 'Y',
        'EDIT_IN_LIST' => 'Y',
        'IS_SEARCHABLE' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => $fieldConfig['label'], 'en' => $fieldConfig['label']],
        'LIST_COLUMN_LABEL' => ['ru' => $fieldConfig['label'], 'en' => $fieldConfig['label']],
        'LIST_FILTER_LABEL' => ['ru' => $fieldConfig['label'], 'en' => $fieldConfig['label']],
    ];
    
    // Дополнительные настройки в зависимости от типа
    $settings = [];
    
    switch ($fieldConfig['type']) {
        case 'string':
            $settings['SIZE'] = 50;
            $settings['ROWS'] = 1;
            break;
            
        case 'integer':
            $settings['DEFAULT_VALUE'] = 0;
            break;
            
        case 'double':
            $settings['PRECISION'] = 2;
            break;
            
        case 'boolean':
            $settings['DEFAULT_VALUE'] = 0;
            $settings['DISPLAY'] = 'CHECKBOX';
            break;
            
        case 'date':
        case 'datetime':
            $settings['DEFAULT_VALUE'] = ['TYPE' => 'NONE', 'VALUE' => ''];
            break;
            
        case 'employee':
            $arFields['USER_TYPE_ID'] = 'employee';
            break;
            
        case 'crm':
            $arFields['USER_TYPE_ID'] = 'crm';
            $settings = $fieldConfig['settings'] ?? [
                'LEAD' => 'N',
                'CONTACT' => 'N', 
                'COMPANY' => 'Y',
                'DEAL' => 'N',
            ];
            break;
            
        case 'hlblock':
            $arFields['USER_TYPE_ID'] = 'hlblock';
            $settings = [
                'HLBLOCK_ID' => $fieldConfig['settings']['HLBLOCK_ID'] ?? 0,
                'HLFIELD_ID' => 0,
            ];
            break;
            
        case 'iblock_section':
            $arFields['USER_TYPE_ID'] = 'iblock_section';
            $iblockId = $fieldConfig['settings']['IBLOCK_ID'] ?? getStructureIblockId();
            $settings = [
                'IBLOCK_TYPE_ID' => $fieldConfig['settings']['IBLOCK_TYPE_ID'] ?? 'structure',
                'IBLOCK_ID' => $iblockId,
                'DISPLAY' => 'LIST',
                'LIST_HEIGHT' => 5,
                'ACTIVE_FILTER' => 'N',
            ];
            break;
    }
    
    $arFields['SETTINGS'] = $settings;
    
    $fieldId = $userTypeEntity->Add($arFields);
    
    if ($fieldId) {
        AddMessage2Log("Created field {$fieldCode} (ID: {$fieldId})", 'hl_install');
        return true;
    } else {
        global $APPLICATION;
        $error = $APPLICATION->GetException();
        AddMessage2Log("ERROR creating field {$fieldCode}: " . ($error ? $error->GetString() : 'Unknown error'), 'hl_install');
        return false;
    }
}

/**
 * Маппинг типов полей
 */
function mapFieldType(string $type): string
{
    $map = [
        'string' => 'string',
        'integer' => 'integer',
        'double' => 'double',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
        'employee' => 'employee',
        'crm' => 'crm',
        'hlblock' => 'hlblock',
        'iblock_section' => 'iblock_section',
    ];
    
    return $map[$type] ?? 'string';
}

// === ОСНОВНОЙ КОД УСТАНОВКИ ===

echo "<pre>\n";
echo "=== Установка Highload-блока 'Расчёт портфеля' ===\n\n";

// 1. Создание HL-блока
$hlBlockId = createHlBlock($hlBlockConfig);

if (!$hlBlockId) {
    echo "ОШИБКА: Не удалось создать Highload-блок\n";
    die();
}

echo "✓ Highload-блок создан/найден: ID = {$hlBlockId}\n\n";

// 2. Создание полей
echo "Создание полей:\n";
$successCount = 0;
$errorCount = 0;

foreach ($fieldsConfig as $fieldCode => $fieldConfig) {
    if (createUserField($hlBlockId, $fieldCode, $fieldConfig)) {
        echo "  ✓ {$fieldCode} ({$fieldConfig['label']})\n";
        $successCount++;
    } else {
        echo "  ✗ {$fieldCode} - ОШИБКА\n";
        $errorCount++;
    }
}

echo "\n";
echo "=== Результат ===\n";
echo "Highload-блок ID: {$hlBlockId}\n";
echo "Успешно создано полей: {$successCount}\n";
echo "Ошибок: {$errorCount}\n";
echo "\nТаблица в БД: {$hlBlockConfig['TABLE_NAME']}\n";
echo "</pre>\n";

AddMessage2Log("=== Installation completed. HL ID: {$hlBlockId}, Fields: {$successCount}, Errors: {$errorCount} ===", 'hl_install');