<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

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
            $this->showError(Loc::getMessage('CRM_MODULE_NOT_INSTALLED'));
            return false;
        }
        
        if (!Loader::includeModule('highloadblock')) {
            $this->showError(Loc::getMessage('HIGHLOADBLOCK_MODULE_NOT_INSTALLED'));
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
            return null;
        }

        $hlblock = HL\HighloadBlockTable::getById($this->hlBlockId)->fetch();
        
        if (!$hlblock) {
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

        $rsData = $entityDataClass::getList([
            'select' => ['*'],
            'order' => ['ID' => 'ASC'],
            'filter' => [$this->companyLinkField => $companyId]
        ]);

        $result = [];
        while ($item = $rsData->fetch()) {
            $result[] = $this->prepareItemData($item);
        }

        return $result;
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
            
            // Обработка разных типов полей
            switch ($fieldConfig['TYPE']) {
                case 'string':
                case 'text':
                    $prepared[$fieldCode] = htmlspecialcharsbx($value);
                    break;
                    
                case 'crm':
                    // Для CRM-полей получаем название элемента
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

        // Формат: CO_1 (Company), C_1 (Contact), L_1 (Lead)
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
        
        // Проверяем базовые права CRM (ПРАВИЛЬНО: передаём 0, а не BX_CRM_PERM_NONE)
        $crmPerms = new \CCrmPerms($USER->GetID());
        
        switch ($action) {
            case 'READ':
                $hasPermission = $this->permissions['canRead'] && 
                                $crmPerms->HavePerm('COMPANY', 0, 'READ');
                break;
                
            case 'EDIT':
            case 'ADD':
                $hasPermission = $this->permissions['canEdit'] && 
                                $crmPerms->HavePerm('COMPANY', 0, 'WRITE');
                break;
                
            case 'DELETE':
                $hasPermission = $this->permissions['canDelete'] && 
                                $crmPerms->HavePerm('COMPANY', 0, 'DELETE');
                break;
                
            default:
                $hasPermission = false;
        }

        // Дополнительная проверка через кастомный класс прав
        if ($hasPermission && class_exists('\CrmHlTabPermissions')) {
            $hasPermission = \CrmHlTabPermissions::checkAccess(
                $USER->GetID(),
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
        if (!$this->checkModules()) {
            return;
        }

        $companyId = $this->arParams['COMPANY_ID'];
        
        if (!$companyId) {
            $this->showError(Loc::getMessage('COMPANY_ID_NOT_SET'));
            return;
        }

        // Проверяем права на чтение
        if (!$this->checkPermissions('READ')) {
            $this->showError(Loc::getMessage('ACCESS_DENIED'));
            return;
        }

        // Получаем данные
        $this->arResult = [
            'ITEMS' => $this->getHlData($companyId),
            'FIELDS_CONFIG' => $this->getFieldsConfig(),
            'COMPANY_ID' => $companyId,
            'TAB_CODE' => $this->arParams['TAB_CODE'],
            'HL_BLOCK_ID' => $this->hlBlockId,
            'PERMISSIONS' => [
                'CAN_READ' => $this->checkPermissions('READ'),
                'CAN_EDIT' => $this->checkPermissions('EDIT'),
                'CAN_ADD' => $this->checkPermissions('ADD'),
                'CAN_DELETE' => $this->checkPermissions('DELETE'),
            ],
            'AJAX_PATH' => '/local/ajax/crm_hl_tabs/',
        ];

        $this->includeComponentTemplate();
    }

    /**
     * Вывод ошибки
     */
    protected function showError($message)
    {
        $this->arResult['ERROR'] = $message;
        $this->includeComponentTemplate();
    }
}