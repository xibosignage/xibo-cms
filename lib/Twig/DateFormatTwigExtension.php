<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DateFormatTwigExtension.php)
 */


namespace Xibo\Twig;


use Twig\Extension\AbstractExtension;

class DateFormatTwigExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'datehms' => new \Twig\TwigFilter('dateFormat', $this)
        );
    }

    /**
     * Formats a date
     *
     * @param string $date in seconds
     *
     * @return string formated as HH:mm:ss
     */
    public function dateFormat( $date )
    {
        return gmdate('H:i:s', $date);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'datehms';
    }
}
