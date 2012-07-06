<?php

class Step2 extends UpgradeStep
{

    public function Boot()
    {
        global $db;

        // Have they allowed us to do this?
        if ($this->a[0])
        {
            // Load all layouts
            $layouts = $db->GetArray("SELECT LayoutId, LayoutXml FROM `layout`");

            foreach($layouts as $layout)
            {
                $layoutId = Kit::ValidateParam($layout['LayoutID'], _INT);
                $layoutXml = Kit::ValidateParam($layout['LayoutXml'], _HTMLSTRING);

                // Do a regex match for the font sizing...
                preg_replace_callback('(@(.*?).)', create_function('$matches', ''), $layoutXml);

                // Also do a regex match for the scrollSpeed?
                
                
                // Update the XML
                $db->query("UPDATE `layout` SET LayoutXml = '" . $layoutXml . "' WHERE LayoutId = " . $layoutId);
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

    /**
     * Take an EM value and return a PX one (I know this is not always true, nested EM's wont work, but its the best we can do
     * and all EM's must be removed.
     * @param <type> $emValue
     */
    public function AdjustFontSize($emValue)
    {
        // Take the EM value and * 16
        // most browsers have 1em = 16px
        return $emValue * 16;
    }
}
?>
