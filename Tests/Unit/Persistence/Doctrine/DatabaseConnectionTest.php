<?php

namespace Konafets\DoctrineDbal\Tests\Unit\Persistence\Doctrine;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Stefano Kowalke <blueduck@gmx.net>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DatabaseConnectionTest
 *
 * @package Konafets\DoctrineDbal\Tests\Unit\Persistence\Doctrine
 * @author  Stefano Kowalke <blueduck@gmx.net>
 */
class DatabaseConnectionTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {
	/**
	 * @var \Konafets\DoctrineDbal\Persistence\Doctrine\DatabaseConnection
	 */
	protected $subject = NULL;

	/**
	 * @var string
	 */
	protected $testTable;

	/**
	 * @var string
	 */
	protected $testField;

	/**
	 * @var string
	 */
	protected $testFieldSecond;

	/**
	 * @var \Doctrine\DBAL\Schema\Schema
	 */
	protected $schema;

	/**
	 * @var \Doctrine\DBAL\Schema\Table
	 */
	protected $table;

	/**
	 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $schemaManager;

	/**
	 * Set the test up
	 */
	public function setUp() {
		$this->subject = GeneralUtility::makeInstance('Konafets\\DoctrineDbal\\Persistence\\Doctrine\\DatabaseConnection');
		$this->subject->setDatabaseName($GLOBALS['TYPO3_DB']->getDatabaseName());
		$this->subject->setDatabaseUsername($GLOBALS['TYPO3_DB']->getDatabaseUsername());
		$this->subject->setDatabasePassword($GLOBALS['TYPO3_DB']->getDatabasePassword());
		$this->subject->setDatabasePort($GLOBALS['TYPO3_DB']->getDatabasePort());
		$this->subject->setDatabaseHost($GLOBALS['TYPO3_DB']->getDatabaseHost());
		$this->subject->setDatabaseDriver($GLOBALS['TYPO3_DB']->getDatabaseDriver());
		$this->subject->setDatabaseSocket($GLOBALS['TYPO3_DB']->getDatabaseSocket());
		$this->subject->connectDatabase();

		$this->testTable       = 'test_t3lib_dbtest';
		$this->testField       = 'fieldblob';
		$this->testFieldSecond = 'fieldblub';

		$this->schema = $this->subject->getSchema();
		$this->schemaManager = $this->subject->getSchemaManager();

		$this->table = $this->schema->createTable($this->testTable);
		$this->table->addColumn('id', 'integer', array('unsigned' => TRUE, 'autoincrement' => TRUE));
		$this->table->addColumn($this->testField, 'blob', array('default' => NULL, 'notnull' => FALSE));
		$this->table->addColumn($this->testFieldSecond, 'integer', array('default' => NULL, 'notnull' => FALSE));
		$this->table->setPrimaryKey(array('id'));
		$this->schemaManager->dropAndCreateTable($this->table);
	}

	/**
	 * Tear the test down
	 */
	public function tearDown() {
		$this->schemaManager->dropTable($this->table);
		$this->subject->close();
		unset($this->subject, $this->table, $this->schemaManager, $this->schema);
	}


	/**
	 * @test
	 */
	public function selectDatabaseReturnsTrue() {
		$this->assertTrue($this->subject->selectDatabase());
	}

	/**
	 * @test
	 * @expectedException \Konafets\DoctrineDbal\Exception\ConnectionException
	 */
	public function selectDatabaseWithNonExistantDatabaseThrowsException() {
		$this->markTestIncomplete();
		$this->subject->setDatabaseName('Foo');
		$this->subject->selectDatabase();
	}

