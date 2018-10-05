<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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


use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Clock
 * @package Xibo\Controller
 */
class Clock extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->session = $session;
    }

    /**
     * Gets the Time
     *
     * @SWG\Get(
     *  path="/clock",
     *  operationId="clock",
     *  tags={"misc"},
     *  description="The Time",
     *  summary="The current CMS time",
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={"title":"time", "type":"string"}
     *      )
     *  )
     * )
     *
     * @throws \Exception
     */
    public function clock()
    {
        $this->session->refreshExpiry = false;

        if ($this->getApp()->request()->isAjax() || $this->isApi()) {
            $output = $this->getDate()->getLocalDate(null, 'H:i T');

            $this->getState()->setData(array('time' => $output));
            $this->getState()->html = $output;
            $this->getState()->clockUpdate = true;
            $this->getState()->success = true;
        } else {
            $this->setNoOutput(true);
            echo $this->getDate()->getLocalDate(null, 'c');
        }
    }
}
