<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Request {
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    private $method;
    private $url;
    private $headers = [];
    private $content;
    private $vars;

    /** @var Log */
    private $logger;

    public static function get($uri) {
        return (new self())->method(self::METHOD_GET)->url($uri)->logger(Services::logger());
    }

    public static function post($uri, $content) {
        return (new self())->method(self::METHOD_POST)->url($uri)->content($content)->logger(Services::logger());
    }

    public function method($method) {
        $this->method = $method;
        return $this;
    }

    public function url($url) {
        $this->url = $url;
        return $this;
    }

    public function content($content) {
        $this->content = $content;
        return $this;
    }

    public function header($header, $value = null) {
        if (is_array($header)) {
            $this->headers = array_merge($this->headers, $header);
        } else {
            $this->headers[$header] = $value;
        }
        return $this;
    }

    public function vars($vars) {
        $this->vars = $vars;
        return $this;
    }

    public function logger($logger) {
        $this->logger = $logger;
        return $this;
    }

    public function run($response_type = Response::TYPE_JSON) {
        $opts = [
            'http' => [
                'method' => $this->method,
                'timeout' => 30,
            ],
        ];
        if ($this->headers) {
            $opts['http']['header'] = implode("\r\n", $this->headers) . "\r\n";
        }
        if ($this->content !== null) {
            $opts['http']['content'] = $this->content;
        }
        $url = $this->url;
        if ($this->vars) {
            $url .= '?' . http_build_query($this->vars);
        }

        $ctx = stream_context_create($opts);

        if ($this->logger) {
            $options_str = [];
            foreach ($opts as $wrapper_name => $wrapper_opts) {
                foreach ($wrapper_opts as $opt_name => $opt_value) {
                    $options_str[] = "$wrapper_name.$opt_name:" . var_export($opt_value, true);
                }
            }
            $this->logger->log("Making request using method " . $this->method . " to " . $url . " with options:\n\t"
                . implode("\n\t", $options_str));
        }

        $body = file_get_contents($url, false, $ctx);

        return new Response($http_response_header ?? [], $body, $response_type);
    }
}
