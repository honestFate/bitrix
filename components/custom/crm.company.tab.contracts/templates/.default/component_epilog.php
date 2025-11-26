<?php
// /local/components/custom/crm.company.tab.contracts/templates/.default/component_epilog.php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;

$APPLICATION->ShowCSS(true, true);

$asset = Asset::getInstance();
$parentTemplatePath = '/local/components/custom/crm.company.tab.base/templates/.default';

$asset->addCss($parentTemplatePath . '/style.css');
