<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Подключаем родительский класс
$parentClassPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/class.php';
if (!class_exists('CrmCompanyTabBase') && file_exists($parentClassPath)) {
    require_once $parentClassPath;
}

/**
 * Компонент "Торговые точки" для карточки компании
 */
class CrmCompanyTabOutlets extends CrmCompanyTabBase
{
    public function __construct($component = null)
    {
        parent::__construct($component);
        
        // ID Highload-блока "Торговые точки"
        $this->hlBlockId = 5;
        
        // Код вкладки
        $this->tabCode = 'tab_outlets';
        
        // Название вкладки
        $this->tabName = 'Торговые точки';
        
        // Конфигурация полей для отображения
        $this->fieldsConfig = [
            'UF_ADDRESS' => [
                'CODE' => 'UF_ADDRESS',
                'NAME' => 'Адрес',
                'TYPE' => 'string',
                'REQUIRED' => true,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_COMPANY_ID' => [
                'CODE' => 'UF_COMPANY_ID',
                'NAME' => 'Компания',
                'TYPE' => 'crm',
                'REQUIRED' => true,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
        ];
        
        // Настройка прав доступа
        $this->permissions = [
            'canRead' => true,
            'canEdit' => true,
            'canDelete' => true
        ];
    }

    /**
     * Переопределение метода подготовки данных элемента
     */
    protected function prepareItemData($item)
    {
        $prepared = parent::prepareItemData($item);
        
        if (!empty($prepared['UF_ADDRESS'])) {
            $prepared['UF_ADDRESS_FORMATTED'] = $this->formatAddress($prepared['UF_ADDRESS']);
        }
        
        return $prepared;
    }

    /**
     * Форматирование адреса
     */
    private function formatAddress($address)
    {
        return trim($address);
    }
}
