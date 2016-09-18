<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class SslCsr {
    private $countryCode;
    private $state;

    public function __construct($countryCode = 'RU', $state = 'Russia') {
        $this->countryCode = $countryCode;
        $this->state = $state;
    }

    public function generateCSR(SslKey $privateKey, $domain, array $san) {
        $san = implode(',', array_map(function ($dns) { return 'DNS:' . $dns; }, $san));
        $tmp_file = File::tmp('csr');
        Defer::add($defer_list, function() use ($tmp_file) { unlink($tmp_file); });

        $str = <<<EOT
HOME = .
RANDFILE = \$ENV::HOME/.rnd
[ req ]
default_bits = 2048
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = $san
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
EOT;

        File::write($tmp_file, $str);

        $dn = [
            "CN" => $domain,
            "ST" => $this->state,
            "C" => $this->countryCode,
            "O" => "Unknown",
        ];
        $config_args = [
            "config" => $tmp_file,
            "digest_alg" => "sha256"
        ];
        $key = $privateKey->getKey();
        $csr = openssl_csr_new($dn, $key, $config_args);

        if (!$csr) throw new \RuntimeException("CSR couldn't be generated! " . openssl_error_string());

        $csr_exported = null;
        openssl_csr_export($csr, $csr_exported);

        if (!preg_match('#REQUEST-----(.*)-----END#s', $csr_exported, $matches)) {
            throw new \RuntimeException('Bad CSR exported');
        }

        return [
            trim(Base64UrlSafeEncoder::encode(base64_decode($matches[1]))),
            $csr_exported,
        ];
    }
}
