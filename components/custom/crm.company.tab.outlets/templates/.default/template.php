<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

/** @var array $arResult */
/** @var array $arParams */
/** @var CMain $APPLICATION */

Loc::loadMessages(__FILE__);
?>

<div class="crm-hl-tab-container" data-tab-code="<?= htmlspecialcharsbx($arResult['TAB_CODE']) ?>" 
     data-company-id="<?= intval($arResult['COMPANY_ID']) ?>"
     data-hl-block-id="<?= intval($arResult['HL_BLOCK_ID']) ?>">

    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="crm-hl-tab-error">
            <?= htmlspecialcharsbx($arResult['ERROR']) ?>
        </div>
    <?php else: ?>

        <!-- Заголовок и кнопка добавления -->
        <div class="crm-hl-tab-header">
            <div class="crm-hl-tab-title">
                <?= Loc::getMessage('CRM_HL_TAB_TITLE') ?>
            </div>
            
            <?php if ($arResult['PERMISSIONS']['CAN_ADD']): ?>
                <button class="ui-btn ui-btn-success crm-hl-tab-add-btn" 
                        data-action="add">
                    <?= Loc::getMessage('CRM_HL_TAB_ADD_BUTTON') ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Таблица с данными -->
        <div class="crm-hl-tab-content">
            <?php if (empty($arResult['ITEMS'])): ?>
                <div class="crm-hl-tab-empty">
                    <?= Loc::getMessage('CRM_HL_TAB_NO_DATA') ?>
                </div>
            <?php else: ?>
                <table class="crm-hl-tab-table">
                    <thead>
                        <tr>
                            <th class="crm-hl-tab-th-id">ID</th>
                            <?php foreach ($arResult['FIELDS_CONFIG'] as $field): ?>
                                <?php if ($field['CODE'] !== 'UF_COMPANY_ID'): // Скрываем поле связи ?>
                                    <th class="crm-hl-tab-th">
                                        <?= htmlspecialcharsbx($field['NAME']) ?>
                                        <?php if ($field['REQUIRED']): ?>
                                            <span class="crm-hl-tab-required">*</span>
                                        <?php endif; ?>
                                    </th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if ($arResult['PERMISSIONS']['CAN_EDIT'] || $arResult['PERMISSIONS']['CAN_DELETE']): ?>
                                <th class="crm-hl-tab-th-actions"><?= Loc::getMessage('CRM_HL_TAB_ACTIONS') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arResult['ITEMS'] as $item): ?>
                            <tr class="crm-hl-tab-row" data-item-id="<?= intval($item['ID']) ?>">
                                <td class="crm-hl-tab-td-id"><?= intval($item['ID']) ?></td>
                                
                                <?php foreach ($arResult['FIELDS_CONFIG'] as $fieldCode => $field): ?>
                                    <?php if ($fieldCode !== 'UF_COMPANY_ID'): ?>
                                        <td class="crm-hl-tab-td" data-field="<?= htmlspecialcharsbx($fieldCode) ?>">
                                            <div class="crm-hl-tab-field-view">
                                                <?= htmlspecialcharsbx($item[$fieldCode] ?? '') ?>
                                            </div>
                                            
                                            <?php if ($arResult['PERMISSIONS']['CAN_EDIT'] && $field['EDITABLE']): ?>
                                                <div class="crm-hl-tab-field-edit" style="display: none;">
                                                    <input type="text" 
                                                           class="crm-hl-tab-input"
                                                           value="<?= htmlspecialcharsbx($item[$fieldCode] ?? '') ?>"
                                                           data-field-code="<?= htmlspecialcharsbx($fieldCode) ?>"
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
                                                <button class="crm-hl-tab-btn crm-hl-tab-btn-edit" 
                                                        data-action="edit"
                                                        title="<?= Loc::getMessage('CRM_HL_TAB_EDIT') ?>">
                                                    <span class="crm-hl-tab-icon-edit"></span>
                                                </button>
                                                <button class="crm-hl-tab-btn crm-hl-tab-btn-save" 
                                                        data-action="save"
                                                        style="display: none;"
                                                        title="<?= Loc::getMessage('CRM_HL_TAB_SAVE') ?>">
                                                    <span class="crm-hl-tab-icon-save"></span>
                                                </button>
                                                <button class="crm-hl-tab-btn crm-hl-tab-btn-cancel" 
                                                        data-action="cancel"
                                                        style="display: none;"
                                                        title="<?= Loc::getMessage('CRM_HL_TAB_CANCEL') ?>">
                                                    <span class="crm-hl-tab-icon-cancel"></span>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($arResult['PERMISSIONS']['CAN_DELETE']): ?>
                                                <button class="crm-hl-tab-btn crm-hl-tab-btn-delete" 
                                                        data-action="delete"
                                                        title="<?= Loc::getMessage('CRM_HL_TAB_DELETE') ?>">
                                                    <span class="crm-hl-tab-icon-delete"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Форма добавления нового элемента (скрыта по умолчанию) -->
        <?php if ($arResult['PERMISSIONS']['CAN_ADD']): ?>
            <div class="crm-hl-tab-add-form" style="display: none;">
                <div class="crm-hl-tab-form-title">
                    <?= Loc::getMessage('CRM_HL_TAB_ADD_NEW') ?>
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
                                       <?= $field['REQUIRED'] ? 'required' : '' ?>>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="crm-hl-tab-form-buttons">
                    <button class="ui-btn ui-btn-success crm-hl-tab-form-save">
                        <?= Loc::getMessage('CRM_HL_TAB_SAVE') ?>
                    </button>
                    <button class="ui-btn ui-btn-link crm-hl-tab-form-cancel">
                        <?= Loc::getMessage('CRM_HL_TAB_CANCEL') ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
BX.ready(function() {
    BX.CrmHlTab.init({
        containerId: '.crm-hl-tab-container',
        ajaxPath: '<?= $arResult['AJAX_PATH'] ?>',
        companyId: <?= intval($arResult['COMPANY_ID']) ?>,
        hlBlockId: <?= intval($arResult['HL_BLOCK_ID']) ?>,
        tabCode: '<?= CUtil::JSEscape($arResult['TAB_CODE']) ?>',
        permissions: <?= CUtil::PhpToJSObject($arResult['PERMISSIONS']) ?>
    });
});
</script>