<?php
// /local/components/custom/crm.company.tab.contracts/class.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$composerAutoload = $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

use Bitrix\Main\Localization\Loc;
use Brick\Money\Money;
use Brick\Money\Context\CustomContext;

Loc::loadMessages(__FILE__);

// Подключаем родительский класс
$parentClassPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/class.php';
if (!class_exists('CrmCompanyTabBase') && file_exists($parentClassPath)) {
    require_once $parentClassPath;
}

/**
 * Компонент "Договоры" для карточки компании (только просмотр)
 */
class CrmCompanyTabContracts extends CrmCompanyTabBase
{
    public function __construct($component = null)
    {
        parent::__construct($component);
        
        $this->hlBlockId = 6;
        $this->tabCode = 'tab_contracts';
        $this->tabName = 'Договоры';
        
        // Конфигурация полей
        $this->fieldsConfig = [
            'UF_ACTIVE' => [
                'CODE' => 'UF_ACTIVE',
                'NAME' => 'Статус',
                'TYPE' => 'boolean',
                'REQUIRED' => false,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_NAME' => [
                'CODE' => 'UF_NAME',
                'NAME' => 'Название',
                'TYPE' => 'string',
                'REQUIRED' => true,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_CREDIT_LIMIT' => [
                'CODE' => 'UF_CREDIT_LIMIT',
                'NAME' => 'Кредитный лимит',
                'TYPE' => 'money',
                'REQUIRED' => true,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_PAYMENT_DELAY' => [
                'CODE' => 'UF_PAYMENT_DELAY',
                'NAME' => 'Отсрочка',
                'TYPE' => 'integer',
                'REQUIRED' => true,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_DATE_START' => [
                'CODE' => 'UF_DATE_START',
                'NAME' => 'Начало',
                'TYPE' => 'date',
                'REQUIRED' => false,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_DATE_END' => [
                'CODE' => 'UF_DATE_END',
                'NAME' => 'Окончание',
                'TYPE' => 'date',
                'REQUIRED' => false,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_CONTRACT_FILE' => [
                'CODE' => 'UF_CONTRACT_FILE',
                'NAME' => 'Файл',
                'TYPE' => 'file',
                'REQUIRED' => false,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
            'UF_DEBT' => [
                'CODE' => 'UF_DEBT',
                'NAME' => 'Задолженность',
                'TYPE' => 'money_minor',
                'REQUIRED' => false,
                'EDITABLE' => false,
                'MULTIPLE' => false,
            ],
        ];
        
        // ТОЛЬКО ПРОСМОТР - без редактирования
        $this->permissions = [
            'canRead' => true,
            'canEdit' => false,
            'canAdd' => false,
            'canDelete' => false
        ];
    }

    /**
     * Подготовка данных элемента
     */
    protected function prepareItemData($item)
    {
        $prepared = [
            'ID' => $item['ID'],
        ];
        
        // Активность
        $prepared['UF_ACTIVE'] = !empty($item['UF_ACTIVE']);
        $prepared['UF_ACTIVE_TEXT'] = $prepared['UF_ACTIVE'] ? 'Активен' : 'Неактивен';
        
        // Название
        $prepared['UF_NAME'] = htmlspecialcharsbx($item['UF_NAME'] ?? '');
        
        // Кредитный лимит (целое число)
        $limit = intval($item['UF_CREDIT_LIMIT'] ?? 0);
        $prepared['UF_CREDIT_LIMIT'] = $limit;
        $prepared['UF_CREDIT_LIMIT_FORMATTED'] = number_format($limit, 0, '', ' ') . ' ₽';
        
        // Отсрочка
        $delay = intval($item['UF_PAYMENT_DELAY'] ?? 0);
        $prepared['UF_PAYMENT_DELAY'] = $delay;
        $prepared['UF_PAYMENT_DELAY_TEXT'] = $delay . ' ' . $this->pluralize($delay, ['день', 'дня', 'дней']);
        
        // Даты
        $prepared['UF_DATE_START'] = $this->formatDate($item['UF_DATE_START'] ?? null);
        $prepared['UF_DATE_END'] = $this->formatDate($item['UF_DATE_END'] ?? null);
        
        // Статус срока действия
        $prepared['STATUS'] = $this->getContractStatus($item);
        
        // Файл договора
        $prepared['UF_CONTRACT_FILE'] = null;
        if (!empty($item['UF_CONTRACT_FILE'])) {
            $fileArray = \CFile::GetFileArray($item['UF_CONTRACT_FILE']);
            if ($fileArray) {
                $prepared['UF_CONTRACT_FILE'] = [
                    'ID' => $item['UF_CONTRACT_FILE'],
                    'NAME' => $fileArray['ORIGINAL_NAME'] ?: $fileArray['FILE_NAME'],
                    'SIZE' => \CFile::FormatSize($fileArray['FILE_SIZE']),
                    'SRC' => $fileArray['SRC'],
                ];
            }
        }

        $deptKopecks = intval($item['UF_DEBT'] ?? 0);
        $prepared['UF_DEBT'] = $deptKopecks;
        $prepared['UF_DEBT_FORMATTED'] = '';
        $prepared['UF_DEBT_TYPE'] = 'zero'; // zero, debit (должны нам), credit (мы должны)

        if ($deptKopecks !== 0) {
            try {
                // Конвертируем копейки в рубли (делим на 100)
                $money = Money::ofMinor($deptKopecks, 'RUB');
                
                // Форматируем
                $amount = $money->getAmount()->toFloat();
                $absAmount = abs($amount);
                
                $prepared['UF_DEBT_FORMATTED'] = number_format($absAmount, 2, ',', ' ') . ' ₽';
                
                if ($deptKopecks > 0) {
                    $prepared['UF_DEBT_TYPE'] = 'debit'; // Дебиторская (нам должны)
                } else {
                    $prepared['UF_DEBT_TYPE'] = 'credit'; // Кредиторская (мы должны)
                }
            } catch (\Exception $e) {
                AddMessage2Log("Error formatting debt: " . $e->getMessage(), 'crm_tabs');
                $prepared['UF_DEBT_FORMATTED'] = number_format(abs($deptKopecks / 100), 2, ',', ' ') . ' ₽';
            }
        }
        
        return $prepared;
    }

    /**
     * Форматирование даты
     */
    private function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }
        
