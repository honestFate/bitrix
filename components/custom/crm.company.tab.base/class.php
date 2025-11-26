<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Highloadblock as HL;

Loc::loadMessages(__FILE__);

/**
 * Базовый класс для компонентов вкладок CRM с работой с Highload-блоками
 */
class CrmCompanyTabBase extends CBitrixComponent
{
    /** @var int ID Highload-блока */
    protected $hlBlockId;
    
    /** @var string Код поля связи с компанией */
    protected $companyLinkField = 'UF_COMPANY_ID';
    
    /** @var array Конфигурация полей для отображения */
    protected $fieldsConfig = [];
    
    /** @var string Код вкладки */
    protected $tabCode = '';
    
    /** @var string Название вкладки */
    protected $tabName = '';
    
    /** @var array Права доступа */
    protected $permissions = [
        'canRead' => true,
        'canEdit' => true,
        'canDelete' => false
    ];

    /**
     * Проверка необходимых модулей
     */
    protected function checkModules()
    {
        if (!Loader::includeModule('crm')) {
            $this->showError(Loc::getMessage('CRM_MODULE_NOT_INSTALLED') ?: 'Модуль CRM не установлен');
            return false;
        }
        
        if (!Loader::includeModule('highloadblock')) {
            $this->showError(Loc::getMessage('HIGHLOADBLOCK_MODULE_NOT_INSTALLED') ?: 'Модуль Highload-блоков не установлен');
            return false;
        }
        
        return true;
    }

    /**
     * Получение сущности Highload-блока
     */
    protected function getHlEntity()
    {
        if (!$this->hlBlockId) {
            AddMessage2Log("HlBlockId not set for tab {$this->tabCode}", 'crm_tabs');
            return null;
        }

        $hlblock = HL\HighloadBlockTable::getById($this->hlBlockId)->fetch();
        
        if (!$hlblock) {
            AddMessage2Log("HlBlock with ID {$this->hlBlockId} not found", 'crm_tabs');
            return null;
        }

        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        return $entity->getDataClass();
    }

    /**
     * Получение данных из Highload-блока для компании
     */
    protected function getHlData($companyId)
    {
        $entityDataClass = $this->getHlEntity();
        
        if (!$entityDataClass) {
            return [];
        }

        try {
            $rsData = $entityDataClass::getList([
                'select' => ['*'],
                'order' => ['ID' => 'ASC'],
                'filter' => [$this->companyLinkField => $companyId]
            ]);

            $result = [];
            while ($item = $rsData->fetch()) {
                $result[] = $this->prepareItemData($item);
            }

            AddMessage2Log("Loaded " . count($result) . " items for company {$companyId} from HL {$this->hlBlockId}", 'crm_tabs');
            
            return $result;
        } catch (\Exception $e) {
            AddMessage2Log("Error loading HL data: " . $e->getMessage(), 'crm_tabs');
            return [];
        }
    }

    /**
     * Подготовка данных элемента для отображения
     */
    protected function prepareItemData($item)
    {
        $prepared = [
            'ID' => $item['ID']
        ];

        foreach ($this->fieldsConfig as $fieldCode => $fieldConfig) {
            $value = $item[$fieldCode] ?? '';
            
            switch ($fieldConfig['TYPE'] ?? 'string') {
                case 'string':
                case 'text':
                    $prepared[$fieldCode] = htmlspecialcharsbx($value);
                    break;
                    
                case 'crm':
                    $prepared[$fieldCode] = $this->getCrmElementName($value);
                    $prepared[$fieldCode . '_RAW'] = $value;
                    break;
                    
                default:
                    $prepared[$fieldCode] = $value;
            }
        }

        return $prepared;
    }

    /**
     * Получение названия элемента CRM
     */
    protected function getCrmElementName($value)
    {
        if (empty($value)) {
            return '';
        }

        if (preg_match('/^CO_(\d+)$/', $value, $matches)) {
            $company = \CCrmCompany::GetByID($matches[1]);
            return $company ? $company['TITLE'] : '';
        }

        return $value;
    }

