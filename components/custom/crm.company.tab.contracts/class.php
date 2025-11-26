<?php
// /local/components/custom/crm.company.tab.contracts/class.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Подключаем родительский класс
$parentClassPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/class.php';
if (!class_exists('CrmCompanyTabBase') && file_exists($parentClassPath)) {
    require_once $parentClassPath;
}

/**
 * Компонент "Договоры" для карточки компании
 */
class CrmCompanyTabContracts extends CrmCompanyTabBase
{
    public function __construct($component = null)
    {
        parent::__construct($component);
        
        // ID Highload-блока "Договоры"
        $this->hlBlockId = 6;
        
        // Код вкладки
        $this->tabCode = 'tab_contracts';
        
        // Название вкладки
        $this->tabName = 'Договоры';
        
        // Конфигурация полей для отображения
        $this->fieldsConfig = [
            'UF_ACTIVE' => [
                'CODE' => 'UF_ACTIVE',
                'NAME' => 'Активен',
                'TYPE' => 'boolean',
                'REQUIRED' => false,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_NAME' => [
                'CODE' => 'UF_NAME',
                'NAME' => 'Название договора',
                'TYPE' => 'string',
                'REQUIRED' => true,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_CREDIT_LIMIT' => [
                'CODE' => 'UF_CREDIT_LIMIT',
                'NAME' => 'Кредитный лимит',
                'TYPE' => 'money',
                'REQUIRED' => true,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_PAYMENT_DELAY' => [
                'CODE' => 'UF_PAYMENT_DELAY',
                'NAME' => 'Отсрочка (дней)',
                'TYPE' => 'integer',
                'REQUIRED' => true,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_DATE_START' => [
                'CODE' => 'UF_DATE_START',
                'NAME' => 'Дата начала',
                'TYPE' => 'date',
                'REQUIRED' => false,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_DATE_END' => [
                'CODE' => 'UF_DATE_END',
                'NAME' => 'Дата окончания',
                'TYPE' => 'date',
                'REQUIRED' => false,
                'EDITABLE' => true,
                'MULTIPLE' => false,
            ],
            'UF_CONTRACT_FILE' => [
                'CODE' => 'UF_CONTRACT_FILE',
                'NAME' => 'Файл договора',
                'TYPE' => 'file',
                'REQUIRED' => false,
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
        
        // Форматирование кредитного лимита
        if (isset($item['UF_CREDIT_LIMIT'])) {
            $prepared['UF_CREDIT_LIMIT'] = $item['UF_CREDIT_LIMIT'];
            $prepared['UF_CREDIT_LIMIT_FORMATTED'] = number_format(
                (float)$item['UF_CREDIT_LIMIT'], 
                2, 
                ',', 
                ' '
            ) . ' ₽';
        }
        
        // Форматирование дат
        if (!empty($item['UF_DATE_START'])) {
            $prepared['UF_DATE_START_FORMATTED'] = $this->formatDate($item['UF_DATE_START']);
        }
        if (!empty($item['UF_DATE_END'])) {
            $prepared['UF_DATE_END_FORMATTED'] = $this->formatDate($item['UF_DATE_END']);
        }
        
        // Статус договора
        $prepared['STATUS'] = $this->getContractStatus($item);
        $prepared['STATUS_CLASS'] = $this->getStatusClass($prepared['STATUS']);
        
        // Обработка файла
        if (!empty($item['UF_CONTRACT_FILE'])) {
            $fileId = $item['UF_CONTRACT_FILE'];
            $fileArray = \CFile::GetFileArray($fileId);
            if ($fileArray) {
                $prepared['UF_CONTRACT_FILE_DATA'] = [
                    'ID' => $fileId,
                    'NAME' => $fileArray['ORIGINAL_NAME'] ?: $fileArray['FILE_NAME'],
                    'SIZE' => \CFile::FormatSize($fileArray['FILE_SIZE']),
                    'SRC' => $fileArray['SRC'],
                ];
            }
        }
        
        // Активность как текст
        $prepared['UF_ACTIVE_TEXT'] = $item['UF_ACTIVE'] ? 'Да' : 'Нет';
        
        return $prepared;
    }

    /**
     * Форматирование даты
     */
    private function formatDate($date)
    {
        if ($date instanceof \Bitrix\Main\Type\Date) {
            return $date->format('d.m.Y');
        }
        if (is_string($date) && !empty($date)) {
            return date('d.m.Y', strtotime($date));
        }
        return '';
    }

    /**
     * Определение статуса договора
     */
    private function getContractStatus($item)
    {
        // Неактивен
        if (empty($item['UF_ACTIVE']) || $item['UF_ACTIVE'] == 0) {
            return 'inactive';
        }
        
        // Проверка срока действия
        if (!empty($item['UF_DATE_END'])) {
            $endDate = $item['UF_DATE_END'];
            if ($endDate instanceof \Bitrix\Main\Type\Date) {
                $endTimestamp = $endDate->getTimestamp();
            } else {
                $endTimestamp = strtotime($endDate);
            }
            
            $now = time();
            $daysLeft = ($endTimestamp - $now) / 86400;
            
            if ($daysLeft < 0) {
                return 'expired';
            }
            if ($daysLeft <= 30) {
                return 'expiring';
            }
        }
        
        return 'active';
    }

    /**
     * CSS-класс для статуса
     */
    private function getStatusClass($status)
    {
        $classes = [
            'active' => 'crm-contract-status-active',
            'inactive' => 'crm-contract-status-inactive',
            'expired' => 'crm-contract-status-expired',
            'expiring' => 'crm-contract-status-expiring',
        ];
        
        return $classes[$status] ?? '';
    }
}