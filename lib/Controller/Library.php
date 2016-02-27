<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner, Spring Signage Ltd
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

use Xibo\Entity\Media;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Helper\XiboUploadHandler;


class Library extends Base
{
    /**
     * Displays the page logic
     */
    function displayPage()
    {
        // Users we have permission to see
        $this->getState()->template = 'library-page';
        $this->getState()->setData([
            'users' => (new UserFactory($this->getApp()))->query(),
            'modules' => (new ModuleFactory($this->getApp()))->query(['module'], ['regionSpecific' => 0, 'enabled' => 1])
        ]);
    }

    /**
     * Prints out a Table of all media items
     *
     * @SWG\Get(
     *  path="/library",
     *  operationId="librarySearch",
     *  tags={"library"},
     *  summary="Library Search",
     *  description="Search the Library for this user",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="formData",
     *      description="Filter by Media Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="media",
     *      in="formData",
     *      description="Filter by Media Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="Filter by Media Type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="formData",
     *      description="Filter by Owner Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Filter by Retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Media")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $user = $this->getUser();

        // Construct the SQL
        $mediaList = (new MediaFactory($this->getApp()))->query($this->gridRenderSort(), $this->gridRenderFilter([
            'mediaId' => Sanitize::getInt('mediaId'),
            'name' => Sanitize::getString('media'),
            'type' => Sanitize::getString('type'),
            'tags' => Sanitize::getString('tags'),
            'ownerId' => Sanitize::getInt('ownerId'),
            'retired' => Sanitize::getInt('retired')
        ]));

        // Add some additional row content
        foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $media->revised = ($media->parentId != 0) ? 1 : 0;

            // Thumbnail URL
            $media->thumbnail = '';

            if ($media->mediaType == 'image') {
                $download = $this->urlFor('library.download', ['id' => $media->mediaId]) . '?preview=1';
                $media->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            $media->fileSizeFormatted = ByteFormatter::format($media->fileSize);

            if ($this->isApi())
                break;

            $media->includeProperty('buttons');
            $media->buttons = array();

            // Buttons
            if ($user->checkEditable($media)) {
                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor('library.edit.form', ['id' => $media->mediaId]),
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($media)) {
                // Delete
                $media->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor('library.delete.form', ['id' => $media->mediaId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $media->buttons[] = array(
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Permissions')
                );
            }

            // Download
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor('library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = (new MediaFactory($this->getApp()))->countLast();
        $this->getState()->setData($mediaList);
    }

    /**
     * Media Delete Form
     * @param int $mediaId
     */
    public function deleteForm($mediaId)
    {
        $media = (new MediaFactory($this->getApp()))->getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        $media->load(['deleting' => true]);

        $this->getState()->template = 'library-form-delete';
        $this->getState()->setData([
            'media' => $media,
            'help' => $this->getHelp()->link('Library', 'Delete')
        ]);
    }

