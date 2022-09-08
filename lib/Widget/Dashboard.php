<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\XmdsConnectorFileEvent;
use Xibo\Event\XmdsConnectorTokenEvent;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Xmds\Wsdl;

class Dashboard extends ModuleWidget
{
    public function getTimezoneList(): array
    {
        $timeZones = [];
        $timeZones[] = ['id' => '', 'value' => null];
        foreach (DateFormatHelper::timezoneList() as $key => $value) {
            $timeZones[] = ['id' => $key, 'value' => $value];
        }
        return $timeZones;
    }

    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('type', $sanitizedParams->getString('type'));

        // URL
        $url = $sanitizedParams->getString('url');
        $this->setOption('url', urlencode($url));
        if (empty($url)) {
            throw new InvalidArgumentException(__('Please supply a URL.'), 'url');
        }

        // Interval
        $interval = $sanitizedParams->getInt('updateInterval', ['default' => 60]);
        if ($interval < 5) {
            throw new InvalidArgumentException(__('5 minutes is the lowest update interval allowed'), 'updateInterval');
        }
        $this->setOption('updateInterval', $interval);

        // Save the widget
        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /** @inheritDoc */
    public function isValid()
    {
        // We're going to assume this is always valid.
        if (empty($this->getOption('url'))) {
            $this->statusMessage = __('Please set a URL');
            return self::$STATUS_INVALID;
        } else {
            return self::$STATUS_VALID;
        }
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0) {
            return parent::preview($width, $height, $scaleOverride);
        }


        if ($this->isValid() === self::$STATUS_VALID) {
            $widthPx = $width . 'px';
            $heightPx = $height . 'px';

            $url = $this->urlFor('module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]);

            return '<iframe scrolling="no" src="' . $url . '?preview=1&debug=1" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
        } else {
            return '<img id="dashboard" src="' . $this->getSpinner() . '" style="display:block;margin-left: auto;margin-right: auto;margin-top: 50px;"/>';
        }
    }

