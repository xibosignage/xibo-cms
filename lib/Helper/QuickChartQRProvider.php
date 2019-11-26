<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Helper;

use QRException;
use RobThree\Auth\Providers\Qr\BaseHTTPQRCodeProvider;

class QuickChartQRProvider extends BaseHTTPQRCodeProvider
{
    public $url;
    public $errorCorrectionLevel;
    public $margin;
    public $backgroundColor;
    public $color;
    public $format;

    /**
     * QuickChartQRProvider constructor.
     * @param string $url URL to a Quick Chart service
     * @param bool $verifyssl
     * @param string $errorCorrectionLevel valid values L, M, Q, H
     * @param int $margin
     * @param string $backgroundColor Hex color code - background colour
     * @param string $color Hex color code - QR colour
     * @param string $format Valid values: png, svg
     * @throws QRException
     */
    function __construct($url, $verifyssl = false, $errorCorrectionLevel = 'L', $margin = 4, $backgroundColor = 'ffffff', $color = '000000', $format = 'png')
    {
        if (!is_bool($verifyssl)) {
            throw new QRException('VerifySSL must be bool');
        }

        $this->verifyssl = $verifyssl;

        $this->url = $url;
        $this->errorCorrectionLevel = $errorCorrectionLevel;
        $this->margin = $margin;
        $this->backgroundColor = $backgroundColor;
        $this->color = $color;
        $this->format = $format;
    }

    /**
     * @return string
     * @throws QRException
     */
    public function getMimeType()
    {
        switch (strtolower($this->format)) {
            case 'png':
                return 'image/png';
            case 'svg':
                return 'image/svg+xml';
        }
        throw new \QRException(sprintf('Unknown MIME-type: %s', $this->format));
    }

    public function getQRCodeImage($qrText, $size)
    {
        return $this->getContent($this->getUrl($qrText, $size));
    }

    public function getUrl($qrText, $size)
    {
        return $this->url . '/qr'
            . '?size=' . $size
            . '&ecLevel=' . strtoupper($this->errorCorrectionLevel)
            . '&margin=' . $this->margin
            . '&light=' . $this->backgroundColor
            . '&dark=' . $this->color
            . '&format=' . strtolower($this->format)
            . '&text=' . rawurlencode($qrText);
    }
}