    /**
     * Delete Media
     * @param int $mediaId
     *
     * @SWG\Delete(
     *  path="/library/{mediaId}",
     *  operationId="libraryDelete",
     *  tags={"library"},
     *  summary="Delete Media",
     *  description="Delete Media from the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="forceDelete",
     *      in="formData",
     *      description="If the media item has been used should it be force removed from items that uses it?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($mediaId)
    {
        $media = (new MediaFactory($this->getApp()))->getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        // Check
        $media->load(['deleting' => true]);

        if ($media->isUsed() && Sanitize::getCheckbox('forceDelete') == 0)
            throw new \InvalidArgumentException(__('This library item is in use.'));

        // Delete
        $media->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $media->name)
        ]);
    }

    /**
     * Add a file to the library
     *  expects to be fed by the blueimp file upload handler
     * @throws \Exception
     *
     * @SWG\Post(
     *  path="/library",
     *  operationId="libraryAdd",
     *  tags={"library"},
     *  summary="Add Media",
     *  description="Add Media to the Library",
     *  @SWG\Parameter(
     *      name="file",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function add()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        $this->ensureLibraryExists();

        // Get Valid Extensions
        if (Sanitize::getInt('oldMediaId') !== null) {
            $media = (new MediaFactory($this->getApp()))->getById(Sanitize::getInt('oldMediaId'));
            $validExt = (new ModuleFactory($this->getApp()))->getValidExtensions(['type' => $media->mediaType]);
        }
        else
            $validExt = (new ModuleFactory($this->getApp()))->getValidExtensions();

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'oldMediaId' => Sanitize::getInt('oldMediaId'),
            'widgetId' => Sanitize::getInt('widgetId'),
            'updateInLayouts' => Sanitize::getCheckbox('updateInLayouts'),
            'deleteOldRevisions' => Sanitize::getCheckbox('deleteOldRevisions'),
            'playlistId' => Sanitize::getInt('playlistId'),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('library.add'),
            'upload_url' => $this->urlFor('library.add'),
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i'
        );

        // Make sure there is room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        if ($libraryLimit > 0 && $this->libraryUsage() > $libraryLimit)
            throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));

        // Check for a user quota
        $this->getUser()->isQuotaFullByUser();
        $this->setNoOutput(true);

        try {
            $this->getLog()->debug('Hand off to Upload Handler with options: %s', json_encode($options));
            // Hand off to the Upload Handler provided by jquery-file-upload
            new XiboUploadHandler($options);
        }
        catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
        }
    }

    /**
     * Edit Form
     * @param int $mediaId
     */
    public function editForm($mediaId)
    {
        $media = (new MediaFactory($this->getApp()))->getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $this->getState()->template = 'library-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'validExtensions' => implode('|', (new ModuleFactory($this->getApp()))->getValidExtensions(['type' => $media->mediaType])),
            'help' => $this->getHelp()->link('Library', 'Edit')
        ]);
    }

    /**
     * Edit Media
     * @param int $mediaId
     *
     * @SWG\Put(
     *  path="/library/{mediaId}",
     *  operationId="libraryEdit",
     *  tags={"library"},
     *  summary="Edit Media",
     *  description="Edit a Media Item in the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Media Item Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Media Item",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Flag indicating if this Layout is retired",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated list of Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInLayouts",
     *      in="formData",
     *      description="Flag indicating whether to update the duration in all Layouts the Media is assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     */
    public function edit($mediaId)
    {
        $media = (new MediaFactory($this->getApp()))->getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $media->name = Sanitize::getString('name');
        $media->duration = Sanitize::getInt('duration');
        $media->retired = Sanitize::getCheckbox('retired');
        $media->replaceTags((new TagFactory($this->getApp()))->tagsFromString(Sanitize::getString('tags')));

        // Should we update the media in all layouts?
        if (Sanitize::getCheckbox('updateInLayouts') == 1) {
            foreach ((new WidgetFactory($this->getApp()))->getByMediaId($media->mediaId) as $widget) {
                /* @var Widget $widget */
                $widget->duration = $media->duration;
                $widget->save();
            }
        }

        $media->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);
    }

    /**
     * Tidy Library
     */
    public function tidyForm()
    {
        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Work out how many files there are
        $media = (new MediaFactory($this->getApp()))->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $size = ByteFormatter::format(array_sum(array_map(function ($element) {
            return $element->fileSize;
        }, $media)));

        $this->getState()->template = 'library-form-tidy';
        $this->getState()->setData([
            'size' => $size,
            'quantity' => count($media),
            'help' => $this->getHelp()->link('Content', 'TidyLibrary')
        ]);
    }

    /**
     * Tidies up the library
     *
     * @SWG\Post(
     *  path="/library/tidy",
     *  operationId="libraryTidy",
     *  tags={"library"},
     *  summary="Tidy Library",
     *  description="Routine tidy of the library, removing unused files.",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function tidy()
    {
        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Get a list of media that is not in use (for this user)
        $media = (new MediaFactory($this->getApp()))->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $i = 0;
        foreach ($media as $item) {
            /* @var Media $item */
            $i++;
            $item->load();
            $item->delete();
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'countDeleted' => $i
        ]);
    }

    /**
     * Make sure the library exists
     * @throws ConfigurationException when the library is not writable
     */
    public static function ensureLibraryExists()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
            mkdir($libraryFolder, 0777, true);

        if (!file_exists($libraryFolder . '/temp'))
            mkdir($libraryFolder . '/temp', 0777, true);

        if (!file_exists($libraryFolder . '/cache'))
            mkdir($libraryFolder . '/cache', 0777, true);

        if (!file_exists($libraryFolder . '/screenshots'))
            mkdir($libraryFolder . '/screenshots', 0777, true);

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
            throw new ConfigurationException(__('Library not writable'));
    }

    public static function getLibraryCacheUri()
    {
        return Config::GetSetting('LIBRARY_LOCATION') . '/cache';
    }

    /**
     * Library Usage
     * @return int
     */
    public static function libraryUsage()
    {
        $results = $this->getStore()->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return Sanitize::int($results[0]['SumSize']);
    }

    /**
     * Gets a file from the library
     * @param int $mediaId
     * @param string $type
     *
     * @SWG\Get(
     *  path="/library/download/{mediaId}/{type}",
     *  operationId="libraryDownload",
     *  tags={"library"},
     *  summary="Download Media",
     *  description="Download a Media file from the Library",
     *  produces=["application/octet-stream"],
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="The Module Type of the Download",
     *      type="string",
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
     */
    public function download($mediaId, $type = '')
    {
        $this->getLog()->debug('Download request for mediaId %d and type %s', $mediaId, $type);

        $media = (new MediaFactory($this->getApp()))->getById($mediaId);

        if (!$this->getUser()->checkViewable($media))
            throw new AccessDeniedException();

        if ($type != '') {
            $widget = (new ModuleFactory($this->getApp()))->create($type);
            $widgetOverride = new Widget();
            $widgetOverride->assignMedia($media->mediaId);
            $widget->setWidget($widgetOverride);

        } else {
            // Make a media module
            $widget = (new ModuleFactory($this->getApp()))->createWithMedia($media);
        }

        $widget->getResource();

        $this->setNoOutput(true);
    }

    /**
     * Installs fonts
     */
    public function installFonts()
    {
        $fontTemplate = '
@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}
        ';

        // Save a fonts.css file to the library for use as a module
        $fonts = (new MediaFactory($this->getApp()))->getByMediaType('font');

        if (count($fonts) < 1)
            return;

        $css = '';
        $localCss = '';
        $ckEditorString = '';

        foreach ($fonts as $font) {
            /* @var Media $font */

            // Separate out the display name and the referenced name (referenced name cannot contain any odd characters or numbers)
            $displayName = $font->name;
            $familyName = preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name));

            // Css for the client contains the actual stored as location of the font.
            $css .= str_replace('[url]', $font->storedAs, str_replace('[family]', $familyName, $fontTemplate));

            // Css for the local CMS contains the full download path to the font
            $url = $this->urlFor('library.download', ['type' => 'font', 'id' => $font->mediaId]) . '?download=1&downloadFromLibrary=1';
            $localCss .= str_replace('[url]', $url, str_replace('[family]', $familyName, $fontTemplate));

            // CKEditor string
            $ckEditorString .= $displayName . '/' . $familyName . ';';
        }

        // Put the player CSS into the modules font.css file so that we can copy it into the library
        file_put_contents(PROJECT_ROOT . '/web/modules/fonts.css', $css);

        // Install it (doesn't expire, isn't a system file, force update)
        $media = (new MediaFactory($this->getApp()))->createModuleSystemFile('fonts.css', PROJECT_ROOT . '/web/modules/fonts.css');
        $media->expires = 0;
        $media->moduleSystemFile = true;
        $media->force = true;
        $media->save();

        // Generate a fonts.css file for use locally (in the CMS)
        file_put_contents(PROJECT_ROOT . '/web/modules/fonts.css', $localCss);

        // Edit the CKEditor file
        $ckEditor = file_get_contents(Theme::uri('libraries/ckeditor/config.js', true));
        $replace = "/*REPLACE*/ config.font_names = '" . $ckEditorString . "' + config.font_names; /*ENDREPLACE*/";

        $ckEditor = preg_replace('/\/\*REPLACE\*\/.*?\/\*ENDREPLACE\*\//', $replace, $ckEditor);

        file_put_contents(Theme::uri('libraries/ckeditor/config.js', true), $ckEditor);
    }

    /**
     * Installs all files related to the enabled modules
     */
    public static function installAllModuleFiles($app)
    {
        $this->getLog()->info('Installing all module files');

        // Do this for all enabled modules
        foreach ((new ModuleFactory($app))->query() as $module) {
            /* @var \Xibo\Entity\Module $module */

            // Install Files for this module
            $moduleObject = (new ModuleFactory($app))->create($module->type);
            $moduleObject->installFiles();
        }
    }

    /**
     * Remove temporary files
     */
    public static function removeTempFiles($app)
    {
        $library = Config::GetSetting('LIBRARY_LOCATION');

        // Dump the files in the temp folder
        foreach (scandir($library . 'temp') as $item) {
            if ($item == '.' || $item == '..')
                continue;

            $this->getLog()->debug('Deleting temp file: ' . $item);

            unlink($library . 'temp' . DIRECTORY_SEPARATOR . $item);
        }
    }

    /**
     * Removes all expired media files
     */
    public static function removeExpiredFiles($app)
    {
        // Get a list of all expired files and delete them
        foreach ((new MediaFactory($app))->query(null, array('expires' => time(), 'allModules' => 1)) as $entry) {
            /* @var \Xibo\Entity\Media $entry */
            // If the media type is a module, then pretend its a generic file
            $this->getLog()->info('Removing Expired File %s', $entry->name);
            $entry->delete();
        }
    }
}
