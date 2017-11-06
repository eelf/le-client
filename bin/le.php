<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

require_once __DIR__ . '/../vendor/autoload.php';

$work_dir = null;
foreach ([__DIR__ . '/../data', sys_get_temp_dir()] as $work_dir_candidate) {
    if (is_dir($work_dir_candidate) && is_writeable($work_dir_candidate)) {
        $work_dir = $work_dir_candidate;
        break;
    }
}

try {
    if (!$work_dir) throw new \Exception("Could not guess writeable work dir");
    \Le\Process::run($argv, $work_dir);
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit($e->getCode() ?: 1);
}

