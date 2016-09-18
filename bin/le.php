<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

require_once __DIR__ . '/../vendor/autoload.php';

$GLOBALS['logger'] = new Le\Log();

$GLOBALS['dumper'] = function($headers, $content) {
    list ($frac, $sec) = explode(' ', microtime());
    $filename = __DIR__ . '/../data/' . date('Y-m-d_H-i-s_', $sec) . str_pad($frac * 1e6, 6, "0", STR_PAD_LEFT);
    Le\File::write($filename . '_headers', print_r($headers, true));
    Le\File::write($filename . '_content', $content);
};

try {
    \Le\Process::run($argv);
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit($e->getCode() ?: 1);
}