	/**
	 * @test
	 * @expectedException \RuntimeException
	 */
	public function connectDatabaseThrowsExeptionsWhenNoDatabaseIsGiven() {
		/** @var \Konafets\DoctrineDbal\Persistence\Doctrine\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('Konafets\\DoctrineDbal\\Persistence\\Legacy\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$subject->connectDatabase();
		$this->assertTrue($subject->isConnected());
	}

	/**
	 * @test
	 */
	public function connectDbConnectsToDatabaseWithoutErrors() {
		$this->subject->connectDatabase();
		$this->assertTrue($this->subject->isConnected());
	}

	/**
	 * @test
	 */
	public function insertQueryCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$fieldValues = array($this->testField => 'Foo');
		$queryExpected = 'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'Foo\')';
		$queryGenerated = $this->subject->createInsertQuery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function insertQueryCreateValidQueryFromMultipleValues() {
		$this->markTestIncomplete('Need to be implemented');
		$fieldValues = array(
				$this->testField => 'Foo',
				$this->testFieldSecond => 'Bar'
		);
		$queryExpected =
			'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ',' . $this->testFieldSecond . ') VALUES (\'Foo\',\'Bar\')';
		$queryGenerated = $this->subject->createInsertQuery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function insertMultipleRowsCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$fields = array($this->testField, $this->testFieldSecond);
		$values = array(
			array('Foo', 100),
			array('Bar', 200),
			array('Baz', 300),
		);
		$queryExpected =
			'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ', ' . $this->testFieldSecond . ') VALUES (\'Foo\', \'100\'), (\'Bar\', \'200\'), (\'Baz\', \'300\')';
		$queryGenerated = $this->subject->createInsertMultipleRowsQuery($this->testTable, $fields, $values);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function updateQueryCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$fieldsValues = array($this->testField => 'May the force be with you.');
		$where = 'id=' . $id;
		$queryExpected =
			'UPDATE ' . $this->testTable . ' SET ' . $this->testField . '=\'May the force be with you.\' WHERE id=' . $id;
		$queryGenerated = $this->subject->createUpdateQuery($this->testTable, $where, $fieldsValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectQueryCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->createSelectQuery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectQueryCreateValidQueryWithEmptyWhereClause() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$where = '';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable;
		$queryGenerated = $this->subject->createSelectQuery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectQueryCreateValidQueryWithGroupByClause() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$where = 'id=' . $id;
		$groupBy = 'id';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' GROUP BY id';
		$queryGenerated = $this->subject->createSelectQuery($this->testField, $this->testTable, $where, $groupBy);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectQueryCreateValidQueryWithOrderByClause() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$where = 'id=' . $id;
		$orderBy = 'id';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' ORDER BY id';
		$queryGenerated = $this->subject->createSelectQuery($this->testField, $this->testTable, $where, '', $orderBy);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectQueryCreateValidQueryWithLimitClause() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$queryGenerated = $this->subject->createSelectQuery($this->testField, $this->testTable, 'id=' . $id, '', '', '1,2');
		$queryExpected =
					'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' LIMIT 1,2';
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function selectSubQueryCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->getLastInsertId();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->createSelectSubQuery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/***************************
	 *
	 * Tests for DELETE queries
	 *
	 ***************************/

	/**
	 * @test
	 */
	public function createDeleteQueryReturnsDeleteQueryObject() {
		$this->assertInstanceOf(
				'\Konafets\DoctrineDbal\Persistence\Database\DeleteQueryInterface',
				$this->subject->createDeleteQuery()
		);
	}

	/**
	 * @test
	 */
	public function executeDeleteQueryReturnsInsertRows() {
		$this->markTestIncomplete('executeInsertQueryNeedsToBeImplementedFirst');
		$fields = array(
				$this->testField => 'Foo',
				$this->testFieldSecond => 'Bar'
			);

		$inserted = $this->subject->executeInsertQuery($this->testTable, $fields);
		$this->assertSame(1, $inserted);

		$deleted = $this->subject->executeDeleteQuery($this->testTable, $fields);
		$this->assertSame(1, $deleted);
	}

	/**
	 * @test
	 */
	public function deleteQueryWithoutWhereClauseCreateValidQuery() {
		$expectedSql = 'DELETE FROM ' . $this->testTable . ' WHERE id=1';
		$queryGenerated = $this->subject->deleteQuery($this->testTable, 'id=1');
		$this->assertSame($expectedSql, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function deleteQueryCreateValidQuery() {
		$this->markTestIncomplete('Need to be implemented');
		$expectedSql = 'DELETE FROM ' . $this->testTable . ' WHERE ' . $this->testField . '=Foo';
		$queryGenerated = $this->subject->deleteQuery($this->testTable, array($this->testField => 'Foo'))->getSql();
		$this->assertSame($expectedSql, $queryGenerated);
	}

	/****************************
	 *
	 * Tests for TRUNCATE queries
	 *
	 ****************************/

	/**
	 * @test
	 */
	public function createTruncateQueryReturnsTruncateQueryObject() {
		$this->assertInstanceOf(
				'Konafets\DoctrineDbal\Persistence\Database\TruncateQueryInterface',
				$this->subject->createTruncateQuery()
		);
	}

	/**
	 * @test
	 */
	public function prepareSelectQueryCreateValidQuery() {
		$this->markTestIncomplete();
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$preparedQuery = $this->subject->prepareSelectQuery('fieldblob,fieldblub', $this->testTable, 'id=:id', '', '', '', array(':id' => 1));
		$preparedQuery->execute();
		$result = $preparedQuery->fetch();
		$expectedResult = array(
			'fieldblob' => 'foo',
			'fieldblub' => null
		);
		$this->assertSame($expectedResult['fieldblob'], $result['fieldblob']);
		$this->assertSame($expectedResult['fieldblub'], $result['fieldblub']);
	}

	/**
	 * @test
	 */
	public function prepareSelectQueryArrayCreateValidQuery() {
		$this->markTestIncomplete();
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$preparedQuery = $this->subject->prepareSelectQueryArray('fieldblob,fieldblub', $this->testTable, 'id=:id', '', '', '', array(':id' => 1));
		$preparedQuery->execute();
		$result = $preparedQuery->fetch();
		$expectedResult = array(
			'fieldblob' => 'foo',
			'fieldblub' => null
		);
		$this->assertSame($expectedResult['fieldblob'], $result['fieldblob']);
		$this->assertSame($expectedResult['fieldblub'], $result['fieldblub']);
	}

	/**
	 * @test
	 */
	public function preparePreparedQueryCreateValidQuery() {
		$this->markTestIncomplete();
		$this->assertTrue(
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$preparedQuery = $this->subject->prepareSelectQueryArray('fieldblob,fieldblub', $this->testTable, 'id=:id', '', '', '', array(':id' => 1));
		$preparedQuery->execute();
		$result = $preparedQuery->fetch();
		$expectedResult = array(
			'fieldblob' => 'foo',
			'fieldblub' => null
		);
		$this->assertSame($expectedResult['fieldblob'], $result['fieldblob']);
		$this->assertSame($expectedResult['fieldblub'], $result['fieldblub']);
	}

	/**
	 * Data Provider for fullQuoteStringReturnsQuotedString()
	 *
	 * @see fullQuoteStringReturnsQuotedString()
	 */
	public function fullQuoteStringReturnsQuotedStringDataProvider() {
		return array(
			'NULL string with ReturnNull is allowed' => array(array(NULL,TRUE), 'NULL'),
			'NULL string with ReturnNull is false' => array(array(NULL,FALSE), '\'\''),
			'Normal string' => array(array('Foo',FALSE), '\'Foo\''),
			'Single quoted string' => array(array("'Hello'",FALSE), "'\\'Hello\\''"),
			'Double quoted string' => array(array('"Hello"',FALSE), '\'\\"Hello\\"\''),
			'String with internal single tick' => array(array('It\'s me',FALSE), '\'It\\\'s me\''),
			'Slashes' => array(array('/var/log/syslog.log',FALSE), '\'/var/log/syslog.log\''),
			'Backslashes' => array(array('\var\log\syslog.log',FALSE), '\'\\\var\\\log\\\syslog.log\''),
		);
	}

	/**
	 * @test
	 * @dataProvider fullQuoteStringReturnsQuotedStringDataProvider
	 *
	 * @param string $values
	 * @param string $expectedResult
	 */
	public function fullQuoteStringReturnsQuotedString($values, $expectedResult) {
		$quotedStr = $this->subject->fullQuoteString($values[0], $this->testTable, $values[1]);
		$this->assertEquals($expectedResult, $quotedStr);
	}

	/**
	 * Data Provider for quoteStringQuotesDoubleQuotesCorrectly()
	 *
	 * @see quoteStringQuotesDoubleQuotesCorrectly()
	 */
	public function quoteStringQuotesCorrectlyDataProvider() {
		return array(
			'Double Quotes' => array('"Hello"', '\\"Hello\\"'),
			'single Quotes' => array('\'Hello\'', '\\\'Hello\\\''),
			'Slashes' => array('/var/log/syslog.log', '/var/log/syslog.log'),
			'BackSlashes' => array('\var\log\syslog.log', '\\\var\\\log\\\syslog.log')
		);
	}

	/**
	 * @test
	 * @dataProvider quoteStringQuotesCorrectlyDataProvider
	 *
	 * @param string $string String to quote
	 * @param string $expectedResult Quoted string we expect
	 */
	public function quoteStringQuotesDoubleQuotesCorrectly($string, $expectedResult) {
		$quotedString = $this->subject->quoteString($string, $this->testTable);
		$this->assertSame($expectedResult, $quotedString);
	}

		/////////////////////////////////////////////////
	// Tests concerning escapeStringForLikeComparison
	/////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function escapeStringForLikeComparison() {
		/** @var \Konafets\DoctrineDbal\Persistence\Legacy\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
//		$subject = $this->getMock('Konafets\\DoctrineDbal\\Persistence\\Legacy\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$this->assertEquals('\'foo_bar%\'', $this->subject->escapeStringForLike('foo_bar%', 'table'));
	}

	/**
	 * Data Provider for cleanIntegerArrayReturnsCleanedArray()
	 *
	 * @see cleanIntegerArrayReturnsCleanedArray()
	 */
	public function cleanIntegerArrayReturnsCleanedArrayDataProvider() {
		return array(
			'Simple numbers' => array(array('234', '-434', 4.3, '4.3'), array(234, -434, 4, 4)),
		);
	}

	/**
	 * @test
	 * @dataProvider cleanIntegerArrayReturnsCleanedArrayDataProvider
	 *
	 * @param string $values
	 * @param string $exptectedResult
	 */
	public function cleanIntegerArrayReturnsCleanedArray($values, $exptectedResult) {
		$cleanedResult = $this->subject->cleanIntegerArray($values);
		$this->assertSame($exptectedResult, $cleanedResult);
	}

	/**
	 * @test
	 */
	public function cleanIntegerListReturnsCleanedString() {
		$str = '234,-434,4.3,0, 1';
		$result = $this->subject->cleanIntegerList($str);
		$this->assertSame('234,-434,4,0,1', $result);
	}

	/**
	 * Data Provider for cleanIntegerArrayDataProvider()
	 *
	 * @see cleanIntegerArrayDataProvider()
	 * @return array
	 */
	public function cleanIntegerArrayDataProvider() {
		return array(
			'simple array' => array(
				array(1, 2, 3),
				array(1, 2, 3)
			),
			'string array' => array(
				array('2', '4', '8'),
				array(2, 4, 8)
			),
			'string array with letters #1' => array(
				array('3', '6letters', '12'),
				array(3, 6, 12)
			),
			'string array with letters #2' => array(
				array('3', 'letters6', '12'),
				array(3, 0, 12)
			),
			'string array with letters #3' => array(
				array('3', '6letters4', '12'),
				array(3, 6, 12)
			),
			'associative array' => array(
				array('apples' => 3, 'bananas' => 4, 'kiwis' => 9),
				array('apples' => 3, 'bananas' => 4, 'kiwis' => 9)
			),
			'associative string array' => array(
				array('apples' => '1', 'bananas' => '5', 'kiwis' => '7'),
				array('apples' => 1, 'bananas' => 5, 'kiwis' => 7)
			),
			'associative string array with letters #1' => array(
				array('apples' => '1', 'bananas' => 'no5', 'kiwis' => '7'),
				array('apples' => 1, 'bananas' => 0, 'kiwis' => 7)
			),
			'associative string array with letters #2' => array(
				array('apples' => '1', 'bananas' => '5yes', 'kiwis' => '7'),
				array('apples' => 1, 'bananas' => 5, 'kiwis' => 7)
			),
			'associative string array with letters #3' => array(
				array('apples' => '1', 'bananas' => '5yes9', 'kiwis' => '7'),
				array('apples' => 1, 'bananas' => 5, 'kiwis' => 7)
			),
			'multidimensional associative array' => array(
				array('apples' => '1', 'bananas' => array(3, 4), 'kiwis' => '7'),
				// intval(array(...)) is 1
				// But by specification "cleanIntArray" should only get used on one-dimensional arrays
				array('apples' => 1, 'bananas' => 1, 'kiwis' => 7)
			),
		);
	}

	/**
	 * @test
	 * @dataProvider cleanIntegerArrayDataProvider
	 * @param array $exampleData The array to sanitize
	 * @param array $expectedResult The expected result
	 */
	public function cleanIntegerArray($exampleData, $expectedResult) {
		/** @var \Konafets\DoctrineDbal\Persistence\Doctrine\DatabaseConnection $subject */
		$subject = new \Konafets\DoctrineDbal\Persistence\Doctrine\DatabaseConnection();
		$sanitizedArray = $subject->cleanIntegerArray($exampleData);
		$this->assertEquals($expectedResult, $sanitizedArray);
	}

	/**
	 * @test
	 */
	public function getLastInsertIdReturnsCorrectId() {
		$this->markTestIncomplete();
		$this->subject->executeInsertQuery($this->testTable, array($this->testField => 'testA'));
		$this->subject->executeInsertQuery($this->testTable, array($this->testField => 'testB'));
		$this->subject->executeInsertQuery($this->testTable, array($this->testField => 'testC'));

		$this->assertEquals(3, $this->subject->getLastInsertId($this->testTable));
	}

	/**
	 * @test
	 */
	public function getAffectedRowsReturnsCorrectAmountOfRows() {
		$this->markTestIncomplete();
		$this->subject->executeInsertQuery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(1, $this->subject->getAffectedRows());
	}

	/**
	 * Prepares the test table for the fetch* Tests
	 */
	protected function prepareTableForFetchTests() {
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('ALTER TABLE ' . $this->testTable . '
				ADD name mediumblob;
			')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('ALTER TABLE ' . $this->testTable . '
				ADD deleted int;
			')
		);

		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('ALTER TABLE ' . $this->testTable . '
				ADD street varchar(100);
			')
		);

		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('ALTER TABLE ' . $this->testTable . '
				ADD city varchar(50);
			')
		);

		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('ALTER TABLE ' . $this->testTable . '
				ADD country varchar(100);
			')
		);

		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Mr. Smith\',\'Oakland Road\',\'Los Angeles\',\'USA\',0)')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Ms. Smith\',\'Oakland Road\',\'Los Angeles\',\'USA\',0)')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Alice im Wunderland\',\'Große Straße\',\'Königreich der Herzen\',\'Wunderland\',0)')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Agent Smith\',\'Unbekannt\',\'Unbekannt\',\'Matrix\',1)')
		);
	}

	/**
	 * @test
	 */
	public function fetchAssocReturnsAssocArray() {
		$this->prepareTableForFetchTests();

		$res = $this->subject->adminQuery('SELECT * FROM ' . $this->testTable);
		$expectedResult = array(
			array(
				'id' => '1',
				'fieldblob' => null,
				'fieldblub' => null,
				'name'      => 'Mr. Smith',
				'deleted'   => '0',
				'street'    => 'Oakland Road',
				'city'      => 'Los Angeles',
				'country'   => 'USA'
			),
			array(
				'id' => '2',
				'fieldblob' => null,
				'fieldblub' => null,
				'name'      => 'Ms. Smith',
				'deleted'   => '0',
				'street'    => 'Oakland Road',
				'city'      => 'Los Angeles',
				'country'   => 'USA'
			),
			array(
				'id' => '3',
				'fieldblob' => null,
				'fieldblub' => null,
				'name'      => 'Alice im Wunderland',
				'deleted'   => '0',
				'street'    => 'Große Straße',
				'city'      => 'Königreich der Herzen',
				'country'   => 'Wunderland'
			),
			array(
				'id' => '4',
				'fieldblob' => null,
				'fieldblub' => null,
				'name'      => 'Agent Smith',
				'deleted'   => '1',
				'street'    => 'Unbekannt',
				'city'      => 'Unbekannt',
				'country'   => 'Matrix'
			)
		);
		$i = 0;
		while ($row = $this->subject->fetchAssoc($res)) {
			$this->assertSame($expectedResult[$i]['id'], $row['id']);
			$this->assertSame($expectedResult[$i]['fieldblob'], $row['fieldblob']);
			$this->assertSame($expectedResult[$i]['fieldblub'], $row['fieldblub']);
			$this->assertSame($expectedResult[$i]['name'], $row['name']);
			$this->assertSame($expectedResult[$i]['deleted'], $row['deleted']);
			$this->assertSame($expectedResult[$i]['street'], $row['street']);
			$this->assertSame($expectedResult[$i]['city'], $row['city']);
			$this->assertSame($expectedResult[$i]['country'], $row['country']);
			$i++;
		}
	}

	/**
	 * @test
	 */
	public function fetchRowReturnsNumericArray() {
		$this->prepareTableForFetchTests();
		$res = $this->subject->adminQuery('SELECT * FROM ' . $this->testTable);
		$expectedResult = array(
					array('1', null, null, 'Mr. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('2', null, null, 'Ms. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('3', null, null, 'Alice im Wunderland', '0', 'Große Straße', 'Königreich der Herzen', 'Wunderland'),
					array('4', null, null, 'Agent Smith', '1', 'Unbekannt', 'Unbekannt', 'Matrix')
				);
		$i = 0;
		while ($row = $this->subject->fetchRow($res)) {
			$this->assertSame($expectedResult[$i], $row);
			$i++;
		}
	}

	/**
	 * @test
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'baz' in 'where clause'
	 */
	public function freeResultReturnsFalse() {
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\')')
		);
		$res = $this->subject->adminQuery('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=baz');
		$this->assertFalse($this->subject->freeResult($res));
	}

