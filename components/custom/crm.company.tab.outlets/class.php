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
                'EDITABLE' => false, // Не редактируем, т.к. это связь с текущей компанией
                'MULTIPLE' => false,
            ],
        ];
        
        // Настройка прав доступа (переопределяем базовые)
        $this->permissions = [
            'canRead' => true,
            'canEdit' => true,
            'canDelete' => true
        ];
    }

    /**
     * Переопределение метода подготовки данных элемента
     * Добавляем специфичную для торговых точек обработку
     */
    protected function prepareItemData($item)
    {
        $prepared = parent::prepareItemData($item);
        
        // Дополнительная обработка адреса
        if (!empty($prepared['UF_ADDRESS'])) {
            // Можно добавить форматирование адреса, геокодирование и т.д.
            $prepared['UF_ADDRESS_FORMATTED'] = $this->formatAddress($prepared['UF_ADDRESS']);
        }
        
        return $prepared;
    }

    /**
     * Форматирование адреса
     */
    private function formatAddress($address)
    {
        // Простое форматирование - можно расширить
        return trim($address);
    }

    /**
     * Дополнительная валидация для торговых точек
     */
    protected function validateOutletData($data)
    {
        $errors = [];
        
        if (empty($data['UF_ADDRESS'])) {
            $errors[] = 'Адрес торговой точки не может быть пустым';
        }
        
        if (mb_strlen($data['UF_ADDRESS']) < 5) {
            $errors[] = 'Адрес слишком короткий. Минимум 5 символов';
        }
        
        if (mb_strlen($data['UF_ADDRESS']) > 255) {
            $errors[] = 'Адрес слишком длинный. Максимум 255 символов';
        }
        
        return $errors;
    }
    
    /**
     * Переопределяем метод выполнения компонента
     * Добавляем название вкладки в результат
     */
    public function executeComponent()
    {
        // Вызываем родительский метод
        parent::executeComponent();
        
        // Добавляем название вкладки
        $this->arResult['TAB_NAME'] = $this->tabName;
    }
}