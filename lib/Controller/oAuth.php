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
namespace Xibo\Controller;

use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class OAuth extends Base
{

    /**
     * Display Page
     */
    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="oauth"><input type="hidden" name="q" value="Grid">');
        Theme::Set('pager', ApplicationState::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Applications'));
        Theme::Set('form_fields', array());
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

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

        $user = $this->getUser();
        $response = $this->getState();

        $store = OAuthStore::instance();

        try {
            $list = $store->listConsumers($this->getUser()->userId);
        } catch (OAuthException $e) {
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

        foreach ($list as $app) {
            $app['application_title'] = \Xibo\Helper\Sanitize::string($app['application_title']);
            $app['application_descr'] = \Xibo\Helper\Sanitize::string($app['application_descr']);
            $app['application_uri'] = \Kit::ValidateParam($app['application_uri'], _URI);
            $app['consumer_key'] = \Xibo\Helper\Sanitize::string($app['consumer_key']);
            $app['consumer_secret'] = \Xibo\Helper\Sanitize::string($app['consumer_secret']);

            $rows[] = $app;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);

    }

    /**
     * View the Log
     */
    public function ViewLog()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        $store = OAuthStore::instance();

        try {
            $list = $store->listLog(null, $this->getUser()->userId);
        } catch (OAuthException $e) {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $rows = array();

        foreach ($list as $row) {
            $row['received'] = \Xibo\Helper\Sanitize::string($row['received']);
            $row['notes'] = \Xibo\Helper\Sanitize::string($row['notes']);
            $row['timestamp'] = \Xibo\Helper\Sanitize::string($row['timestamp']);

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('applications_form_view_log');

        $response->SetFormRequestResponse($output, __('OAuth Access Log'), '1000', '600');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Services&Category=Log')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->dialogClass = 'modal-big';

    }

    /**
     * Authorize an OAuth request OR display the Authorize form.
     */
    public function authorize()
    {
        // Do we have an OAuth signed request?
        $userid = $this->getUser()->userId;

        $server = new OAuthServer();

        // Request must be signed
        try {
            $consumerDetails = $server->authorizeVerify();

            // Has the user submitted the form?
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                // See if the user clicked the 'allow' submit button
                if (isset($_POST['Allow']))
                    $authorized = true;
                else
                    $authorized = false;

                Log::notice('Allow submitted. Application is ' . (($authorized) ? 'authed' : 'denied'));

                // Set the request token to be authorized or not authorized
                // When there was a oauth_callback then this will redirect to the consumer
                $server->authorizeFinish($authorized, $userid);

                // No oauth_callback, show the user the result of the authorization
                echo __('Request authorized. Please return to your application.');
            } else {
                // Not submitted the form, therefore we must show the login box.
                $store = OAuthStore::instance();
                $consumer = $store->getConsumer($consumerDetails['consumer_key'], $userid, true);

                Theme::Set('application_title', $consumer['application_title']);
                Theme::Set('application_descr', $consumer['application_descr']);
                Theme::Set('application_uri', $consumer['application_uri']);

                $this->getState()->html .= Theme::RenderReturn('header');
                $this->getState()->html .= Theme::RenderReturn('application_verify');
                $this->getState()->html .= Theme::RenderReturn('footer');
                exit();
            }
        } catch (OAuthException $e) {
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
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        Theme::Set('form_id', 'RegisterOAuth');
        Theme::Set('form_action', 'index.php?p=oauth&q=Register');

        $formFields = array();
        $formFields[] = Form::AddText('requester_name', __('Full Name'), NULL,
            __('The name of the person or organization that authored this application.'), 'n', 'required');

        $formFields[] = Form::AddEmail('requester_email', __('Email Address'), NULL,
            __('The email address of the person or organization that authored this application.'), 'e', 'required');

        $formFields[] = Form::AddText('application_uri', __('Application Homepage'), NULL,
            __('The URL of your application homepage'), 'h', '');

        $formFields[] = Form::AddText('callback_uri', __('Application Homepage'), NULL,
            __('The call back URL for requests'), 'c', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Registration for Consumer Information'), '550px', '475px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Services&Category=Register')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Register'), '$("#RegisterOAuth").submit()');

    }

    /**
     * Register a new application with OAuth
     */
    public function Register()
    {


        $user = $this->getUser();
        $response = $this->getState();
        $userid = \Xibo\Helper\Sanitize::getInt('userid');

        $message = '';

        try {
            $store = OAuthStore::instance();
            $key = $store->updateConsumer($_POST, $userid);

            $c = $store->getConsumer($key, $userid);

            $message .= sprintf(__('Your consumer key is: %s'), $c['consumer_key']) . '<br />';
            $message .= sprintf(__('Your consumer secret is: %s'), $c['consumer_secret']) . '<br />';
        } catch (OAuthException $e) {
            trigger_error('Error: ' . $e->getMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse($message, false);

    }

    /**
     * Shows the Authorised applications this user has
     */
    public function UserTokens()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        $store = OAuthStore::instance();

        try {
            $list = $store->listConsumerTokens(Kit::GetParam('userID', _GET, _INT));
        } catch (OAuthException $e) {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $rows = array();

        foreach ($list as $app) {
            $app['application_title'] = \Xibo\Helper\Sanitize::string($app['application_title']);
            $app['enabled'] = \Xibo\Helper\Sanitize::string($app['enabled']);
            $app['status'] = \Xibo\Helper\Sanitize::string($app['status']);

            $rows[] = $app;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('application_form_user_applications');

        $response->SetFormRequestResponse($output, __('Authorized applications for user'), '650', '450');
        $response->AddButton(__('Help'), "XiboHelpRender('" . Help::Link('User', 'Applications') . "')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }
}

?>