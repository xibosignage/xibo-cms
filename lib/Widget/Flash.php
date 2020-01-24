<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
namespace Xibo\Widget;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
/**
 * Class Flash
 * @package Xibo\Widget
 */
class Flash extends ModuleWidget
{

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'flash-designer-javascript';
    }

    /** @inheritdoc */
    public function editForm(Request $request, Response $response)
    {
        return 'generic-form-edit';
    }

    /** @inheritdoc */
    public function edit(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0, Request $request)
    {
        if ($this->module->previewEnabled == 0) {
            return parent::preview($width, $height, $scaleOverride, $request);
        }

        $url = $this->urlFor($request,'module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]);

        return '<object width="' . $width . '" height="' . $height . '">
            <param name="movie" value="' . $url . '"></param>
            <param name="allowFullScreen" value="false"></param>
            <param name="allowscriptaccess" value="always"></param>
            <param name="wmode" value="transaprent"></param>
            <embed src="' . $url . '"
                   type="application/x-shockwave-flash"
                   allowscriptaccess="always"
                   allowfullscreen="true"
                   width="' . $width . '" height="' . $height . '"
                   wmode="transparent">
            </embed>
        </object>';
    }

    /**
     * Get Resource
     * @param Request $request
     * @param Response $response
     * @return mixed
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getResource(Request $request, Response $response)
    {
        $this->download($request, $response);
    }

    /** @inheritdoc */
    public function isValid()
    {
        return self::$STATUS_VALID;
    }
}
