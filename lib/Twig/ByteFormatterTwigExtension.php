<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ByteFormatterTwigExtension.php)
 */


namespace Xibo\Twig;


use Xibo\Helper\ByteFormatter;

class ByteFormatterTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'byteFormatter';
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('byteFormat', array($this, 'byteFormat'))
        );
    }

    public function byteFormat($bytes)
    {
        return ByteFormatter::format($bytes);
    }
}