<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Login.php) is part of Xibo.
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
use dashboardDAO;
use Xibo\Helper\Log;
use Exception;
use Kit;
use mediamanagerDAO;
use statusdashboardDAO;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Login extends Base
{
    /**
     * Output a login form
     */
    public function loginForm()
    {
        Theme::Set('form_action', $this->urlFor('login'));
        Theme::Set('form_meta', '<input type="hidden" name="priorPage" value="' . $this->getSession()->get('priorPage') . '" />');
        Theme::Set('about_url', $this->urlFor('about'));
        Theme::Set('source_url', Theme::SourceLink());

        // Message (either from the URL or the session)
        Theme::Set('login_message', $this->getFlash('login_message'));
        $this->getState()->html .= Theme::RenderReturn('login_page');
    }

    /**
     * login
     */
    public function login()
    {
        // Get our username and password
        $username = Sanitize::userName($this->param('username'));
        $password = Sanitize::password($this->param('password'));

        // Get our user
        try {
            $user = UserFactory::getByName($username);

            // Check password
            $user->checkPassword($password);

            // We are authenticated, so upgrade the user to the salted mechanism if necessary
            if (!$user->isSalted()) {
                // TODO: Call User controller to change the password
            }

            // We are logged in!
            $user->loggedIn = 1;

            // Overwrite our stored user with this new object.
            $this->app->user = $user;

            // Switch Session ID's
            $session = $this->getSession();
            $session->setIsExpired(0);
            $session->RegenerateSessionID(session_id());
            $session->set_user(session_id(), $user->userId, 'user');
        }
        catch (NotFoundException $e) {
            throw new AccessDeniedException();
        }
    }

    function logout($referingpage = '')
    {
        $user = $this->getUser();
        $db =& $this->db;

        $username = \Kit::GetParam('username', _SESSION, _USERNAME);

        //logs the user out -- true if ok
        $userId = \Kit::GetParam('userid', _SESSION, _INT);

        //write out to the db that the logged in user has accessed the page still
        $SQL = sprintf("UPDATE user SET loggedin = 0 WHERE userid = %d", $userId);
        if (!$results = $db->query($SQL)) trigger_error("Can not write last accessed info.", E_USER_ERROR);

        //to log out a user we need only to clear out some session vars
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        $session = $this->getSession();
        $session->setIsExpired(1);

        if ($referingpage == '')
            $referingpage = 'index';

        //then go back to the index page
        header('Location:index.php?p=' . $referingpage);
        exit;
    }

    function displayPage()
    {
        $db =& $this->db;
        $user = $this->getUser();

        // Shall we show the new user dashboard?
        $newUserWizard = 1;
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('SELECT newUserWizard FROM `user` WHERE userid = :userid');
            $sth->execute(array('userid' => $user->userId));

            $newUserWizard = $sth->fetchColumn(0);
        } catch (Exception $e) {
            Log::error($e->getMessage(), get_class(), __FUNCTION__);
        }

        if ($newUserWizard == 0 || \Kit::GetParam('sp', _GET, _WORD) == 'welcome') {

            // Update to say we have seen it
            try {
                $dbh = \Xibo\Storage\PDOConnect::init();

                $sth = $dbh->prepare('UPDATE `user` SET newUserWizard = 1 WHERE userid = :userid');
                $sth->execute(array('userid' => $user->userId));
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }

            $this->getState()->html .= Theme::RenderReturn('new_user_welcome');
        } else {

            $homepage = $this->user->homePage;

            if ($homepage == 'mediamanager') {
                include('lib/pages/mediamanager.class.php');
                $userHomepage = new mediamanagerDAO($db, $user);
            } else if ($homepage == 'statusdashboard') {
                include('lib/pages/statusdashboard.class.php');
                $userHomepage = new statusdashboardDAO($db, $user);
            } else {
                include("lib/pages/dashboard.class.php");
                $userHomepage = new dashboardDAO($db, $user);
            }

            $userHomepage->displayPage();
        }
    }

    /**
     * Ping Pong
     * @return
     */
    public function PingPong()
    {
        $response = $this->getState();

        $response->success = true;

    }

    /**
     * Shows information about Xibo
     * @return
     */
    function About()
    {
        $response = $this->getState();

        Theme::Set('version', VERSION);

        // Render the Theme and output
        $output = Theme::RenderReturn('about_text');

        $response->SetFormRequestResponse($output, __('About'), '500', '500');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    function ExchangeGridTokenForFormToken()
    {

        // Check our grid token against the one provided.
        if (!Kit::CheckToken('gridToken'))
            die(__('Sorry the form has expired. Please refresh.'));

        echo \Kit::Token('token', false);
        exit();
    }
}

?>
