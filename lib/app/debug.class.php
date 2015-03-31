<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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

class Debug
{
    private static $_logSql = null;
    private static $_level = NULL;
    private static $pdo = NULL;

    public function __construct()
    {
        if (self::$_level == NULL) {
            // Determine the auditing level
            self::$_level = Debug::getLevel(Config::GetSetting('audit'));
        }

        if (self::$_logSql == NULL) {
            self::$_logSql = 1;
        }
    }

    public static function getLevel($type)
    {
        switch ($type) {
            case 'audit':
                $level = 10;
                break;

            case 'info':
                $level = 5;
                break;

            case 'error':
                $level = 1;
                break;

            default:
                $level = 0;
        }

        return $level;
    }
    
    public function ErrorHandler($errno, $errmsg, $filename, $linenum, $vars) {

        // timestamp for the error entry
        $dt = date("Y-m-d H:i:s (T)");

        // define an assoc array of error string
        // in reality the only entries we should
        // consider are E_WARNING, E_NOTICE, E_USER_ERROR,
        // E_USER_WARNING and E_USER_NOTICE
        $errortype = array(E_ERROR => 'Error', E_WARNING => 'Warning', E_PARSE =>
                'Parsing Error', E_NOTICE => 'Notice', E_CORE_ERROR => 'Core Error',
                E_CORE_WARNING => 'Core Warning', E_COMPILE_ERROR => 'Compile Error',
                E_COMPILE_WARNING => 'Compile Warning', E_USER_ERROR => 'User Error',
                E_USER_WARNING => 'User Warning', E_USER_NOTICE => 'User Notice', E_STRICT =>
                'Runtime Notice', E_RECOVERABLE_ERROR => 'Recoverable Error', 8192 => 'Deprecated Call');

        // set of errors for which a var trace will be saved
        $user_errors_halt = array(E_USER_ERROR);
        $user_errors_inline = array(E_USER_WARNING);

        $err = "<errormsg>" . $errmsg . "</errormsg>\n";
        $err .= "<errornum>" . $errno . "</errornum>\n";
        $err .= "<errortype>" . $errortype[$errno] . "</errortype>\n";
        $err .= "<scriptname>" . $filename . "</scriptname>\n";
        $err .= "<scriptlinenum>" . $linenum . "</scriptlinenum>\n";

        // Log everything
        Debug::LogEntry("error", $err);
        
        // Test to see if this is a HALT error or not (we do the same if we are in production or not!)
        if (in_array($errno, $user_errors_halt)) 
        {
            // We have a halt error
            Debug::LogEntry('audit', 'Creating a Response Manager to deal with the HALT Error.');

            $response = new ResponseManager();
            
            $response->SetError($errmsg);
            $response->Respond();
        }
        
        // Is Debug Enabled? (i.e. Development or Support)
        if (error_reporting() != 0) 
        {
            if (in_array($errno, $user_errors_inline)) 
            {
                // This is an inline error - therefore we really want to pop up a message box with this in it - so we know?
                // For now we treat this like a halt error? Or do we just try and output some javascript to pop up an error
                // surely the javascript idea wont work in ajax?
                // or prehaps we add this to the session errormessage so we see it at a later date?
                echo $errmsg;
                die();
            }
        }
        
        // Must return false
        return false;
    }
    
    /**
     * Mail an error - currently disabled
     * @return 
     * @param $errmsg Object
     * @param $err Object
     */
    function MailError($errmsg, $err) 
    {
        return true;

        $to = 'info@xibo.org.uk';
        
        $from = Config::GetSetting("mail_from");
        if ($from == "") return true;
        
        $subject = "Error message from Digital Signage System";
        $message = wordwrap("$errmsg\n$err");

        $headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n" .
                "X-Mailer: PHP/" . phpversion();

        if (!mail($to, $subject, $message, $headers)) trigger_error("Mail not accepted", E_USER_NOTICE);
        return true;
    }

    /**
     * Write an Entry to the Log table
     * @param $type string
     * @param $message string
     * @param $page string[optional]
     * @param $function string[optional]
     * @param $logdate string[optional]
     * @param $displayid int[optional]
     * @param $scheduleID int[optional]
     * @param $layoutid int[optional]
     * @param $mediaid string[optional]
     */ 
    static function LogEntry($type, $message, $page = "", $function = "", $logdate = "", $displayid = 0, $scheduleID = 0, $layoutid = 0, $mediaid = '')
    {
        // Check level
        if (Debug::getLevel($type) > self::$_level)
            return;

        // Log
        self::log($type, $message, $page, $function, $logdate, $displayid, $scheduleID, $layoutid, $mediaid);
    }

