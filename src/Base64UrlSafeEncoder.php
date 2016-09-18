<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Base64UrlSafeEncoder {
    public static function encode($input) {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    public static function decode($input) {
        if ($remainder = strlen($input) % 4) {
            $input = str_pad($input, strlen($input) + 4 - $remainder, '=');
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
