<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UrlDecodeTwigExtension.php)
 */


namespace Xibo\Twig;


class UrlDecodeTwigExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'url_decode' => new \Twig_Filter_Method($this, 'urlDecode')
        );
    }

    /**
     * URL Decode a string
     *
     * @param string $url
     *
     * @return string The decoded URL
     */
    public function urlDecode( $url )
    {
        return urldecode( $url );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'url_decode';
    }
}