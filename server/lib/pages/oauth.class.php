<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class oauthDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;
    }

    function displayPage()
    {
        include('template/pages/oauth_view.php');
        return false;
    }

    public function RegisterForm()
    {
        $db 		=& $this->db;
        $user		=& $this->user;
        $response	= new ResponseManager();

        $msgCancel      = __('Cancel');
        $msgRegister    = __('Register');

        $form = <<<END

<form id="RegisterOAuth" class="XiboForm" method="post" action="index.php?p=oauth&q=Register">
    <fieldset>
	<legend>About You</legend>

	<p>
	    <label for="requester_name">Your name</label><br/>
	    <input class="text required" id="requester_name"  name="requester_name" type="text" value="" />
	</p>

	<p>
	    <label for="requester_email">Your email address</label><br/>
	    <input class="email required" id="requester_email"  name="requester_email" type="text" value="" />
	</p>
    </fieldset>

    <fieldset>
	<legend>Location Of Your Application Or Site</legend>

	<p>
	    <label for="application_uri">URL of your application or site</label><br/>
	    <input id="application_uri" class="text" name="application_uri" type="text" value="" />
	</p>

	<p>
	    <label for="callback_uri">Callback URL</label><br/>
	    <input id="callback_uri" class="text" name="callback_uri" type="text" value="" />
	</p>
    </fieldset>
</form>

END;

        $response->SetFormRequestResponse($form, __('Registration for Consumer Information'), '550px', '475px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Services&Category=Register')");
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgRegister, '$("#RegisterOAuth").submit()');
        $response->Respond();
    }

    public function Register()
    {
        $db 		=& $this->db;
        $user		=& $this->user;
        $response	= new ResponseManager();
        $userid         = Kit::GetParam('userid', _SESSION, _INT);

        $message        = '';

        try
	{
            $store = OAuthStore::instance();
            $key   = $store->updateConsumer($_POST, $userid);

            $c = $store->getConsumer($key, $userid);

            $message .= sprintf(__('Your consumer key is: %s'),$c['consumer_key']) . '<br />';
            $message .= sprintf(__('Your consumer secret is: %s'), $c['consumer_secret']) . '<br />';
	}
	catch (OAuthException $e)
	{
            trigger_error('Error: ' . $e->getMessage(), E_USER_ERROR);
	}

        $response->SetFormSubmitResponse($message, false);
	$response->Respond();
    }

    function on_page_load()
    {
        return '';
    }

    function echo_page_heading()
    {
        return true;
    }
}
?>