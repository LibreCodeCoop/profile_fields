<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Listener;

use OCA\ProfileFields\Listener\BeforeTemplateRenderedListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

class BeforeTemplateRenderedListenerTest extends TestCase {
	public function testHandleIgnoresUnrelatedEvent(): void {
		$listener = new BeforeTemplateRenderedListener();

		$listener->handle(new class() extends Event {
		});

		self::assertTrue(true);
	}
}
