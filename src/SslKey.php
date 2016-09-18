<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class SslKey {
    private $key;

    private function __construct($key) {
        $this->key = $key;
    }

    public function export() {
        $privateKey = null;
        if (!openssl_pkey_export($this->key, $privateKey)) {
            throw new \RuntimeException("Key export failed!");
        }
        return $privateKey;
    }

    public function sign($data, $sig_alg = 'SHA256') {
        $signed = null;
        openssl_sign($data, $signed, $this->key, $sig_alg);
        return $signed;
    }

    public function getKey() {
        return $this->key;
    }

    public function getDetails() {
        return openssl_pkey_get_details($this->key);
    }

    public static function generateNew() {
        $res = openssl_pkey_new([
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "private_key_bits" => 4096,
        ]);
        return new self($res);
    }

    public static function fromFile($path)
    {
        if (($key = openssl_pkey_get_private('file://' . $path)) === false) {
            throw new \RuntimeException(openssl_error_string());
        }
        return new self($key);
    }
}
