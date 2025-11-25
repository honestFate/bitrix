<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

/** @var array $arResult */
/** @var array $arParams */
/** @var CMain $APPLICATION */

Loc::loadMessages(__FILE__);
?>

<div class="crm-hl-tab-container" 
     id="crm-hl-tab-<?= htmlspecialcharsbx($arResult['TAB_CODE']) ?>"
     data-tab-code="<?= htmlspecialcharsbx($arResult['TAB_CODE']) ?>" 
     data-company-id="<?= intval($arResult['COMPANY_ID']) ?>"
     data-hl-block-id="<?= intval($arResult['HL_BLOCK_ID']) ?>"
     data-ajax-path="<?= htmlspecialcharsbx($arResult['AJAX_PATH']) ?>"
     data-permissions='<?= CUtil::PhpToJSObject($arResult['PERMISSIONS']) ?>'>

    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="crm-hl-tab-error">
            <div class="crm-hl-tab-error-icon">⚠</div>
            <div class="crm-hl-tab-error-text">
                <?= htmlspecialcharsbx($arResult['ERROR']) ?>
            </div>
        </div>
    <?php else: ?>

        <!-- Заголовок и кнопка добавления -->
        <div class="crm-hl-tab-header">
            <div class="crm-hl-tab-header-left">
                <h2 class="crm-hl-tab-title">
                    <?= htmlspecialcharsbx($arResult['TAB_NAME'] ?? Loc::getMessage('CRM_HL_TAB_TITLE')) ?>
                </h2>
                <div class="crm-hl-tab-count">
                    Записей: <span class="crm-hl-tab-count-number"><?= count($arResult['ITEMS']) ?></span>
                </div>
            </div>
            
            <?php if ($arResult['PERMISSIONS']['CAN_ADD']): ?>
                <button class="ui-btn ui-btn-success crm-hl-tab-add-btn" 
                        data-action="add">
                    <span class="crm-hl-tab-btn-icon">+</span>
                    <?= Loc::getMessage('CRM_HL_TAB_ADD_BUTTON') ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Таблица с данными -->
        <div class="crm-hl-tab-content">
            <?php if (empty($arResult['ITEMS'])): ?>
                <div class="crm-hl-tab-empty">
                    <div class="crm-hl-tab-empty-icon">📋</div>
                    <div class="crm-hl-tab-empty-text">
                        <?= Loc::getMessage('CRM_HL_TAB_NO_DATA') ?>
                    </div>
                    <?php if ($arResult['PERMISSIONS']['CAN_ADD']): ?>
                        <button class="ui-btn ui-btn-primary crm-hl-tab-add-btn-empty" 
                                data-action="add">
                            <?= Loc::getMessage('CRM_HL_TAB_ADD_FIRST') ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="crm-hl-tab-table-wrapper">
                    <table class="crm-hl-tab-table">
                        <thead>
                            <tr>
                                <th class="crm-hl-tab-th-id">
                                    <div class="crm-hl-tab-th-content">ID</div>
                                </th>
                                <?php foreach ($arResult['FIELDS_CONFIG'] as $field): ?>
                                    <?php if ($field['CODE'] !== 'UF_COMPANY_ID'): ?>
                                        <th class="crm-hl-tab-th">
                                            <div class="crm-hl-tab-th-content">
                                                <?= htmlspecialcharsbx($field['NAME']) ?>
                                                <?php if ($field['REQUIRED']): ?>
                                                    <span class="crm-hl-tab-required">*</span>
                                                <?php endif; ?>
                                            </div>
                                        </th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <?php if ($arResult['PERMISSIONS']['CAN_EDIT'] || $arResult['PERMISSIONS']['CAN_DELETE']): ?>
                                    <th class="crm-hl-tab-th-actions">
                                        <div class="crm-hl-tab-th-content">
                                            <?= Loc::getMessage('CRM_HL_TAB_ACTIONS') ?>
                                        </div>
                                    </th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arResult['ITEMS'] as $item): ?>
                                <tr class="crm-hl-tab-row" data-item-id="<?= intval($item['ID']) ?>">
                                    <td class="crm-hl-tab-td-id">
                                        <span class="crm-hl-tab-id-badge"><?= intval($item['ID']) ?></span>
                                    </td>
                                    
                                    <?php foreach ($arResult['FIELDS_CONFIG'] as $fieldCode => $field): ?>
                                        <?php if ($fieldCode !== 'UF_COMPANY_ID'): ?>
                                            <td class="crm-hl-tab-td" data-field="<?= htmlspecialcharsbx($fieldCode) ?>">
                                                <div class="crm-hl-tab-field-view">
                                                    <?php if (!empty($item[$fieldCode])): ?>
                                                        <?= htmlspecialcharsbx($item[$fieldCode]) ?>
                                                    <?php else: ?>
                                                        <span class="crm-hl-tab-empty-value">—</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($arResult['PERMISSIONS']['CAN_EDIT'] && $field['EDITABLE']): ?>
                                                    <div class="crm-hl-tab-field-edit">
                                                        <input type="text" 
                                                               class="crm-hl-tab-input"
                                                               value="<?= htmlspecialcharsbx($item[$fieldCode] ?? '') ?>"
                                                               data-field-code="<?= htmlspecialcharsbx($fieldCode) ?>"
                                                               placeholder="<?= htmlspecialcharsbx($field['NAME']) ?>"
                                                               <?= $field['REQUIRED'] ? 'required' : '' ?>>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($arResult['PERMISSIONS']['CAN_EDIT'] || $arResult['PERMISSIONS']['CAN_DELETE']): ?>
                                        <td class="crm-hl-tab-td-actions">
                                            <div class="crm-hl-tab-actions">
                                                <?php if ($arResult['PERMISSIONS']['CAN_EDIT']): ?>
                                                    <!-- Режим просмотра -->
                                                    <button class="crm-hl-tab-btn crm-hl-tab-btn-edit" 
                                                            data-action="edit"
                                                            title="<?= Loc::getMessage('CRM_HL_TAB_EDIT') ?>">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <path d="M11.333 2.00004C11.5081 1.82494 11.716 1.68605 11.9447 1.59129C12.1735 1.49653 12.4187 1.44775 12.6663 1.44775C12.914 1.44775 13.1592 1.49653 13.3879 1.59129C13.6167 1.68605 13.8246 1.82494 13.9997 2.00004C14.1748 2.17513 14.3137 2.383 14.4084 2.61178C14.5032 2.84055 14.552 3.08575 14.552 3.33337C14.552 3.58099 14.5032 3.82619 14.4084 4.05497C14.3137 4.28374 14.1748 4.49161 13.9997 4.66671L5.33301 13.3334L1.99967 14.3334L2.99967 11L11.333 2.00004Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                    
                                                    <!-- Режим редактирования -->
                                                    <button class="crm-hl-tab-btn crm-hl-tab-btn-save" 
                                                            data-action="save"
                                                            title="<?= Loc::getMessage('CRM_HL_TAB_SAVE') ?>">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <path d="M13.3333 4L6 11.3333L2.66667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                    <button class="crm-hl-tab-btn crm-hl-tab-btn-cancel" 
                                                            data-action="cancel"
                                                            title="<?= Loc::getMessage('CRM_HL_TAB_CANCEL') ?>">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($arResult['PERMISSIONS']['CAN_DELETE']): ?>
                                                    <button class="crm-hl-tab-btn crm-hl-tab-btn-delete" 
                                                            data-action="delete"
                                                            title="<?= Loc::getMessage('CRM_HL_TAB_DELETE') ?>">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <path d="M2 4H3.33333H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                            <path d="M5.33301 4.00004V2.66671C5.33301 2.31309 5.47348 1.97395 5.72353 1.7239C5.97358 1.47385 6.31272 1.33337 6.66634 1.33337H9.33301C9.68663 1.33337 10.0258 1.47385 10.2758 1.7239C10.5259 1.97395 10.6663 2.31309 10.6663 2.66671V4.00004M12.6663 4.00004V13.3334C12.6663 13.687 12.5259 14.0261 12.2758 14.2762C12.0258 14.5262 11.6866 14.6667 11.333 14.6667H4.66634C4.31272 14.6667 3.97358 14.5262 3.72353 14.2762C3.47348 14.0261 3.33301 13.687 3.33301 13.3334V4.00004H12.6663Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Форма добавления нового элемента -->
        <?php if ($arResult['PERMISSIONS']['CAN_ADD']): ?>
            <div class="crm-hl-tab-add-form" id="crm-hl-tab-add-form-<?= htmlspecialcharsbx($arResult['TAB_CODE']) ?>">
                <div class="crm-hl-tab-form-header">
                    <h3 class="crm-hl-tab-form-title">
                        <?= Loc::getMessage('CRM_HL_TAB_ADD_NEW') ?>
                    </h3>
                    <button class="crm-hl-tab-form-close" data-action="close-form" title="Закрыть">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                
                <div class="crm-hl-tab-form-fields">
                    <?php foreach ($arResult['FIELDS_CONFIG'] as $fieldCode => $field): ?>
                        <?php if ($fieldCode !== 'UF_COMPANY_ID' && $field['EDITABLE']): ?>
                            <div class="crm-hl-tab-form-field">
                                <label class="crm-hl-tab-form-label">
                                    <?= htmlspecialcharsbx($field['NAME']) ?>
                                    <?php if ($field['REQUIRED']): ?>
                                        <span class="crm-hl-tab-required">*</span>
                                    <?php endif; ?>
                                </label>
                                <input type="text" 
                                       class="crm-hl-tab-form-input"
                                       data-field-code="<?= htmlspecialcharsbx($fieldCode) ?>"
                                       placeholder="Введите <?= htmlspecialcharsbx(mb_strtolower($field['NAME'])) ?>"
                                       <?= $field['REQUIRED'] ? 'required' : '' ?>>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="crm-hl-tab-form-buttons">
                    <button class="ui-btn ui-btn-success crm-hl-tab-form-save">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;">
                            <path d="M13.3333 4L6 11.3333L2.66667 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?= Loc::getMessage('CRM_HL_TAB_SAVE') ?>
                    </button>
                    <button class="ui-btn ui-btn-light crm-hl-tab-form-cancel">
                        <?= Loc::getMessage('CRM_HL_TAB_CANCEL') ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>