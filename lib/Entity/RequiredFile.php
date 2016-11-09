<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Nonce.php)
 */


namespace Xibo\Entity;


use Xibo\Exception\FormExpiredException;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Helper\ObjectVars;
use Xibo\Helper\Random;
use Xibo\Service\LogServiceInterface;

/**
 * Class RequiredFile
 * @package Xibo\Entity
 */
class RequiredFile
{
    public $nonce;
    public $expiry;
    public $lastUsed;
    public $displayId;
    public $size;
    public $storedAs;
    public $layoutId;
    public $regionId;
    public $mediaId;
    public $bytesRequested = 0;
    public $complete = 0;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  RequiredFileFactory */
    private $requiredFileFactory;

    /** @inheritdoc */
    public function __sleep()
    {
        return ['nonce', 'expiry', 'lastUsed', 'displayId', 'size', 'storedAs', 'layoutId', 'regionId', 'mediaId', 'bytesRequested', 'complete'];
    }

    /**
     * Entity constructor.
     * @param LogServiceInterface $log
     * @param RequiredFileFactory $requiredFileFactory
     * @return $this
     */
    public function setDependencies($log, $requiredFileFactory)
    {
        $this->log = $log;
        $this->requiredFileFactory = $requiredFileFactory;
        return $this;
    }

    /**
     * @return LogServiceInterface
     */
    private function getLog()
    {
        return $this->log;
    }

    /**
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'refreshNonce' => true,
            'refreshExpiry' => false
        ], $options);

        $originalNonce = '';

        // Always update the nonce when we save
        if ($options['refreshNonce']) {
            $this->lastUsed = 0;
            $this->expiry = time() + 86400;
            $originalNonce = $this->nonce;
            $this->nonce = md5(Random::generateString() . SECRET_KEY . time() . $this->layoutId . $this->regionId . $this->mediaId);
        } else if ($options['refreshExpiry']) {
            $this->lastUsed = 0;
            $this->expiry = time() + 86400;
        }

        $this->requiredFileFactory->addOrReplace($this, ($options['refreshNonce'] ? $originalNonce : $this->nonce));
    }

    public function resetExpiry()
    {
        $this->expiry = time() + 86400;
    }

    public function expireSoon()
    {
        if (!$this->isExpired())
            $this->expiry = time() + 120;
    }

    /**
     * Is expired?
     * @return bool
     */
    public function isExpired()
    {
        return ($this->expiry < time());
    }

    /**
     * Is valid?
     * @throws FormExpiredException
     */
    public function isValid()
    {
        $this->getLog()->debug('Checking validity ' . json_encode($this));

        if (($this->lastUsed != 0 && $this->bytesRequested > $this->size) || $this->isExpired())
            throw new FormExpiredException('File expired or used');
    }

    /**
     * Mark this file as used
     */
    public function markUsed()
    {
        $this->getLog()->debug('Marking ' . $this->nonce . ' as used');
        $this->lastUsed = time();
        $this->save(['refreshNonce' => false]);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($properties as $key => $value) {
            $json[$key] = $value;
        }
        return $json;
    }
}