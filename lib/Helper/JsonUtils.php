<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (JsonUtils.php)
 */


namespace Xibo\Helper;


class JsonUtils
{
    /**
     * Json Encode, handling and logging errors
     * http://stackoverflow.com/questions/10199017/how-to-solve-json-error-utf8-error-in-php-json-decode
     * @param  mixed $mixed The item to encode
     * @return mixed The Encoded Item
     */
    public static function jsonEncode($mixed)
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $encoded = json_encode($mixed, JSON_PRETTY_PRINT);
        }
        else {
            $encoded = json_encode($mixed);
        }

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $encoded;
            case JSON_ERROR_DEPTH:
                Log::debug('Maximum stack depth exceeded');
                return false;
            case JSON_ERROR_STATE_MISMATCH:
                Log::debug('Underflow or the modes mismatch');
                return false;
            case JSON_ERROR_CTRL_CHAR:
                Log::debug('Unexpected control character found');
                return false;
            case JSON_ERROR_SYNTAX:
                Log::debug('Syntax error, malformed JSON');
                return false;
            case JSON_ERROR_UTF8:
                $clean = JsonUtils::utf8ize($mixed);
                return JsonUtils::jsonEncode($clean);
            default:
                Log::debug('Unknown error');
                return false;
        }
    }

    /**
     * Utf8ize a string or array
     * http://stackoverflow.com/questions/10199017/how-to-solve-json-error-utf8-error-in-php-json-decode
     * @param  mixed $mixed The item to uft8ize
     * @return mixed The utf8ized item
     */
    public static function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = JsonUtils::utf8ize($value);
            }
        }
        else if (is_string ($mixed)) {
            return utf8_encode($mixed);
        }
        return $mixed;
    }
}