<?php
// /local/components/custom/crm.company.tab.contracts/templates/.default/template.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Подключаем родительский шаблон
$parentTemplatePath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base/templates/.default/template.php';

if (file_exists($parentTemplatePath)) {
    include $parentTemplatePath;
} else {
    echo '<div style="padding: 20px; background: #ffebee; color: #c62828; border-radius: 4px;">';
    echo '<strong>Ошибка:</strong> Не найден родительский шаблон';
    echo '</div>';
}