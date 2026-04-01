<?php
/*
 * Copyright (C) 2026 Xibo Players
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 */

namespace Xibo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Display;

/**
 * Unit tests for Display::isPwa() and Display::isWebSocketXmrSupported()
 */
class DisplayEntityTest extends TestCase
{
    private function makeDisplay(string $clientType, int $clientCode = 0): Display
    {
        // Use reflection to create Display without constructor (avoids DI dependencies)
        $ref = new \ReflectionClass(Display::class);
        $display = $ref->newInstanceWithoutConstructor();
        $display->clientType = $clientType;
        $display->clientCode = $clientCode;
        return $display;
    }

    // ── isPwa() ──────────────────────────────────────────────────

    public function testIsPwaReturnsTrueForChromeOS(): void
    {
        $this->assertTrue($this->makeDisplay('chromeOS')->isPwa());
    }

    public function testIsPwaReturnsTrueForPwa(): void
    {
        $this->assertTrue($this->makeDisplay('pwa')->isPwa());
    }

    public function testIsPwaReturnsFalseForLinux(): void
    {
        $this->assertFalse($this->makeDisplay('linux', 400)->isPwa());
    }

    public function testIsPwaReturnsFalseForWindows(): void
    {
        $this->assertFalse($this->makeDisplay('windows', 407)->isPwa());
    }

    public function testIsPwaReturnsFalseForAndroid(): void
    {
        $this->assertFalse($this->makeDisplay('android', 408)->isPwa());
    }

    // ── isWebSocketXmrSupported() ────────────────────────────────

    public function testXmrSupportedForChromeOS(): void
    {
        $this->assertTrue($this->makeDisplay('chromeOS')->isWebSocketXmrSupported());
    }

    public function testXmrSupportedForPwa(): void
    {
        $this->assertTrue($this->makeDisplay('pwa')->isWebSocketXmrSupported());
    }

    public function testXmrSupportedForLinuxAbove400(): void
    {
        $this->assertTrue($this->makeDisplay('linux', 400)->isWebSocketXmrSupported());
    }

    public function testXmrNotSupportedForLinuxBelow400(): void
    {
        $this->assertFalse($this->makeDisplay('linux', 399)->isWebSocketXmrSupported());
    }

    public function testXmrSupportedForWindowsAbove407(): void
    {
        $this->assertTrue($this->makeDisplay('windows', 407)->isWebSocketXmrSupported());
    }

    public function testXmrNotSupportedForWindowsBelow407(): void
    {
        $this->assertFalse($this->makeDisplay('windows', 406)->isWebSocketXmrSupported());
    }

    public function testXmrSupportedForAndroidAbove408(): void
    {
        $this->assertTrue($this->makeDisplay('android', 408)->isWebSocketXmrSupported());
    }

    public function testXmrNotSupportedForAndroidBelow408(): void
    {
        $this->assertFalse($this->makeDisplay('android', 407)->isWebSocketXmrSupported());
    }

    public function testXmrNotSupportedForUnknownType(): void
    {
        $this->assertFalse($this->makeDisplay('unknown', 999)->isWebSocketXmrSupported());
    }
}
