<?php

namespace Xibo\Controller;

use GuzzleHttp\Psr7\Stream;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Factory\FontFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\FontUploadHandler;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\Random;
use Xibo\Helper\XiboUploadHandler;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
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

    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $fonts = $this->fontFactory->query($this->gridRenderSort($parsedQueryParams), $this->gridRenderFilter([
            'id' => $parsedQueryParams->getInt('id'),
            'name' => $parsedQueryParams->getString('name'),
        ], $parsedQueryParams));

        foreach ($fonts as $font) {
            $font->fileSizeFormatted = ByteFormatter::format($font->size);
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
                'url' => $this->urlFor($request,'font.details', ['id' => $font->id]),
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

    public function download(Request $request, Response $response, $id)
    {
        if (is_numeric($id)) {
            $font = $this->fontFactory->getById($id);
        } else {
            $font = $this->fontFactory->getByName($id)[0];
        }

        $this->getLog()->debug('Download request for fontId ' . $id);

        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $libraryPath = $library . 'fonts/' . DIRECTORY_SEPARATOR . $font->fileName;
        $attachmentName = urlencode($font->fileName);
        // Issue some headers
        $response = HttpCacheProvider::withEtag($response, $font->md5);
        $response = HttpCacheProvider::withExpires($response, '+1 week');

        return $this->returnFile($response, $libraryPath, $attachmentName, '/font/download/' . $font->fileName);
    }

    /**
     * @return string[]
     */
    private function getValidExtensions()
    {
        return ['otf', 'ttf', 'eot', 'svg', 'woff'];
    }

    public function add(Request $request, Response $response)
    {
        if (!$this->getUser()->featureEnabled('font.add')) {
            throw new AccessDeniedException();
        }

        $parsedBody = $this->getSanitizer($request->getParams());
        $options = $parsedBody->getArray('options', ['default' => []]);

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);
        $validExt = $this->getValidExtensions();

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor($request,'font.add'),
            'upload_url' => $this->urlFor($request,'font.add'),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->getMediaService()->libraryUsage() > $libraryLimit),
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new FontUploadHandler($options);

        // all done, refresh fonts.css
        $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser());

        return $this->render($request, $response);
    }

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
        $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser());

        return $this->render($request, $response);
    }

    /**
     * Return the CMS flavored font css
     * @param Request|null $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontList(Request $request, Response $response)
    {
        // Regenerate the CSS for fonts
        $css = $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser(), ['invalidateCache' => false]);

        return $response->withJson([
            'list' => $css['list']
        ]);
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
        // Regenerate the CSS for fonts
        $css = $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser(), ['invalidateCache' => false]);
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/fontcss_' . Random::generateString();

        $response = $this->createTempFontCssFile($response, $tempFileName, $css['css']);

        $this->setNoOutput(true);

        $response = $response->withHeader('Content-Type', 'text/css')
            ->withBody(new Stream(fopen($tempFileName, 'r')));

        return $this->render($request, $response);
    }

    /**
     * Return the Player flavored font css
     * @param Request|null $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontPlayerCss(Request $request, Response $response)
    {
        // Regenerate the CSS for fonts
        $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser(), ['invalidateCache' => false]);
        $fontsCss = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'fonts/fonts.css';

        $this->setNoOutput(true);

        $response = $response->withHeader('Content-Type', 'text/css')
            ->withBody(new Stream(fopen($fontsCss, 'r')));

        return $this->render($request, $response);
    }

    public function downloadFontsCss(Request $request, Response $response)
    {
        // Regenerate the CSS for fonts
        $css = $this->getMediaService()->installFonts(RouteContext::fromRequest($request)->getRouteParser(), ['invalidateCache' => false]);
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/fontcss_' . Random::generateString();

        $params = $this->getSanitizer($request->getParams());
        $isPlayer = $params->getCheckbox('forPlayer');

        // which css file we want to return?
        $cssDetails = $isPlayer ? $css['playerCss'] : $css['css'];

        $response = $this->createTempFontCssFile($response, $tempFileName, $cssDetails);

        return $this->returnFile($response, $tempFileName, 'fonts.css', '/library/fontcss/download?forPlayer='.$isPlayer);
    }

    private function createTempFontCssFile($response, $tempFileName, $css)
    {
        // Work out the etag
        // Issue some headers
        $response = HttpCacheProvider::withEtag($response, md5($css));
        $response = HttpCacheProvider::withExpires($response, '+1 week');

        // Return the CSS to the browser as a file
        $out = fopen($tempFileName, 'w');
        fputs($out, $css);
        fclose($out);

        return $response;
    }

    private function returnFile($response, $filePath, $attachmentName, $nginxRedirect)
    {
        $this->setNoOutput(true);
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        // Set some headers
        $headers = [];
        $headers['Content-Length'] = filesize($filePath);
        $headers['Content-Type'] = 'application/octet-stream';
        $headers['Content-Transfer-Encoding'] = 'Binary';
        $headers['Content-disposition'] = 'attachment; filename="' . $attachmentName . '"';

        // Output the file
        if ($sendFileMode === 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers['X-Sendfile'] = $filePath;
        } else if ($sendFileMode === 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers['X-Accel-Redirect'] = $nginxRedirect;
        }

        // Add the headers we've collected to our response
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        // Should we output the file via the application stack, or directly by reading the file.
        if ($sendFileMode == 'Off') {
            // Return the file with PHP
            $response = $response->withBody(new Stream(fopen($filePath, 'r')));

            $this->getLog()->debug('Returning Stream with response body, sendfile off.');
        } else {
            $this->getLog()->debug('Using sendfile to return the file, only output headers.');
        }

        return $response;
    }
}