    public static function Audit($message, $displayId = 0)
    {
        if (self::$_level < 10)
            return;

        // Get the calling class / function
        $trace = debug_backtrace();
        $caller = $trace[1];

        Debug::LogEntry('audit', $message, (isset($caller['class'])) ? $caller['class'] : 'Global', $caller['function'], '', $displayId);
    }

    public static function Info($message, $displayId = 0)
    {
        if (self::$_level < 5)
            return;

        // Get the calling class / function
        $trace = debug_backtrace();
        $caller = $trace[1];

        Debug::LogEntry('info', $message, (isset($caller['class'])) ? $caller['class'] : 'Global', $caller['function'], '', $displayId);
    }

    public static function Error($message, $displayId = 0)
    {
        if (self::$_level < 1)
            return;

        // Get the calling class / function
        $trace = debug_backtrace();
        $caller = $trace[1];

        Debug::LogEntry('error', $message, (isset($caller['class'])) ? $caller['class'] : 'Global', $caller['function'], '', $displayId);
    }

    /**
     * Log the SQL statement
     * @param string $sql
     * @param array $params[Optional] The Params
     * @param int[Optional] $displayId
     */
    public static function sql($sql, $params = array(), $displayId = 0)
    {
        if (self::$_logSql != 1)
            return;

        // Get the calling class / function
        $trace = debug_backtrace();
        $caller = $trace[1];

        Debug::LogEntry('audit', 'SQL: ' . $sql . '. Params: ' . var_export($params, true) . '.', (isset($caller['class'])) ? $caller['class'] : 'Global', $caller['function'], '', $displayId);
    }

    /**
     * Log
     * @param string $type
     * @param string $message
     * @param string $page[Optional]
     * @param string $function[Optional]
     * @param string $logDate[Optional]
     * @param int $displayId[Optional]
     * @param int $scheduleId[Optional]
     * @param int $layoutId[Optional]
     * @param string $mediaId[Optional]
     */
    public static function log($type, $message, $page = null, $function = null, $logDate = null, $displayId = 0, $scheduleId = 0, $layoutId = 0, $mediaId = null)
    {
        if (self::$pdo == NULL)
            self::$pdo = PDOConnect::newConnection();

        $currentDate = date("Y-m-d H:i:s");
        $requestUri = Kit::GetParam('REQUEST_URI', $_SERVER, _STRING, 'Not Supplied');
        $requestIp = Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING, 'Not Supplied');
        $requestUserAgent = Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'Not Supplied');
        $requestUserAgent = substr($requestUserAgent, 0, 253);
        $userId = Kit::GetParam('userid', _SESSION, _INT, 0);
        $message = Kit::ValidateParam($message, _HTMLSTRING);

        // Prepare the variables
        if ($logDate == null)
            $logDate = $currentDate;

        if ($page == null)
            $page = Kit::GetParam('p', _GET, _WORD);

        // Insert into the DB
        try {
            $dbh = self::$pdo;

            $SQL  = 'INSERT INTO log (logdate, type, page, function, message, requesturi, remoteaddr, useragent, userid, displayid, scheduleid, layoutid, mediaid)
                      VALUES (:logdate, :type, :page, :function, :message, :requesturi, :remoteaddr, :useragent, :userid, :displayid, :scheduleid, :layoutid, :mediaid) ';

            $sth = $dbh->prepare($SQL);

            $params = array(
                'logdate' => $logDate,
                'type' => $type,
                'page' => $page,
                'function' => $function,
                'message' => $message,
                'requesturi' => $requestUri,
                'remoteaddr' => $requestIp,
                'useragent' => $requestUserAgent,
                'userid' => $userId,
                'displayid' => Kit::ValidateParam($displayId, _INT),
                'scheduleid' => Kit::ValidateParam($scheduleId, _INT),
                'layoutid' => Kit::ValidateParam($layoutId, _INT),
                'mediaid' => Kit::ValidateParam($mediaId, _INT)
            );

            $sth->execute($params);
        }
        catch (PDOException $e) {
            // In this case just silently log the error
            error_log($message . '\n\n', 3, './err_log.xml');
            error_log($e->getMessage() . '\n\n', 3, './err_log.xml');
        }
    }
}
?>
