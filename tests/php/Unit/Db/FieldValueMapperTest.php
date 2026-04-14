<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Db;

use OCA\ProfileFields\Db\FieldValueMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldValueMapperTest extends TestCase {
	private IDBConnection&MockObject $db;
	private IQueryBuilder&MockObject $queryBuilder;
	private IResult&MockObject $result;
	private FieldValueMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->queryBuilder = $this->createMock(IQueryBuilder::class);
		$this->result = $this->createMock(IResult::class);
		$this->mapper = new FieldValueMapper($this->db);
	}

	public function testFindDistinctUserUidsUsesFetchAllColumnModeForCompatibility(): void {
		$this->db->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($this->queryBuilder);

		$this->queryBuilder->expects($this->once())
			->method('selectDistinct')
			->with('user_uid')
			->willReturnSelf();
		$this->queryBuilder->expects($this->once())
			->method('from')
			->with('profile_fields_values')
			->willReturnSelf();
		$this->queryBuilder->expects($this->once())
			->method('orderBy')
			->with('user_uid', 'ASC')
			->willReturnSelf();
		$this->queryBuilder->expects($this->once())
			->method('executeQuery')
			->willReturn($this->result);

		$this->result->expects($this->once())
			->method('fetchAll')
			->with(PDO::FETCH_COLUMN)
			->willReturn(['alice', 7, 'bob']);
		$this->result->expects($this->never())
			->method('fetchFirstColumn');
		$this->result->expects($this->once())
			->method('closeCursor')
			->willReturn(true);

		$this->assertSame(['alice', '7', 'bob'], $this->mapper->findDistinctUserUids());
	}
}
