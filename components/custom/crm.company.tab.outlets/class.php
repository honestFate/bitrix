<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Подключаем родительский класс
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/class.php';

/**
 * Компонент "Торговые точки" для карточки компании
 */
class CrmCompanyTabOutlets extends CrmCompanyTabBase
{
    public function __construct($component = null)
    {
        parent::__construct($component);
        
        // ID Highload-блока
        $this->hlBlockId = 5;
        
        // Код вкладки
        $this->tabCode = 'tab_outlets';
        
        // Название вкладки
        $this->tabName = Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_NAME');
        
        // Конфигурация полей для отображения
        $this->fieldsConfig = [
            'UF_ADDRESS' => [
                'CODE' => 'UF_ADDRESS',
                'NAME' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_FIELD_ADDRESS'),
                'TYPE' => 'string',
                'REQUIRED' => true,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_COMPANY_ID' => [
                'CODE' => 'UF_COMPANY_ID',
                'NAME' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_FIELD_COMPANY'),
                'TYPE' => 'crm',
                'REQUIRED' => true,
                'EDITABLE' => false, // Не редактируем, т.к. это связь с текущей компанией
                'MULTIPLE' => false,
            ],
        ];
        
        // Настройка прав доступа (можно переопределить)
        $this->permissions = [
            'canRead' => true,
            'canEdit' => true,
            'canDelete' => true
        ];
    }

    /**
     * Переопределение метода подготовки данных элемента
     * Можно добавить специфичную для торговых точек обработку
     */
    protected function prepareItemData($item)
    {
        $prepared = parent::prepareItemData($item);
        
        // Дополнительная обработка для торговых точек
        // Например, можно добавить геокодирование адреса
        if (!empty($prepared['UF_ADDRESS'])) {
            $prepared['UF_ADDRESS_SHORT'] = $this->getShortAddress($prepared['UF_ADDRESS']);
        }
        
        return $prepared;
    }

    /**
     * Получение короткого адреса (первые 50 символов)
     */
    private function getShortAddress($address)
    {
        if (mb_strlen($address) > 50) {
            return mb_substr($address, 0, 50) . '...';
        }
        return $address;
    }

    /**
     * Дополнительная валидация для торговых точек
     */
    protected function validateOutletData($data)
    {
        $errors = [];
        
        if (empty($data['UF_ADDRESS'])) {
            $errors[] = Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_ERROR_ADDRESS_EMPTY');
        }
        
        if (mb_strlen($data['UF_ADDRESS']) > 255) {
            $errors[] = Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_ERROR_ADDRESS_TOO_LONG');
        }
        
        return $errors;
    }
}