<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Controller;

use GuzzleHttp\Psr7\Stream;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Invalidation;
use Xibo\Factory\FontFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Service\DownloadService;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
use Xibo\Service\UploadService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class Font extends Base
{
    /**
     * @var FontFactory
     */
    private $fontFactory;
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    public function __construct(FontFactory $fontFactory)
    {
        $this->fontFactory = $fontFactory;
    }

    public function useMediaService(MediaServiceInterface $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getMediaService(): MediaServiceInterface
    {
        return $this->mediaService->setUser($this->getUser());
    }

    public function getFontFactory() : FontFactory
    {
        return $this->fontFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        if (!$this->getUser()->featureEnabled('font.view')) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'fonts-page';
        $this->getState()->setData([
            'validExt' => implode('|', $this->getValidExtensions())
        ]);

        return $this->render($request, $response);
    }

    /**
     * Prints out a Table of all Font items
     *
     * @SWG\Get(
     *  path="/fonts",
     *  operationId="fontSearch",
     *  tags={"font"},
     *  summary="Font Search",
     *  description="Search the available Fonts",
     *  @SWG\Parameter(
     *      name="id",
     *      in="query",
     *      description="Filter by Font Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="Filter by Font Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Font")
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $fonts = $this->fontFactory->query($this->gridRenderSort($parsedQueryParams), $this->gridRenderFilter([
            'id' => $parsedQueryParams->getInt('id'),
            'name' => $parsedQueryParams->getString('name'),
        ], $parsedQueryParams));

        foreach ($fonts as $font) {
            $font->setUnmatchedProperty('fileSizeFormatted', ByteFormatter::format($font->size));
            $font->buttons = [];
            if ($this->isApi($request)) {
                break;
            }

            // download the font file
            $font->buttons[] = [
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor($request, 'font.download', ['id' => $font->id]),
                'text' => __('Download')
            ];

            // font details from fontLib and preview text
            $font->buttons[] = [
                'id' => 'font_button_details',
                'url' => $this->urlFor($request, 'font.details', ['id' => $font->id]),
                'text' => __('Details')
            ];

            $font->buttons[] = ['divider' => true];

            if ($this->getUser()->featureEnabled('font.delete')) {
                // Delete Button
                $font->buttons[] = [
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor($request, 'font.form.delete', ['id' => $font->id]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'font.delete', ['id' => $font->id])
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'content_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $font->name]
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->fontFactory->countLast();
        $this->getState()->setData($fonts);

        return $this->render($request, $response);
    }

    /**
     * Font details provided by FontLib
     *
     * @SWG\Get(
     *  path="/fonts/details/{id}",
     *  operationId="fontDetails",
     *  tags={"font"},
     *  summary="Font Details",
     *  description="Get the Font details",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Font ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={
     *              "title"="details",
     *              "type"="array"
     *          }
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \FontLib\Exception\FontNotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getFontLibDetails(Request $request, Response $response, $id)
    {
        $font = $this->fontFactory->getById($id);
        $fontLib = \FontLib\Font::load($font->getFilePath());
        $fontLib->parse();

        $fontDetails = [
            'Name' => $fontLib->getFontName(),
            'SubFamily Name' => $fontLib->getFontSubfamily(),
            'Subfamily ID' => $fontLib->getFontSubfamilyID(),
            'Full Name' => $fontLib->getFontFullName(),
            'Version' => $fontLib->getFontVersion(),
            'Font Weight' => $fontLib->getFontWeight(),
            'Font Postscript Name' => $fontLib->getFontPostscriptName(),
            'Font Copyright' => $fontLib->getFontCopyright(),
        ];

        $this->getState()->template = 'fonts-fontlib-details';
        $this->getState()->setData([
            'details' => $fontDetails,
            'fontId' => $font->id
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/fonts/download/{id}",
     *  operationId="fontDownload",
     *  tags={"font"},
     *  summary="Download Font",
     *  description="Download a Font file from the Library",
     *  produces={"application/octet-stream"},
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Font ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(type="file"),
     *      @SWG\Header(
     *          header="X-Sendfile",
     *          description="Apache Send file header - if enabled.",
     *          type="string"
     *      ),
     *      @SWG\Header(
     *          header="X-Accel-Redirect",
     *          description="nginx send file header - if enabled.",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function download(Request $request, Response $response, $id)
    {
        if (is_numeric($id)) {
            $font = $this->fontFactory->getById($id);
        } else {
            $font = $this->fontFactory->getByName($id)[0];
        }

        $this->getLog()->debug('Download request for fontId ' . $id);

        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        $attachmentName = urlencode($font->fileName);
        $libraryPath = $library . 'fonts' . DIRECTORY_SEPARATOR . $font->fileName;

        $downLoadService = new DownloadService($libraryPath, $sendFileMode);
        $downLoadService->useLogger($this->getLog()->getLoggerInterface());

        return $downLoadService->returnFile($response, $attachmentName, '/download/fonts/' . $font->fileName);
    }

    /**
     * @return string[]
     */
    private function getValidExtensions()
    {
        return ['otf', 'ttf', 'eot', 'svg', 'woff'];
    }

    /**
     * Font Upload
     *
     * @SWG\Post(
     *  path="/fonts",
     *  operationId="fontUpload",
     *  tags={"font"},
     *  summary="Font Upload",
     *  description="Upload a new Font file",
     *  @SWG\Parameter(
     *      name="files",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Font Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        if (!$this->getUser()->featureEnabled('font.add')) {
            throw new AccessDeniedException();
        }

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);
        $validExt = $this->getValidExtensions();

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->getMediaService()->libraryUsage() > $libraryLimit),
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        $uploadService = new UploadService($libraryFolder . 'temp/', $options, $this->getLog(), $this->getState());
        $uploadHandler = $uploadService->createUploadHandler();

        $uploadHandler->setPostProcessor(function ($file, $uploadHandler) use ($libraryFolder) {
            // Return right away if the file already has an error.
            if (!empty($file->error)) {
                return $file;
            }

            $this->getUser()->isQuotaFullByUser(true);

            // Get the uploaded file and move it to the right place
            $filePath = $libraryFolder . 'temp/' . $file->fileName;

            // Add the Font
            $font = $this->getFontFactory()
                ->createFontFromUpload($filePath, $file->name, $file->fileName, $this->getUser()->userName);
            $font->save();

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->size != $font->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            // everything is fine, move the file from temp folder.
            rename($filePath, $libraryFolder . 'fonts/' . $font->fileName);

            // return
            $file->id = $font->id;
            $file->md5 = $font->md5;
            $file->name = $font->name;

            return $file;
        });

        // Handle the post request
        $uploadHandler->post();

        // all done, refresh fonts.css
        $this->getMediaService()->updateFontsCss();

        // Explicitly set the Content-Type header to application/json
        $response = $response->withHeader('Content-Type', 'application/json');

        return $this->render($request, $response);
    }

    /**
     * Font Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        if (!$this->getUser()->featureEnabled('font.delete')) {
            throw new AccessDeniedException();
        }

        if (is_numeric($id)) {
            $font = $this->fontFactory->getById($id);
        } else {
            $font = $this->fontFactory->getByName($id)[0];
        }

        $this->getState()->template = 'font-form-delete';
        $this->getState()->setData([
            'font' => $font
        ]);

        return $this->render($request, $response);
    }

    /**
     * Font Delete
     *
     * @SWG\Delete(
     *  path="/fonts/{id}/delete",
     *  operationId="fontDelete",
     *  tags={"font"},
     *  summary="Font Delete",
     *  description="Delete existing Font file",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Font ID to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function delete(Request $request, Response $response, $id)
    {
        if (!$this->getUser()->featureEnabled('font.delete')) {
            throw new AccessDeniedException();
        }

        if (is_numeric($id)) {
            $font = $this->fontFactory->getById($id);
        } else {
            $font = $this->fontFactory->getByName($id)[0];
        }

        // delete record and file
        $font->delete();

        // refresh fonts.css
        $this->getMediaService()->updateFontsCss();

        return $this->render($request, $response);
    }

    /**
     * Return the CMS flavored font css
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontCss(Request $request, Response $response)
    {
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'fonts/local_fontcss';

        $cacheItem = $this->getMediaService()->getPool()->getItem('localFontCss');
        $cacheItem->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        if ($cacheItem->isMiss()) {
            $this->getLog()->debug('local font css cache has expired, regenerating');

            $cacheItem->lock(60);
            $localCss = '';
            // Regenerate the CSS for fonts
            foreach ($this->fontFactory->query() as $font) {
                // Go through all installed fonts each time and regenerate.
                $fontTemplate = '@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}';
                // Css for the local CMS contains the full download path to the font
                $url = $this->urlFor($request, 'font.download', ['id' => $font->id]);
                $localCss .= str_replace('[url]', $url, str_replace('[family]', $font->familyName, $fontTemplate));
            }

            // cache
            $cacheItem->set($localCss);
            $cacheItem->expiresAfter(new \DateInterval('P30D'));
            $this->getMediaService()->getPool()->saveDeferred($cacheItem);
        } else {
            $this->getLog()->debug('local font css file served from cache ');
            $localCss = $cacheItem->get();
        }

        // Return the CSS to the browser as a file
        $out = fopen($tempFileName, 'w');
        if (!$out) {
            throw new ConfigurationException(__('Unable to write to the library'));
        }
        fputs($out, $localCss);
        fclose($out);

        // Work out the etag
        $response = HttpCacheProvider::withEtag($response, md5($localCss));

        $this->setNoOutput(true);

        $response = $response->withHeader('Content-Type', 'text/css')
            ->withBody(new Stream(fopen($tempFileName, 'r')));

        return $this->render($request, $response);
    }
}
