<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Этот шаблон является прокладкой для подключения родительского шаблона.
 * Дочерний компонент использует шаблон родительского компонента.
 */

// Путь к родительскому компоненту
$parentComponentPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/custom/crm.company.tab.base';
$parentTemplatePath = $parentComponentPath . '/templates/.default/template.php';

// Проверяем существование родительского шаблона
if (file_exists($parentTemplatePath)) {
    // Подключаем родительский шаблон
    include $parentTemplatePath;
} else {
    // Если родительский шаблон не найден - показываем ошибку
    echo '<div style="padding: 20px; background: #ffebee; color: #c62828; border-radius: 4px;">';
    echo '<strong>Ошибка:</strong> Не найден родительский шаблон по пути: ' . htmlspecialchars($parentTemplatePath);
    echo '</div>';
}