        if ($date instanceof \Bitrix\Main\Type\Date) {
            return $date->format('d.m.Y');
        }
        
        if (is_string($date)) {
            $timestamp = strtotime($date);
            return $timestamp ? date('d.m.Y', $timestamp) : '';
        }
        
        return '';
    }

    /**
     * Определение статуса договора
     */
    private function getContractStatus($item)
    {
        if (empty($item['UF_ACTIVE'])) {
            return ['code' => 'inactive', 'text' => 'Неактивен', 'class' => 'status-inactive'];
        }
        
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
                return ['code' => 'expired', 'text' => 'Истёк', 'class' => 'status-expired'];
            }
            
            if ($daysLeft <= 30) {
                $days = ceil($daysLeft);
                return [
                    'code' => 'expiring', 
                    'text' => 'Истекает через ' . $days . ' ' . $this->pluralize($days, ['день', 'дня', 'дней']),
                    'class' => 'status-expiring'
                ];
            }
        }
        
        return ['code' => 'active', 'text' => 'Активен', 'class' => 'status-active'];
    }

    /**
     * Склонение слов
     */
    private function pluralize($number, $forms)
    {
        $number = abs($number) % 100;
        $n1 = $number % 10;
        
        if ($number > 10 && $number < 20) {
            return $forms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $forms[1];
        }
        if ($n1 == 1) {
            return $forms[0];
        }
        
        return $forms[2];
    }
}