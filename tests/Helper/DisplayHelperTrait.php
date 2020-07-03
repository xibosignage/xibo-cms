<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (DisplayHelperTrait.php)
 */


namespace Xibo\Tests\Helper;


use Carbon\Carbon;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Trait DisplayHelperTrait
 * @package Helper
 */
trait DisplayHelperTrait
{
    /**
     * @param int $status
     * @param string $type
     * @return XiboDisplay
     * @throws \Exception
     */
    protected function createDisplay($status = null, $type = 'windows')
    {
        // Generate names for display and xmr channel
        $hardwareId = Random::generateString(12, 'phpunit');
        $xmrChannel = Random::generateString(50);

        $this->getLogger()->debug('Creating Display called ' . $hardwareId);

        // This is a dummy pubKey and isn't used by anything important
        $xmrPubkey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDmdnXL4gGg3yJfmqVkU1xsGSQI
3b6YaeAKtWuuknIF1XAHAHtl3vNhQN+SmqcNPOydhK38OOfrdb09gX7OxyDh4+JZ
inxW8YFkqU0zTqWaD+WcOM68wTQ9FCOEqIrbwWxLQzdjSS1euizKy+2GcFXRKoGM
pbBhRgkIdydXoZZdjQIDAQAB
-----END PUBLIC KEY-----';

        // Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId,
            $hardwareId,
            $type,
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $xmrChannel,
            $xmrPubkey
        );

        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);

        if (count($displays) != 1)
            $this->fail('Display was not added correctly');

        /** @var XiboDisplay $display */
        $display = $displays[0];

        // Set the initial status
        if ($status !== null)
            $this->displaySetStatus($display, $status);

        return $display;
    }

    /**
     * @param XiboDisplay $display
     * @param int $status
     */
    protected function displaySetStatus($display, $status)
    {
        $display->mediaInventoryStatus = $status;

        $this->getStore()->update('UPDATE `display` SET MediaInventoryStatus = :status, auditingUntil = :auditingUntil WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'auditingUntil' => Carbon::now()->addSeconds(86400)->format('U'),
            'status' => $status
        ]);
        $this->getStore()->commitIfNecessary();
        $this->getStore()->close();
    }

    /**
     * @param XiboDisplay $display
     */
    protected function displaySetLicensed($display)
    {
        $display->licensed = 1;

        $this->getStore()->update('UPDATE `display` SET licensed = 1, auditingUntil = :auditingUntil WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'auditingUntil' => Carbon::now()->addSeconds(86400)->format('U')
        ]);
        $this->getStore()->commitIfNecessary();
        $this->getStore()->close();
    }

    /**
     * @param XiboDisplay $display
     * @param string $timeZone
     */
    protected function displaySetTimezone($display, $timeZone)
    {
        $this->getStore()->update('UPDATE `display` SET timeZone = :timeZone WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'timeZone' => $timeZone
        ]);
        $this->getStore()->commitIfNecessary();
        $this->getStore()->close();
    }

    /**
     * @param XiboDisplay $display
     */
    protected function deleteDisplay($display)
    {
        $display->delete();
    }

    /**
     * @param XiboDisplay $display
     * @param int $status
     * @return bool
     */
    protected function displayStatusEquals($display, $status)
    {
        // Requery the Display
        try {
            $check = (new XiboDisplay($this->getEntityProvider()))->getById($display->displayId);

            $this->getLogger()->debug('Tested Display ' . $display->display . '. Status returned is ' . $check->mediaInventoryStatus);

            return $check->mediaInventoryStatus === $status;

        } catch (XiboApiException $xiboApiException) {
            $this->getLogger()->error('API exception for ' . $display->displayId. ': ' . $xiboApiException->getMessage());
            return false;
        }

    }
}