<?php
namespace Konafets\DoctrineDbal\Persistence\Legacy;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004-2013 Kasper Skårhøj (kasperYYYY@typo3.com)
 *  (c) 2014      Stefano Kowalke <blueduck@gmx.net>
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
 *  A copy is found in the text file GPL.txt and important notices to the license
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
 * Contains the class "DatabaseConnection" containing functions for building SQL queries
 * and mysqli wrappers, thus providing a foundational API to all database
 * interaction.
 * This class is instantiated globally as $TYPO3_DB in TYPO3 scripts.
 *
 * TYPO3 "database wrapper" class (new in 3.6.0)
 * This class contains
 * - abstraction functions for executing INSERT/UPDATE/DELETE/SELECT queries ("Query execution"; These are REQUIRED for all future connectivity to the database, thus ensuring DBAL compliance!)
 * - functions for building SQL queries (INSERT/UPDATE/DELETE/SELECT) ("Query building"); These are transitional functions for building SQL queries in a more automated way. Use these to build queries instead of doing it manually in your code!
 * - mysqli wrapper functions; These are transitional functions. By a simple search/replace you should be able to substitute all mysql*() calls with $GLOBALS['TYPO3_DB']->sql*() and your application will work out of the box. YOU CANNOT (legally) use any mysqli functions not found as wrapper functions in this class!
 * See the Project Coding Guidelines (doc_core_cgl) for more instructions on best-practise
 *
 * This class is not in itself a complete database abstraction layer but can be extended to be a DBAL (by extensions, see "dbal" for example)
 * ALL connectivity to the database in TYPO3 must be done through this class!
 * The points of this class are:
 * - To direct all database calls through this class so it becomes possible to implement DBAL with extensions.
 * - To keep it very easy to use for developers used to MySQL in PHP - and preserve as much performance as possible when TYPO3 is used with MySQL directly...
 * - To create an interface for DBAL implemented by extensions; (Eg. making possible escaping characters, clob/blob handling, reserved words handling)
 * - Benchmarking the DB bottleneck queries will become much easier; Will make it easier to find optimization possibilities.
 *
 * USE:
 * In all TYPO3 scripts the global variable $TYPO3_DB is an instance of this class. Use that.
 * Eg. $GLOBALS['TYPO3_DB']->sql_fetch_assoc()
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author Stefano Kowalke <blueduck@gmx.net>
 */
class DatabaseConnection extends \Konafets\DoctrineDbal\Persistence\Doctrine\DatabaseConnection {

	/**
	 * The AND constraint in where clause
	 *
	 * @var string
	 */
	const AND_Constraint = 'AND';

	/**
	 * The OR constraint in where clause
	 *
	 * @var string
	 */
	const OR_Constraint = 'OR';

	/************************************
	 *
	 * Query execution
	 *
	 * These functions are the RECOMMENDED DBAL functions for use in your applications
	 * Using these functions will allow the DBAL to use alternative ways of accessing data (contrary to if a query is returned!)
	 * They compile a query AND execute it immediately and then return the result
	 * This principle heightens our ability to create various forms of DBAL of the functions.
	 * Generally: We want to return a result pointer/object, never queries.
	 * Also, having the table name together with the actual query execution allows us to direct the request to other databases.
	 *
	 **************************************/

