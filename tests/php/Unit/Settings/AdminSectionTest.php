<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Settings\AdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSectionTest extends TestCase {
	private IURLGenerator&MockObject $urlGenerator;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
	}

	public function testSectionMetadataIsExposed(): void {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Profile fields')
			->willReturn('Profile fields');
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'actions/user.svg')
			->willReturn('/core/img/actions/user.svg');

		$section = new AdminSection($this->urlGenerator, $this->l10n);

		$this->assertSame(Application::APP_ID, $section->getID());
		$this->assertSame('Profile fields', $section->getName());
		$this->assertSame(30, $section->getPriority());
		$this->assertSame('/core/img/actions/user.svg', $section->getIcon());
	}
}
