<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (StatusDashboard.php) is part of Xibo.
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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;
use Exception;
use SimplePie;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;

class StatusDashboard extends Base
{
    function displayPage()
    {
        $data = [];

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        // Get some data for a bandwidth chart
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT FROM_UNIXTIME(month) AS month, IFNULL(SUM(Size), 0) AS size FROM `bandwidth` WHERE month > :month GROUP BY FROM_UNIXTIME(month) ORDER BY MIN(month);');
            $sth->execute(array('month' => time() - (86400 * 365)));

            $results = $sth->fetchAll();

            // Monthly bandwidth - optionally tested against limits
            $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

            $maxSize = 0;
            foreach ($results as $row) {
                $maxSize = ($row['size'] > $maxSize) ? $row['size'] : $maxSize;
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            if ($xmdsLimit > 0) {
                // Convert to appropriate size (xmds limit is in KB)
                $xmdsLimit = ($xmdsLimit * 1024) / (pow(1024, $base));
                $data['xmdsLimit'] = $xmdsLimit . ' ' . $suffixes[$base];
            }

            $output = array();

            foreach ($results as $row) {
                $size = ((double)$row['size']) / (pow(1024, $base));
                $remaining = $xmdsLimit - $size;
                $output[] = array(
                    'label' => Date::getLocalDate(Date::getDateFromGregorianString($row['month']), 'F'),
                    'value' => round($size, 2),
                    'limit' => round($remaining, 2)
                );
            }

            // What if we are empty?
            if (count($output) == 0) {
                $output[] = array(
                    'label' => Date::getLocalDate(null, 'F'),
                    'value' => 0,
                    'limit' => 0
                );
            }

            // Set the data
            $data['xmdsLimitSet'] = ($xmdsLimit > 0);
            $data['bandwidthSuffix'] = $suffixes[$base];
            $data['bandwidthWidget'] = json_encode($output);

            // We would also like a library usage pie chart!
            $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');
            $libraryLimit = $libraryLimit * 1024;

            // Library Size in Bytes
            $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM media GROUP BY type;');
            $sth->execute();

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $output = array();
            $totalSize = 0;
            foreach ($results as $library) {
                $output[] = array(
                    'value' => round((double)$library['SumSize'] / (pow(1024, $base)), 2),
                    'label' => ucfirst($library['type'])
                );
                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);
                $output[] = array(
                    'value' => $remaining,
                    'label' => __('Free')
                );
            }

            // What if we are empty?
            if (count($output) == 0) {
                $output[] = array(
                    'label' => __('Empty'),
                    'value' => 0
                );
            }

            $data['libraryLimitSet'] = $libraryLimit;
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidget'] = json_encode($output);

            // Also a display widget
            $displays = DisplayFactory::query(['display']);
            $rows = array();

            if (is_array($displays) && count($displays) > 0) {
                // Output a table showing the displays
                foreach ($displays as $display) {
                    /* @var \Xibo\Entity\Display $display */
                    $row['mediainventorystatus'] = ($display->mediaInventoryStatus == 1) ? 'success' : (($display->mediaInventoryStatus == 2) ? 'danger' : 'warning');
                    $row['display'] = $display->display;
                    $row['loggedin'] = $display->loggedIn;
                    $row['licensed'] = $display->licensed;
                    // Assign this to the table row
                    $rows[] = $row;
                }
            }

            $data['display-widget-rows']= $rows;

            // Get a count of users
            $sth = $dbh->prepare('SELECT IFNULL(COUNT(*), 0) AS count_users FROM `user`');
            $sth->execute();

            $data['countUsers'] = $sth->fetchColumn(0);

            // Get a count of active layouts
            $sth = $dbh->prepare('SELECT IFNULL(COUNT(*), 0) AS count_scheduled FROM `schedule_detail` WHERE :now BETWEEN FromDT AND ToDT');
            $sth->execute(array('now' => time()));

            $data['nowShowing'] = $sth->fetchColumn(0);

            // Latest news
            if (Config::GetSetting('DASHBOARD_LATEST_NEWS_ENABLED') == 1) {
                // Make sure we have the cache location configured
                Library::ensureLibraryExists();

                // Use SimplePie to get the feed
                $feed = new SimplePie();
                $feed->set_cache_location(Library::getLibraryCacheUri());
                $feed->set_feed_url(Theme::getConfig('latest_news_url'));
                $feed->set_cache_duration(86400);
                $feed->handle_content_type();
                $feed->init();

                $latestNews = array();

                if ($feed->error()) {
                    Log::notice('Feed Error: ' . $feed->error(), get_class(), __FUNCTION__);
                } else {
                    // Store our formatted items
                    foreach ($feed->get_items() as $item) {
                        $latestNews[] = array(
                            'title' => $item->get_title(),
                            'description' => $item->get_description(),
                            'link' => $item->get_link()
                        );
                    }
                }

                $data['latestNews'] = $latestNews;
            }
            else {
                $data['latestNews'] = array(array('title' => __('Latest news not enabled.'), 'description' => '', 'link' => ''));
            }
        }
        catch (Exception $e) {

            Log::error($e->getMessage());

            // Show the error in place of the bandwidth chart
            $data['widget-error'] = 'Unable to get widget details';
        }

        // Do we have an embedded widget?
        $data['embedded-widget'] = html_entity_decode(Config::GetSetting('EMBEDDED_STATUS_WIDGET'));

        // Render the Theme and output
        $this->getState()->template = 'dashboard-status-page';
        $this->getState()->setData($data);
    }
}
