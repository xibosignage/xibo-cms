<?php
/*
 * UnitTests for Graph Xibo Module
 * Copyright (C) 2018 Lukas Zurschmiede
 *
 * This Xibo-Module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * This Xibo-Module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with This Xibo-Module.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);
namespace Xibo\Tests\Widget;
use Xibo\Tests\Widget\WidgetBase;

/**
 * Class GraphTest
 * @package Xibo\Tests\Widget
 */
final class GraphTest extends WidgetBase
{
    /**
     * The Graph-Widget instance we can check on
     * @var \Xibo\Widget\Graph
     */
    private $impl;
    
    /**
     * Test initialize
     */
    public function __construct()
    {
        $this->impl = $this->getInstance(\Xibo\Widget\Graph::class);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#IsValid()
     * 
     * We can be sure the Graph-Widget to see what it looks like on the player
     * 
     * @test
     */
    public function testIsValid(): void
    {
        // Running
        $result = $this->impl->IsValid();
        
        // Verify: The Module-Content does not depend in the player
        $this->assertSame($result, 1);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#extractUniqueValues()
     * 
     * Test extract all Series-Identifier from the Dataset values and returns a set
     * 
     * -> No Series-Identifier given: an Empty list is returned
     * 
     * @test
     */
    public function testExtractUniqueValues_NoIdentifier(): void
    {
        // Preparing
        $data = [
            ['col1' => 'One1',   'col2' => 'One2',   'col3' => 'One3'],
            ['col1' => 'Two1',   'col2' => 'Two2',   'col3' => 'Two3'],
            ['col1' => 'Three1', 'col2' => 'One2',   'col3' => 'Two3'],
            ['col1' => 'Four1',  'col2' => 'Two2',   'col3' => 'Two3']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'extractUniqueValues', [&$data]);
        
        // Verify
        $this->assertSame($result, []);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#extractUniqueValues()
     * 
     * Test extract all Series-Identifier from the Dataset values and returns a set
     * 
     * -> Invalid Series-Identifier given: an Empty list is returned
     * 
     * @test
     */
    public function testExtractUniqueValues_InvalidIdentifier(): void
    {
        // Preparing
        $data = [
            ['col1' => 'One1',   'col2' => 'One2',   'col3' => 'One3'],
            ['col1' => 'Two1',   'col2' => 'Two2',   'col3' => 'Two3'],
            ['col1' => 'Three1', 'col2' => 'One2',   'col3' => 'Two3'],
            ['col1' => 'Four1',  'col2' => 'Two2',   'col3' => 'Two3']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'extractUniqueValues', [&$data, 'col55']);
        
        // Verify
        $this->assertSame($result, []);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#extractUniqueValues()
     * 
     * Test extract all Series-Identifier from the Dataset values and returns a set
     * 
     * @test
     */
    public function testExtractUniqueValues_WithIdentifier_AllUnique(): void
    {
        // Preparing
        $data = [
            ['col1' => 'One1',   'col2' => 'One2',   'col3' => 'One3'],
            ['col1' => 'Two1',   'col2' => 'Two2',   'col3' => 'Two3'],
            ['col1' => 'Three1', 'col2' => 'One2',   'col3' => 'Two3'],
            ['col1' => 'Four1',  'col2' => 'Two2',   'col3' => 'Two3']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'extractUniqueValues', [&$data, 'col1']);
        
        // Verify
        $this->assertSame($result, ['One1', 'Two1', 'Three1', 'Four1']);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#extractUniqueValues()
     * 
     * Test extract all Series-Identifier from the Dataset values and returns a set
     * 
     * @test
     */
    public function testExtractUniqueValues_WithIdentifier(): void
    {
        // Preparing
        $data = [
            ['col1' => 'One1',   'col2' => 'One2',   'col3' => 'One3'],
            ['col1' => 'Two1',   'col2' => 'Two2',   'col3' => 'Two3'],
            ['col1' => 'Three1', 'col2' => 'One2',   'col3' => 'Two3'],
            ['col1' => 'Four1',  'col2' => 'Two2',   'col3' => 'Two3']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'extractUniqueValues', [&$data, 'col2']);
        
        // Verify
        $this->assertSame($result, ['One2', 'Two2']);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are no columns, no DataSets, en empty list is returned
     * 
     * @test
     */
    public function testPrepareLegend_NoColumns_NoDatasets(): void
    {
        // Preparing
        $columns = [];
        $dataset = $this->getDataSet(false);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns]);
        
        // Verifying
        $this->assertSame($result, []);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are no columns, all are returned which are from type 2 (Number) or 3 (Date)
     * 
     * @test
     */
    public function testPrepareLegend_NoColumns(): void
    {
        // Preparing
        $columns = [];
        $dataset = $this->getDataSet(true);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns]);
        
        // Verifying
        $this->assertSame($result, ['col2', 'col3', 'col7', 'col8']);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are columns given, only those are returned and which are from type 2 (Number) or 3 (Date)
     * 
     * @test
     */
    public function testPrepareLegend_WithColumns(): void
    {
        // Preparing
        $columns = ['col1', 'col2', 'col7'];
        $dataset = $this->getDataSet(true);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns]);
        
        // Verifying: col1 has the wrong dataTypeId and therefore is not returned
        $this->assertSame($result, ['col2', 'col7']);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are columns given, only those are returned and which are from type 2 (Number) or 3 (Date)
     * The Column which is defined as the label is not processed and not returned, how ever if it's given in the columns list
     * 
     * @test
     */
    public function testPrepareLegend_WithColumns_WithLabel(): void
    {
        // Preparing
        $columns = ['col1', 'col2', 'col7'];
        $dataset = $this->getDataSet(true);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns, [], 'col7']);
        
        // Verifying: col1 has the wrong dataTypeId and therefore is not returned
        $this->assertSame($result, ['col2']);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are columns given, only those are returned and which are from type 2 (Number) or 3 (Date)
     * If there is a series list given, all columns are returned prefixed with each value in the series list
     * Each series is processed for each column, means the resulting list is ordered based on the series identifier as given
     * 
     * @test
     */
    public function testPrepareLegend_WithColumns_WithSeriesIdentifier(): void
    {
        // Preparing
        $columns = ['col1', 'col2', 'col7'];
        $series = ['ser1', 'ser2'];
        $dataset = $this->getDataSet(true);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns, $series]);
        
        // Verifying: col1 has the wrong dataTypeId and therefore is not returned
        $this->assertSame($result, ['ser1: col2', 'ser1: col7', 'ser2: col2', 'ser2: col7']);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareLegend()
     * 
     * If there are columns given, only those are returned and which are from type 2 (Number) or 3 (Date)
     * If there is a series list given, all columns are returned prefixed with each value in the series list
     * Each series is processed for each column, means the resulting list is ordered based on the series identifier as given
     * 
     * The Column which is defined as the label is not processed and not returned, how ever if it's given in the columns list
     * 
     * @test
     */
    public function testPrepareLegend_WithColumns_WithSeriesIdentifier_WithLabel(): void
    {
        // Preparing
        $columns = ['col1', 'col2', 'col7'];
        $series = ['ser1', 'ser2'];
        $dataset = $this->getDataSet(true);
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareLegend', [&$dataset, &$columns, $series, 'col7']);
        
        // Verifying: col1 has the wrong dataTypeId and therefore is not returned
        $this->assertSame($result, ['ser1: col2', 'ser2: col2']);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareData()
     * 
     * No Label column and no Series-Identifier given:
     * -> Each column-value from each row is returned as is
     * 
     * @test
     */
    public function testPrepareData_NoSeriesIdentifier_NoLabel(): void
    {
        // Preparing
        $columns = ['col2', 'col3'];
        $data = [
            ['lbl' => 'aa', 'ser' => 'AA', 'col2' => '11', 'col3' => '21'],
            ['lbl' => 'aa', 'ser' => 'BB', 'col2' => '12', 'col3' => '22'],
            ['lbl' => 'bb', 'ser' => 'AA', 'col2' => '13', 'col3' => '23'],
            ['lbl' => 'bb', 'ser' => 'BB', 'col2' => '14', 'col3' => '24'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '15', 'col3' => '25'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '16', 'col3' => '26'],
            ['lbl' => 'dd', 'ser' => 'BB', 'col2' => '17', 'col3' => '27']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareData', [&$data, &$columns, '', '']);
        
        // Verifying
        $this->assertSame($result, [
            [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ], // col2 as floats
            [ 21.0, 22.0, 23.0, 24.0, 25.0, 26.0, 27.0 ]  // col3 as floats
        ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareData()
     * 
     * With a Label column but no Series-Identifier given:
     * -> The values from rows with the same label are summarized
     * 
     * @test
     */
    public function testPrepareData_NoSeriesIdentifier_WithLabel(): void
    {
        // Preparing
        $columns = ['col2', 'col3'];
        $data = [
            ['lbl' => 'aa', 'ser' => 'AA', 'col2' => '11', 'col3' => '21'],
            ['lbl' => 'aa', 'ser' => 'BB', 'col2' => '12', 'col3' => '22'],
            ['lbl' => 'bb', 'ser' => 'AA', 'col2' => '13', 'col3' => '23'],
            ['lbl' => 'bb', 'ser' => 'BB', 'col2' => '14', 'col3' => '24'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '15', 'col3' => '25'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '16', 'col3' => '26'],
            ['lbl' => 'dd', 'ser' => 'BB', 'col2' => '17', 'col3' => '27']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareData', [&$data, &$columns, 'lbl', '']);
        
        // Verifying
        $this->assertSame($result, [
            [ 11.0+12.0, 13.0+14.0, 15.0+16.0, 17.0 ], // col2 as floats, summarized by lbl
            [ 21.0+22.0, 23.0+24.0, 25.0+26.0, 27.0 ]  // col3 as floats, summarized by lbl
        ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#prepareData()
     * 
     * With a Label column and a Series-Identifier given:
     * -> The values are grouped by the series identifier
     * -> The values from rows with the same label are summarized
     * 
     * @test
     */
    public function testPrepareData_WithSeriesIdentifier_WithLabel(): void
    {
        // Preparing
        $columns = ['col2', 'col3'];
        $data = [
            ['lbl' => 'aa', 'ser' => 'AA', 'col2' => '11', 'col3' => '21'],
            ['lbl' => 'aa', 'ser' => 'BB', 'col2' => '12', 'col3' => '22'],
            ['lbl' => 'bb', 'ser' => 'AA', 'col2' => '13', 'col3' => '23'],
            ['lbl' => 'bb', 'ser' => 'BB', 'col2' => '14', 'col3' => '24'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '15', 'col3' => '25'],
            ['lbl' => 'cc', 'ser' => 'AA', 'col2' => '16', 'col3' => '26'],
            ['lbl' => 'dd', 'ser' => 'BB', 'col2' => '17', 'col3' => '27']
        ];
        
        // Running
        $result = $this->callMethod($this->impl, 'prepareData', [&$data, &$columns, 'lbl', 'ser']);
        
        // Verifying
        $this->assertSame($result, [
            [ 11.0, 13.0, 15.0+16.0 ], // col2 as floats from ser: AA
            [ 21.0, 23.0, 25.0+26.0 ], // col2 as floats from ser: BB
            [ 12.0, 14.0, 17.0 ],      // col3 as floats from ser: AA
            [ 22.0, 24.0, 27.0 ]       // col3 as floats from ser: BB
        ]);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#regroupData()
     * 
     * Test how the method works if the data array is empty
     * 
     * @test
     */
    public function testRegroupData_EmptyLists(): void
    {
        // Preparing
        $data = [];
        
        // Running
        $this->callMethod($this->impl, 'regroupData', [&$data]);
        
        // Verifying
        $this->assertSame($data, []);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#regroupData()
     * 
     * Test how the method works if the data array only contains one list of data flat data
     * Expected is a resulting list of each value in it's own list
     * 
     * @test
     */
    public function testRegroupData_FlatDataLists(): void
    {
        // Preparing
        $data = [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ];
        
        // Running
        $this->callMethod($this->impl, 'regroupData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [
            [ 11.0 ],
            [ 12.0 ],
            [ 13.0 ],
            [ 14.0 ],
            [ 15.0 ],
            [ 16.0 ],
            [ 17.0 ]
        ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#regroupData()
     * 
     * Test how the method works if the data array only contains one list of data
     * Expected is a resulting list of each value in it's own list
     * 
     * @test
     */
    public function testRegroupData_OneDataLists(): void
    {
        // Preparing
        $data = [ [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ] ];
        
        // Running
        $this->callMethod($this->impl, 'regroupData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [
            [ 11.0 ],
            [ 12.0 ],
            [ 13.0 ],
            [ 14.0 ],
            [ 15.0 ],
            [ 16.0 ],
            [ 17.0 ]
        ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#regroupData()
     * 
     * Test the correct regrouping of the data from one array to an other.
     * Based on the same values as the data for the prepareData() functions.
     * But here we use the resulting data-array for the test.
     * 
     * @test
     */
    public function testRegroupData_MultipleDataLists(): void
    {
        // Preparing
        $data = [
            [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ],
            [ 21.0, 22.0, 23.0, 24.0, 25.0, 26.0, 27.0 ],
            [ 31.0, 32.0, 33.0, 34.0, 35.0, 36.0, 37.0 ]
        ];
        
        // Running
        $this->callMethod($this->impl, 'regroupData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [
            [ 11.0, 21.0, 31.0 ],
            [ 12.0, 22.0, 32.0 ],
            [ 13.0, 23.0, 33.0 ],
            [ 14.0, 24.0, 34.0 ],
            [ 15.0, 25.0, 35.0 ],
            [ 16.0, 26.0, 36.0 ],
            [ 17.0, 27.0, 37.0 ]
        ]);
    }
    
    
    /**
     * Tests the Method \Xibo\Widget\Graph#summarizeData()
     * 
     * If the input is an empty list, nothing is done
     * 
     * @test
     */
    public function testSummarizeData_EmptyList(): void
    {
        // Preparing
        $data = [];
        
        // Running
        $this->callMethod($this->impl, 'summarizeData', [&$data]);
        
        // Verifying
        $this->assertSame($data, []);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#summarizeData()
     * 
     * If the input is a flat list of values, nothing is done
     * 
     * @test
     */
    public function testSummarizeData_FlatList(): void
    {
        // Preparing
        $data = [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ];
        
        // Running
        $this->callMethod($this->impl, 'summarizeData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#summarizeData()
     * 
     * If the input is a flat list of values, nothing is done
     * 
     * @test
     */
    public function testSummarizeData_OneDataList(): void
    {
        // Preparing
        $data = [ [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ] ];
        
        // Running
        $this->callMethod($this->impl, 'summarizeData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ]);
    }
    
    /**
     * Tests the Method \Xibo\Widget\Graph#summarizeData()
     * 
     * If the input is a list of flat lists, all indexes are summarized and replaced in a flat list
     * 
     * @test
     */
    public function testSummarizeData_MultipleDataList(): void
    {
        // Preparing
        $data = [
            [ 11.0, 12.0, 13.0, 14.0, 15.0, 16.0, 17.0 ],
            [ 21.0, 22.0, 23.0, 24.0, 25.0, 26.0, 27.0 ],
            [ 31.0, 32.0, 33.0, 34.0, 35.0, 36.0, 37.0 ]
        ];
        
        // Running
        $this->callMethod($this->impl, 'summarizeData', [&$data]);
        
        // Verifying
        $this->assertSame($data, [
          11.0 + 21.0 + 31.0,
          12.0 + 22.0 + 32.0,
          13.0 + 23.0 + 33.0,
          14.0 + 24.0 + 34.0,
          15.0 + 25.0 + 35.0,
          16.0 + 26.0 + 36.0,
          17.0 + 27.0 + 37.0,
        ]);
    }
    
    
    
    
    /**
     * Creates a new DataSet and optionally adds 10 columns with different dataTypeIds (index%5)
     * @param boolean Add or not to add the columns, thats the question :o)
     * @return \Xibo\Entity\DataSet
     */
    private function getDataSet($columns): \Xibo\Entity\DataSet
    {
        $ds = new \Xibo\Entity\DataSet($this->store, $this->log, $this->sanitizer, $this->config, $this->pool, $this->dataSetFactory, $this->dataSetColumnFactory, $this->permissionFactory, $this->displayFactory, $this->date);
        if ($columns) {
            foreach (['col0', 'col1', 'col2', 'col3', 'col4', 'col5', 'col6', 'col7', 'col8', 'col9'] as $k => $column) {
                $ds->columns[] = (object)[
                    'dataTypeId' => $k%5,
                    'heading' => $column
                ];
            }
        }
        return $ds;
    }
}