	/**
	 * @test
	 */
	public function sqlFreeResultReturnsTrue() {
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\')')
		);
		$res = $this->subject->adminQuery('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\'');
		$this->assertTrue($this->subject->freeResult($res));
	}

	/**
	 * @test
	 */
	public function getErrorMessageNoError() {
		$this->markTestIncomplete();
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals('', $this->subject->getErrorMessage());
	}

	/**
	 * @test
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'field list'
	 */
	public function getErrorMessageWhenInsertIntoInexistentField() {
		$this->markTestIncomplete();
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
		$this->assertEquals('Unknown column \'test\' in \'field list\'', $this->subject->getErrorMessage());
	}

	/**
	 * @test
	 */
	public function getErrorCodeNoError() {
		$this->markTestIncomplete();
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(0, $this->subject->getErrorCode());
	}

	/**
	 * @test
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'field list'
	 */
	public function getErrorCodeNoWhenInsertIntoInexistentField() {
		$this->markTestIncomplete();
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
		$this->assertEquals(1054, $this->subject->getErrorCode());
	}

	/**
	 * Data Provider for sqlNumRowsReturnsCorrectAmountOfRows()
	 *
	 * @see sqlNumRowsReturnsCorrectAmountOfRows()
	 *
	 * @return array
	 */
	public function getResultRowCountReturnsCorrectAmountOfRowsProvider() {
		$sql1 = 'SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\'';
		$sql2 = 'SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\' OR fieldblob=\'bar\'';
		$sql3 = 'SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\' OR fieldblob=\'bar\' OR fieldblob=\'foo\'';

		return array(
			'One result' => array($sql1, 1),
			'Two results' => array($sql2, 2),
			'Three results' => array($sql3, 3),
		);
	}

	/**
	 * @test
	 * @dataProvider getResultRowCountReturnsCorrectAmountOfRowsProvider
	 *
	 * @param string $sql
	 * @param string $expectedResult
	 */
	public function getResultRowCountReturnsCorrectAmountOfRows($sql, $expectedResult) {
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\');')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'bar\');')
		);
		$this->assertInstanceOf(
			'\Doctrine\DBAL\Driver\PDOStatement',
			$this->subject->adminQuery('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\');')
		);

		$res = $this->subject->adminQuery($sql);
		$numRows = $this->subject->getResultRowCount($res);
		$this->assertSame($expectedResult, $numRows);
	}

	/**
	 * @test
	 *
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'where clause'
	 */
	public function getResultRowCountReturnsFalse() {
		$res = $this->subject->adminQuery('SELECT * FROM ' . $this->testTable . ' WHERE test=\'baz\'');
		$numRows = $this->subject->getResultRowCount($res);
		$this->assertFalse($numRows);
	}

	/**
	 * @test
	 */
	public function dataSeek() {
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 */
	public function getFieldType() {
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 */
	public function listDatabases() {
		$databases = $this->subject->adminQuery('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
		$result = $this->subject->listDatabases();
		$this->assertSame(count($result), $databases->rowCount());

		$i = 0;
		while ($database = $this->subject->fetchAssoc($databases)) {
			$this->assertSame($database['SCHEMA_NAME'], $result[$i]);
			$i++;
		}
	}

	/**
	 * @test
	 */
	public function listTables() {
		$result = $this->subject->listTables();
		$this->assertArrayHasKey('tt_content', $result);
		$this->assertArrayHasKey('pages', $result);
	}

	/**
	 * @test
	 */
	public function listFields() {
		$result = $this->subject->listFields($this->testTable);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey($this->testField, $result);
	}

	/**
	 * @test
	 */
	public function listKeys() {
		$result = $this->subject->listKeys($this->testTable);
		$this->assertEquals('id', $result[0]['Column_name']);
	}

	/**
	 * @test
	 */
	public function listDatabaseCharsets() {
		$columnsRes = $this->subject->adminQuery('SHOW CHARACTER SET');
		$result = $this->subject->listDatabaseCharsets();
		$this->assertEquals(count($result), $columnsRes->rowCount());

		/** @var array $row */
		while (($row = $this->subject->fetchAssoc($columnsRes))) {
			$this->assertArrayHasKey($row['Charset'], $result);
		}
	}

	/**
	 * @test
	 */
	public function debugCheckRecordset() {
		$this->markTestIncomplete();
	}
}

