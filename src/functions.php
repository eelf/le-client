<?php

namespace Le;

function ensure_extensions(array $exts) {
    foreach ($exts as $ext) {
        if (!extension_loaded($ext) && !dl("$ext.so")) throw new \Exception("could not load extension $ext");
    }
}
