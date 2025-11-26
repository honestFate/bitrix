<?php
// /local/components/custom/crm.company.tab.contracts/templates/.default/component_epilog.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;

$asset = Asset::getInstance();
$parentTemplatePath = '/local/components/custom/crm.company.tab.base/templates/.default';

$asset->addCss($parentTemplatePath . '/style.css');
$asset->addJs($parentTemplatePath . '/script.js');

// Дополнительные стили для договоров
$asset->addString('<style>
.crm-contract-status-active { color: #52c41a; }
.crm-contract-status-inactive { color: #8c8c8c; }
.crm-contract-status-expired { color: #ff4d4f; }
.crm-contract-status-expiring { color: #faad14; }
.crm-contract-file-link { 
    display: inline-flex; 
    align-items: center; 
    gap: 6px;
    color: #1890ff;
    text-decoration: none;
}
.crm-contract-file-link:hover { text-decoration: underline; }
.crm-contract-limit { font-weight: 600; color: #262626; }
</style>', true, \Bitrix\Main\Page\AssetLocation::AFTER_CSS);

$configId = 'crmHlTabConfig_' . md5($arResult['TAB_CODE'] . '_' . $arResult['COMPANY_ID']);
?>

<script>
window.<?= $configId ?> = <?= CUtil::PhpToJSObject([
    'containerId' => '#crm-hl-tab-' . $arResult['TAB_CODE'],
    'ajaxPath' => $arResult['AJAX_PATH'],
    'companyId' => $arResult['COMPANY_ID'],
    'hlBlockId' => $arResult['HL_BLOCK_ID'],
    'tabCode' => $arResult['TAB_CODE'],
    'permissions' => $arResult['PERMISSIONS']
]) ?>;

(function() {
    'use strict';
    
    var configId = '<?= $configId ?>';
    var maxAttempts = 50;
    var attemptCount = 0;
    
    function tryInit() {
        attemptCount++;
        
        if (typeof BX === 'undefined' || typeof BX.CrmHlTab === 'undefined') {
            if (attemptCount < maxAttempts) {
                setTimeout(tryInit, 100);
                return;
            }
            console.error('[CrmHlTab] Failed to load');
            return;
        }
        
        if (typeof window[configId] === 'undefined') {
            console.error('[CrmHlTab] Config not found');
            return;
        }
        
        BX.CrmHlTab.init(window[configId]);
        console.log('[CrmHlTab] Contracts tab initialized');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        setTimeout(tryInit, 100);
    }
})();
</script>