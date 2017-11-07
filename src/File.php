<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class File {
    public static function write($name, $str) {
        $wrote = file_put_contents($name, $str);
        if ($wrote !== $expected = strlen($str)) {
            throw new \RuntimeException("Wrote " . var_export($wrote, true) . " to $name instead of $expected");
        }
        return $wrote;
    }

    public static function tmp($prefix, $dir = null) {
        $tmp_dir = $dir ?? sys_get_temp_dir();
        $tmp_name = tempnam($tmp_dir, $prefix);
        if ($tmp_name === false) throw new \RuntimeException("Could not make tmp file in $tmp_dir with prefix $prefix");
        return $tmp_name;
    }

    public static function chmod($name, $mode) {
        if (!chmod($name, $mode)) throw new \Exception("could not chmod($name, $mode)");
    }

    public static function copy($from, $to, $remote_from = null, $remote_to = null) {
        if (!$remote_from && !$remote_to) {
            $result = copy($from, $to);
            if (!$result) throw new \Exception("could not copy $from $to");
        } else if ($remote_from && $remote_to) {
            throw new \Exception("cannot copy from and to remote");
        } else {
            if ($remote_from) {
                $args = ["-P", $remote_from['port'], "$remote_from[user]@$remote_from[host]:$from", $to];
            } else {
                $args = ["-P", $remote_to['port'], $from, "$remote_to[user]@$remote_to[host]:$to"];
            }
            list ($code, $out) = self::exec('scp', $args, '2>&1');
            if ($code) {
                throw new \Exception("could not copy: $out");
            }
        }
    }

    public static function mkdir($intermediate, $dir, $remote = null) {
        if (!$remote) {
            $result = mkdir($dir, 0777, $intermediate);
            if (!$result) throw new \Exception("failed to mkdir");
        } else {
            $args = [$dir];
            if ($intermediate) array_unshift($args, '-p');
            $args = array_merge(['-p', $remote['port'], "$remote[user]@$remote[host]", 'mkdir'], $args);
            list ($code, $output) = self::exec('ssh', $args, '2>&1');
            if ($code) {
                throw new \Exception("failed to mkdir: $output");
            }
        }
    }

    private static function exec($prog, $args, $redir) {
        $cmd = $prog . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' ' . $redir;
        Services::logger()->log("exec $cmd");
        exec($cmd, $out, $ret);
        $out = implode("\n", $out);
        Services::logger()->log("= $ret\n$out");
        return [$ret, $out];
    }
}
