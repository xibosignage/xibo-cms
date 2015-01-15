<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-13 Daniel Garner
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

class oauthDAO extends baseDAO {

    /**
     * Display Page
     */
    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="oauth"><input type="hidden" name="q" value="Grid">');
        Theme::Set('pager', ResponseManager::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Applications'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    function actionMenu() {

        return array(
                array('title' => __('Add Application'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=oauth&q=RegisterForm',
                    'help' => __('Add an Application'),
                    'onclick' => ''
                    ),
                array('title' => __('View Activity'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=oauth&q=ViewLog',
                    'help' => __('View a log of application activity'),
                    'onclick' => ''
                    )
            );                   
    }

    /**
     * Display page grid
     */
    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

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

        $cols = array(
                array('name' => 'application_title', 'title' => __('Title')),
                array('name' => 'application_descr', 'title' => __('Description')),
                array('name' => 'application_uri', 'title' => __('Homepage')),
                array('name' => 'consumer_key', 'title' => __('Key')),
                array('name' => 'consumer_secret', 'title' => __('Secret'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($list as $app)
        {
            $app['application_title'] = Kit::ValidateParam($app['application_title'], _STRING);
            $app['application_descr'] = Kit::ValidateParam($app['application_descr'], _STRING);
            $app['application_uri'] = Kit::ValidateParam($app['application_uri'], _URI);
            $app['consumer_key'] = Kit::ValidateParam($app['consumer_key'], _STRING);
            $app['consumer_secret'] = Kit::ValidateParam($app['consumer_secret'], _STRING);

            $rows[] = $app;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * View the Log
     */
    public function ViewLog()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $store = OAuthStore::instance();

        try
        {
            $list = $store->listLog(null, $this->user->userid);
        }
        catch (OAuthException $e)
        {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $rows = array();

        foreach($list as $row)
        {
            $row['received'] = Kit::ValidateParam($row['received'], _STRING);
            $row['notes'] = Kit::ValidateParam($row['notes'], _STRING);
            $row['timestamp'] = Kit::ValidateParam($row['timestamp'], _STRING);

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('applications_form_view_log');

        $response->SetFormRequestResponse($output, __('OAuth Access Log'), '1000', '600');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Services&Category=Log')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->dialogClass = 'modal-big';
        $response->Respond();
    }

    /**
     * Authorize an OAuth request OR display the Authorize form.
     */
    public function authorize()
    {
        // Do we have an OAuth signed request?
        $userid = $this->user->userid;

        $server = new OAuthServer();

        // Request must be signed
        try
        {
            $consumerDetails = $server->authorizeVerify();

            // Has the user submitted the form?
            if ($_SERVER['REQUEST_METHOD'] == 'POST')
            {

                // See if the user clicked the 'allow' submit button
                if (isset($_POST['Allow']))
                    $authorized = true;
                else
                    $authorized = false;

                Debug::LogEntry('audit', 'Allow submitted. Application is ' . (($authorized) ? 'authed' : 'denied'));

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

                Theme::Set('application_title', $consumer['application_title']);
                Theme::Set('application_descr', $consumer['application_descr']);
                Theme::Set('application_uri', $consumer['application_uri']);

                Theme::Render('header');
                Theme::Render('application_verify');
                Theme::Render('footer');
                exit();
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

        Theme::Set('form_id', 'RegisterOAuth');
        Theme::Set('form_action', 'index.php?p=oauth&q=Register');

        $formFields = array();
        $formFields[] = FormManager::AddText('requester_name', __('Full Name'), NULL, 
            __('The name of the person or organization that authored this application.'), 'n', 'required');

        $formFields[] = FormManager::AddEmail('requester_email', __('Email Address'), NULL, 
            __('The email address of the person or organization that authored this application.'), 'e', 'required');

        $formFields[] = FormManager::AddText('application_uri', __('Application Homepage'), NULL, 
            __('The URL of your application homepage'), 'h', '');

        $formFields[] = FormManager::AddText('callback_uri', __('Application Homepage'), NULL, 
            __('The call back URL for requests'), 'c', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Registration for Consumer Information'), '550px', '475px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Services&Category=Register')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Register'), '$("#RegisterOAuth").submit()');
        $response->Respond();
    }

    /**
     * Register a new application with OAuth
     */
    public function Register()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $userid = Kit::GetParam('userid', _SESSION, _INT);

        $message = '';

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

        $rows = array();

        foreach ($list as $app)
        {
            $app['application_title'] = Kit::ValidateParam($app['application_title'], _STRING);
            $app['enabled'] = Kit::ValidateParam($app['enabled'], _STRING);
            $app['status'] = Kit::ValidateParam($app['status'], _STRING);

            $rows[] = $app;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('application_form_user_applications');

        $response->SetFormRequestResponse($output, __('Authorized applications for user'), '650', '450');
        $response->AddButton(__('Help'), "XiboHelpRender('" . HelpManager::Link('User', 'Applications') . "')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }
}
?>