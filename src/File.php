<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class File {
    public static function write($name, $str) {
        $wrote = file_put_contents($name, $str);
        if ($wrote !== $expected = strlen($str)) {
            throw new \RuntimeException("Wrote " . var_export($wrote, true) . " to $name instead of $expected");
        }
    }

    public static function tmp($prefix, $dir = null) {
        return tempnam($dir !== null ? $dir : sys_get_temp_dir(), $prefix);
    }
}
