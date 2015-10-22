<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerActionHelper.php)
 */


namespace Xibo\Helper;


use Xibo\Entity\Display;
use Xibo\Exception\ConfigurationException;
use Xibo\XMR\PlayerAction;
use Xibo\XMR\PlayerActionException;

class PlayerActionHelper
{
    /**
     * @param array[Display]|Display $displays
     * @param PlayerAction $action
     * @throws ConfigurationException
     */
    public static function sendAction($displays, $action)
    {
        if (!is_array($displays))
            $displays = [$displays];

        // XMR network address
        $xmrAddress = Config::GetSetting('XMR_ADDRESS');

        if ($xmrAddress == '')
            throw new \InvalidArgumentException(__('XMR address is not set'));

        // Send a message to all displays
        foreach ($displays as $display) {
            /* @var Display $display */
            if ($display->xmrChannel == '' || $display->xmrPubKey == '')
                throw new \InvalidArgumentException(__('This Player is not configured or ready to receive XMR commands'));

            try {
                // Assign the Layout to the Display
                if (!$action->setIdentity($display->xmrChannel, $display->xmrPubKey)->send($xmrAddress))
                    throw new ConfigurationException(__('This command has been refused'));

            } catch (PlayerActionException $sockEx) {
                Log::emergency('XMR Connection Failure: %s', $sockEx->getMessage());
                Log::debug('XMR Connection Failure, trace: %s', $sockEx->getTraceAsString());
                throw new ConfigurationException(__('Connection Failed'));
            }
        }
    }
}