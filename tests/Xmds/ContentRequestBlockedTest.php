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

namespace Xibo\Tests\Xmds;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Display;
use Xibo\Event\XmdsContentRequestEvent;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Xmds\LogProcessor;
use Xibo\Xmds\Soap;
use Xibo\Xmds\Soap3;
use Xibo\Xmds\Soap7;

/**
 * Tests that every XMDS call that gives a display content stops with a SOAP fault while content is blocked.
 */
class ContentRequestBlockedTest extends TestCase
{
    /**
     * The XMDS calls that give content, with their arguments.
     */
    public static function contentCalls(): array
    {
        return [
            'RequiredFiles' => ['RequiredFiles', ['key', 'hardware']],
            'Schedule' => ['Schedule', ['key', 'hardware']],
            'GetResource' => ['GetResource', ['key', 'hardware', 1, 'region', 2]],
            'GetFile' => ['GetFile', ['key', 'hardware', 3, 'media', 0, 100]],
            'GetDependency' => ['GetDependency', ['key', 'hardware', 'font', 'font.ttf', 0, 100]],
            'GetData' => ['GetData', ['key', 'hardware', 4]],
            'GetWeather' => ['GetWeather', ['key', 'hardware']],
        ];
    }

    /**
     * @dataProvider contentCalls
     */
    public function testContentCallStopsWhileContentIsBlocked(string $method, array $arguments): void
    {
        $soap = $this->soap(Soap7::class, $this->blockingDispatcher());

        $this->expectException(\SoapFault::class);
        $this->expectExceptionMessage('Content updates are not available');

        $soap->$method(...$arguments);
    }

    public function testSoap3GetFileStopsWhileContentIsBlocked(): void
    {
        $soap = $this->soap(Soap3::class, $this->blockingDispatcher());

        $this->expectException(\SoapFault::class);
        $this->expectExceptionMessage('Content updates are not available');

        $soap->GetFile('key', 'hardware', '3.mp4', 'media', 0, 100, 3);
    }

    public function testContentIsAllowedWithoutAListener(): void
    {
        $soap = $this->soap(Soap7::class, new EventDispatcher());

        $isContentAllowed = \Closure::bind(fn () => $this->isContentAllowed(), $soap, Soap::class)();

        $this->assertTrue($isContentAllowed);
    }

    /** A dispatcher with a listener that blocks content, as the Pro licence listener does while walled. */
    private function blockingDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(XmdsContentRequestEvent::$NAME, function (XmdsContentRequestEvent $event) {
            $event->block();
        });

        return $dispatcher;
    }

    /**
     * A Soap object for an authorised display within its bandwidth limit.
     * @template T of \Xibo\Xmds\Soap
     * @param class-string<T> $class
     * @return T
     */
    private function soap(string $class, EventDispatcher $dispatcher)
    {
        $sanitizer = $this->createMock(SanitizerInterface::class);

        $soap = $this->getMockBuilder($class)->disableOriginalConstructor()
            ->onlyMethods(['getSanitizer', 'getConfig', 'isValidServerKey', 'authDisplay', 'checkBandwidth', 'getLog'])
            ->getMock();
        $soap->method('getSanitizer')->willReturn($sanitizer);
        $soap->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $soap->method('isValidServerKey')->willReturn(true);
        $soap->method('authDisplay')->willReturn(true);
        $soap->method('checkBandwidth')->willReturn(true);
        $soap->method('getLog')->willReturn($this->createMock(LogServiceInterface::class));

        $display = $this->getMockBuilder(Display::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $display->displayId = 7;

        // Bound to Soap, which holds these properties (dispatcher is private there).
        \Closure::bind(function () use ($display, $dispatcher) {
            $this->logProcessor = (new \ReflectionClass(LogProcessor::class))->newInstanceWithoutConstructor();
            $this->display = $display;
            $this->dispatcher = $dispatcher;
        }, $soap, Soap::class)();

        return $soap;
    }
}
