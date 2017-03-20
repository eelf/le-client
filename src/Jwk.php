<?php

namespace Le;

class Jwk {
	public function sign($payload, SslKey $Key, $nonce) {
        $payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));

        $details = $Key->getDetails();
        $header = [
            "alg" => "RS256",
            "jwk" => [
                "kty" => "RSA",
                "n" => Base64UrlSafeEncoder::encode($details["rsa"]["n"]),
                "e" => Base64UrlSafeEncoder::encode($details["rsa"]["e"]),
            ]
        ];

        $protected = $header;
        $protected["nonce"] = $nonce;
        $protected64 = Base64UrlSafeEncoder::encode(json_encode($protected));

        $signed = $Key->sign($protected64 . '.' . $payload64);

        $data = json_encode([
            'header' => $header,
            'protected' => $protected64,
            'payload' => $payload64,
            'signature' => Base64UrlSafeEncoder::encode($signed),
        ]);
        return $data;
	}
}
