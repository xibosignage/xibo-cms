<?php

namespace Xibo\Controller;

use GuzzleHttp\Psr7\Stream;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Factory\FontFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\Random;
use Xibo\Helper\UploadHandler;
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
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        $attachmentName = urlencode($font->fileName);
        $libraryPath = $library . 'fonts' . DIRECTORY_SEPARATOR . $font->fileName;

        $downLoadService = new DownloadService($libraryPath, $sendFileMode);
        $downLoadService->useLogger($this->getLog()->getLoggerInterface());

        return $downLoadService->returnFile($response, $attachmentName, '/font/download/' . $font->fileName);
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

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);
        $validExt = $this->getValidExtensions();

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'upload_dir' => $libraryFolder . 'temp/',
            'script_url' => $this->urlFor($request, 'font.add'),
            'upload_url' => $this->urlFor($request, 'font.add'),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->getMediaService()->libraryUsage() > $libraryLimit),
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        $uploadService = new UploadService($options, $this->getLog(), $this->getState());
        $uploadHandler = $uploadService->createUploadHandler();

        $uploadHandler->setPostProcessor(function ($file, $uploadHandler) {
            // Return right away if the file already has an error.
            if (!empty($file->error)) {
                return $file;
            }

            $this->getUser()->isQuotaFullByUser(true);

            /** @var UploadHandler $uploadHandler */
            $filePath = $uploadHandler->getUploadPath() . $file->fileName;
            $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

            // Add the Font
            $font = $this->getFontFactory()->createEmpty();
            $fontLib = \FontLib\Font::load($filePath);

            // check embed flag
            $embed = intval($fontLib->getData('OS/2', 'fsType'));
            // if it's not embeddable, throw exception
            if ($embed != 0 && $embed != 8) {
                throw new InvalidArgumentException(__('Font file is not embeddable due to its permissions'));
            }

            $name = ($file->name == '') ? $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily() : $file->name;

            $font->modifiedBy = $this->getUser()->userName;
            $font->name = $name;
            $font->fileName = $file->fileName;
            $font->size = filesize($filePath);
            $font->md5 = md5_file($filePath);
            $font->save();

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->size != $font->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            // everything is fine, move the file from temp folder.
            rename($filePath, $libraryLocation . 'fonts/' . $font->fileName);

            // return
            $file->id = $font->id;
            $file->md5 = $font->md5;

            return $file;
        });

        $uploadHandler->post();

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
        // Work out the etag
        $response = HttpCacheProvider::withEtag($response, md5($css['css']));

        // Return the CSS to the browser as a file
        $out = fopen($tempFileName, 'w');
        fputs($out, $css['css']);
        fclose($out);

        $this->setNoOutput(true);

        $response = $response->withHeader('Content-Type', 'text/css')
            ->withBody(new Stream(fopen($tempFileName, 'r')));

        return $this->render($request, $response);
    }
}
