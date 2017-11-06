<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Api {
    const BASE = 'https://acme-v01.api.letsencrypt.org';
    const LICENSE = 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf';

    private $base;
    private $key;
    private $nonce;

    public function __construct($base, SslKey $key) {
        $this->base = $base;
        $this->key = $key;
    }

    public function directory() {
        $Request = Request::get($this->base . '/directory');
        $Response = $Request->run();
        $this->nonce = $Response->getHeader('replay-nonce');
        return $Response;
    }

    public function newReg($license) {
        return $this->signedRequest(
            '/acme/new-reg',
            ['resource' => 'new-reg', 'agreement' => $license]
        );
    }

    public function newAuthz($domain) {
        return $this->signedRequest(
            "/acme/new-authz",
            ["resource" => "new-authz", "identifier" => ["type" => "dns", "value" => $domain]]
        );
    }

    public function newCert($csr) {
        $Response = $this->signedRequest(
            "/acme/new-cert",
            ['resource' => 'new-cert', 'csr' => $csr]
        );
        if ($Response->getStatusCode() != 201) {
            throw new \RuntimeException("Invalid response code: " . print_r($Response->getStatusAndHeaders(), true));
        }
        return $Response;
    }

    public function signedRequest($uri, array $payload) {
        if ($this->nonce === null) {
            $this->directory();
            if ($this->nonce === null) {
                throw new \Exception("Could not get nonce after directory request");
            }
        }

        if (!preg_match('#^http#', $uri)) {
            $uri = $this->base . $uri;
        }

        $Jwk = new Jwk;
        $data = $Jwk->sign($payload, $this->key, $this->nonce);

        $Request = Request::post($uri, $data);
        $Request->header([
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        $Response = $Request->run();
        return $Response;
    }
}
