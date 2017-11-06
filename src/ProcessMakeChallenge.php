<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class ProcessMakeChallenge {
    public function run($domain, $account_key_path) {
        if (!extension_loaded('hash')) throw new \RuntimeException("ext hash not loaded");
        if (!$domain || !$account_key_path) throw new \Exception("domain or path to account key is not specified");

        $Key = SslKey::fromFile($account_key_path);
        $Api = new Api(Api::BASE, $Key);

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

        $name = tempnam(Services::work_dir(), 'token');
        chmod($name, 0644);
        File::write($name, $payload);

        $Logger = Services::logger();
        $Logger->log("put file $name to <web-root>/.well-known/acme-challenge/$token");
        $Logger->log("challenge_uri: $challenge_uri");
        $Logger->log("token: $token");
        $Logger->log("file: $name");
        $Logger->log("location: $location");
        $Logger->log("php le.php challenge $account_key_path $challenge_uri $token $name $location");
        return [
            'confirmation_file' => $name,
            'token' => $token,
            'uri' => $challenge_uri,
            'location' => $location,
        ];
    }
}
