<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Process {
    public static function run($argv, $work_dir) {
        Services::logger(new Log());
        Services::work_dir($work_dir);

        $dumper = function($headers, $content) {
            list ($frac, $sec) = explode(' ', microtime());
            $work_dir = Services::work_dir();
            $filename = $work_dir . '/' . date('Y-m-d_H-i-s_', $sec) . str_pad($frac * 1e6, 6, "0", STR_PAD_LEFT);
            File::write($filename . '_headers', print_r($headers, true));
            File::write($filename . '_content', $content);
        };
        Services::dumper($dumper);

        $action = $argv[1] ?? null;

        if (!$action || $action == 'help') {
            echo <<<EOT
help                            this help
make_key key_path               make key and save to key_path
reg_account key_path            reg new account with key path
make_challenge private_key domain
challenge key_path challnge_uri challange_token payload_file location
make_cert domain_key domain private_key
prolongate config domain
EOT;
        } else if ($action == 'prolongate') {
            self::prolongate($argv[2] ?? null, $argv[3] ?? null);
        } else if ($action == 'make_key') {
            $path = $argv[2] ?? null;
            $Key = SslKey::generateNew();
            $key_str = $Key->export();
            File::write($path, $key_str);
            Services::logger()->log("Wrote key to $path");
        } else if ($action == 'reg_account') {
            if (!isset($argv[2])) throw new \RuntimeException("key_path");
            $path = $argv[2];
            $Key = SslKey::fromFile($path);

            Services::logger()->log("Read key from $path");

            $Api = new Api(Api::BASE, $Key);
            $Response = $Api->newReg(Api::LICENSE);
            Services::logger()->log("Register call result: " . print_r($Response->getStatusAndHeaders(), true)
                . "\n" . $Response->getContent());

        } else if ($action == 'make_challenge') {
            $account_key_path = $argv[2] ?? null;
            $domain = $argv[3] ?? null;
            $Process = new ProcessMakeChallenge();
            $Process->run($domain, $account_key_path);
        } else if ($action == 'challenge') {
            $path = $argv[2] ?? null;
            $uri = $argv[3] ?? null;
            $token = $argv[4] ?? null;
            $file = $argv[5] ?? null;
            $location = $argv[6] ?? null;
            $Process = new ProcessChallenge();
            $Process->run($path, $uri, $token, $file, $location);
        } else if ($action == 'make_cert') {
            $domain_key = $argv[2] ?? null;
            $domain = $argv[3] ?? null;
            $private_key = $argv[4] ?? null;
            $Process = new ProcessMakeCert();
            $Process->run($domain, $domain_key, $private_key);
        }
    }

    private static function prolongate($config_path, $domain) {
        self::ensure_extensions(['hash', 'json']);

        if (!is_file($config_path)) throw new \Exception("not a config file $config_path");
        $config = require $config_path;
        if (!isset($config['domains'][$domain])) throw new \Exception("no config for domain $domain");
        $domain_config = $config['domains'][$domain];
        if (!isset($config['accounts'][$domain_config['account']])) throw new \Exception("no config for account $domain_config[account]");
        $account_config = $config['accounts'][$domain_config['account']];

        $MakeChallengeProc = new ProcessMakeChallenge();
        $make_challenge_res = $MakeChallengeProc->run($domain, $account_config['key']);

//        $domain_config['auth_agent'];
        $dir = $domain_config['web_root'] . '/.well-known/acme-challenge';
        list ($code, $output) = self::execRemote('mkdir', ['-p', $dir], '2>&1', $domain_config['user'], $domain_config['host'], $domain_config['port']);
        if ($code) {
            throw new \Exception("failed to mkdir: $output");
        }

        $remote_file = $dir . '/' . $make_challenge_res['token'];
        list ($code, $output) = self::copyRemote($make_challenge_res['confirmation_file'], $remote_file, $domain_config['user'], $domain_config['host'], $domain_config['port']);
        if ($code) {
            throw new \Exception("failed to copy token: $output");
        }

        $ChallegeProc = new ProcessChallenge();
        $ChallegeProc->run($account_config['key'], $make_challenge_res['uri'], $make_challenge_res['token'], $make_challenge_res['confirmation_file'], $make_challenge_res['location']);

        $Key = SslKey::generateNew();
        $key_str = $Key->export();
        $path = tempnam(Services::work_dir(), 'key');
        File::write($path, $key_str);
        Services::logger()->log("Wrote key to $path");

        $MakeCertProc = new ProcessMakeCert();
        $fullchain_filename = $MakeCertProc->run($domain, $path, $account_config['key']);

        list ($code, $output) = self::copyRemote($fullchain_filename, $domain_config['cert'], $domain_config['user'], $domain_config['host'], $domain_config['port']);
        if ($code) {
            throw new \Exception("failed to copy cert: $output");
        }
        list ($code, $output) = self::copyRemote($path, $domain_config['private'], $domain_config['user'], $domain_config['host'], $domain_config['port']);
        if ($code) {
            throw new \Exception("failed to copy key: $output");
        }
        //chown key and cert and reload nginx
    }

    private static function execRemote($prog, $args, $redir, $user, $host, $port) {
        $args = array_merge(["-p", $port, "$user@$host"], [$prog], $args);
        return self::exec('ssh', $args, $redir);
    }

    private static function copyRemote($local, $remote, $user, $host, $port) {
        $args = array_merge(["-P", $port, $local, "$user@$host:$remote"]);
        return self::exec('scp', $args, '2>&1');
    }

    private static function exec($prog, $args, $redir) {
        $cmd = $prog . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' ' . $redir;
        Services::logger()->log("exec $cmd");
        exec($cmd, $out, $ret);
        $out = implode("\n", $out);
        Services::logger()->log("= $ret\n$out");
        return [$ret, $out];
    }

    private static function ensure_extensions(array $exts) {
        foreach ($exts as $ext) {
            if (!extension_loaded($ext) && !dl("$ext.so")) throw new \Exception("could not load extension $ext");
        }
    }
}
