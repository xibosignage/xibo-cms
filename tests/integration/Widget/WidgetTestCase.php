<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (WidgetTestCase.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\Tests\LocalWebTestCase;

class WidgetTestCase extends LocalWebTestCase
{
	 /**
     *  Preparing environment for tests
     */
    public function setupEnv()
    {
    	# Create layout with random name
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
    }
}