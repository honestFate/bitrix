<?php
// /local/components/custom/crm.company.tab.contracts/templates/.default/template.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
$tabCode = htmlspecialcharsbx($arResult['TAB_CODE']);
$companyId = intval($arResult['COMPANY_ID']);
?>

<div class="crm-contracts-tab" id="crm-contracts-<?= $tabCode ?>">
    
    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="crm-contracts-error">
            <span class="crm-contracts-error-icon">⚠</span>
            <span><?= htmlspecialcharsbx($arResult['ERROR']) ?></span>
        </div>
    <?php else: ?>
    
        <div class="crm-contracts-header">
            <h3 class="crm-contracts-title">Договоры</h3>
            <span class="crm-contracts-count"><?= count($arResult['ITEMS']) ?></span>
        </div>
        
        <?php if (empty($arResult['ITEMS'])): ?>
            <div class="crm-contracts-empty">
                <div class="crm-contracts-empty-icon">📄</div>
                <div class="crm-contracts-empty-text">Нет договоров</div>
                <div class="crm-contracts-empty-hint">Договоры добавляются через 1С</div>
            </div>
        <?php else: ?>
            <div class="crm-contracts-list">
                <?php foreach ($arResult['ITEMS'] as $item): ?>
                    <div class="crm-contract-card <?= $item['STATUS']['class'] ?>">
                        <div class="crm-contract-header">
                            <div class="crm-contract-name"><?= $item['UF_NAME'] ?></div>
                            <div class="crm-contract-status <?= $item['STATUS']['class'] ?>">
                                <?= $item['STATUS']['text'] ?>
                            </div>
                        </div>
                        
                        <div class="crm-contract-body">
                            <div class="crm-contract-row">
                                <div class="crm-contract-field">
                                    <span class="crm-contract-label">Кредитный лимит</span>
                                    <span class="crm-contract-value crm-contract-money">
                                        <?= $item['UF_CREDIT_LIMIT_FORMATTED'] ?>
                                    </span>
                                </div>
                                <div class="crm-contract-field">
                                    <span class="crm-contract-label">Отсрочка платежа</span>
                                    <span class="crm-contract-value">
                                        <?= $item['UF_PAYMENT_DELAY_TEXT'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="crm-contract-row">
                                <div class="crm-contract-field">
                                    <span class="crm-contract-label">Период действия</span>
                                    <span class="crm-contract-value">
                                        <?php if ($item['UF_DATE_START'] || $item['UF_DATE_END']): ?>
                                            <?= $item['UF_DATE_START'] ?: '—' ?> 
                                            → 
                                            <?= $item['UF_DATE_END'] ?: 'бессрочно' ?>
                                        <?php else: ?>
                                            <span class="crm-contract-empty-value">Не указан</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($item['UF_CONTRACT_FILE']): ?>
                                    <div class="crm-contract-field">
                                        <span class="crm-contract-label">Документ</span>
                                        <a href="<?= $item['UF_CONTRACT_FILE']['SRC'] ?>" 
                                           class="crm-contract-file" 
                                           target="_blank"
                                           title="<?= htmlspecialcharsbx($item['UF_CONTRACT_FILE']['NAME']) ?>">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M14 10V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M4.66669 6.66667L8.00002 10L11.3334 6.66667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M8 10V2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?= htmlspecialcharsbx($item['UF_CONTRACT_FILE']['NAME']) ?></span>
                                            <small>(<?= $item['UF_CONTRACT_FILE']['SIZE'] ?>)</small>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="crm-contract-footer">
                            <span class="crm-contract-id">ID: <?= $item['ID'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>