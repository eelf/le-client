<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class ProcessMakeCert {
    public function run($domain, $domain_key, $private_key_path) {
        $domains = [$domain];

        $DomainKey = SslKey::fromFile($domain_key);

        $SslCsr = new SslCsr();
        list ($csr, $_) = $SslCsr->generateCSR($DomainKey, reset($domains), $domains);

        $PrivateKey = SslKey::fromFile($private_key_path);

        $LeApi = new Api(Api::BASE, $PrivateKey);

        $Response = $LeApi->newCert($csr);

        $location = $Response->getHeader('location');

        $certificates = [];
        while (!($Response = self::waitForCert($location)));

        $certificates[] = self::makePemFromBody($Response->getContent());

        $Logger = Services::logger();

        foreach ((array)$Response->getHeader('link') as $link) {
            // [link] => </acme/issuer-cert>;rel="up"
            if (!preg_match('#<(?P<link>.*)>;rel="up"#', $link, $m)) {
                $Logger->log("bad link: $link");
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
        $fullchain_filename = Services::work_dir() . '/fullchain.pem';
        File::write($fullchain_filename, implode("\n", $certificates));
        File::write(Services::work_dir() . '/cert.pem', array_shift($certificates));
        File::write(Services::work_dir() . '/chain.pem', implode("\n", $certificates));
        $Logger->log("wrote files: fullchain.pem cert.pem chain.pem");
        $Logger->log("nginx config:\n"
            . "ssl_certificate     fullchain.pem;\n"
            . "ssl_certificate_key domain.key;");
        return $fullchain_filename;
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
}