	/**
	 * Creates and executes an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 * Using this function specifically allows us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Table name
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$insertFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		$res = $this->query($this->INSERTquery($table, $fields_values, $no_quote_fields));
		if ($this->debugOutput) {
			$this->debug('exec_INSERTquery');
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_INSERTquery_postProcessAction($table, $fields_values, $no_quote_fields, $this);
		}
		return $res;
	}

	/**
	 * Creates and executes an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function exec_INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		$res = $this->query($this->INSERTmultipleRows($table, $fields, $rows, $no_quote_fields));
		if ($this->debugOutput) {
			$this->debug('exec_INSERTmultipleRows');
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_INSERTmultipleRows_postProcessAction($table, $fields, $rows, $no_quote_fields, $this);
		}
		return $res;
	}

	/**
	 * Creates and executes an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 * Using this function specifically allow us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$updateFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		$res = $this->query($this->UPDATEquery($table, $where, $fields_values, $no_quote_fields));

		if ($this->debugOutput) {
			$this->debug('exec_UPDATEquery');
		}

		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_UPDATEquery_postProcessAction($table, $where, $fields_values, $no_quote_fields, $this);
		}

		return $res;
	}

	/**
	 * Creates and executes a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function exec_DELETEquery($table, $where) {
		$res = $this->query($this->DELETEquery($table, $where));

		if ($this->debugOutput) {
			$this->debug('exec_DELETEquery');
		}

		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_DELETEquery_postProcessAction($table, $where, $this);
		}

		return $res;
	}

	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param string $select_fields List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param string $where_clause Additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		$query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
		$res = $this->query($query);
		if ($this->debugOutput) {
			$this->debug('exec_SELECTquery');
		}
		if ($this->explainOutput) {
			$this->explain($query, $from_table, $res->num_rows);
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_SELECTquery_postProcessAction($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $this);
		}
		return $res;
	}

	/**
	 * Creates and executes a SELECT query, selecting fields ($select) from two/three tables joined
	 * Use $mm_table together with $local_table or $foreign_table to select over two tables. Or use all three tables to select the full MM-relation.
	 * The JOIN is done with [$local_table].uid <--> [$mm_table].uid_local  / [$mm_table].uid_foreign <--> [$foreign_table].uid
	 * The function is very useful for selecting MM-relations between tables adhering to the MM-format used by TCE (TYPO3 Core Engine). See the section on $GLOBALS['TCA'] in Inside TYPO3 for more details.
	 *
	 * @param string $select Field list for SELECT
	 * @param string $local_table Tablename, local table
	 * @param string $mm_table Tablename, relation table
	 * @param string $foreign_table Tablename, foreign table
	 * @param string $whereClause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT! You have to prepend 'AND ' to this parameter yourself!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see exec_SELECTquery()
	 * @deprecated
	 */
	public function exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '') {
		$foreign_table_as = $foreign_table == $local_table ? $foreign_table . uniqid('_join') : '';
		$mmWhere = $local_table ? $local_table . '.uid=' . $mm_table . '.uid_local' : '';
		$mmWhere .= ($local_table and $foreign_table) ? ' AND ' : '';
		$tables = ($local_table ? $local_table . ',' : '') . $mm_table;
		if ($foreign_table) {
			$mmWhere .= ($foreign_table_as ?: $foreign_table) . '.uid=' . $mm_table . '.uid_foreign';
			$tables .= ',' . $foreign_table . ($foreign_table_as ? ' AS ' . $foreign_table_as : '');
		}
		return $this->exec_SELECTquery($select, $tables, $mmWhere . ' ' . $whereClause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Executes a select based on input query parts array
	 *
	 * @param array $queryParts Query parts array
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see exec_SELECTquery()
	 * @deprecated
	 */
	public function exec_SELECT_queryArray($queryParts) {
		return $this->exec_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT']);
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND traverse result set and returns array with records in.
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @param string $uidIndexField If set, the result array will carry this field names value as index. Requires that field to be selected of course!
	 * @return array|NULL Array of rows, or NULL in case of SQL error
	 * @deprecated
	 */
	public function exec_SELECTgetRows($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $uidIndexField = '') {
		$res = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
		if ($this->debugOutput) {
			$this->debug('exec_SELECTquery');
		}
		if (!$this->sql_error()) {
			$output = array();
			if ($uidIndexField) {
				while ($tempRow = $this->sql_fetch_assoc($res)) {
					$output[$tempRow[$uidIndexField]] = $tempRow;
				}
			} else {
				while ($output[] = $this->sql_fetch_assoc($res)) {

				}
				array_pop($output);
			}
			$this->sql_free_result($res);
		} else {
			$output = NULL;
		}
		return $output;
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND gets a result set and returns an array with a single record in.
	 * LIMIT is automatically set to 1 and can not be overridden.
	 *
	 * @param string $select_fields List of fields to select from the table.
	 * @param string $from_table Table(s) from which to select.
	 * @param string $where_clause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param boolean $numIndex If set, the result will be fetched with sql_fetch_row, otherwise sql_fetch_assoc will be used.
	 * @return array Single row or NULL if it fails.
	 * @deprecated
	 */
	public function exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $numIndex = FALSE) {
		$res = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, '1');
		if ($this->debugOutput) {
			$this->debug('exec_SELECTquery');
		}
		$output = NULL;
		if ($res !== FALSE) {
			if ($numIndex) {
				$output = $this->sql_fetch_row($res);
			} else {
				$output = $this->sql_fetch_assoc($res);
			}
			$this->sql_free_result($res);
		}
		return $output;
	}

	/**
	 * Counts the number of rows in a table.
	 *
	 * @param string $field Name of the field to use in the COUNT() expression (e.g. '*')
	 * @param string $table Name of the table to count rows for
	 * @param string $where (optional) WHERE statement of the query
	 * @return mixed Number of rows counter (integer) or FALSE if something went wrong (boolean)
	 * @deprecated
	 */
	public function exec_SELECTcountRows($field, $table, $where = '') {
		$count = FALSE;
		$resultSet = $this->exec_SELECTquery('COUNT(' . $field . ')', $table, $where);
		if ($resultSet !== FALSE) {
			list($count) = $this->sql_fetch_row($resultSet);
			$count = (int)$count;
			$this->sql_free_result($resultSet);
		}
		return $count;
	}

	/**
	 * Truncates a table.
	 *
	 * @param string $table Database tablename
	 * @return mixed Result from handler
	 * @deprecated
	 */
	public function exec_TRUNCATEquery($table) {
		$res = $this->query($this->TRUNCATEquery($table));

		if ($this->debugOutput) {
			$this->debug('exec_TRUNCATEquery');
		}

		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_TRUNCATEquery_postProcessAction($table, $this);
		}

		return $res;
	}

	/**************************************
	 *
	 * Query building
	 *
	 **************************************/
	/**
	 * Creates an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 *
	 * @param string $table See exec_INSERTquery()
	 * @param array $fields_values See exec_INSERTquery()
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return string|NULL Full SQL query for INSERT, NULL if $fields_values is empty
	 * @deprecated
	 */
	public function INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		return parent::insertQuery($table, $fields_values, $no_quote_fields = FALSE);
	}

	/**
	 * Creates an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return string|NULL Full SQL query for INSERT, NULL if $rows is empty
	 * @deprecated
	 */
	public function INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this
		// function (contrary to values in the arrays which may be insecure).
		if (count($rows) === 0) {
			return NULL;
		}
		foreach ($this->preProcessHookObjects as $hookObject) {
			/** @var $hookObject PreProcessQueryHookInterface */
			$hookObject->INSERTmultipleRows_preProcessAction($table, $fields, $rows, $no_quote_fields, $this);
		}
		// Build query
		$query = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES ';
		$rowSQL = array();
		foreach ($rows as $row) {
			// Quote and escape values
			$row = $this->fullQuoteArray($row, $table, $no_quote_fields);
			$rowSQL[] = '(' . implode(', ', $row) . ')';
		}
		$query .= implode(', ', $rowSQL);
		// Return query
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**
	 * Creates an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 *
	 *
	 * @param string $table See exec_UPDATEquery()
	 * @param string $where See exec_UPDATEquery()
	 * @param array $fields_values See exec_UPDATEquery()
	 * @param boolean $no_quote_fields
	 * @throws \InvalidArgumentException
	 * @return string Full SQL query for UPDATE
	 * @deprecated
	 */
	public function UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		return parent::updateQuery($table, $where, $fields_values, $no_quote_fields);
	}

	/**
	 * Creates a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table See exec_DELETEquery()
	 * @param string $where See exec_DELETEquery()
	 * @return string Full SQL query for DELETE
	 * @throws \InvalidArgumentException
	 * @deprecated
	 */
	public function DELETEquery($table, $where) {
		return parent::deleteQuery($table, $where);
	}

	/**
	 * Creates a SELECT SQL-statement
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @return string Full SQL query for SELECT
	 * @deprecated
	 */
	public function SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		foreach ($this->preProcessHookObjects as $hookObject) {
			/** @var $hookObject PreProcessQueryHookInterface */
			$hookObject->SELECTquery_preProcessAction($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, $this);
		}
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this function
		// Build basic query
		$query = 'SELECT ' . $select_fields . ' FROM ' . $from_table . ((string)$where_clause !== '' ? ' WHERE ' . $where_clause : '');
		// Group by
		$query .= (string)$groupBy !== '' ? ' GROUP BY ' . $groupBy : '';
		// Order by
		$query .= (string)$orderBy !== '' ? ' ORDER BY ' . $orderBy : '';
		// Group by
		$query .= (string)$limit !== '' ? ' LIMIT ' . $limit : '';
		// Return query
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**
	 * Creates a SELECT SQL-statement to be used as subquery within another query.
	 * BEWARE: This method should not be overriden within DBAL to prevent quoting from happening.
	 *
	 * @param string $select_fields List of fields to select from the table.
	 * @param string $from_table Table from which to select.
	 * @param string $where_clause Conditional WHERE statement
	 * @return string Full SQL query for SELECT
	 * @deprecated
	 */
	public function SELECTsubquery($select_fields, $from_table, $where_clause) {
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this function
		// Build basic query:
		$query = 'SELECT ' . $select_fields . ' FROM ' . $from_table . ((string)$where_clause !== '' ? ' WHERE ' . $where_clause : '');
		// Return query
		if ($this->debugOutput || $this->store_lastBuiltQuery) {
			$this->debug_lastBuiltQuery = $query;
		}
		return $query;
	}

	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See exec_TRUNCATEquery()
	 * @return string Full SQL query for TRUNCATE TABLE
	 * @deprecated
	 */
	public function TRUNCATEquery($table) {
		return parent::truncateQuery($table);
	}



	/**************************************
	 *
	 * Prepared Query Support
	 *
	 **************************************/
	/**
	 * Creates a SELECT prepared SQL statement.
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 * @deprecated
	 */
	public function prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array()) {
		return $this->prepareSelectQuery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Creates a SELECT prepared SQL statement based on input query parts array
	 *
	 * @param array $queryParts Query parts array
	 * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 * @deprecated
	 */
	public function prepare_SELECTqueryArray(array $queryParts, array $input_parameters = array()) {
		return $this->prepare_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT'], $input_parameters);
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query The query to execute
	 * @param array $queryComponents The components of the query to execute
	 * @return \mysqli_stmt|object MySQLi statement / DBAL object
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 * @deprecated
	 */
	public function prepare_PREPAREDquery($query, array $queryComponents) {
		return $this->preparePreparedQuery($query, $queryComponents);
	}

	/**************************************
	 *
	 * Various helper functions
	 *
	 * Functions recommended to be used for
	 * - escaping values,
	 * - cleaning lists of values,
	 * - stripping of excess ORDER BY/GROUP BY keywords
	 *
	 **************************************/
	/**
	 * Escaping and quoting values for SQL statements.
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @param boolean $allowNull Whether to allow NULL values
	 * @return string Output string; Wrapped in single quotes and quotes in the string (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 * @deprecated
	 */
	public function fullQuoteStr($str, $table, $allowNull = FALSE) {
		return $this->fullQuoteString($str, $table, $allowNull);
	}

	/**
	 * Escaping values for SQL LIKE statements.
	 *
	 * @param string $str   Input string
	 * @param string $table Table name for which to escape string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 *
	 * @return string Output string; % and _ will be escaped with \ (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 * @deprecated
	 */
	public function escapeStrForLike($str, $table) {
		return $this->escapeStringForLike($str, $table);
	}

	/**
	 * Substitution for PHP function "addslashes()"
	 * Use this function instead of the PHP addslashes() function when you build queries - this will prepare your code for DBAL.
	 * NOTICE: You must wrap the output of this function in SINGLE QUOTES to be DBAL compatible. Unless you have to apply the single quotes yourself you should rather use ->fullQuoteStr()!
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @return string Output string; Quotes (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 * @deprecated
	 */
	public function quoteStr($str, $table) {
		return $this->quoteString($str, $table);
	}

	/**
	 * Will convert all values in the one-dimensional array to integers.
	 * Useful when you want to make sure an array contains only integers before imploding them in a select-list.
	 *
	 * @param array $arr Array with values
	 * @return array The input array with all values cast to (int)
	 * @see cleanIntList()
	 * @deprecated
	 */
	public function cleanIntArray($arr) {
		return $this->cleanIntegerArray($arr);
	}

	/**
	 * Will force all entries in the input comma list to integers
	 * Useful when you want to make sure a commalist of supposed integers really contain only integers; You want to know that when you don't trust content that could go into an SQL statement.
	 *
	 * @param string $list List of comma-separated values which should be integers
	 * @return string The input list but with every value cast to (int)
	 * @see cleanIntArray()
	 * @deprecated
	 */
	public function cleanIntList($list) {
		return $this->cleanIntegerList($list);
	}

	/**************************************
	 *
	 * MySQL(i) wrapper functions
	 * (For use in your applications)
	 *
	 **************************************/
	/**
	 * Executes query
	 * MySQLi query() wrapper function
	 * Beware: Use of this method should be avoided as it is experimentally supported by DBAL. You should consider
	 * using exec_SELECTquery() and similar methods instead.
	 *
	 * @param string $query Query to execute
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function sql_query($query) {
		$res = $this->query($query);
		if ($this->debugOutput) {
			$this->debug('sql_query', $query);
		}
		return $res;
	}

	/**
	 * Returns the error status on the last query() execution
	 *
	 * @return string MySQLi error string.
	 * @deprecated
	 */
	public function sql_error() {
		return $this->getErrorMessage();
	}

	/**
	 * Returns the error number on the last query() execution
	 *
	 * @return integer MySQLi error number
	 * @deprecated
	 */
	public function sql_errno() {
		return $this->getErrorCode();
	}

	/**
	 * Returns the number of selected rows.
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return integer Number of resulting rows
	 * @deprecated
	 */
	public function sql_num_rows($res) {
		return $this->getResultRowCount($res);
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * MySQLi fetch_assoc() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Associative array of result row.
	 * @deprecated
	 */
	public function sql_fetch_assoc($res) {
		return $this->fetchAssoc($res);
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * MySQLi fetch_row() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Array with result rows.
	 * @deprecated
	 */
	public function sql_fetch_row($res) {
		return $this->fetchRow($res);
	}

	/**
	 * Free result memory
	 * free_result() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 * @deprecated
	 */
	public function sql_free_result($res) {
		return $this->freeResult($res);
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @return integer The uid of the last inserted record.
	 * @deprecated
	 */
	public function sql_insert_id() {
		return $this->getLastInsertId();
	}

	/**
	 * Returns the number of rows affected by the last INSERT, UPDATE or DELETE query
	 *
	 * @return integer Number of rows affected by last query
	 * @deprecated
	 */
	public function sql_affected_rows() {
		return $this->getAffectedRows();
	}

	/**
	 * Move internal result pointer
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param integer $seek Seek result number.
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 * @deprecated
	 */
	public function sql_data_seek($res, $seek) {
		if ($this->debugCheckRecordset($res)) {
			return $res->data_seek($seek);
		} else {
			return FALSE;
		}
	}

	/**
	 * Get the type of the specified field in a result
	 * mysql_field_type() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param integer $pointer Field index.
	 * @return string Returns the name of the specified field index, or FALSE on error
	 * @deprecated
	 */
	public function sql_field_type($res, $pointer) {
		// mysql_field_type compatibility map
		// taken from: http://www.php.net/manual/en/mysqli-result.fetch-field-direct.php#89117
		// Constant numbers see http://php.net/manual/en/mysqli.constants.php
		$mysql_data_type_hash = array(
			1=>'tinyint',
			2=>'smallint',
			3=>'int',
			4=>'float',
			5=>'double',
			7=>'timestamp',
			8=>'bigint',
			9=>'mediumint',
			10=>'date',
			11=>'time',
			12=>'datetime',
			13=>'year',
			16=>'bit',
			//252 is currently mapped to all text and blob types (MySQL 5.0.51a)
			253=>'varchar',
			254=>'char',
			246=>'decimal'
		);
		if ($this->debugCheckRecordset($res)) {
			$metaInfo = $this->fetchColumn($pointer);
			if ($metaInfo === FALSE) {
				return FALSE;
			}
			return $mysql_data_type_hash[$metaInfo->type];
		} else {
			return FALSE;
		}
	}

	/**
	 * Open a (persistent) connection to a MySQL server
	 *
	 * @param string $host Deprecated since 6.1, will be removed in two versions. Database host IP/domain[:port]
	 * @param string $username Deprecated since 6.1, will be removed in two versions. Username to connect with.
	 * @param string $password Deprecated since 6.1, will be removed in two versions. Password to connect with.
	 * @return boolean|void
	 * @throws \RuntimeException
	 * @deprecated
	 */
	public function sql_pconnect($host = NULL, $username = NULL, $password = NULL) {
		if ($host || $username || $password) {
			$this->handleDeprecatedConnectArguments($host, $username, $password);
		}

		if ($this->isConnected) {
			return $this->link;
		} else {
			return $this->getConnection();
		}
	}

	/**
	 * Select a SQL database
	 *
	 * @param string $TYPO3_db Deprecated since 6.1, will be removed in two versions. Database to connect to.
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 * @deprecated
	 */
	public function sql_select_db($TYPO3_db = NULL) {
		if ($TYPO3_db) {
			GeneralUtility::deprecationLog(
				'DatabaseConnection->sql_select_db() should be called without arguments.' .
					' Use the setDatabaseName() before. Will be removed two versions after 6.1.'
			);
			$this->setDatabaseName($TYPO3_db);
		}

		return $this->selectDatabase();
	}

	/**************************************
	 *
	 * SQL admin functions
	 * (For use in the Install Tool and Extension Manager)
	 *
	 **************************************/
	/**
	 * Listing databases from current MySQL connection. NOTICE: It WILL try to select those databases and thus break selection of current database.
	 * This is only used as a service function in the (1-2-3 process) of the Install Tool.
	 * In any case a lookup should be done in the _DEFAULT handler DBMS then.
	 * Use in Install Tool only!
	 *
	 * @return array Each entry represents a database name
	 * @throws \RuntimeException
	 * @deprecated
	 */
	public function admin_get_dbs() {
		return $this->listDatabases();
	}

	/**
	 * Returns the list of tables from the default database, TYPO3_db (quering the DBMS)
	 * In a DBAL this method should 1) look up all tables from the DBMS  of
	 * the _DEFAULT handler and then 2) add all tables *configured* to be managed by other handlers
	 *
	 * @return array Array with tablenames as key and arrays with status information as value
	 * @deprecated
	 */
	public function admin_get_tables() {
		return $this->listTables();
	}

	/**
	 * Returns information about each field in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * @param string $tableName Table name
	 * @return array Field information in an associative array with fieldname => field row
	 * @deprecated
	 */
	public function admin_get_fields($tableName) {
		return $this->listFields($tableName);
	}

	/**
	 * Returns information about each index key in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 *
	 * @param string $tableName Table name
	 * @return array Key information in a numeric array
	 * @deprecated
	 */
	public function admin_get_keys($tableName) {
		return $this->listKeys($tableName);
	}

	/**
	 * Returns information about the character sets supported by the current DBM
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * This is used by the Install Tool to convert tables with non-UTF8 charsets
	 * Use in Install Tool only!
	 *
	 * @return array Array with Charset as key and an array of "Charset", "Description", "Default collation", "Maxlen" as values
	 * @deprecated
	 */
	public function admin_get_charsets() {
		return $this->listDatabaseCharsets();
	}

	/**
	 * mysqli() wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
	 *
	 * @param string $query Query to execute
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @deprecated
	 */
	public function admin_query($query) {
		return $this->adminQuery($query);
	}

	/******************************
	 *
	 * Connect handling
	 *
	 ******************************/

	/**
	 * Set the charset that should be used for the MySQL connection.
	 * The given value will be passed on to mysqli_set_charset().
	 *
	 * The default value of this setting is utf8.
	 *
	 * @param string $connectionCharset The connection charset that will be passed on to mysqli_set_charset() when connecting the database. Default is utf8.
	 * @return void
	 * @deprecated
	 */
	public function setConnectionCharset($connectionCharset = 'utf8') {
		$this->setDatabaseCharset($connectionCharset);
	}

	/**
	 * Connects to database for TYPO3 sites:
	 *
	 * @param string $host Deprecated since 6.1, will be removed in two versions Database. host IP/domain[:port]
	 * @param string $username Deprecated since 6.1, will be removed in two versions. Username to connect with
	 * @param string $password Deprecated since 6.1, will be removed in two versions. Password to connect with
	 * @param string $db Deprecated since 6.1, will be removed in two versions. Database name to connect to
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 * @internal param string $user Username to connect with.
	 * @return void
	 * @deprecated
	 */
	public function connectDB($host = NULL, $username = NULL, $password = NULL, $db = NULL) {
		// Early return if connected already
		if ($this->isConnected) {
			return;
		}

		if ($host || $username || $password || $db) {
			$this->handleDeprecatedConnectArguments($host, $username, $password, $db);
		}

		$this->connectDatabase();
	}

	/**
	 * Handle deprecated arguments for sql_pconnect() and connectDB()
	 *
	 * @param string|null $host Database host[:port]
	 * @param string|null $username Database user name
	 * @param string|null $password User password
	 * @param string|null $db Database
	 * @deprecated
	 */
	protected function handleDeprecatedConnectArguments($host = NULL, $username = NULL, $password = NULL, $db = NULL) {
		GeneralUtility::deprecationLog(
			'DatabaseConnection->sql_pconnect() and DatabaseConnection->connectDB() should be ' .
			'called without arguments. Use the setters instead.'
		);
		if ($host) {
			if (strpos($host, ':') > 0) {
				list($databaseHost, $databasePort) = explode(':', $host);
				$this->setDatabaseHost($databaseHost);
				$this->setDatabasePort($databasePort);
			} else {
				$this->setDatabaseHost($host);
			}
		}
		if ($username) {
			$this->setDatabaseUsername($username);
		}
		if ($password) {
			$this->setDatabasePassword($password);
		}
		if ($db) {
			$this->setDatabaseName($db);
		}
	}

	/******************************
	 *
	 * Debugging
	 *
	 ******************************/

	/**
	 * Checks if record set is valid and writes debugging information into devLog if not.
	 *
	 * @param boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @return boolean TRUE if the  record set is valid, FALSE otherwise
	 * @todo Define visibility
	 * @deprecated
	 */
	public function debug_check_recordset($res) {
		return $this->debugCheckRecordset($res);
	}

	/**
	 * Serialize destructs current connection
	 *
	 * @return array All protected properties that should be saved
	 */
	public function __sleep() {
		$this->disconnectIfConnected();
		return array(
			'debugOutput',
			'explainOutput',
			'persistentDatabaseConnection',
			'connectionCompression',
			'initializeCommandsAfterConnect',
			'schema',
			'schemaManager',
			'platform',
		    'connectionParams',
		);
	}
}
