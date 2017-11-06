<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class ProcessChallenge {
    public function run($path, $uri, $token, $file, $location) {
        $payload = file_get_contents($file);
        $Key = SslKey::fromFile($path);
        $Api = new Api(Api::BASE, $Key);

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
        $Logger = Services::logger();
        while (true) {
            if (empty($arr['status']) || $arr['status'] == "invalid") {
                throw new \RuntimeException("Verification ended with error: " . print_r($arr, true));
            }
            if ($arr['status'] !== "pending") {
                $Logger->log("Status is not pending but " . $arr['status']);
                break;
            }

            sleep(1);

            $Reqeust = Request::get($location);
            $Response = $Reqeust->run();
            $arr = $Response->getContent();
        }
    }
}