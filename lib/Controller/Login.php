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
use Config;
use dashboardDAO;
use Debug;
use Exception;
use Kit;
use mediamanagerDAO;
use statusdashboardDAO;
use Xibo\Exception\FormExpiredException;
use Xibo\Helper\ApplicationState;
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
        Theme::Set('about_url', 'index.php?p=index&q=About');
        Theme::Set('source_url', Theme::SourceLink());

        // Message (either from the URL or the session)
        $message = \Kit::GetParam('message', _GET, _STRING, \Kit::GetParam('message', _SESSION, _STRING, ''));
        Theme::Set('login_message', $message);
        $this->getState()->html .= Theme::RenderReturn('login_page');
    }

    function login()
    {
        $user = $this->getUser();

        // Check the token
        if (!Kit::CheckToken()) {
            throw new FormExpiredException();
        }

        // this page must be called from a form therefore we expect POST variables
        $username = \Kit::GetParam('username', _POST, _USERNAME);
        $password = \Kit::GetParam('password', _POST, _PASSWORD);

        if ($user->login($username, $password)) {
            $userId = \Kit::GetParam('userid', _SESSION, _INT);

            $this->getSession()->set_user(session_id(), $userId, 'user');
        }
    }

    function logout($referingpage = '')
    {
        global $user;
        $db =& $this->db;

        $username = \Kit::GetParam('username', _SESSION, _USERNAME);

        //logs the user out -- true if ok
        $user->logout();

        if ($referingpage == '')
            $referingpage = 'index';

        //then go back to the index page
        header('Location:index.php?p=' . $referingpage);
        exit;
    }

    function random_word($length)
    {
        srand((double)microtime() * 1000000);

        $vowels = array("a", "e", "i", "o", "u");
        $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
            "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr", "sl", "cl");

        $num_vowels = count($vowels);

        $num_cons = count($cons);

        $password = "";
        for ($i = 0; $i < $length; $i++) {
            $password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
        }
        return substr($password, 0, $length);
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
            $sth->execute(array('userid' => $user->userid));

            $newUserWizard = $sth->fetchColumn(0);
        } catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        }

        if ($newUserWizard == 0 || \Kit::GetParam('sp', _GET, _WORD) == 'welcome') {

            // Update to say we have seen it
            try {
                $dbh = \Xibo\Storage\PDOConnect::init();

                $sth = $dbh->prepare('UPDATE `user` SET newUserWizard = 1 WHERE userid = :userid');
                $sth->execute(array('userid' => $user->userid));
            } catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
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
        $response = new ApplicationState();

        $response->success = true;
        $response->Respond();
    }

    /**
     * Shows information about Xibo
     * @return
     */
    function About()
    {
        $response = new ApplicationState();

        Theme::Set('version', VERSION);

        // Render the Theme and output
        $output = Theme::RenderReturn('about_text');

        $response->SetFormRequestResponse($output, __('About'), '500', '500');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
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
