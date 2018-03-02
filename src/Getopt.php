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

    public function set(&$o, $name, $value = true) {
        $o[$name] = $value;
    }

    public function append(&$o, $name, $value = true) {
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

    /**
     * @param array $args
     * @param array $formal [flag => '' must not have value, flag => ':' must have value, flag => '::' may have value]
     * @return array[] [[option => value, ...], [non-options...]]
     */
    public function parse(array $args, array $formal) {
        array_shift($args);//program being launched
        $o = $no = [];
        // current_flag contains flag name which expects value, with modal may(2)/must(1) set respectively
        $expect_value = $current_flag = null;
        while ($args) {
            $arg = array_shift($args);
            $is_meta = $arg == '--';
            $is_value = empty($arg) || $arg == '-' || $arg[0] != '-';

            if ($expect_value && $is_value) {
                ($this->set_strategy)($o, $current_flag, $arg);
                $expect_value = $current_flag = null;
                continue;
            }
            if ($expect_value & 1 && !$is_value) {
                ($this->error_strategy)(self::ERROR_MUST_HAVE_VALUE, $formal, $arg, $current_flag);
                $expect_value = $current_flag = null;
            }

            if ($is_meta) {
                $no = array_merge($no, $args);
                break;
            }
            if ($is_value) {
                $no[] = $arg;
                continue;
            }
            // not empty, ne -, ne --, starts with - (i.e. is_option)

            if ($no) ($this->error_strategy)(self::ERROR_HAVE_FLAG_AFTER_NONOPT, $formal, $arg);

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
                $expect_value = $formal[$name] == ':' ? 1 : ($formal[$name] == '::' ? 2 : 0);
            }
        }
        if ($expect_value & 1) {
            // no more values but there must be one
            ($this->error_strategy)(self::ERROR_MUST_HAVE_VALUE, $formal, null, $current_flag);
        }
        if ($expect_value & 2) {
            // no more values but there may be one
            ($this->set_strategy)($o, $current_flag);
        }
        return [$o, $no];
    }
}
