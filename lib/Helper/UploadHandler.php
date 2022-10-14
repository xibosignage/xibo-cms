<?php

namespace Xibo\Helper;

use Xibo\Service\LogServiceInterface;
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
    /** @var LogServiceInterface */
    private $logger;
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
     * @param LogServiceInterface $logger
     * @return $this
     */
    public function setLogger(LogServiceInterface $logger)
    {
        $this->logger = $logger;
        return $this;
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
     * Get Upload path
     * @return string
     */
    public function getUploadPath()
    {
        return $this->options['upload_dir'];
    }

    /**
     * Handle form data from BlueImp
     * @param $file
     * @param $index
     */
    protected function handle_form_data($file, $index)
    {
        try {
            $filePath = $this->getUploadPath() . $file->name;
            $file->fileName = $file->name;

            $name = $this->getParam($index, 'name', $file->name);
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

            $this->logger->debug('Upload complete for name: ' . $name . '. Index is ' . $index);

            if ($this->postProcess !== null) {
                $file = call_user_func($this->postProcess, $file, $this);
            }
        } catch (\Exception $exception) {
            $this->logger->error('Error uploading file : ' . $exception->getMessage());
            $this->logger->debug($exception->getTraceAsString());

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
