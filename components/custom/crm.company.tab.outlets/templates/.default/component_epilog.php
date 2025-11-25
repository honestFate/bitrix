<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * component_epilog.php
 * Подключает стили и скрипты из родительского компонента
 * и инициализирует JavaScript после их загрузки
 */

use Bitrix\Main\Page\Asset;

$APPLICATION->ShowHead();

$asset = Asset::getInstance();

// Путь к родительскому шаблону
$parentTemplatePath = '/local/components/custom/crm.company.tab.base/templates/.default';

// Подключаем CSS родительского компонента
$asset->addCss($parentTemplatePath . '/style.css');

// Подключаем JS родительского компонента
// ВАЖНО: addJs добавляет скрипт в конец страницы, но выполнение может быть отложено
$asset->addJs($parentTemplatePath . '/script.js');

// Генерируем уникальный ID для конфигурации
$configId = 'crmHlTabConfig_' . md5($arResult['TAB_CODE'] . '_' . $arResult['COMPANY_ID']);
?>

<!-- Сохраняем конфигурацию в глобальную переменную -->
<script>
window.<?= $configId ?> = <?= CUtil::PhpToJSObject([
    'containerId' => '#crm-hl-tab-' . $arResult['TAB_CODE'],
    'ajaxPath' => $arResult['AJAX_PATH'],
    'companyId' => $arResult['COMPANY_ID'],
    'hlBlockId' => $arResult['HL_BLOCK_ID'],
    'tabCode' => $arResult['TAB_CODE'],
    'permissions' => $arResult['PERMISSIONS']
]) ?>;
</script>

<!-- Инициализация после полной загрузки страницы -->
<script>
(function() {
    'use strict';
    
    var configId = '<?= $configId ?>';
    var maxAttempts = 50;
    var attemptCount = 0;
    var initInterval = null;
    
    function tryInit() {
        attemptCount++;
        
        // Проверяем наличие BX и BX.CrmHlTab
        if (typeof BX === 'undefined' || typeof BX.CrmHlTab === 'undefined') {
            if (attemptCount < maxAttempts) {
                console.log('[CrmHlTab] Waiting for BX.CrmHlTab... attempt ' + attemptCount + '/' + maxAttempts);
                return false;
            } else {
                console.error('[CrmHlTab] Failed to load after ' + maxAttempts + ' attempts');
                console.error('[CrmHlTab] Check if script.js is loaded: <?= $parentTemplatePath ?>/script.js');
                if (initInterval) {
                    clearInterval(initInterval);
                }
                return true; // Останавливаем попытки
            }
        }
        
        // Проверяем наличие конфигурации
        if (typeof window[configId] === 'undefined') {
            console.error('[CrmHlTab] Configuration not found:', configId);
            if (initInterval) {
                clearInterval(initInterval);
            }
            return true;
        }
        
        // Проверяем наличие контейнера
        var container = document.querySelector(window[configId].containerId);
        if (!container) {
            console.error('[CrmHlTab] Container not found:', window[configId].containerId);
            if (initInterval) {
                clearInterval(initInterval);
            }
            return true;
        }
        
        // Всё готово - инициализируем
        console.log('[CrmHlTab] Initializing with config:', window[configId]);
        
        try {
            BX.CrmHlTab.init(window[configId]);
            console.log('[CrmHlTab] Successfully initialized (attempt ' + attemptCount + ')');
            
            // Очищаем интервал
            if (initInterval) {
                clearInterval(initInterval);
            }
            
            return true; // Успешная инициализация
        } catch (e) {
            console.error('[CrmHlTab] Initialization error:', e);
            if (initInterval) {
                clearInterval(initInterval);
            }
            return true;
        }
    }
    
    // Запускаем проверку через интервал
    function startInit() {
        // Немедленная попытка
        if (tryInit()) {
            return;
        }
        
        // Если не получилось - запускаем интервал
        initInterval = setInterval(function() {
            if (tryInit()) {
                clearInterval(initInterval);
            }
        }, 100);
    }
    
    // Ждем полной загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startInit);
    } else if (document.readyState === 'interactive') {
        // DOM готов, но скрипты могут еще загружаться
        setTimeout(startInit, 100);
    } else {
        // Страница полностью загружена
        startInit();
    }
})();
</script>