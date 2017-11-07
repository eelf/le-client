<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Defer {
    private $callable, $args;

    public static function add(&$list, Callable $callable, ...$args) {
        $list[] = new self($callable, $args);
    }

    public function __construct(Callable $callable, array $args) {
        $this->callable = $callable;
        $this->args = $args;
    }

    public function __destruct() {
        ($this->callable)(...$this->args);
    }
}
