<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Defer {
    private $callable;
    private $args;

    public static function add(&$list, Callable $callable) {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        $list[] = new self($callable, $args);
    }

    private function __construct(Callable $callable, array $args) {
        $this->callable = $callable;
        $this->args = $args;
    }

    public function __destruct() {
        $callable = $this->callable;
        $this->callable = null;
        call_user_func_array($callable, $this->args);
    }
}
