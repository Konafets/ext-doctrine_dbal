<?php
namespace Konafets\DoctrineDbal\Tests\Unit\Persistence\Legacy;

/***************************************************************
 * Copyright notice
 *
 * (c) 2010-2013 Ernesto Baschny (ernst@cron-it.de)
 * (c) 2014      Stefano Kowalke (blueduck@gmx.net)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Test case
 *
 */
class DatabaseConnectionTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
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
	 * Set the test up
	 *
	 * @return void
	 */
	public function setUp() {
		$this->subject = $GLOBALS['TYPO3_DB'];
		$this->testTable = 'test_t3lib_dbtest';
		$this->testField = 'fieldblob';
		$this->testFieldSecond = 'fieldblub';
		$this->subject->sql_query('CREATE TABLE ' . $this->testTable . ' (
			id int(11) unsigned NOT NULL auto_increment,' .
			$this->testField . ' mediumblob,' .
			$this->testFieldSecond . ' mediumblob,
			PRIMARY KEY (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		');
	}

	/**
	 * Tear the test down
	 *
	 * @return void
	 */
	public function tearDown() {
		$this->subject->sql_query('DROP TABLE ' . $this->testTable . ';');
		unset($this->subject);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectDbReturnsTrue() {
		$this->assertTrue($this->subject->sql_select_db());
	}

	/**
	 * @test
	 * @expectedException \RuntimeException
	 * @expectedExceptionMessage TYPO3 Fatal Error: Cannot connect to the current database, "Foo"!
	 * @return void
	 */
	public function selectDbReturnsFalse() {
		$this->subject->setDatabaseName('Foo');
		$this->assertFalse($this->subject->sql_select_db());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlAffectedRowsReturnsCorrectAmountOfRows() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(1, $this->subject->sql_affected_rows());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlInsertIdReturnsCorrectId() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(1, $this->subject->sql_insert_id());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function noSqlError() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals('', $this->subject->sql_error());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlErrorWhenInsertIntoInexistentField() {
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
		$this->assertEquals('Unknown column \'test\' in \'field list\'', $this->subject->sql_error());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function noSqlErrorCode() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(0, $this->subject->sql_errno());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlErrorNoWhenInsertIntoInexistentField() {
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
		$this->assertEquals(1054, $this->subject->sql_errno());
	}

	/**
	 * @test
	 */
	public function sqlPconnectReturnsInstanceOfMySqli() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$this->assertInstanceOf('mysqli', $subject->sql_pconnect());
	}

	/**
	 * @test
	 * @expectedException \RuntimeException
	 */
	public function connectDbThrowsExeptionsWhenNoDatabaseIsGiven() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$subject->connectDB();
		$this->assertTrue($subject->isConnected());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function connectDbConnectsToDatabaseWithoutErrors() {
		$this->subject->connectDB();
		$this->assertTrue($this->subject->isConnected());
	}

	/**
	 * Data Provider for fullQuoteStrReturnsQuotedString()
	 *
	 * @see fullQuoteStrReturnsQuotedString()
	 *
	 * @return array
	 */
	public function fullQuoteStrReturnsQuotedStringDataProvider() {
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
	 * @dataProvider fullQuoteStrReturnsQuotedStringDataProvider
	 *
	 * @param string $values
	 * @param string $expectedResult
	 *
	 * @return void
	 */
	public function fullQuoteStrReturnsQuotedString($values, $expectedResult) {
		$quotedStr = $this->subject->fullQuoteStr($values[0], $this->testTable, $values[1]);
		$this->assertEquals($expectedResult, $quotedStr);
	}

	/**
	 * Data Provider for fullQuoteArrayQuotesArray()
	 *
	 * @see fullQuoteArrayQuotesArray()
	 *
	 * @return array
	 */
	public function fullQuoteArrayQuotesArrayDataProvider() {
		return array(
			'NULL array with ReturnNull is allowed' => array(
				array(
					array(NULL,NULL),
					FALSE,
					TRUE
				),
				array('NULL', 'NULL')
			),

			'NULL array with ReturnNull is false' => array(
				array(
					array(NULL,NULL),
					FALSE,
					FALSE
				),
				array('\'\'', '\'\'')
			),

			'Strings in array' => array(
				array(
					array('Foo', 'Bar'),
					FALSE,
					FALSE
				),
				array('\'Foo\'', '\'Bar\'')
			),

			'Single quotes in array' => array(
				array(
					array("'Hello'"),
					FALSE,
					FALSE
				),
				array("'\\'Hello\\''")
			),

			'Double quotes in array' => array(
				array(
					array('"Hello"'),
					FALSE,
					FALSE
				),
				array('\'\\"Hello\\"\'')
			),

			'Slashes in array' => array(
				array(
					array('/var/log/syslog.log'),
					FALSE,
					FALSE
				),
				array('\'/var/log/syslog.log\'')
			),

			'Backslashes in array' => array(
				array(
					array('\var\log\syslog.log'),
					FALSE,
					FALSE
				),
				array('\'\\\var\\\log\\\syslog.log\'')
			),

			'Strings with internal single tick' => array(
				array(
					array('Hey!', 'It\'s me'),
					FALSE,
					FALSE
				),
				array('\'Hey!\'', '\'It\\\'s me\'')
			),

			'no quotes strings from array' => array(
				array(
						array(
							'First' => 'Hey!',
							'Second' => 'It\'s me',
							'Third' => 'O\' Reily'
						),
						array('First', 'Third'),
						FALSE
				),
				array('First' =>'Hey!', 'Second' => '\'It\\\'s me\'', 'Third' => 'O\' Reily')
			),

			'no quotes strings from string' => array(
				array(
						array(
							'First' => 'Hey!',
							'Second' => 'It\'s me',
							'Third' => 'O\' Reily'
						),
						'First,Third',
						FALSE
				),
				array('First' =>'Hey!', 'Second' => '\'It\\\'s me\'', 'Third' => 'O\' Reily')
			),
		);
	}

	/**
	 * @test
	 * @dataProvider fullQuoteArrayQuotesArrayDataProvider
	 *
	 * @param string $values
	 * @param string $expectedResult
	 *
	 * @return void
	 */
	public function fullQuoteArrayQuotesArray($values, $expectedResult) {
		$quotedResult = $this->subject->fullQuoteArray($values[0], $this->testTable, $values[1], $values[2]);
		$this->assertSame($expectedResult, $quotedResult);
	}

	/**
	 * Data Provider for quoteStrQuotesDoubleQuotesCorrectly()
	 *
	 * @see quoteStrQuotesDoubleQuotesCorrectly()
	 *
	 * @return array
	 */
	public function quoteStrQuotesCorrectlyDataProvider() {
		return array(
			'Double Quotes' => array('"Hello"', '\\"Hello\\"'),
			'single Quotes' => array('\'Hello\'', '\\\'Hello\\\''),
			'Slashes' => array('/var/log/syslog.log', '/var/log/syslog.log'),
			'BackSlashes' => array('\var\log\syslog.log', '\\\var\\\log\\\syslog.log')
		);
	}

	/**
	 * @test
	 * @dataProvider quoteStrQuotesCorrectlyDataProvider
	 *
	 * @param string $string String to quote
	 * @param string $expectedResult Quoted string we expect
	 *
	 * @return void
	 */
	public function quoteStrQuotesDoubleQuotesCorrectly($string, $expectedResult) {
		$quotedString = $this->subject->quoteStr($string, $this->testTable);
		$this->assertSame($expectedResult, $quotedString);
	}

	/**
	 * Data Provider for cleanIntArrayReturnsCleanedArray()
	 *
	 * @see cleanIntArrayReturnsCleanedArray()
	 *
	 * @return array
	 */
	public function cleanIntArrayReturnsCleanedArrayDataProvider() {
		return array(
			'Simple numbers' => array(array('234', '-434', 4.3, '4.3'), array(234, -434, 4, 4)),
		);
	}

	/**
	 * @test
	 * @dataProvider cleanIntArrayReturnsCleanedArrayDataProvider
	 *
	 * @param string $values
	 * @param string $exptectedResult
	 *
	 * @return void
	 */
	public function cleanIntArrayReturnsCleanedArray($values, $exptectedResult) {
		$cleanedResult = $this->subject->cleanIntArray($values);
		$this->assertSame($exptectedResult, $cleanedResult);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function cleanIntListReturnsCleanedString() {
		$str = '234,-434,4.3,0, 1';
		$result = $this->subject->cleanIntList($str);
		$this->assertSame('234,-434,4,0,1', $result);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function disconnectIfConnectedDisconnects() {
		$this->assertTrue($this->subject->isConnected());
		$this->subject->setDatabaseHost('127.0.0.1');
		$this->assertFalse($this->subject->isConnected());
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminQueryReturnsTrueForInsertQuery() {
		$this->assertTrue($this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminQueryReturnsTrueForUpdateQuery() {
		$this->assertTrue($this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
		$id = $this->subject->sql_insert_id();
		$this->assertTrue($this->subject->admin_query('UPDATE ' . $this->testTable . ' SET fieldblob=\'bar\' WHERE id=' . $id));
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminQueryReturnsTrueForDeleteQuery() {
		$this->assertTrue($this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
		$id = $this->subject->sql_insert_id();
		$this->assertTrue($this->subject->admin_query('DELETE FROM ' . $this->testTable . ' WHERE id=' . $id));
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminQueryReturnsResultForSelectQuery() {
		$this->assertTrue($this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
		$res = $this->subject->admin_query('SELECT fieldblob FROM ' . $this->testTable);
		$this->assertInstanceOf('mysqli_result', $res);
		$result = $res->fetch_assoc();
		$this->assertEquals('foo', $result[$this->testField]);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminGetCharsetsReturnsArrayWithCharsets() {
		$columnsRes = $this->subject->admin_query('SHOW CHARACTER SET');
		$result = $this->subject->admin_get_charsets();
		$this->assertEquals(count($result), $columnsRes->num_rows);

		/** @var array $row */
		while (($row = $columnsRes->fetch_assoc())) {
			$this->assertArrayHasKey($row['Charset'], $result);
		}
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminGetKeysReturnIndexKeysOfTable() {
		$result = $this->subject->admin_get_keys($this->testTable);
		$this->assertEquals('id', $result[0]['Column_name']);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminGetFieldsReturnFieldInformationsForTable() {
		$result = $this->subject->admin_get_fields($this->testTable);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey($this->testField, $result);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminGetTablesReturnAllTablesFromDatabase() {
		$result = $this->subject->admin_get_tables();
		$this->assertArrayHasKey('tt_content', $result);
		$this->assertArrayHasKey('pages', $result);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function adminGetDbsReturnsAllDatabases() {
		$databases = $this->subject->admin_query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
		$result = $this->subject->admin_get_dbs();
		$this->assertSame(count($result), $databases->num_rows);

		$i = 0;
		while ($database = $databases->fetch_assoc()) {
			$this->assertSame($database['SCHEMA_NAME'], $result[$i]);
			$i++;
		}
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function insertQueryCreateValidQuery() {
		$fieldValues = array($this->testField => 'Foo');
		$queryExpected = 'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'Foo\')';
		$queryGenerated = $this->subject->INSERTquery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function insertQueryCreateValidQueryFromMultipleValues() {
		$fieldValues = array(
				$this->testField => 'Foo',
				$this->testFieldSecond => 'Bar'
		);
		$queryExpected =
			'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ',' . $this->testFieldSecond . ') VALUES (\'Foo\',\'Bar\')';
		$queryGenerated = $this->subject->INSERTquery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function insertMultipleRowsCreateValidQuery() {
		$fields = array($this->testField, $this->testFieldSecond);
		$values = array(
			array('Foo', 100),
			array('Bar', 200),
			array('Baz', 300),
		);
		$queryExpected =
			'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ', ' . $this->testFieldSecond . ') VALUES (\'Foo\', \'100\'), (\'Bar\', \'200\'), (\'Baz\', \'300\')';
		$queryGenerated = $this->subject->INSERTmultipleRows($this->testTable, $fields, $values);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function updateQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$fieldsValues = array($this->testField => 'May the force be with you.');
		$where = 'id=' . $id;
		$queryExpected =
			'UPDATE ' . $this->testTable . ' SET ' . $this->testField . '=\'May the force be with you.\' WHERE id=' . $id;
		$queryGenerated = $this->subject->UPDATEquery($this->testTable, $where, $fieldsValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function deleteQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'DELETE FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->DELETEquery($this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectQueryCreateValidQueryWithEmptyWhereClause() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = '';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable;
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectQueryCreateValidQueryWithGroupByClause() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$groupBy = 'id';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' GROUP BY id';
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where, $groupBy);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectQueryCreateValidQueryWithOrderByClause() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$orderBy = 'id';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' ORDER BY id';
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where, '', $orderBy);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectQueryCreateValidQueryWithLimitClause() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, 'id=' . $id, '', '', '1,2');
		$queryExpected =
					'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' LIMIT 1,2';
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function selectSubQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->SELECTsubquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function truncateQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);

		$queryExpected =
			'TRUNCATE TABLE ' . $this->testTable;
		$queryGenerated = $this->subject->TRUNCATEquery($this->testTable);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function prepareSelectQueryCreateValidQuery() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$preparedQuery = $this->subject->prepare_SELECTquery('fieldblob,fieldblub', $this->testTable, 'id=:id', '', '', '', array(':id' => 1));
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
	 * Data Provider for sqlNumRowsReturnsCorrectAmountOfRows()
	 *
	 * @see sqlNumRowsReturnsCorrectAmountOfRows()
	 *
	 * @return array
	 */
	public function sqlNumRowsReturnsCorrectAmountOfRowsProvider() {
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
	 * @dataProvider sqlNumRowsReturnsCorrectAmountOfRowsProvider
	 *
	 * @param string $sql
	 * @param string $expectedResult
	 *
	 * @return void
	 */
	public function sqlNumRowsReturnsCorrectAmountOfRows($sql, $expectedResult) {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'foo\')')
		);
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'bar\')')
		);
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\')')
		);

		$res = $this->subject->admin_query($sql);
		$numRows = $this->subject->sql_num_rows($res);
		$this->assertSame($expectedResult, $numRows);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlNumRowsReturnsFalse() {
		$res = $this->subject->admin_query('SELECT * FROM ' . $this->testTable . ' WHERE test=\'baz\'');
		$numRows = $this->subject->sql_num_rows($res);
		$this->assertFalse($numRows);
	}

	/**
	 * Prepares the test table for the fetch* Tests
	 *
	 * @return void
	 */
	protected function prepareTableForFetchTests() {
		$this->assertTrue(
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD name mediumblob;
			')
		);
		$this->assertTrue(
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD deleted int;
			')
		);

		$this->assertTrue(
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD street varchar(100);
			')
		);

		$this->assertTrue(
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD city varchar(50);
			')
		);

		$this->assertTrue(
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD country varchar(100);
			')
		);

		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Mr. Smith\',\'Oakland Road\',\'Los Angeles\',\'USA\',0)')
		);
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Ms. Smith\',\'Oakland Road\',\'Los Angeles\',\'USA\',0)')
		);
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Alice im Wunderland\',\'Große Straße\',\'Königreich der Herzen\',\'Wunderland\',0)')
		);
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (name,street,city,country,deleted) VALUES (\'Agent Smith\',\'Unbekannt\',\'Unbekannt\',\'Matrix\',1)')
		);
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlFetchAssocReturnsAssocArray() {
		$this->prepareTableForFetchTests();

		$res = $this->subject->admin_query('SELECT * FROM ' . $this->testTable);
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
		while ($row = $this->subject->sql_fetch_assoc($res)) {
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
	 *
	 * @return void
	 */
	public function sqlFetchRowReturnsNumericArray() {
		$this->prepareTableForFetchTests();
		$res = $this->subject->admin_query('SELECT * FROM ' . $this->testTable);
		$expectedResult = array(
					array('1', null, null, 'Mr. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('2', null, null, 'Ms. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('3', null, null, 'Alice im Wunderland', '0', 'Große Straße', 'Königreich der Herzen', 'Wunderland'),
					array('4', null, null, 'Agent Smith', '1', 'Unbekannt', 'Unbekannt', 'Matrix')
				);
		$i = 0;
		while ($row = $this->subject->sql_fetch_row($res)) {
			$this->assertSame($expectedResult[$i], $row);
			$i++;
		}
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlFreeResultReturnsFalse() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\')')
		);
		$res = $this->subject->admin_query('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=baz');
		$this->assertFalse($this->subject->sql_free_result($res));
	}

	/**
	 * @test
	 *
	 * @return void
	 */
	public function sqlFreeResultReturnsNull() {
		$this->assertTrue(
			$this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES (\'baz\')')
		);
		$res = $this->subject->admin_query('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\'');
		$this->assertNULL($this->subject->sql_free_result($res));
	}

	/**
	 * @test
	 */
	public function sql_select_dbReturnsTrue() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject->_set('databaseName', $this->testTable);

		$mysqliMock = $this->getMock('mysqli');
		$mysqliMock
			->expects($this->once())
			->method('select_db')
			->with($this->equalTo($this->testTable))->will($this->returnValue(TRUE));
		$subject->_set('link', $mysqliMock);

		$this->assertTrue($subject->sql_select_db());
	}

	/**
	 * @test
	 */
	public function sql_select_dbReturnsFalse() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject->_set('databaseName', $this->testTable);

		$mysqliMock = $this->getMock('mysqli');
		$mysqliMock
			->expects($this->once())
			->method('select_db')
			->with($this->equalTo($this->testTable))->will($this->returnValue(FALSE));
		$subject->_set('link', $mysqliMock);

		$this->assertFalse($subject->sql_select_db());
	}

	//////////////////////////////////////////////////
	// Write/Read tests for charsets and binaries
	//////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function storedFullAsciiRangeCallsLinkObjectWithGivenData() {
		$binaryString = '';
		for ($i = 0; $i < 256; $i++) {
			$binaryString .= chr($i);
		}

		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('fullQuoteStr'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject
			->expects($this->any())
			->method('fullQuoteStr')
			->will($this->returnCallback(function ($data) {
				return $data;
			}));
		$mysqliMock = $this->getMock('mysqli');
		$mysqliMock
			->expects($this->once())
			->method('query')
			->with('INSERT INTO aTable (fieldblob) VALUES (' . $binaryString . ')');
		$subject->_set('link', $mysqliMock);

		$subject->exec_INSERTquery('aTable', array('fieldblob' => $binaryString));
	}

	/**
	 * @test
	 */
	public function storedGzipCompressedDataReturnsSameData() {
		$testStringWithBinary = @gzcompress('sdfkljer4587');

		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('fullQuoteStr'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject
			->expects($this->any())
			->method('fullQuoteStr')
			->will($this->returnCallback(function ($data) {
				return $data;
			}));
		$mysqliMock = $this->getMock('mysqli');
		$mysqliMock
			->expects($this->once())
			->method('query')
			->with('INSERT INTO aTable (fieldblob) VALUES (' . $testStringWithBinary . ')');
		$subject->_set('link', $mysqliMock);

		$subject->exec_INSERTquery('aTable', array('fieldblob' => $testStringWithBinary));
	}


	////////////////////////////////
	// Tests concerning listQuery
	////////////////////////////////

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/23253
	 */
	public function listQueryWithIntegerCommaAsValue() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('quoteStr'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject
			->expects($this->any())
			->method('quoteStr')
			->will($this->returnCallback(function ($data) {
				return $data;
			}));
		// Note: 44 = ord(',')
		$this->assertEquals($subject->listQuery('dummy', 44, 'table'), $subject->listQuery('dummy', '44', 'table'));
	}

	/**
	 * @test
	 * @expectedException \InvalidArgumentException
	 */
	public function listQueryThrowsExceptionIfValueContainsComma() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('quoteStr'), array(), '', FALSE);
		$subject->_set('isConnected', TRUE);
		$subject->listQuery('aField', 'foo,bar', 'aTable');
	}


	////////////////////////////////
	// Tests concerning searchQuery
	////////////////////////////////

	/**
	 * Data provider for searchQueryCreatesQuery
	 *
	 * @return array
	 */
	public function searchQueryDataProvider() {
		return array(
			'One search word in one field' => array(
				'(pages.title LIKE \'%TYPO3%\')',
				array('TYPO3'),
				array('title'),
				'pages',
				'AND'
			),

			'One search word in multiple fields' => array(
				'(pages.title LIKE \'%TYPO3%\' OR pages.keyword LIKE \'%TYPO3%\' OR pages.description LIKE \'%TYPO3%\')',
				array('TYPO3'),
				array('title', 'keyword', 'description'),
				'pages',
				'AND'
			),

			'Multiple search words in one field with AND constraint' => array(
				'(pages.title LIKE \'%TYPO3%\') AND (pages.title LIKE \'%is%\') AND (pages.title LIKE \'%great%\')',
				array('TYPO3', 'is', 'great'),
				array('title'),
				'pages',
				'AND'
			),

			'Multiple search words in one field with OR constraint' => array(
				'(pages.title LIKE \'%TYPO3%\') OR (pages.title LIKE \'%is%\') OR (pages.title LIKE \'%great%\')',
				array('TYPO3', 'is', 'great'),
				array('title'),
				'pages',
				'OR'
			),

			'Multiple search words in multiple fields with AND constraint' => array(
				'(pages.title LIKE \'%TYPO3%\' OR pages.keywords LIKE \'%TYPO3%\' OR pages.description LIKE \'%TYPO3%\') AND ' .
					'(pages.title LIKE \'%is%\' OR pages.keywords LIKE \'%is%\' OR pages.description LIKE \'%is%\') AND ' .
					'(pages.title LIKE \'%great%\' OR pages.keywords LIKE \'%great%\' OR pages.description LIKE \'%great%\')',
				array('TYPO3', 'is', 'great'),
				array('title', 'keywords', 'description'),
				'pages',
				'AND'
			),

			'Multiple search words in multiple fields with OR constraint' => array(
				'(pages.title LIKE \'%TYPO3%\' OR pages.keywords LIKE \'%TYPO3%\' OR pages.description LIKE \'%TYPO3%\') OR ' .
					'(pages.title LIKE \'%is%\' OR pages.keywords LIKE \'%is%\' OR pages.description LIKE \'%is%\') OR ' .
					'(pages.title LIKE \'%great%\' OR pages.keywords LIKE \'%great%\' OR pages.description LIKE \'%great%\')',
				array('TYPO3', 'is', 'great'),
				array('title', 'keywords', 'description'),
				'pages',
				'OR'
			),
		);
	}

	/**
	 * @test
	 * @dataProvider searchQueryDataProvider
	 */
	public function searchQueryCreatesQuery($expectedResult, $searchWords, $fields, $table, $constraint) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
		$subject = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('quoteStr'), array(), '', FALSE);
		$subject
			->expects($this->any())
			->method('quoteStr')
			->will($this->returnCallback(function ($data) {
				return $data;
			}));

		$this->assertSame($expectedResult, $subject->searchQuery($searchWords, $fields, $table, $constraint));
	}


	/////////////////////////////////////////////////
	// Tests concerning escapeStringForLikeComparison
	/////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function escapeStringForLikeComparison() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
		$subject = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$this->assertEquals('foo\\_bar\\%', $subject->escapeStrForLike('foo_bar%', 'table'));
	}


	/////////////////////////////////////////////////
	// Tests concerning stripOrderByForOrderByKeyword
	/////////////////////////////////////////////////

	/**
	 * Data Provider for stripGroupByForGroupByKeyword()
	 *
	 * @see stripOrderByForOrderByKeyword()
	 * @return array
	 */
	public function stripOrderByForOrderByKeywordDataProvider() {
		return array(
			'single ORDER BY' => array('ORDER BY name, tstamp', 'name, tstamp'),
			'single ORDER BY in lower case' => array('order by name, tstamp', 'name, tstamp'),
			'ORDER BY with additional space behind' => array('ORDER BY  name, tstamp', 'name, tstamp'),
			'ORDER BY without space between the words' => array('ORDERBY name, tstamp', 'name, tstamp'),
			'ORDER BY added twice' => array('ORDER BY ORDER BY name, tstamp', 'name, tstamp'),
			'ORDER BY added twice without spaces in the first occurrence' => array('ORDERBY ORDER BY  name, tstamp', 'name, tstamp'),
			'ORDER BY added twice without spaces in the second occurrence' => array('ORDER BYORDERBY name, tstamp', 'name, tstamp'),
			'ORDER BY added twice without spaces' => array('ORDERBYORDERBY name, tstamp', 'name, tstamp'),
			'ORDER BY added twice without spaces afterwards' => array('ORDERBYORDERBYname, tstamp', 'name, tstamp'),
		);
	}

	/**
	 * @test
	 * @dataProvider stripOrderByForOrderByKeywordDataProvider
	 * @param string $orderByClause The clause to test
	 * @param string $expectedResult The expected result
	 * @return void
	 */
	public function stripOrderByForOrderByKeyword($orderByClause, $expectedResult) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
		$subject = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$strippedQuery = $subject->stripOrderBy($orderByClause);
		$this->assertEquals($expectedResult, $strippedQuery);
	}


	/////////////////////////////////////////////////
	// Tests concerning stripGroupByForGroupByKeyword
	/////////////////////////////////////////////////

	/**
	 * Data Provider for stripGroupByForGroupByKeyword()
	 *
	 * @see stripGroupByForGroupByKeyword()
	 * @return array
	 */
	public function stripGroupByForGroupByKeywordDataProvider() {
		return array(
			'single GROUP BY' => array('GROUP BY name, tstamp', 'name, tstamp'),
			'single GROUP BY in lower case' => array('group by name, tstamp', 'name, tstamp'),
			'GROUP BY with additional space behind' => array('GROUP BY  name, tstamp', 'name, tstamp'),
			'GROUP BY without space between the words' => array('GROUPBY name, tstamp', 'name, tstamp'),
			'GROUP BY added twice' => array('GROUP BY GROUP BY name, tstamp', 'name, tstamp'),
			'GROUP BY added twice without spaces in the first occurrence' => array('GROUPBY GROUP BY  name, tstamp', 'name, tstamp'),
			'GROUP BY added twice without spaces in the second occurrence' => array('GROUP BYGROUPBY name, tstamp', 'name, tstamp'),
			'GROUP BY added twice without spaces' => array('GROUPBYGROUPBY name, tstamp', 'name, tstamp'),
			'GROUP BY added twice without spaces afterwards' => array('GROUPBYGROUPBYname, tstamp', 'name, tstamp'),
		);
	}

	/**
	 * @test
	 * @dataProvider stripGroupByForGroupByKeywordDataProvider
	 * @param string $groupByClause The clause to test
	 * @param string $expectedResult The expected result
	 * @return void
	 */
	public function stripGroupByForGroupByKeyword($groupByClause, $expectedResult) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
		$subject = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$strippedQuery = $subject->stripGroupBy($groupByClause);
		$this->assertEquals($expectedResult, $strippedQuery);
	}


	/////////////////////////////////////////////////
	// Tests concerning stripOrderByForOrderByKeyword
	/////////////////////////////////////////////////

	/**
	 * Data Provider for stripGroupByForGroupByKeyword()
	 *
	 * @see stripOrderByForOrderByKeyword()
	 * @return array
	 */
	public function cleanIntArrayDataProvider() {
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
	 * @dataProvider cleanIntArrayDataProvider
	 * @param array $exampleData The array to sanitize
	 * @param array $expectedResult The expected result
	 * @return void
	 */
	public function cleanIntArray($exampleData, $expectedResult) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection $subject */
		$subject = new \TYPO3\CMS\Core\Database\DatabaseConnection();
		$sanitizedArray = $subject->cleanIntArray($exampleData);
		$this->assertEquals($expectedResult, $sanitizedArray);
	}

}