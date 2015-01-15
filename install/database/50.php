<?php

class Step50 extends UpgradeStep
{

    public function Boot()
    {
        global $db;

        // Get a list of all pagegroups assigned to groups
        if (!$results = $db->GetArray("SELECT DISTINCT groupid, pages.pagegroupid FROM `lkpagegroup` INNER JOIN pages ON lkpagegroup.pageid = pages.pageid"))
            return true;

        foreach($results as $page) 
        {
            // Delete it
            $db->query("DELETE FROM lkpagegroup WHERE groupid = " . $page['groupid'] . " AND pageID IN (SELECT pageid FROM pages WHERE pagegroupid = " . $page['pagegroupid'] . ")");
            
            // Re-add it
            $db->query("INSERT INTO lkpagegroup (groupID, pageID) SELECT " . $page['groupid'] . ", pageid FROM pages WHERE pagegroupid = " . $page['pagegroupid']);
        }
        
        return true;
    }
}
?>
