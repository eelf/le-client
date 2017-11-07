<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class ProcessProlongate {
    public function run($config_path, $domain) {
        ensure_extensions(['hash', 'json']);

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
        File::mkdir(true, $dir, $domain_config);

        $remote_file = $dir . '/' . $make_challenge_res['token'];
        File::copy($make_challenge_res['confirmation_file'], $remote_file, null, $domain_config);

        $ChallegeProc = new ProcessChallenge();
        $ChallegeProc->run(
            $account_config['key'],
            $make_challenge_res['uri'],
            $make_challenge_res['token'],
            $make_challenge_res['confirmation_file'],
            $make_challenge_res['location']
        );

        $Key = SslKey::generateNew();
        $key_str = $Key->export();
        $path = File::tmp('key', Services::work_dir());
        File::write($path, $key_str);
        Services::logger()->log("Wrote key to $path");

        $MakeCertProc = new ProcessMakeCert();
        $fullchain_filename = $MakeCertProc->run($domain, $path, $account_config['key']);

        File::copy($fullchain_filename, $domain_config['cert'], null, $domain_config);
        File::copy($path, $domain_config['private'], null, $domain_config);
        //chown key and cert and reload nginx
    }
}
