<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Response {
    const TYPE_JSON = 'json';

    private $status;
    private $headers;
    private $content;
    private $decode_err;

    public function __construct($headers, $content, $type = self::TYPE_JSON) {
        $dumper = Services::dumper();
        $dumper($headers, $content);

        foreach ($headers as $idx => $header) {
            if ($idx == 0) {
                $this->status = explode(' ', $header, 3);
                continue;
            }
            $header_arr = explode(':', $header, 2);
            if (!isset($header_arr[1])) {
                throw new \RuntimeException("Bad header: $header");
            }
            $name = strtolower(trim($header_arr[0]));
            $value = trim($header_arr[1]);
            if (isset($this->headers[$name])) {
                if (!is_array($this->headers[$name])) $this->headers[$name] = [$this->headers[$name]];
                $this->headers[$name][] = $value;
            } else {
                $this->headers[$name] = $value;
            }
        }

        if ($type == self::TYPE_JSON) {
            $this->content = json_decode($content, true);
            if ($this->content === null && $content !== 'null') {
                $this->decode_err = json_last_error() . ':' . json_last_error_msg();
                $this->content = $content;
            }
        } else {
            $this->content = $content;
        }
    }

    public function getDecodeErr() {
        return $this->decode_err;
    }

    public function getContent() {
        return $this->content;
    }

    public function getHeader($name) {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function getStatusProtocol() {
        return $this->status[0];
    }

    public function getStatusCode() {
        return $this->status[1];
    }

    public function getStatusText() {
        return $this->status[2];
    }

    public function getStatusAndHeaders() {
        return [$this->status, $this->headers];
    }
}
