<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

class indexDAO extends baseDAO {
    
    function login() 
    {
        $db         =& $this->db;
        $user       =& $this->user;
        global $session;

        // this page must be called from a form therefore we expect POST variables      
        $username = Kit::GetParam('username', _POST, _USERNAME);
        $password = Kit::GetParam('password', _POST, _PASSWORD);
        $referingpage = rawurldecode(Kit::GetParam('referingPage', _GET, _STRING));

        // Check the token
        if (!Kit::CheckToken()) {
            // We would usually issue a HALT error here - but in the case of login we should redirect instead
            // Split on &amp; and rejoin with &
            $params = explode('&amp;', $referingpage, 3);
            $referingpage = implode('&', $params);

            $session->set('message', __('Sorry the form has expired. Please refresh.'));

            header('Location:index.php?' . $referingpage);
            exit;
        }

        if ($user->login($username,$password)) 
        {
            $userid     = Kit::GetParam('userid', _SESSION, _INT);
            
            $session->set_user(session_id(), $userid, 'user');
        }
        
        Debug::LogEntry('audit', 'Login with referring page: ' . $referingpage);
        
        if ($referingpage == '') 
        {
            header('Location:index.php?p=index');
        }
        else 
        {
            // Split on &amp; and rejoin with &
            $params = explode('&amp;', $referingpage, 3);
            unset($params['message']);
            $referingpage = implode('&', $params);

            header('Location:index.php?' . $referingpage);
        }

        exit;
    }

    function logout($referingpage = '')
    {
        global $user;
        $db =& $this->db;

        $username = Kit::GetParam('username', _SESSION, _USERNAME);

        //logs the user out -- true if ok
        $user->logout();

        if ($referingpage == '')
            $referingpage = 'index';

        //then go back to the index page
        header('Location:index.php?p=' . $referingpage);
        exit;
    }
    
    function forgotten() 
    {
        // Called by a submit to the Forgotten Details form 
        // Checks the validity of the data provided, and emails a new password to the user
        $db =& $this->db;
        
        $username   = Kit::GetParam('f_username', _POST, _USERNAME);
        $email      = Kit::GetParam('f_email', _POST, _STRING);
        $return     = "index.php";
        
        if ($username == "" || $email == "") 
        {
            setMessage("Username and Email address need to be filled in");
            return $return;
        }
        
        //send the email
        $from = Config::GetSetting("mail_from");
        if ($from == "") 
        {
            setMessage("Email is not set up, please contact your IT manager");
            return $return;
        }
        
        //check the user details
        $SQL = sprintf("SELECT userid FROM user WHERE username = '%s' AND email = '%s'", $db->escape_string($username), $db->escape_string($email));
        
        if (!$results = $db->query($SQL)) 
        {
            trigger_error($db->error);
            trigger_error("Can not get the user information", E_USER_ERROR);
        }
        
        if ($db->num_rows($results) !=1) 
        {
            setMessage("The details you entered are incorrect.");
            return $return;
        }
        
        $row = $db->get_row($results);
        
        $userid         = Kit::ValidateParam($row[0], _INT); //user ID for the user that wants a new password

        $password_plain = $this->random_word(8); //generate a new password
        $password       = md5($password_plain);
        
        //update the password
        $SQL = sprintf("UPDATE user SET UserPassword = '%s', CSPRNG = 0 WHERE userid = %d", $db->escape_string($password), $userid);
        
        if (!$db->query($SQL)) 
        {
            trigger_error($db->error());
            trigger_error("Unable to send new password", E_USER_ERROR);
        }

        $headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n" .
            "X-Mailer: PHP/" . phpversion();
        
        if (!@mail($email,"Xibo: New Password request for $username","Your new password is $password_plain \n  . You may now login with these details.", $headers)) 
        {
            setMessage("Email is not set up, please contact your IT manager");
            return $return;
        }
        
        setMessage("New Password Sent to your email address");
        return $return;
    }
    
    function random_word($length)
    {
        srand((double)microtime()*1000000);   

        $vowels = array("a", "e", "i", "o", "u");
        $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
        "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr", "sl", "cl");

        $num_vowels = count($vowels);

        $num_cons = count($cons);
        
        $password = "";
        for($i = 0; $i < $length; $i++)
        {
            $password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
        }
        return substr($password, 0, $length);
    }  
    
    function displayPage()
    {
        $db     =& $this->db;
        $user   =& $this->user;

        // Shall we show the new user dashboard?
        $newUserWizard = 1;
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT newUserWizard FROM `user` WHERE userid = :userid');
            $sth->execute(array('userid' => $user->userid));

            $newUserWizard = $sth->fetchColumn(0);
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        }

        if ($newUserWizard == 0 || Kit::GetParam('sp', _GET, _WORD) == 'welcome') {

            // Update to say we have seen it
            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('UPDATE `user` SET newUserWizard = 1 WHERE userid = :userid');
                $sth->execute(array('userid' => $user->userid));
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
            }

            Theme::Render('new_user_welcome');
        }
        else {

            $homepage = $this->user->homePage;

            if ($homepage == 'mediamanager')
            {
                include('lib/pages/mediamanager.class.php');
                $userHomepage = new mediamanagerDAO($db, $user);
            }
            else if ($homepage == 'statusdashboard') {
                include('lib/pages/statusdashboard.class.php');
                $userHomepage = new statusdashboardDAO($db, $user); 
            }
            else
            {
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
        $response = new ResponseManager();
        
        $response->success = true;
        $response->Respond();
    }
    
    /**
     * Shows information about Xibo
     * @return 
     */
    function About()
    {
        $response = new ResponseManager();
        
        Theme::Set('version', VERSION);
        
        // Render the Theme and output
        $output = Theme::RenderReturn('about_text');
        
        $response->SetFormRequestResponse($output, __('About'), '500', '500');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    function ExchangeGridTokenForFormToken() {

        // Check our grid token against the one provided.
        if (!Kit::CheckToken('gridToken'))
            die(__('Sorry the form has expired. Please refresh.'));

        echo Kit::Token('token', false);
        exit();
    }
}
?>
