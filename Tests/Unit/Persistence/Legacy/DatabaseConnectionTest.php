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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test case
 *
 */
class DatabaseConnectionTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \Konafets\DoctrineDbal\Persistence\Legacy\DatabaseConnection
	 */
	protected $subject = NULL;

	/**
	 * @var string
	 */
	protected $testTable;

	/**
	 * @var string
	 */
	protected $testTableMm;

	/**
	 * @var string
	 */
	protected $testTableForeign;

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
	 * @var \Doctrine\DBAL\Schema\Table
	 */
	protected $mmTable;

	/**
	 * @var \Doctrine\DBAL\Schema\Table
	 */
	protected $foreignTable;

	/**
	 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $schemaManager;

	/**
	 * Set the test up
	 */
	public function setUp() {
		$this->subject = GeneralUtility::makeInstance('Konafets\\DoctrineDbal\\Persistence\\Legacy\\DatabaseConnection');
		$this->subject->setDatabaseName(TYPO3_db);
		$this->subject->setDatabaseUsername(TYPO3_db_username);
		$this->subject->setDatabasePassword(TYPO3_db_password);
		$this->subject->setDatabasePort($GLOBALS['TYPO3_DB']->getDatabasePort());
		$this->subject->setDatabaseHost($GLOBALS['TYPO3_DB']->getDatabaseHost());
		$this->subject->connectDB();

		$this->testTable        = 'test_t3lib_dbtest';
		$this->testTableMm      = 'test_t3lib_dbtest_mm';
		$this->testTableForeign = 'test_t3lib_dbtest_foreign';
		$this->testField        = 'fieldblob';
		$this->testFieldSecond  = 'fieldblub';

		$this->schema = $this->subject->getSchema();
		$this->schemaManager = $this->subject->getSchemaManager();

		$this->table = $this->schema->createTable($this->testTable);
		$this->table->addColumn('id', 'integer', array('unsigned' => TRUE, 'autoincrement' => TRUE));
		$this->table->addColumn('uid', 'integer', array('unsigned' => TRUE));
		$this->table->addColumn($this->testField, 'blob', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->table->addColumn($this->testFieldSecond, 'integer', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->table->setPrimaryKey(array('id'));
		$this->schemaManager->dropAndCreateTable($this->table);

		$this->mmTable = $this->schema->createTable($this->testTableMm);
		$this->mmTable->addColumn('id', 'integer', array('unsigned' => TRUE, 'autoincrement' => TRUE));
		$this->mmTable->addColumn('uid_local', 'integer', array('unsigned' => TRUE));
		$this->mmTable->addColumn('uid_foreign', 'integer', array('unsigned' => TRUE));
		$this->mmTable->addColumn($this->testField, 'blob', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->mmTable->addColumn($this->testFieldSecond, 'integer', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->mmTable->setPrimaryKey(array('id'));

		$this->schemaManager->dropAndCreateTable($this->mmTable);

		$this->foreignTable = $this->schema->createTable($this->testTableForeign);
		$this->foreignTable->addColumn('id', 'integer', array('unsigned' => TRUE, 'autoincrement' => TRUE));
		$this->foreignTable->addColumn('uid', 'integer', array('unsigned' => TRUE));
		$this->foreignTable->addColumn($this->testField, 'blob', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->foreignTable->addColumn($this->testFieldSecond, 'integer', array('default' => NULL, 'notnull' => FALSE, 'collate'=>'utf8_general_ci'));
		$this->foreignTable->setPrimaryKey(array('id'));

		$this->schemaManager->dropAndCreateTable($this->foreignTable);
	}

	/**
	 * Tear the test down
	 */
	public function tearDown() {
		$this->schemaManager->dropTable($this->table);
		$this->schemaManager->dropTable($this->mmTable);
		$this->schemaManager->dropTable($this->foreignTable);
		$this->subject->close();
		unset($this->subject, $this->table, $this->mmTable, $this->foreignTable, $this->schemaManager, $this->schema);
	}

	/**
	 * @test
	 */
	public function sql_select_dbReturnsTrue() {
		$this->assertTrue($this->subject->sql_select_db());
	}

	/**
	 * @test
	 * @expectedException \Konafets\DoctrineDbal\Exception\ConnectionException
	 * @expectedExceptionMessage SQLSTATE[HY000] [1049] Unknown database 'foo'
	 */
	public function sql_select_dbReturnsFalse() {
		$this->subject->setDatabaseName('Foo');
		$this->assertFalse($this->subject->sql_select_db());
	}

	/**
	 * @test
	 */
	public function sql_affected_rowsReturnsCorrectAmountOfRows() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(1, $this->subject->sql_affected_rows());
	}

	/**
	 * @test
	 */
	public function sql_insert_idReturnsCorrectId() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(1, $this->subject->sql_insert_id());
	}

	/**
	 * @test
	 */
	public function sql_errorRaisesNoError() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals('', $this->subject->sql_error());
	}

	/**
	 * @test
	 *
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'field list'
	 */
	public function exec_INSERTqueryIntoInexistentFieldThrowsException() {
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
	}

	/**
	 * @test
	 */
	public function sql_errnoRaisesNoError() {
		$this->subject->exec_INSERTquery($this->testTable, array($this->testField => 'test'));
		$this->assertEquals(0, $this->subject->sql_errno());
	}

	/**
	 * @test
	 *
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'field list'
	 */
	public function exec_INSERTqueryThrowsExceptionWhenInsertIntoInexistentField() {
		$this->subject->exec_INSERTquery($this->testTable, array('test' => 'test'));
	}

	/**
	 * @test
	 */
	public function sql_pconnectReturnsInstanceOfDoctrineDBALConnection() {
		/** @var \Konafets\DoctrineDbal\Persistence\Legacy\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('Konafets\\DoctrineDbal\\Persistence\\Legacy\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$this->assertInstanceOf('Doctrine\\DBAL\\Connection', $subject->sql_pconnect());
	}

	/**
	 * @test
	 * @expectedException \RuntimeException
	 */
	public function connectDBThrowsExeptionsWhenNoDatabaseIsGiven() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $subject */
		$subject = $this->getAccessibleMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$subject->connectDB();
		$this->assertTrue($subject->isConnected());
	}

	/**
	 * @test
	 */
	public function connectDBConnectsToDatabaseWithoutErrors() {
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
	 */
	public function cleanIntArrayReturnsCleanedArray($values, $exptectedResult) {
		$cleanedResult = $this->subject->cleanIntArray($values);
		$this->assertSame($exptectedResult, $cleanedResult);
	}

	/**
	 * @test
	 */
	public function cleanIntListReturnsCleanedString() {
		$str = '234,-434,4.3,0, 1';
		$result = $this->subject->cleanIntList($str);
		$this->assertSame('234,-434,4,0,1', $result);
	}

	/**
	 * @test
	 */
	public function disconnectIfConnectedDisconnects() {
		$this->assertTrue($this->subject->isConnected());
		$this->subject->setDatabaseHost('127.0.0.1');
		$this->assertFalse($this->subject->isConnected());
	}

	/**
	 * @test
	 */
	public function admin_queryReturnsTrueForInsertQuery() {
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
	}

	/**
	 * @test
	 */
	public function admin_queryReturnsTrueForUpdateQuery() {
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
		$id = $this->subject->sql_insert_id();
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('UPDATE ' . $this->testTable . ' SET fieldblob=\'bar\' WHERE id=' . $id));
	}

	/**
	 * @test
	 */
	public function admin_queryReturnsTrueForDeleteQuery() {
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
				$id = $this->subject->sql_insert_id();
				$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('DELETE FROM ' . $this->testTable . ' WHERE id=' . $id));
	}

	/**
	 * @test
	 */
	public function admin_queryReturnsResultForSelectQuery() {
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $this->subject->admin_query('INSERT INTO ' . $this->testTable . ' (fieldblob) VALUES (\'foo\')'));
		$stmt = $this->subject->admin_query('SELECT fieldblob FROM ' . $this->testTable);
		$this->assertInstanceOf('Doctrine\\DBAL\\Driver\\Statement', $stmt);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		$this->assertEquals('foo', $result[$this->testField]);
		$stmt->closeCursor();
	}

	/**
	 * @test
	 */
	public function admin_get_charsetsReturnsArrayWithCharsets() {
		$columnsRes = $this->subject->admin_query('SHOW CHARACTER SET');
		$result = $this->subject->admin_get_charsets();
		$this->assertEquals(count($result), $columnsRes->rowCount());

		/** @var array $row */
		while (($row = $columnsRes->fetch(\PDO::FETCH_ASSOC))) {
			$this->assertArrayHasKey($row['Charset'], $result);
		}
		$columnsRes->closeCursor();
	}

	/**
	 * @test
	 */
	public function admin_get_keysReturnIndexKeysOfTable() {
		$result = $this->subject->admin_get_keys($this->testTable);
		$this->assertEquals('id', $result[0]['Column_name']);
	}

	/**
	 * @test
	 */
	public function admin_get_fieldsReturnFieldInformationsForTable() {
		$result = $this->subject->admin_get_fields($this->testTable);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey($this->testField, $result);
	}

	/**
	 * @test
	 */
	public function admin_get_tablesReturnAllTablesFromDatabase() {
		$result = $this->subject->admin_get_tables();
		$this->assertArrayHasKey('tt_content', $result);
		$this->assertArrayHasKey('pages', $result);
	}

	/**
	 * @test
	 */
	public function admin_get_dbsReturnsAllDatabases() {
		$tempDatabasename = $this->subject->getDatabaseName();
		$databases = $this->subject->admin_query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
		$result = $this->subject->admin_get_dbs();
		$this->assertSame(count($result), $databases->rowCount());

		$i = 0;
		while ($database = $databases->fetch(\PDO::FETCH_ASSOC)) {
			$this->assertSame($database['SCHEMA_NAME'], $result[$i]);
			$i++;
		}
		$this->subject->setDatabaseName($tempDatabasename);
	}

	/**
	 * @test
	 */
	public function INSERTqueryCreateValidQuery() {
		$fieldValues = array($this->testField => 'Foo');
		$queryExpected = 'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ') VALUES(\'Foo\')';
		$queryGenerated = $this->subject->INSERTquery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function INSERTqueryCreateValidQueryFromMultipleValues() {
		$fieldValues = array(
				$this->testField => 'Foo',
				$this->testFieldSecond => 'Bar'
		);
		$queryExpected =
			'INSERT INTO ' . $this->testTable . ' (' . $this->testField . ', ' . $this->testFieldSecond . ') VALUES(\'Foo\', \'Bar\')';
		$queryGenerated = $this->subject->INSERTquery($this->testTable, $fieldValues);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function INSERTmultipleRowsCreateValidQuery() {
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
	 */
	public function UPDATEqueryCreateValidQuery() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
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
	 */
	public function DELETEqueryCreateValidQuery() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'DELETE FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->DELETEquery($this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function SELECTqueryCreateValidQuery() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function SELECTqueryCreateValidQueryWithEmptyWhereClause() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$where = '';
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable;
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function SELECTqueryCreateValidQueryWithGroupByClause() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
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
	 */
	public function SELECTqueryCreateValidQueryWithOrderByClause() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
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
	 */
	public function SELECTqueryCreateValidQueryWithLimitClause() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$id = $this->subject->sql_insert_id();
		$queryGenerated = $this->subject->SELECTquery($this->testField, $this->testTable, 'id=' . $id, '', '', '1,2');
		$queryExpected =
					'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id . ' LIMIT 1,2';
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function SELECTsubqueryCreateValidQuery() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$id = $this->subject->sql_insert_id();
		$where = 'id=' . $id;
		$queryExpected =
			'SELECT ' . $this->testField . ' FROM ' . $this->testTable . ' WHERE id=' . $id;
		$queryGenerated = $this->subject->SELECTsubquery($this->testField, $this->testTable, $where);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function TRUNCATEqueryCreateValidQuery() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$queryExpected =
			'TRUNCATE ' . $this->testTable;
		$queryGenerated = $this->subject->TRUNCATEquery($this->testTable);
		$this->assertSame($queryExpected, $queryGenerated);
	}

	/**
	 * @test
	 */
	public function prepare_SELECTqueryCreateValidQuery() {
		$this->markTestIncomplete('Needs to be implemented');
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
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
	 * Data Provider for sql_num_rowsReturnsCorrectAmountOfRows()
	 *
	 * @see sql_num_rowsReturnsCorrectAmountOfRows()
	 *
	 * @return array
	 */
	public function sql_num_rowsReturnsCorrectAmountOfRowsProvider() {
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
	 * @dataProvider sql_num_rowsReturnsCorrectAmountOfRowsProvider
	 *
	 * @param string $sql
	 * @param string $expectedResult
	 */
	public function sql_num_rowsReturnsCorrectAmountOfRows($sql, $expectedResult) {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'foo')));
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'bar')));
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'baz')));

		$res = $this->subject->admin_query($sql);
		$numRows = $this->subject->sql_num_rows($res);

		$this->assertSame($expectedResult, $numRows);
	}


	/**
	 * @test
	 *
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'test' in 'where clause'
	 */
	public function sql_num_rowsReturnsFalse() {
		$res = $this->subject->admin_query('SELECT * FROM ' . $this->testTable . ' WHERE test=\'baz\'');
		$numRows = $this->subject->sql_num_rows($res);
		$this->assertFalse($numRows);
	}

	/**
	 * Prepares the test table for the fetch* Tests
	 */
	protected function prepareTableForFetchTests() {
		$this->assertInstanceOf(
			'Doctrine\\DBAL\\Driver\\Statement',
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD name mediumblob;
			')
		);

		$this->assertInstanceOf(
			'Doctrine\\DBAL\\Driver\\Statement',
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD deleted int;
			')
		);

		$this->assertInstanceOf(
			'Doctrine\\DBAL\\Driver\\Statement',
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD street varchar(100);
			')
		);

		$this->assertInstanceOf(
			'Doctrine\\DBAL\\Driver\\Statement',
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD city varchar(50);
			')
		);

		$this->assertInstanceOf(
			'Doctrine\\DBAL\\Driver\\Statement',
			$this->subject->sql_query('ALTER TABLE ' . $this->testTable . '
				ADD country varchar(100);
			')
		);

		$values = array(
			'name' => 'Mr. Smith',
			'street' => 'Oakland Road',
			'city' => 'Los Angeles',
			'country' => 'USA',
			'deleted' => 0
		);
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, $values));

		$values = array(
			'name' => 'Ms. Smith',
			'street' => 'Oakland Road',
			'city' => 'Los Angeles',
			'country' => 'USA',
			'deleted' => 0
		);
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, $values));

		$values = array(
			'name' => 'Alice im Wunderland',
			'street' => 'Große Straße',
			'city' => 'Königreich der Herzen',
			'country' => 'Wunderland',
			'deleted' => 0
		);
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, $values));

		$values = array(
			'name' => 'Agent Smith',
			'street' => 'Unknown',
			'city' => 'Unknown',
			'country' => 'Matrix',
			'deleted' => 1
		);
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, $values));
	}

	/**
	 * @test
	 */
	public function sql_fetch_assocReturnsAssocArray() {
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
				'street'    => 'Unknown',
				'city'      => 'Unknown',
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
	 */
	public function sql_fetch_rowReturnsNumericArray() {
		$this->prepareTableForFetchTests();
		$res = $this->subject->admin_query('SELECT * FROM ' . $this->testTable);
		$expectedResult = array(
					array('1', '0', null, null, 'Mr. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('2', '0', null, null, 'Ms. Smith', '0', 'Oakland Road', 'Los Angeles', 'USA'),
					array('3', '0', null, null, 'Alice im Wunderland', '0', 'Große Straße', 'Königreich der Herzen', 'Wunderland'),
					array('4', '0', null, null, 'Agent Smith', '1', 'Unknown', 'Unknown', 'Matrix')
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
	 * @expectedException \Doctrine\DBAL\DBALException
	 * @expectedExceptionMessage SQLSTATE[42S22]: Column not found: 1054 Unknown column 'baz' in 'where clause'
	 */
	public function sql_free_resultReturnsFalse() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'baz')));
		$res = $this->subject->admin_query('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=baz');
		$this->assertFalse($this->subject->sql_free_result($res));
	}

	/**
	 * @test
	 */
	public function sql_free_resultReturnsTrue() {
		$this->assertSame(1, $this->subject->getDatabaseHandle()->insert($this->testTable, array($this->testField => 'baz')));
		$res = $this->subject->admin_query('SELECT * FROM test_t3lib_dbtest WHERE fieldblob=\'baz\'');
		$this->assertTrue($this->subject->sql_free_result($res));
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
	public function escapeStrForLikeComparison() {
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
	 */
	public function stripGroupByForGroupByKeyword($groupByClause, $expectedResult) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject $subject */
		$subject = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array('dummy'), array(), '', FALSE);
		$strippedQuery = $subject->stripGroupBy($groupByClause);
		$this->assertEquals($expectedResult, $strippedQuery);
	}


	/////////////////////////////////////////////////
	// Tests concerning cleanIntArrayDataProvider
	/////////////////////////////////////////////////

	/**
	 * Data Provider for cleanIntArrayDataProvider()
	 *
	 * @see cleanIntArrayDataProvider()
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
	 * @note to be migrate
	 */
	public function cleanIntArray($exampleData, $expectedResult) {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection $subject */
		$subject = new \TYPO3\CMS\Core\Database\DatabaseConnection();
		$sanitizedArray = $subject->cleanIntArray($exampleData);
		$this->assertEquals($expectedResult, $sanitizedArray);
	}
}