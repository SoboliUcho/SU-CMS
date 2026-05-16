<?php
namespace Core;

class Config
{
    protected static array $config = [];

    public static function get(string $key, $default = null)
    {   
        $segments = explode('.', $key);
        $first_segment = $segments[0] ?? null;
        if (empty(self::$config [$first_segment])) {
            self::$config [$first_segment]= require __DIR__ . '/../config/'.$segments[0].'.php';
        }
        // Logger::getInstance()->debug("Config loaded for key: $key", ['config' => self::$config, 'segments' => $segments]);
        $segments = array_slice($segments, 1);
        $value = self::$config [$first_segment] ?? null;
        while ($segment = array_shift($segments)) {
            // Logger::getInstance()->debug("Accessing config segment: $segment", ['current_value' => $value]);
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }
}