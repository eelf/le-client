<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class Getopt {
    const SET_STRATEGY_REPLACE = 1,
        SET_STRATEGY_APPEND = 2;
    const ERROR_STRATEGY_CONTINUE = 1,
        ERROR_STRATEGY_EXIT = 2,
        ERROR_STRATEGY_EXCEPTION = 3;
    const ERROR_MUST_HAVE_VALUE = 1,
        ERROR_UNKNONW_FLAG = 2,
        ERROR_HAVE_FLAG_AFTER_NONOPT = 3;

    private $set_strategy,
        $error_strategy;

    public function __construct($set_strategy = self::SET_STRATEGY_REPLACE, $error_strategy = self::ERROR_STRATEGY_EXCEPTION) {
        if (is_callable($set_strategy)) {
            $this->set_strategy = $set_strategy;
        } else if ($set_strategy == self::SET_STRATEGY_REPLACE) {
            $this->set_strategy = [$this, 'set'];
        } else {
            $this->set_strategy = [$this, 'append'];
        }

        if (is_callable($error_strategy)) {
            $this->error_strategy = $error_strategy;
        } else if ($error_strategy == self::ERROR_STRATEGY_CONTINUE) {
            $this->error_strategy = [$this, 'dummy'];
        } else if ($error_strategy == self::ERROR_STRATEGY_EXIT) {
            $this->error_strategy = [$this, 'errorExit'];
        } else {
            $this->error_strategy = [$this, 'exception'];
        }
    }

    public function set(&$o, $name, $value = null) {
        $o[$name] = $value;
    }

    public function append(&$o, $name, $value = null) {
        if (!isset($o[$name])) $o[$name] = [];
        $o[$name][] = $value;
    }

    public function dummy($type, $formal, $arg, $current_flag = null) {}

    public function errorExit($type, $formal, $arg, $current_flag = null) {
        if ($type == self::ERROR_MUST_HAVE_VALUE) {
            echo "expected value for $current_flag but got $arg\n";
        } else {
            echo "unknown flag $arg\n";
        }
        exit(2);
    }

    public function exception($type, $formal, $arg, $current_flag = null) {
        if ($type == self::ERROR_MUST_HAVE_VALUE) {
            $E = new \Exception("expected value for $current_flag but got $arg");
        } else if ($type == self::ERROR_HAVE_FLAG_AFTER_NONOPT) {
            $E = new \Exception("have flag $arg after non-option");
        } else {
            $E = new \Exception("unknown flag $arg");
        }
        throw $E;
    }

    public function usage($formal) {
        $out = "usage:\n";
        foreach ($formal as $name => $modal) {
            $out .= "  -$name\n";
        }
        return $out;
    }

    public function parse($args, $formal) {
        array_shift($args);//program being launched
        $o = $no = [];
        // current_flag contains flag name which expects value, with modal may/must set respectively
        $current_flag = $may_value = $must_value = null;
        // $formal = [
        //'key' => '' // must have no value
        //'key1' => ':' // must have value
        //'key2' => '::' // may have value
        //];
        while ($args) {
            $arg = array_shift($args);
            $is_meta = $arg == '--';
            $is_value = empty($arg) || $arg == '-' || $arg[0] != '-';
            $is_option = !$is_value && !$is_meta;

            if ($must_value || $may_value) {
                if ($is_value) ($this->set_strategy)($o, $current_flag, $arg);
                else if ($must_value) ($this->error_strategy)(self::ERROR_MUST_HAVE_VALUE, $formal, $arg, $current_flag);
                $current_flag = $must_value = $may_value = null;
                continue;
            }

            if ($is_meta) {
                $no = array_merge($no, $args);
                break;
            }
            if ($is_option) {
                if ($no) ($this->error_strategy)(self::ERROR_HAVE_FLAG_AFTER_NONOPT, $formal, $arg);
                // not empty, ne -, ne --, starts with -
                // can start with as many minuses as it wants
                $name = ltrim($arg, '-');
                $name = explode('=', $name, 2);
                if (isset($name[1])) {
                    array_unshift($args, $name[1]);
                }
                $name = $name[0];
                if (!isset($formal[$name])) ($this->error_strategy)(self::ERROR_UNKNONW_FLAG, $formal, $arg);
                if ($formal[$name] == '') ($this->set_strategy)($o, $name);
                else {
                    $current_flag = $name;
                    $may_value = $formal[$name] == '::';
                    $must_value = $formal[$name] == ':';
                }
            } else {
                $no[] = $arg;
            }
        }
        if ($current_flag && $must_value) {
            ($this->error_strategy)(self::ERROR_MUST_HAVE_VALUE, $formal, null, $current_flag);
        }
        if ($current_flag && $may_value) {
            ($this->set_strategy)($o, $current_flag);
        }
        return [$o, $no];
    }
}
