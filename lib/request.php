<?php

/**
 * Read a scalar request value without triggering array-to-string warnings.
 */
function request_scalar_value(array $source, $key, $default = '')
{
    if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
        return $default;
    }
    return (string) $source[$key];
}

function request_text_value(array $source, $key, $default = '')
{
    return trim(strip_tags(request_scalar_value($source, $key, $default)));
}
