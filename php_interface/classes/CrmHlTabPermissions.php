<?php
/**
 * Класс управления правами доступа к вкладкам CRM с Highload-блоками
 */
class CrmHlTabPermissions
{
    /**
     * Конфигурация прав доступа
     * Структура:
     * [
     *     'tab_code' => [
     *         'user_groups' => [1, 5], // ID групп с доступом
     *         'actions' => ['READ', 'WRITE', 'DELETE'],
     *         'blocks' => [ // Для вкладок с несколькими блоками
     *             'hl_block_5' => ['READ', 'WRITE'],
     *             'info_block_10' => ['READ']
     *         ]
     *     ]
     * ]
     */
    private static $config = [];
    
    /**
     * Загрузка конфигурации из файла
     */
    private static function loadConfig()
    {
        if (!empty(self::$config)) {
            return;
        }
        
        $configFile = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/crm_tab_permissions.php';
        
        if (file_exists($configFile)) {
            self::$config = include $configFile;
        }
    }
    
    /**
     * Проверка доступа пользователя к вкладке
     * 
     * @param int $userId ID пользователя
     * @param string $tabCode Код вкладки
     * @param string $action Действие (READ, WRITE, DELETE)
     * @return bool
     */
    public static function checkAccess($userId, $tabCode, $action = 'READ')
    {
        self::loadConfig();
        
        // Если конфигурация пуста - доступ по умолчанию
        if (empty(self::$config)) {
            return true;
        }
        
        // Если для вкладки нет конфигурации - доступ разрешен
        if (!isset(self::$config[$tabCode])) {
            return true;
        }
        
        $tabConfig = self::$config[$tabCode];
        
        // Проверка групп пользователя
        if (!empty($tabConfig['user_groups'])) {
            $userGroups = \CUser::GetUserGroup($userId);
            $hasGroup = array_intersect($userGroups, $tabConfig['user_groups']);
            
            if (empty($hasGroup)) {
                return false;
            }
        }
        
        // Проверка действий
        if (!empty($tabConfig['actions'])) {
            if (!in_array($action, $tabConfig['actions'])) {
                return false;
            }
        }
        
        // Дополнительная проверка через кастомный callback
        if (isset($tabConfig['callback']) && is_callable($tabConfig['callback'])) {
            return call_user_func($tabConfig['callback'], $userId, $tabCode, $action);
        }
        
        return true;
    }
    
    /**
     * Проверка доступа к конкретному блоку внутри вкладки
     * 
     * @param int $userId ID пользователя
     * @param string $tabCode Код вкладки
     * @param string $blockId ID блока (например, 'hl_block_5' или 'info_block_10')
     * @param string $action Действие
     * @return bool
     */
    public static function checkBlockAccess($userId, $tabCode, $blockId, $action = 'READ')
    {
        self::loadConfig();
        
        // Если конфигурации нет - доступ разрешен
        if (empty(self::$config[$tabCode]['blocks'])) {
            return true;
        }
        
        $blocksConfig = self::$config[$tabCode]['blocks'];
        
        // Если для блока нет конфигурации - доступ запрещен
        if (!isset($blocksConfig[$blockId])) {
            return false;
        }
        
        // Проверка действия
        if (!in_array($action, $blocksConfig[$blockId])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Проверка доступа к полям
     * 
     * @param int $userId ID пользователя
     * @param int $hlBlockId ID Highload-блока
     * @param array $fields Массив кодов полей
     * @param string $action Действие
     * @return bool
     */
    public static function checkFieldAccess($userId, $hlBlockId, $fields, $action = 'READ')
    {
        self::loadConfig();
        
        // Получаем конфигурацию для конкретного HL-блока
        $blockKey = 'hl_block_' . $hlBlockId;
        
        if (empty(self::$config['field_restrictions'][$blockKey])) {
            return true;
        }
        
        $fieldConfig = self::$config['field_restrictions'][$blockKey];
        $userGroups = \CUser::GetUserGroup($userId);
        
        // Проверяем каждое поле
        foreach ($fields as $fieldCode) {
            if (isset($fieldConfig[$fieldCode])) {
                $fieldRules = $fieldConfig[$fieldCode];
                
                // Если поле требует определенных групп
                if (!empty($fieldRules['required_groups'])) {
                    $hasGroup = array_intersect($userGroups, $fieldRules['required_groups']);
                    if (empty($hasGroup)) {
                        return false;
                    }
                }
                
                // Если поле запрещено для определенных групп
                if (!empty($fieldRules['denied_groups'])) {
                    $hasDeniedGroup = array_intersect($userGroups, $fieldRules['denied_groups']);
                    if (!empty($hasDeniedGroup)) {
                        return false;
                    }
                }
                
                // Проверка действий для поля
                if (!empty($fieldRules['actions'])) {
                    if (!in_array($action, $fieldRules['actions'])) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Получение списка доступных блоков для пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $tabCode Код вкладки
     * @return array Массив ID блоков с правами ['block_id' => ['READ', 'WRITE']]
     */
    public static function getAvailableBlocks($userId, $tabCode)
    {
        self::loadConfig();
        
        if (empty(self::$config[$tabCode]['blocks'])) {
            return [];
        }
        
        $result = [];
        $blocksConfig = self::$config[$tabCode]['blocks'];
        
        foreach ($blocksConfig as $blockId => $actions) {
            $availableActions = [];
            
            foreach ($actions as $action) {
                if (self::checkBlockAccess($userId, $tabCode, $blockId, $action)) {
                    $availableActions[] = $action;
                }
            }
            
            if (!empty($availableActions)) {
                $result[$blockId] = $availableActions;
            }
        }
        
        return $result;
    }
    
    /**
     * Фильтрация полей по правам доступа
     * 
     * @param int $userId ID пользователя
     * @param int $hlBlockId ID Highload-блока
     * @param array $fields Массив полей для фильтрации
     * @param string $action Действие
     * @return array Отфильтрованный массив полей
     */
    public static function filterFields($userId, $hlBlockId, $fields, $action = 'READ')
    {
        $filtered = [];
        
        foreach ($fields as $fieldCode => $fieldConfig) {
            if (self::checkFieldAccess($userId, $hlBlockId, [$fieldCode], $action)) {
                $filtered[$fieldCode] = $fieldConfig;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Установка конфигурации программно
     * 
     * @param array $config
     */
    public static function setConfig($config)
    {
        self::$config = $config;
    }
    
    /**
     * Добавление правила доступа
     * 
     * @param string $tabCode Код вкладки
     * @param array $rule Правило доступа
     */
    public static function addRule($tabCode, $rule)
    {
        self::loadConfig();
        self::$config[$tabCode] = $rule;
    }
}