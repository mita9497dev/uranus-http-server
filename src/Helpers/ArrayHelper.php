<?php 

namespace Mita\UranusHttpServer\Helpers;

class ArrayHelper
{
    /**
     * Check if the key exists in the array
     * 
     * @param array $array
     * @param string|int|array $key
     * @return bool
     */
    public static function has(array $array, $key): bool
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if (!array_key_exists($k, $array)) {
                    return false;
                }
            }
            return true;
        }
        return array_key_exists($key, $array);
    }

    public static function get(array $array, string $key, $default = null)
    {
        return self::has($array, $key) ? $array[$key] : $default;
    }

    public static function set(array &$array, $key, $value)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $array[$k] = $value;
            }
        } else {
            $array[$key] = $value;
        }
    }

    public static function remove(array &$array, $key)
    {
        unset($array[$key]);
    }

    public static function clear(array &$array)
    {
        $array = [];
    }

    public static function isEmpty(array $array): bool
    {
        return empty($array);
    }

    public static function isNotEmpty(array $array): bool
    {
        return !self::isEmpty($array);
    }

    public static function keys(array $array): array
    {
        return array_keys($array);
    }

    public static function values(array $array): array
    {
        return array_values($array);
    }
}
