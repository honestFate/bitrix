<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Точка входа для компонента "Торговые точки"
 * Этот файл загружает класс компонента и запускает его выполнение
 */

// Подключаем класс компонента
require_once __DIR__ . '/class.php';

// Создаём экземпляр компонента и запускаем его
$component = new CrmCompanyTabOutlets($this);
$component->executeComponent();