    /** @inheritDoc */
    public function getResource($displayId = 0)
    {
        // Always generate a token
        $event = new XmdsConnectorTokenEvent();
        $event->setTargets($displayId, $this->getWidgetId());
        $event->setTtl($this->getCacheDuration() * 2);
        $this->getDispatcher()->dispatch($event, XmdsConnectorTokenEvent::$NAME);
        $token = $event->getToken();

        if (empty($token)) {
            throw new ConfigurationException(__('No token returned'));
        }

        if ($this->isPreview()) {
            $url = $this->urlFor('layout.preview.connector', [], ['token' => $token]);
        } else {
            // This is fallback HTML for the player.
            // so output a link to the XMDS file request.
            $url = Wsdl::getRoot() . '?connector=true&token=' . $token;
        }

        // Construct the response HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery.min.js')
            ->appendJavaScript('var xiboICTargetId = ' . $this->getWidgetId() . ';')
            ->appendJavaScriptFile('xibo-interactive-control.min.js')
            ->appendCss('
                #loader {
                  display: block;
                  margin-left: auto;
                  margin-right: auto;
                  margin-top: 50px;
                }
                #dashboard {
                  position: fixed; 
                  top: 0; 
                  left: 0; 
                  width: 100%; 
                  height: 100%;
                }
                #dashboard img {
                  margin: auto; 
                  width: 100%;
                  height: 100%;
                }
            ')
            ->appendOptions([
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height,
                'url' => $url,
                'interval' => $this->getOption('updateInterval', 60),
                'isPreview' => $this->isPreview()
            ])
            ->appendJavaScript('
                var interval;
                $(function() {                    
                    loadImage();
                });
                
                function loadImage() {
                    $("#dashboard").html("");
                    var image = new Image();
                    image.onload = function() {
                        $("#dashboard").append($("<img src=\"" + this.src + "\" >"));
                        $("#loader").hide();
                        
                        if (interval) {
                            clearInterval(interval)
                        }
                        interval = setInterval(loadImage, options.interval * 60 * 1000);
                    }
                    image.onerror = function() {
                        $("#loader").show();
                        if (interval) {
                            clearInterval(interval)
                        }
                        interval = setInterval(loadImage, 60 * 1000);
                    }
                    
                    if (options.url) {
                        if (options.isPreview) {
                            var queryParams = new URLSearchParams(window.location.search)
                            if(queryParams.has("debug")) {
                                var debug = queryParams.get("debug")
                                if (debug == 1) {
                                    options.url = options.url.concat("&isDebug=1")
                                }
                            }
                        }
                        
                        image.src = options.url;
                    }
                }
            ')
            ->appendBody('
                <img id="loader" alt="Loading..." src="' . $this->getSpinner() . '" />
                <div id="dashboard"></div>
                <!-- TOKEN=' . $token . ' -->
            ');

        return $this->finaliseGetResource();
    }

    private function getSpinner(): string
    {
        return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQy
        AiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPgo8c3ZnIH
        dpZHRoPSI0MHB4IiBoZWlnaHQ9IjQwcHgiIHZpZXdCb3g9IjAgMCA0MCA0MCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcm
        cvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4bWw6c3BhY2U9InByZXNlcnZlIiBzdHlsZT0iZm
        lsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7c3Ryb2tlLWxpbmVqb2luOnJvdW5kO3N0cm9rZS1taXRlcmxpbWl0OjEuNDE0MjE7Ii
        B4PSIwcHgiIHk9IjBweCI+CiAgICA8ZGVmcz4KICAgICAgICA8c3R5bGUgdHlwZT0idGV4dC9jc3MiPjwhW0NEQVRBWwogICAgICAgICAgICBALX
        dlYmtpdC1rZXlmcmFtZXMgc3BpbiB7CiAgICAgICAgICAgICAgZnJvbSB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybTogcm90YX
        RlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKICAgICAgICAgICAgICAgIC13ZWJraXQtdHJhbnNmb3JtOiByb3RhdG
        UoLTM1OWRlZykKICAgICAgICAgICAgICB9CiAgICAgICAgICAgIH0KICAgICAgICAgICAgQGtleWZyYW1lcyBzcGluIHsKICAgICAgICAgICAgIC
        Bmcm9tIHsKICAgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKIC
        AgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKC0zNTlkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICB9CiAgICAgICAgICAgIH
        N2ZyB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybS1vcmlnaW46IDUwJSA1MCU7CiAgICAgICAgICAgICAgICAtd2Via2l0LWFuaW
        1hdGlvbjogc3BpbiAxLjVzIGxpbmVhciBpbmZpbml0ZTsKICAgICAgICAgICAgICAgIC13ZWJraXQtYmFja2ZhY2UtdmlzaWJpbGl0eTogaGlkZG
        VuOwogICAgICAgICAgICAgICAgYW5pbWF0aW9uOiBzcGluIDEuNXMgbGluZWFyIGluZmluaXRlOwogICAgICAgICAgICB9CiAgICAgICAgXV0+PC
        9zdHlsZT4KICAgIDwvZGVmcz4KICAgIDxnIGlkPSJvdXRlciI+CiAgICAgICAgPGc+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0yMCwwQzIyLjIwNT
        gsMCAyMy45OTM5LDEuNzg4MTMgMjMuOTkzOSwzLjk5MzlDMjMuOTkzOSw2LjE5OTY4IDIyLjIwNTgsNy45ODc4MSAyMCw3Ljk4NzgxQzE3Ljc5ND
        IsNy45ODc4MSAxNi4wMDYxLDYuMTk5NjggMTYuMDA2MSwzLjk5MzlDMTYuMDA2MSwxLjc4ODEzIDE3Ljc5NDIsMCAyMCwwWiIgc3R5bGU9ImZpbG
        w6YmxhY2s7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNNS44NTc4Niw1Ljg1Nzg2QzcuNDE3NTgsNC
        4yOTgxNSA5Ljk0NjM4LDQuMjk4MTUgMTEuNTA2MSw1Ljg1Nzg2QzEzLjA2NTgsNy40MTc1OCAxMy4wNjU4LDkuOTQ2MzggMTEuNTA2MSwxMS41MD
        YxQzkuOTQ2MzgsMTMuMDY1OCA3LjQxNzU4LDEzLjA2NTggNS44NTc4NiwxMS41MDYxQzQuMjk4MTUsOS45NDYzOCA0LjI5ODE1LDcuNDE3NTggNS
        44NTc4Niw1Ljg1Nzg2WiIgc3R5bGU9ImZpbGw6cmdiKDIxMCwyMTAsMjEwKTsiLz4KICAgICAgICA8L2c+CiAgICAgICAgPGc+CiAgICAgICAgIC
        AgIDxwYXRoIGQ9Ik0yMCwzMi4wMTIyQzIyLjIwNTgsMzIuMDEyMiAyMy45OTM5LDMzLjgwMDMgMjMuOTkzOSwzNi4wMDYxQzIzLjk5MzksMzguMj
        ExOSAyMi4yMDU4LDQwIDIwLDQwQzE3Ljc5NDIsNDAgMTYuMDA2MSwzOC4yMTE5IDE2LjAwNjEsMzYuMDA2MUMxNi4wMDYxLDMzLjgwMDMgMTcuNz
        k0MiwzMi4wMTIyIDIwLDMyLjAxMjJaIiBzdHlsZT0iZmlsbDpyZ2IoMTMwLDEzMCwxMzApOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KIC
        AgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksMjguNDkzOUMzMC4wNTM2LDI2LjkzNDIgMzIuNTgyNCwyNi45MzQyIDM0LjE0MjEsMjguNDkzOU
        MzNS43MDE5LDMwLjA1MzYgMzUuNzAxOSwzMi41ODI0IDM0LjE0MjEsMzQuMTQyMUMzMi41ODI0LDM1LjcwMTkgMzAuMDUzNiwzNS43MDE5IDI4Lj
        Q5MzksMzQuMTQyMUMyNi45MzQyLDMyLjU4MjQgMjYuOTM0MiwzMC4wNTM2IDI4LjQ5MzksMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxMDEsMT
        AxLDEwMSk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNMy45OTM5LDE2LjAwNjFDNi4xOTk2OCwxNi
        4wMDYxIDcuOTg3ODEsMTcuNzk0MiA3Ljk4NzgxLDIwQzcuOTg3ODEsMjIuMjA1OCA2LjE5OTY4LDIzLjk5MzkgMy45OTM5LDIzLjk5MzlDMS43OD
        gxMywyMy45OTM5IDAsMjIuMjA1OCAwLDIwQzAsMTcuNzk0MiAxLjc4ODEzLDE2LjAwNjEgMy45OTM5LDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2
        IoMTg3LDE4NywxODcpOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTUuODU3ODYsMjguNDkzOUM3Lj
        QxNzU4LDI2LjkzNDIgOS45NDYzOCwyNi45MzQyIDExLjUwNjEsMjguNDkzOUMxMy4wNjU4LDMwLjA1MzYgMTMuMDY1OCwzMi41ODI0IDExLjUwNj
        EsMzQuMTQyMUM5Ljk0NjM4LDM1LjcwMTkgNy40MTc1OCwzNS43MDE5IDUuODU3ODYsMzQuMTQyMUM0LjI5ODE1LDMyLjU4MjQgNC4yOTgxNSwzMC
        4wNTM2IDUuODU3ODYsMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxNjQsMTY0LDE2NCk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogIC
        AgICAgICAgICA8cGF0aCBkPSJNMzYuMDA2MSwxNi4wMDYxQzM4LjIxMTksMTYuMDA2MSA0MCwxNy43OTQyIDQwLDIwQzQwLDIyLjIwNTggMzguMj
        ExOSwyMy45OTM5IDM2LjAwNjEsMjMuOTkzOUMzMy44MDAzLDIzLjk5MzkgMzIuMDEyMiwyMi4yMDU4IDMyLjAxMjIsMjBDMzIuMDEyMiwxNy43OT
        QyIDMzLjgwMDMsMTYuMDA2MSAzNi4wMDYxLDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2IoNzQsNzQsNzQpOyIvPgogICAgICAgIDwvZz4KICAgIC
        AgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksNS44NTc4NkMzMC4wNTM2LDQuMjk4MTUgMzIuNTgyNCw0LjI5ODE1IDM0LjE0Mj
        EsNS44NTc4NkMzNS43MDE5LDcuNDE3NTggMzUuNzAxOSw5Ljk0NjM4IDM0LjE0MjEsMTEuNTA2MUMzMi41ODI0LDEzLjA2NTggMzAuMDUzNiwxMy
        4wNjU4IDI4LjQ5MzksMTEuNTA2MUMyNi45MzQyLDkuOTQ2MzggMjYuOTM0Miw3LjQxNzU4IDI4LjQ5MzksNS44NTc4NloiIHN0eWxlPSJmaWxsOn
        JnYig1MCw1MCw1MCk7Ii8+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4K';
    }

    /**
     * This is how often our token gets refreshed.
     * @inheritDoc
     */
    public function getCacheDuration()
    {
        // 24 hours
        return 3600 * 24;
    }

    /**
     * Our links are per-display, so we need to cache per display too.
     * @inheritDoc
     */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . '_' . $displayId;
    }

    /** @inheritDoc */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Dashboards';
            $module->type = 'dashboard';
            $module->class = 'Xibo\Widget\Dashboard';
            $module->description = 'Securely connect to services like Microsoft PowerBI and display dashboards';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->viewPath = '../modules';

            // Set the newly created module and then call install
            $this->setModule($module);
            $this->installModule();
        }

        // Install and additional module files that are required.
        $this->installFiles();
    }
}
