<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Process {
    /** @var Log */
    private static $logger;

    public static function run($argv) {
        self::$logger = $GLOBALS['logger'] ?? null;

        $action = isset($argv[1]) ? $argv[1] : null;
        if (!$action || $action == 'help') {
            echo <<<EOT
help                            this help
make_key key_path               make key and save to key_path
reg_account key_path            reg new account with key path
make_challenge key_path domain
challenge key_path challnge_uri challange_token payload_file location
make_cert domain_key domain private_key
EOT;
        } else if ($action == 'make_key') {
            if (!isset($argv[2])) throw new \RuntimeException("key_path");
            $path = $argv[2];
            $Key = SslKey::generateNew();
            $key_str = $Key->export();
            File::write($path, $key_str);
            if (self::$logger) self::$logger->log("Wrote key to $path");
        } else if ($action == 'reg_account') {
            if (!isset($argv[2])) throw new \RuntimeException("key_path");
            $path = $argv[2];
            $Key = SslKey::fromFile($path);

            if (self::$logger) self::$logger->log("Read key from $path");

            $Api = new Api(Api::BASE, $Key);
            $Response = $Api->newReg(Api::LICENSE);
            if (self::$logger) self::$logger->log("Register call result: " . print_r($Response->getStatusAndHeaders(), true)
                . "\n" . $Response->getContent());

        } else if ($action == 'make_challenge') {
            if (!isset($argv[3])) throw new \RuntimeException("not enough params");
            $path = $argv[2];
            $domain = $argv[3];
            self::makeChallenge($path, $domain);
        } else if ($action == 'challenge') {
            if (!isset($argv[6])) throw new \RuntimeException("not enough params");
            $path = $argv[2];
            $uri = $argv[3];
            $token = $argv[4];
            $file = $argv[5];
            $location = $argv[6];
            self::challenge($path, $uri, $token, $file, $location);
        } else if ($action == 'make_cert') {
            if (!isset($argv[4])) throw new \RuntimeException("not enough params");
            $domain_key = $argv[2];
            $domain = $argv[3];
            $private_key = $argv[4];
            self::makeCert($domain_key, $domain, $private_key);
        }
    }

    public static function makeChallenge($path, $domain) {
        if (!extension_loaded('hash')) throw new \RuntimeException("ext hash not loaded");
        $Key = SslKey::fromFile($path);
        $Api = new Api(Api::BASE, $Key);
        self::ensureNonce($Api);

        $Response = $Api->newAuthz($domain);
        $arr = $Response->getContent();
        if (!is_array($arr)) {
            throw new \RuntimeException("Failed to get json response:" . $Response->getDecodeErr() . ":" . $arr);
        }

        $challenge = array_reduce(
            $arr['challenges'],
            function($v, $w) { return $v ? $v : ($w['type'] == 'http-01' ? $w : false); }
        );
        if (!$challenge) throw new \RuntimeException("no challenge:" . print_r($arr, true));
        $token = $challenge['token'];
        $challenge_uri = $challenge['uri'];

        $location = $Response->getHeader('location');


        $details = $Key->getDetails();

        $header = [
            "e" => Base64UrlSafeEncoder::encode($details["rsa"]["e"]),
            "kty" => "RSA",
            "n" => Base64UrlSafeEncoder::encode($details["rsa"]["n"])
        ];
        $payload = $challenge['token'] . '.' . Base64UrlSafeEncoder::encode(hash('sha256', json_encode($header), true));

        $name = tempnam(sys_get_temp_dir(), 'token');
        chmod($name, 0644);
        File::write($name, $payload);

        if (self::$logger) {
            self::$logger->log("put file $name to <web-root>/.well-known/acme-challenge/$token");
            self::$logger->log("challenge_uri: $challenge_uri");
            self::$logger->log("token: $token");
            self::$logger->log("file: $name");
            self::$logger->log("location: $location");
        }
    }

    public static function challenge($path, $uri, $token, $file, $location) {
        $payload = file_get_contents($file);
        $Key = SslKey::fromFile($path);
        $Api = new Api(Api::BASE, $Key);
        self::ensureNonce($Api);

        $Result = $Api->signedRequest(
            $uri,
            [
                "resource" => "challenge",
                "type" => "http-01",
                "keyAuthorization" => $payload,
                "token" => $token,
            ]
        );

        $arr = $Result->getContent();
        while (true) {
            if (empty($arr['status']) || $arr['status'] == "invalid") {
                throw new \RuntimeException("Verification ended with error: " . print_r($arr, true));
            }
            if ($arr['status'] !== "pending") break;

            sleep(1);

            $Reqeust = Request::get($location);
            $Response = $Reqeust->run();
            $arr = $Response->getContent();
        }
    }

    public static function makeCert($domain_key, $domain, $private_key) {
        $domains = [$domain];

        $DomainKey = SslKey::fromFile($domain_key);

        $SslCsr = new SslCsr();
        list ($csr, $_) = $SslCsr->generateCSR($DomainKey, reset($domains), $domains);

        $PrivateKey = SslKey::fromFile($private_key);

        $LeApi = new Api(Api::BASE, $PrivateKey);

        self::ensureNonce($LeApi);

        $Response = $LeApi->newCert($csr);

        $location = $Response->getHeader('location');

        $certificates = [];
        while (!($Response = self::waitForCert($location)));

        $certificates[] = self::makePemFromBody($Response->getContent());

        foreach ((array)$Response->getHeader('link') as $link) {
            // [link] => </acme/issuer-cert>;rel="up"
            if (!preg_match('#<(?P<link>.*)>;rel="up"#', $link, $m)) {
                if (self::$logger) self::$logger->log("bad link: $link");
                continue;
            }
            $link = $m['link'];
            // if link is relative then make it absolute
            if (!preg_match('#^[a-z]+://#', $link)) {
                $link = Api::BASE . $link;
            }
            $Response = Request::get($link)->run();
            $certificates[] = self::makePemFromBody($Response->getContent());
        }

        if (empty($certificates)) throw new \RuntimeException('No certificates generated');
        File::write('fullchain.pem', implode("\n", $certificates));
        File::write('cert.pem', array_shift($certificates));
        File::write("chain.pem", implode("\n", $certificates));
        if (self::$logger) {
            self::$logger->log("wrote files: fullchain.pem cert.pem chain.pem");
            self::$logger->log("nginx config:\n"
                . "ssl_certificate     fullchain.pem;\n"
                . "ssl_certificate_key domain.key;");
        }
    }

    private static function makePemFromBody($body) {
        $pem = chunk_split(base64_encode($body), 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n$pem-----END CERTIFICATE-----\n";
    }

    /**
     * @param $url
     * @return Response|bool false when retry needed
     */
    private static function waitForCert($url) {
        $Response = Request::get($url)->run();

        if (!in_array($Response->getStatusCode(), [200, 202])) {
            throw new \RuntimeException("Can't get certificate: HTTP code " . print_r($Response->getStatusAndHeaders(), true));
        }
        if ($Response->getStatusCode() == 202) {
            sleep(1);
            return false;
        }
        return $Response;
    }

    private static function ensureNonce(Api $Api) {
        if (!$Api->getNonce()) {
            $Api->directory();
        }
    }
}
