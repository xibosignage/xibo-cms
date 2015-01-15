<?php
include_once('lib/data/data.class.php');
include_once('lib/data/campaign.data.class.php');
include_once('lib/data/campaignsecurity.data.class.php');

class Step46 extends UpgradeStep
{
    public function Boot()
    {
        global $db;

        // On upgrade, fix all of the layouts, excluding the default
        
        $campaign = new Campaign($db);

        $SQL = "SELECT LayoutID, Layout, UserID FROM layout WHERE layout <> 'Default Layout'";

        $layouts = $db->GetArray($SQL);

        // Create a campaign record for all of the layouts that currently exist
        foreach ($layouts as $layout)
        {
            $layoutId = $layout['LayoutID'];
            $campaignId = $campaign->Add($layout['Layout'], 1, $layout['UserID']);
            $campaign->Link($campaignId, $layoutId, 1);
            
            // Update Security
            $SQL  = "INSERT INTO lkcampaigngroup (CampaignID, GroupID, View, Edit, Del) ";
            $SQL .= " SELECT '$campaignId', GroupID, View, Edit, Del ";
            $SQL .= "  FROM lklayoutgroup ";
            $SQL .= " WHERE lklayoutgroup.LayoutID = $layoutId";

            $db->query($SQL);

            // Update Events
            $db->query("UPDATE schedule SET layoutid = '$campaignId' WHERE layoutid = '$layoutId'");
            $db->query("UPDATE schedule_detail SET layoutid = '$campaignId' WHERE layoutid = '$layoutId'");
        }

        // Also run a script to tidy up orphaned media in the library
        $library = Config::GetSetting('LIBRARY_LOCATION');
        $library = rtrim($library, '/') . '/';

        // Dump the files in the temp folder
        foreach (scandir($library . 'temp') as $item)
        {
            if ($item == '.' || $item == '..')
                continue;

            unlink($library . 'temp' . DIRECTORY_SEPARATOR . $item);
        }

        // Have commented this block out, as am not 100% convinced that it doesn't
        // delete things it shouldn't
        // 
        // Get a list of all media files
//        foreach(scandir($library) as $file)
//        {
//            if ($file == '.' || $file == '..')
//                continue;
//
//            if (is_dir($library . $file))
//                continue;
//
//            $rowCount = $db->GetCountOfRows("SELECT * FROM media WHERE storedAs = '" . $file . "'");
//            
//            // For each media file, check to see if the file still exists in the library
//            if ($rowCount == 0)
//            {
//                // If not, delete it
//                unlink($library . $file);
//
//                if (file_exists($library . 'tn_' . $file))
//                {
//                    unlink($library . 'tn_' . $file);
//                }
//
//                if (file_exists($library . 'bg_' . $file))
//                {
//                    unlink($library . 'bg_' . $file);
//                }
//            }
//        }

        return true;
    }
}
?>
