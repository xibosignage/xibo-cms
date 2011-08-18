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
        // Just a normal call to this page.
        include('template/pages/oauth_view.php');

        return false;
    }

    public function Filter()
    {
        $filterForm = <<<END
            <div class="FilterDiv" id="DisplayGroupFilter">
                <form onsubmit="return false">
                    <input type="hidden" name="p" value="oauth">
                    <input type="hidden" name="q" value="Grid">
                </form>
            </div>
END;

        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                $filterForm
            </div>
            <div class="XiboData">

            </div>
        </div>
HTML;
            echo $xiboGrid;
    }

    public function Grid()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $store = OAuthStore::instance();

        try
        {
            $list = $store->listConsumers($this->user->userid);
        }
        catch (OAuthException $e)
        {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Applications.'), E_USER_ERROR);
        }
        
        $msgTitle   = __('App Title');
        $msgDesc    = __('App Desc');
        $msgUri     = __('App Homepage');
        
        $msgConKey  = __('App Key');
        $msgConSecret = __('App Secret');
        
        $msgAction  = __('Action');
        $msgEdit    = __('Edit');

        $output = <<<END
<div class="info_table">
    <table style="width:100%">
        <thead>
        <tr>
            <th>$msgTitle</th>
            <th>$msgDesc</th>
            <th>$msgUri</th>
            <th>$msgConKey</th>
            <th>$msgConSecret</th>
            <th>$msgAction</th>
        </tr>
        </thead>
        <tbody>
END;

        foreach($list as $app)
        {
            $appId  = Kit::ValidateParam($app['id'], _INT);
            $title  = Kit::ValidateParam($app['application_title'], _STRING);
            $desc   = Kit::ValidateParam($app['application_descr'], _STRING);
            $url    = Kit::ValidateParam($app['application_uri'], _URI);
            $conKey = Kit::ValidateParam($app['consumer_key'], _STRING);
            $conSecret = Kit::ValidateParam($app['consumer_secret'], _STRING);

            $output .= '<tr>';
            $output .= '    <td>' . $title . '</td>';
            $output .= '    <td>' . $desc . '</td>';
            $output .= '    <td>' . $url . '</td>';
            $output .= '    <td>' . $conKey . '</td>';
            $output .= '    <td>' . $conSecret . '</td>';
            $output .= '</tr>';
        }

        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function ViewLog()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $store = OAuthStore::instance();

        try
        {
            $list = $store->listLog($this->user->userid);
        }
        catch (OAuthException $e)
        {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $output .= '<div class="info_table">';
        $output .= '    <table style="width:100%">';
        $output .= '        <thead>';
        $output .= sprintf('    <th>%s</th>', __('Header'));
        $output .= sprintf('    <th>%s</th>', __('Notes'));
        $output .= sprintf('    <th>%s</th>', __('Timestamp'));
        $output .= '        </thead>';
        $output .= '        <tbody>';

        foreach($list as $logEntry)
        {
            $header     = Kit::ValidateParam($logEntry['received'], _STRING);
            $notes      = Kit::ValidateParam($logEntry['notes'], _STRING);
            $timestamp  = Kit::ValidateParam($logEntry['timestamp'], _STRING);

            $output .= '<tr>';
            $output .= '<td>' . $header . '</td>';
            $output .= '<td>' . $notes . '</td>';
            $output .= '<td>' . $timestamp . '</td>';
            $output .= '</tr>';
        }

        $output .= '        </tbody>';
        $output .= '    </table>';
        $output .= '</div>';

        $response->SetFormRequestResponse($output, __('OAuth Access Log'), '1000', '600');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * Authorize an OAuth request OR display the Authorize form.
     */
    public function authorize()
    {
        // Do we have an OAuth signed request?
        $userid = Kit::GetParam('userid', _SESSION, _INT);

        $server = new OAuthServer();

        // Request must be signed
        try
        {
            $consumerDetails = $server->authorizeVerify();

            // Has the user submitted the form?
            if ($_SERVER['REQUEST_METHOD'] == 'POST')
            {
                // See if the user clicked the 'allow' submit button (or whatever you choose)
                if (isset($_POST['Allow']))
                    $authorized = true;
                else
                    $authorized = false;

                // Set the request token to be authorized or not authorized
                // When there was a oauth_callback then this will redirect to the consumer
                $server->authorizeFinish($authorized, $userid);

                // No oauth_callback, show the user the result of the authorization
                echo __('Request authorized. Please return to your application.');
           }
           else
           {
               // Not submitted the form, therefore we must show the login box.
               $store = OAuthStore::instance();
               $consumer = $store->getConsumer($consumerDetails['consumer_key'], $userid, true);
               
               include('template/pages/oauth_verify.php');
           }
        }
        catch (OAuthException $e)
        {
            // Unsigned request is not allowed.
            trigger_error($e->getMessage());
            trigger_error(__('Unsigned requests are not allowed to the authorize page.'), E_USER_ERROR);
        }
    }

    /**
     * Form to register a new application.
     */
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

    /**
     * Register a new application with OAuth
     */
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

    /**
     * Shows the Authorised applications this user has
     */
    public function UserTokens()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $store = OAuthStore::instance();

        try
        {
            $list = $store->listConsumerTokens(Kit::GetParam('userID', _GET, _INT));
        }
        catch (OAuthException $e)
        {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $output  = '<div class="info_table">';
        $output .= '    <table style="width:100%">';
        $output .= '        <thead>';
        $output .= sprintf('    <th>%s</th>', __('Application'));
        $output .= sprintf('    <th>%s</th>', __('Enabled'));
        $output .= sprintf('    <th>%s</th>', __('Status'));
        $output .= '        </thead>';
        $output .= '        <tbody>';

        foreach($list as $app)
        {
            $title      = Kit::ValidateParam($app['application_title'], _STRING);
            $enabled    = Kit::ValidateParam($app['enabled'], _STRING);
            $status     = Kit::ValidateParam($app['status'], _STRING);

            $output .= '<tr>';
            $output .= '<td>' . $title . '</td>';
            $output .= '<td>' . $enabled . '</td>';
            $output .= '<td>' . $status . '</td>';
            $output .= '</tr>';
        }

        $output .= '        </tbody>';
        $output .= '    </table>';
        $output .= '</div>';

        $response->SetFormRequestResponse($output, __('Authorized applications for user'), '650', '450');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }
}
?>