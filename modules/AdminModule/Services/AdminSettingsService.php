<?php

namespace Modules\AdminModule\Services;

use Core\Db;
use Core\Modules\ModuleManager;

class AdminSettingsService
{
    public function catalog(): array
    {
        $catalog = ModuleManager::instance()->settingsCatalog();
        $stored = Db::queryAll('SELECT module_name, setting_key, setting_value FROM module_settings');

        foreach ($stored as $row) {
            $moduleName = $row['module_name'] ?? null;
            $settingKey = $row['setting_key'] ?? null;
            if ($moduleName === null || $settingKey === null || !isset($catalog[$moduleName])) {
                continue;
            }

            $catalog[$moduleName]['values'][$settingKey] = $this->decodeValue($row['setting_value'] ?? null);
        }

        return $catalog;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}