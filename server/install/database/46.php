<?php
include('lib/data/data.class.php');
include('lib/data/campaign.data.class.php');
include('lib/data/campaignsecurity.data.class.php');

class Step46 extends UpgradeStep
{
    public function Boot()
    {
        global $db;
        
        $campaign = new Campaign($db);

        $SQL = "SELECT LayoutID, Layout, UserID FROM layout";

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

        return true;
    }
}
?>