    /**
     * Проверка прав доступа
     */
    protected function checkPermissions($action = 'READ')
    {
        global $USER;
        
        $userId = $USER->GetID();
        
        if ($USER->IsAdmin()) {
            return true;
        }
        
        $crmPerms = new \CCrmPerms($userId);
        
        // Проверка базовых прав CRM
        // GetPermType возвращает уровень доступа (NONE, SELF, DEPARTMENT, ALL и т.д.)
        switch ($action) {
            case 'READ':
                if (!$this->permissions['canRead']) {
                    return false;
                }
                $permType = $crmPerms->GetPermType('COMPANY', 'READ');
                $hasPermission = ($permType !== BX_CRM_PERM_NONE);
                break;
                
            case 'EDIT':
            case 'ADD':
                if (!$this->permissions['canEdit']) {
                    return false;
                }
                $permType = $crmPerms->GetPermType('COMPANY', 'WRITE');
                $hasPermission = ($permType !== BX_CRM_PERM_NONE);
                break;
                
            case 'DELETE':
                if (!$this->permissions['canDelete']) {
                    return false;
                }
                $permType = $crmPerms->GetPermType('COMPANY', 'DELETE');
                $hasPermission = ($permType !== BX_CRM_PERM_NONE);
                break;
                
            default:
                $hasPermission = false;
        }

        if ($hasPermission && class_exists('\CrmHlTabPermissions')) {
            $hasPermission = \CrmHlTabPermissions::checkAccess(
                $userId,
                $this->tabCode,
                $action
            );
        }

        return $hasPermission;
    }

    /**
     * Получение конфигурации полей
     */
    protected function getFieldsConfig()
    {
        return $this->fieldsConfig;
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($params)
    {
        $params['COMPANY_ID'] = intval($params['COMPANY_ID'] ?? 0);
        $params['TAB_CODE'] = trim($params['TAB_CODE'] ?? $this->tabCode);
        
        return parent::onPrepareComponentParams($params);
    }

    /**
     * Выполнение компонента
     */
    public function executeComponent()
    {
        AddMessage2Log("Executing component {$this->tabCode} for company {$this->arParams['COMPANY_ID']}", 'crm_tabs');
        
        if (!$this->checkModules()) {
            return;
        }

        $companyId = $this->arParams['COMPANY_ID'];
        
        if (!$companyId) {
            $this->showError(Loc::getMessage('COMPANY_ID_NOT_SET') ?: 'Не указан ID компании');
            return;
        }

        // Проверяем права на чтение
        if (!$this->checkPermissions('READ')) {
            $this->showError(Loc::getMessage('ACCESS_DENIED') ?: 'Доступ запрещен');
            return;
        }

        // Получаем данные
        $this->arResult = [
            'ITEMS' => $this->getHlData($companyId),
            'FIELDS_CONFIG' => $this->getFieldsConfig(),
            'COMPANY_ID' => $companyId,
            'TAB_CODE' => $this->arParams['TAB_CODE'] ?: $this->tabCode,
            'TAB_NAME' => $this->tabName,
            'HL_BLOCK_ID' => $this->hlBlockId,
            'PERMISSIONS' => [
                'CAN_READ' => $this->checkPermissions('READ'),
                'CAN_EDIT' => $this->checkPermissions('EDIT'),
                'CAN_ADD' => $this->checkPermissions('ADD'),
                'CAN_DELETE' => $this->checkPermissions('DELETE'),
            ],
            'AJAX_PATH' => '/local/ajax/',
        ];

        $this->includeComponentTemplate();
    }

    /**
     * Вывод ошибки
     */
    protected function showError($message)
    {
        AddMessage2Log("Component error: {$message}", 'crm_tabs');
        $this->arResult['ERROR'] = $message;
        $this->includeComponentTemplate();
    }
}
