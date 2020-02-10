<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 * All Rights Reserved
 *
 * This is commercial proprietary software.
 */

namespace Xibo\Helper;


use Xibo\Support\Sanitizer\RespectSanitizer;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Support\Validator\RespectValidator;
use Xibo\Support\Validator\ValidatorInterface;

class SanitizerService
{
    /**
     * @param $array
     * @return SanitizerInterface
     */
    public function getSanitizer($array)
    {
        return (new RespectSanitizer())->setCollection($array)->setDefaultOptions(['throwClass' => '\Xibo\Support\Exception\InvalidArgumentException']);
    }

    /**
     * @return ValidatorInterface
     */
    public function getValidator()
    {
        return new RespectValidator();
    }
}