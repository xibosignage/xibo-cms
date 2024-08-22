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

namespace Xibo\Helper;

use Xibo\Support\Exception\LibraryFullException;

/**
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName
 */
class UploadHandler extends BlueImpUploadHandler
{
    /**
     * @var callable
     */
    private $postProcess;

    /** @var ApplicationState */
    private $state;

    /**
     * Set post processor
     * @param callable $function
     */
    public function setPostProcessor(callable $function)
    {
        $this->postProcess = $function;
    }

    /**
     * @param ApplicationState $state
     * @return $this
     */
    public function setState(ApplicationState $state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * Handle form data from BlueImp
     * @param $file
     * @param $index
     */
    protected function handleFormData($file, $index)
    {
        try {
            $filePath = $this->getUploadDir() . $file->name;
            $file->fileName = $file->name;

            $name = htmlspecialchars($this->getParam($index, 'name', $file->name));
            $file->name = $name;

            // Check Library
            if ($this->options['libraryQuotaFull']) {
                throw new LibraryFullException(
                    sprintf(
                        __('Your library is full. Library Limit: %s K'),
                        $this->options['libraryLimit']
                    )
                );
            }

            $this->getLogger()->debug('Upload complete for name: ' . $name . '. Index is ' . $index);

            if ($this->postProcess !== null) {
                $file = call_user_func($this->postProcess, $file, $this);
            }
        } catch (\Exception $exception) {
            $this->getLogger()->error('Error uploading file : ' . $exception->getMessage());
            $this->getLogger()->debug($exception->getTraceAsString());

            // Unlink the temporary file
            @unlink($filePath);
            $this->state->setCommitState(false);
            $file->error = $exception->getMessage();
        }

        return $file;
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
