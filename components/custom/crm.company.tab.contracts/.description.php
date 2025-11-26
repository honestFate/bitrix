<?php
// /local/components/custom/crm.company.tab.contracts/.description.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME' => 'Договоры компании',
    'DESCRIPTION' => 'Вкладка с договорами для карточки компании CRM',
    'ICON' => '/images/icon.gif',
    'SORT' => 30,
    'PATH' => [
        'ID' => 'crm',
        'NAME' => 'CRM',
        'CHILD' => [
            'ID' => 'crm_tabs',
            'NAME' => 'Вкладки CRM',
        ],
    ],
];