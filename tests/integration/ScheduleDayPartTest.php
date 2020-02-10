<?php


namespace Xibo\Tests\integration;


use Jenssegers\Date\Date;
use Xibo\Entity\Display;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDaypart;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class ScheduleDayPartTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $layout;

    /** @var \Xibo\OAuth2\Client\Entity\XiboDisplay */
    protected $display;

    /** @var \Xibo\OAuth2\Client\Entity\XiboSchedule */
    protected $event;

    /** @var \Xibo\OAuth2\Client\Entity\XiboDaypart */
    protected $dayPart;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class() .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        $layout = $this->getDraft($this->layout);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        // Check us in again
        $this->layout = $this->publish($this->layout);

        // Build the layout
        $this->buildLayout($this->layout);

        // Create a Display
        $this->display = $this->createDisplay();

        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display);

        // Make sure the Layout Status is as we expect
        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout Status isnt as expected');

        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Create a Day Part
        // calculate a few hours either side of now
        $now = Date::now()->startOfHour()->subHour();

        $this->dayPart = (new XiboDaypart($this->getEntityProvider()))->create(
            Random::generateString(5),
            '',
            $now->format('H:i'),
            $now->copy()->addHours(5)->format('H:i')
        );

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the Display
        $this->deleteDisplay($this->display);

        // Delete the DayPart
        $this->dayPart->delete();
    }
    // </editor-fold>

    public function testSchedule()
    {
        // Our CMS is in GMT
        // Create a schedule one hours time in my player timezone
        $date = Date::now()->setTime(0,0,0);

        $this->getLogger()->debug('Event start will be at: ' . $date->format('Y-m-d H:i:s'));

        $response = $this->client->post('/schedule', [
            'fromDt' => $date->format('Y-m-d H:i:s'),
            'dayPartId' => $this->dayPart->dayPartId,
            'eventTypeId' => 1,
            'campaignId' => $this->layout->campaignId,
            'displayGroupIds' => [$this->display->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 0,
            'scheduleRecurrenceType' => null,
            'scheduleRecurrenceDetail' => null,
            'scheduleRecurrenceRange' => null,
            'syncTimezone' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->Schedule($this->display->license));
        //$this->getLogger()->debug($xml->saveXML());

        // Check our event is present.
        $layouts = $xml->getElementsByTagName('layout');

        $this->assertTrue(count($layouts) == 1, 'Unexpected number of events');

        foreach ($layouts as $layout) {
            $xmlFromDt = $layout->getAttribute('fromdt');
            $xmlToDt = $layout->getAttribute('todt');
            $this->assertEquals($date->format('Y-m-d') . ' ' . $this->dayPart->startTime . ':00', $xmlFromDt, 'From date doesnt match: ' . $xmlFromDt);
            $this->assertEquals($date->format('Y-m-d') . ' ' . $this->dayPart->endTime . ':00', $xmlToDt, 'To date doesnt match: ' . $xmlToDt);
        }

        // Also check this layout is in required files.
        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->RequiredFiles($this->display->license));
        //$this->getLogger()->debug($xml->saveXML());

        // Find using XPATH
        $xpath = new \DOMXPath($xml);
        $nodes =$xpath->query('//file[@type="layout"]');

        $this->assertGreaterThanOrEqual(1, $nodes->count(), 'Layout not in required files');

        $found = false;
        foreach ($nodes as $node) {
            /** @var \DOMNode $node */
            if ($this->layout->layoutId == $node->attributes->getNamedItem('id')->nodeValue) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->fail('Layout not found in Required Files XML');
        }
    }
}