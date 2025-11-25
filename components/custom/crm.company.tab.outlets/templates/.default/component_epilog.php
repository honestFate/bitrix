<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * component_epilog.php
 * Подключает стили и скрипты из родительского компонента
 * и инициализирует JavaScript после их загрузки
 */

use Bitrix\Main\Page\Asset;

$asset = Asset::getInstance();

// Путь к родительскому шаблону
$parentTemplatePath = '/local/components/custom/crm.company.tab.base/templates/.default';

// Подключаем CSS родительского компонента
$asset->addCss($parentTemplatePath . '/style.css');

// Подключаем JS родительского компонента
$asset->addJs($parentTemplatePath . '/script.js');

// Inline скрипт для инициализации компонента
// Выполнится после загрузки всех скриптов
?>
<script>
(function() {
    'use strict';
    
    // Функция инициализации
    function initCrmHlTab() {
        var tabCode = '<?= CUtil::JSEscape($arResult['TAB_CODE']) ?>';
        var containerId = '#crm-hl-tab-' + tabCode;
        var container = document.querySelector(containerId);
        
        if (!container) {
            console.error('CrmHlTab: Container not found -', containerId);
            return;
        }
        
        // Проверяем наличие BX.CrmHlTab
        if (typeof BX === 'undefined' || typeof BX.CrmHlTab === 'undefined') {
            console.error('BX.CrmHlTab not loaded!');
            console.log('Trying to load from container data attributes...');
            
            // Попробуем через небольшую задержку
            setTimeout(initCrmHlTab, 100);
            return;
        }
        
        // Получаем данные из data-атрибутов контейнера
        var config = {
            containerId: containerId,
            ajaxPath: container.dataset.ajaxPath || '<?= CUtil::JSEscape($arResult['AJAX_PATH']) ?>',
            companyId: parseInt(container.dataset.companyId) || <?= intval($arResult['COMPANY_ID']) ?>,
            hlBlockId: parseInt(container.dataset.hlBlockId) || <?= intval($arResult['HL_BLOCK_ID']) ?>,
            tabCode: tabCode,
            permissions: <?= CUtil::PhpToJSObject($arResult['PERMISSIONS']) ?>
        };
        
        console.log('Initializing CrmHlTab with config:', config);
        
        // Инициализируем компонент
        BX.CrmHlTab.init(config);
    }
    
    // Запускаем инициализацию когда BX готов
    if (typeof BX !== 'undefined' && BX.ready) {
        BX.ready(initCrmHlTab);
    } else {
        // Если BX еще не загрузился, ждем событие DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCrmHlTab);
        } else {
            // DOM уже загружен, запускаем сразу
            initCrmHlTab();
        }
    }
})();
</script>