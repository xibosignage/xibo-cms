<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Tests\Widget\Definition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\SanitizerService;
use Xibo\Listener\DataSetDataProviderListener;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Definition\Property;
use Xibo\Widget\Definition\Sql;
use Xibo\Widget\Provider\DataProviderInterface;

class SqlTest extends TestCase
{
    public static function allowedFilters(): array
    {
        return [
            'plain comparison' => ['id = 1'],
            'backticked column' => ['`name` = \'Smith\''],
            'backticked column with a space' => ['`My Column` = 1'],
            'function on a backticked column' => ['UPPER(`name`) = \'SMITH\''],
            'backticked column before IN' => ['`name` IN (\'a\', \'b\')'],
            'escaped apostrophe' => ['`name` = \'O\'\'Brien\''],
            'escaped backslash' => ['`path` LIKE \'%C:\\\\temp\\\\%\''],
            'keyword inside a string' => ['`name` = \'Select\''],
            'minus minus number is not a comment' => ['x = 5--1'],
        ];
    }

    #[DataProvider('allowedFilters')]
    public function testAllowedFiltersAreUnchanged(string $filter)
    {
        $this->assertSame($filter, Sql::sanitizeFragment($filter, 'filter'));
    }

    public static function disallowedFilters(): array
    {
        return [
            'SLEEP' => ['id = 1 AND SLEEP(1) = 0'],
            'backticked SLEEP' => ['id = 1 AND `SLEEP`(1) = 0'],
            'backticked SLEEP with spaces' => ["id = 1 AND `SLEEP` \n (1) = 0"],
            'backticked LOAD_FILE' => ['`LOAD_FILE`(\'/etc/passwd\') IS NOT NULL'],
            'schema qualified call' => ['`mysql`.`sleep`(1) = 0'],
            'quoted string called' => ['\'SLEEP\'(1) = 0'],
            'backticked BENCHMARK' => ['`BENCHMARK`(1000000, 1) = 0'],
            'trailing comment' => ['x = 1 --'],
            'trailing comment and newline' => ["x = 1 --\n"],
            'comment with text' => ['x = 1 -- comment'],
            'hash comment' => ['x = 1 #'],
            'block comment' => ['x = 1 /**/'],
            'stacked query' => ['x = 1; DROP TABLE user'],
            'hex literal' => ['x = 0x41'],
        ];
    }

    #[DataProvider('disallowedFilters')]
    public function testDisallowedFiltersAreRejected(string $filter)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter contains disallowed keywords');
        Sql::sanitizeFragment($filter, 'filter');
    }

    public static function unclosedQuotes(): array
    {
        return [
            'unescaped apostrophe' => ['`name` = \'O\'Brien\''],
            'unclosed backslash quote' => ['name = \'it\\\'s'],
            'unclosed backtick' => ['`name = 1'],
        ];
    }

    #[DataProvider('unclosedQuotes')]
    public function testUnclosedQuotesGetTheirOwnMessage(string $filter)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter has an unclosed quote');
        Sql::sanitizeFragment($filter, 'filter');
    }

    public function testCleanupStillCountsCallsAndStrayQuotesForTheFormulaPath()
    {
        $count = 0;
        Sql::cleanup('`SLEEP`(1)', $count);
        $this->assertGreaterThan(0, $count);

        $count = 0;
        $strayQuotes = 0;
        Sql::cleanup('name = \'it\\\'s', $count, $strayQuotes);
        $this->assertGreaterThan(0, $count);
        $this->assertGreaterThan(0, $strayQuotes);
    }

    public function testEscapeLiteral()
    {
        $this->assertSame('O\'\'Brien', Sql::escapeLiteral('O\'Brien'));
        $this->assertSame('C:\\\\temp', Sql::escapeLiteral('C:\\temp'));
        $this->assertSame('\\\\\'\'', Sql::escapeLiteral('\\\''));
        $this->assertSame('Smith', Sql::escapeLiteral('Smith'));
    }

    public function testSqlPropertyRejectsInsteadOfRewriting()
    {
        $property = $this->sqlProperty();
        $params = (new SanitizerService())->getSanitizer(['filter' => 'name = \'it\\\'s']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter has an unclosed quote');
        $property->setValueByType($params, null, true);
    }

    public function testSqlPropertyKeepsAValidFilter()
    {
        $property = $this->sqlProperty();
        $params = (new SanitizerService())->getSanitizer(['filter' => '`name` = \'O\'\'Brien\'']);

        $property->setValueByType($params, null, true);
        $this->assertSame('`name` = \'O\'\'Brien\'', $property->value);
    }

    public function testClauseBuilderEscapesValues()
    {
        $clauses = [
            ['filterClause' => '`name`', 'filterClauseCriteria' => 'equals', 'filterClauseValue' => 'O\'Brien'],
            [
                'filterClause' => '`path`',
                'filterClauseCriteria' => 'starts-with',
                'filterClauseValue' => 'C:\\temp',
                'filterClauseOperator' => 'OR',
            ],
        ];

        $filter = $this->buildFilterClause($clauses);

        $this->assertSame('`name` = \'O\'\'Brien\' OR `path` LIKE \'C:\\\\temp%\'', $filter);
        $this->assertSame($filter, Sql::sanitizeFragment($filter, 'filter'));
    }

    public function testClauseBuilderValueCannotBreakOutOfItsString()
    {
        $filter = $this->buildFilterClause([
            [
                'filterClause' => '`name`',
                'filterClauseCriteria' => 'equals',
                'filterClauseValue' => '\' OR `SLEEP`(1) = 0 OR \'',
            ],
        ]);

        $this->assertSame('`name` = \'\'\' OR `SLEEP`(1) = 0 OR \'\'\'', $filter);
        $this->assertSame($filter, Sql::sanitizeFragment($filter, 'filter'));
    }

    private function sqlProperty(): Property
    {
        $property = new Property();
        $property->id = 'filter';
        $property->type = 'text';
        $property->variant = 'sql';
        return $property;
    }

    private function buildFilterClause(array $clauses): string
    {
        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('getProperty')->willReturnCallback(fn ($name, $default = null) => match ($name) {
            'useFilteringClause' => 0,
            'filterClauses' => json_encode($clauses),
            default => $default,
        });

        $listener = (new \ReflectionClass(DataSetDataProviderListener::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($listener, 'buildFilterClause');
        return $method->invoke($listener, $dataProvider);
    }
}
