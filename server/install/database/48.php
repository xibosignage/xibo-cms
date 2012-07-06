<?php

class Step48 extends UpgradeStep
{

    public function Boot()
    {
        global $db;

        // Have they allowed us to do this?
        if ($this->a[0])
        {
            // Load all layouts
            $layouts = $db->GetArray("SELECT LayoutID, Xml FROM `layout`");

            echo ':';

            foreach($layouts as $layout)
            {
                $layoutId = Kit::ValidateParam($layout['LayoutID'], _INT);
                $layoutXml = Kit::ValidateParam($layout['Xml'], _HTMLSTRING);

                echo '.';

                // Do a regex match for the font sizing...
                $layoutXml = preg_replace_callback('/font-size:(.*?)em;/', create_function('$matches', 'return "font-size:" . $matches[1] * 16 . "px;";'), $layoutXml);

                // Also do a regex match for the scrollSpeed
                $scrollSpeedLookup = '
                    $return = 2;

                    if($matches[1] <= 5)
                        $return = 15;
                    else if ($matches[1] <= 10)
                        $return = 10;
                    else if ($matches[1] <= 15)
                        $return = 5;
                    else if ($matches[1] <= 20)
                        $return = 4;
                    else if ($matches[1] <= 25)
                        $return = 3;
                    else if ($matches[1] <= 30)
                        $return = 2;
                    else if ($matches[1] > 30)
                        $return = 1;

                    return "<scrollSpeed>" . $return . "</scrollSpeed>";
                ';

                $layoutXml = preg_replace_callback('/<scrollSpeed>(.*?)<\/scrollSpeed>/', create_function('$matches', $scrollSpeedLookup), $layoutXml);
                
                // Update the XML
                $db->query("UPDATE `layout` SET Xml = '" . $layoutXml . "' WHERE LayoutId = " . $layoutId);
            }
        }

        return true;
    }

    public function Questions()
    {
        $this->q[0]['question'] = "There are some design changes in Xibo and we would like to run an automatic conversion over your layouts. Is this OK? If not your should manually adjust all text/rss items to have a smaller = slower scrolling speed, and to remove any EM font sizing with the 'Remove Format' rubber.";
        $this->q[0]['type'] = _CHECKBOX;
        $this->q[0]['default'] = true;
        return $this->q;
    }

    public function ValidateQuestion($questionNumber,$response)
    {
        switch ($questionNumber)
        {
            case 0:
                $this->a[0] = Kit::ValidateParam($response, _BOOL);
                return true;
        }

        return false;
    }
}
?>
