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
        return $wrote;
    }

    public static function tmp($prefix, $dir = null) {
        $tmp_dir = $dir ?? sys_get_temp_dir();
        $tmp_name = tempnam($tmp_dir, $prefix);
        if ($tmp_name === false) throw new \RuntimeException("Could not make tmp file in $tmp_dir with prefix $prefix");
        return $tmp_name;
    }
}
