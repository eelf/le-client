<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

require_once __DIR__ . '/../vendor/autoload.php';

ensure_extensions(['dom']);

try {
    $work_dir = (function() {
        foreach ([__DIR__ . '/../data', sys_get_temp_dir()] as $work_dir_candidate) {
            if (is_dir($work_dir_candidate) && is_writeable($work_dir_candidate)) {
                return $work_dir_candidate;
            }
        }
        return null;
    })();
    if (!$work_dir) throw new \Exception("Could not guess writeable work dir");
    Services::work_dir($work_dir);

    Services::logger(new Log());

    $dumper = function($headers, $content) {
        list ($frac, $sec) = explode(' ', microtime());
        $work_dir = Services::work_dir();
        $filename = $work_dir . '/' . date('Y-m-d_H-i-s_', $sec) . str_pad($frac * 1e6, 6, "0", STR_PAD_LEFT);
        File::write($filename . '_headers', print_r($headers, true));
        File::write($filename . '_content', $content);
    };
    Services::dumper($dumper);

    $usage = function() {
        echo <<<EOT
help                            this help
make_key key_path               make key and save to key_path
reg_account key_path            reg new account with key path
make_challenge private_key domain
challenge key_path challnge_uri challange_token payload_file location
make_cert domain_key domain private_key
prolongate config domain
EOT;

    };
    $make_key = function($path) {
        $Key = SslKey::generateNew();
        $key_str = $Key->export();
        File::write($path, $key_str);
        Services::logger()->log("Wrote key to $path");
    };
    $reg_account = function($path) {
        $Key = SslKey::fromFile($path);
        Services::logger()->log("Read key from $path");
        $Api = new Api(Api::BASE, $Key);
        $Response = $Api->newReg(Api::LICENSE);
        Services::logger()->log(
            "Register call result: " . print_r($Response->getStatusAndHeaders(), true)
                . "\n" . $Response->getContent()
        );
    };
    $commands = [
        '' => ['call' => $usage],
        'help' => ['alias' => ''],
        'prolongate' => ['args' => 2, 'call' => ProcessProlongate::class],
        'make_key' => ['args' => 1, 'call' => $make_key],
        'reg_account' => ['args' => 1, 'call' => $reg_account],
        'make_challenge' => ['args' => 2, 'call' => ProcessMakeChallenge::class],
        'challenge' => ['args' => 5, 'call' => ProcessChallenge::class],
        'make_cert' => ['args' => 3, 'call' => ProcessMakeCert::class],
    ];
    $Getopt = new Getopt();
    [$o, $no] = $Getopt->parse($argv, []);
    $command = array_shift($no);
    if (!isset($commands[$command])) throw new \Exception("unknown command $command");
    $command = $commands[$command];

    if (isset($command['alias'])) $command = $commands[$command['alias']];

    if (isset($command['args']) && count($no) != $command['args']) throw new \Exception("not enough args");
    if (is_string($command['call'])) {
        if (class_exists($command['call'])) {
            (new $command['call'])->run(...$no);
        }
    } else if (is_callable($command['call'])) {
        $command['call'](...$no);
    }
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit($e->getCode() ?: 1);
}

