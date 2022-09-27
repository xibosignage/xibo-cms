<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\LibraryFullException;

class FontUploadHandler extends BlueImpUploadHandler
{
    /**
     * Handle form data from BlueImp
     * @param $file
     * @param $index
     */
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Font $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        // Link the file to the module
        $fileName = $file->name;
        $libraryLocation = $controller->getConfig()->getSetting('LIBRARY_LOCATION');
        $filePath = $libraryLocation . 'temp/' . $fileName;

        $controller->getLog()->debug('Upload complete for name: ' . $fileName . '. Index is ' . $index);

        // Upload and Save
        try {
            // Check Library
            if ($this->options['libraryQuotaFull']) {
                throw new LibraryFullException(
                    sprintf(
                        __('Your library is full. Library Limit: %s K'),
                        $this->options['libraryLimit']
                    )
                );
            }
            // Check for a user quota
            // this method has the ability to reconnect to MySQL in the event that the upload has taken a long time.
            // OSX-381
            $controller->getUser()->isQuotaFullByUser(true);

            // Get some parameters
            $name = $this->getParam($index, 'name', $fileName);

            // Add the Font
            $font = $controller->getFontFactory()->createEmpty();
            $fontLib = \FontLib\Font::load($filePath);

            // check embed flag
            $embed = intval($fontLib->getData('OS/2', 'fsType'));
            // if it's not embeddable, log error and skip it
            if ($embed != 0 && $embed != 8) {
                throw new InvalidArgumentException(__('Font file is not embeddable due to its permissions'));
            }

            $name = ($name == '') ? $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily() : $name;

            $font->modifiedBy = $controller->getUser()->userName;
            $font->name = $name;
            $font->fileName = $fileName;
            $font->size = filesize($filePath);
            $font->md5 = md5_file($filePath);
            $font->save();

            // Configure the return values according to the media item we've added
            $file->name = $name;
            $file->id = $font->id;
            $file->fileSize = $font->size;
            $file->md5 = $font->md5;
            $file->fileName = $fileName;

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->fileSize != $file->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            // everything is fine, move the file from temp folder.
            rename($filePath, $libraryLocation . 'fonts/' . $font->fileName);
        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading font: ' . $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            // Unlink the temporary file
            @unlink($filePath);

            $file->error = $e->getMessage();

            // Don't commit
            $controller->getState()->setCommitState(false);
        }
    }

    /**
     * Get Param from File Input, taking into account multi-upload index if applicable
     * @param int $index
     * @param string $param
     * @param mixed $default
     * @return mixed
     */
    private function getParam($index, $param, $default)
    {
        if ($index === null) {
            if (isset($_REQUEST[$param])) {
                return $_REQUEST[$param];
            } else {
                return $default;
            }
        } else {
            if (isset($_REQUEST[$param][$index])) {
                return $_REQUEST[$param][$index];
            } else {
                return $default;
            }
        }
    }
}