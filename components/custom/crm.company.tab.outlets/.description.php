<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_NAME'),
    'DESCRIPTION' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_DESC'),
    'ICON' => '/images/icon.gif',
    'SORT' => 20,
    'PATH' => [
        'ID' => 'crm',
        'NAME' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_PATH_NAME'),
        'CHILD' => [
            'ID' => 'crm_tabs',
            'NAME' => Loc::getMessage('CRM_COMPANY_TAB_OUTLETS_PATH_CHILD_NAME'),
        ],
    ],
];