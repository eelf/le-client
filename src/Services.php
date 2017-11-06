<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Services {
    private static $Logger;

    public static function logger(Log $Logger = null) : Log {
        if ($Logger) self::$Logger = $Logger;
        return self::$Logger;
    }

    private static $Dumper;

    public static function dumper(Callable $Dumper = null) : Callable {
        if ($Dumper) self::$Dumper = $Dumper;
        return self::$Dumper;
    }

    private static $work_dir;

    public static function work_dir($work_dir = null) : string {
        if ($work_dir) self::$work_dir = $work_dir;
        return self::$work_dir;
    }
}
