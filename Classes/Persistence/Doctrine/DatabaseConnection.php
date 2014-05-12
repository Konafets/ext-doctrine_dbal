<?php

namespace Konafets\DoctrineDbal\Persistence\Doctrine;

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
 * Class DatabaseConnection
 *
 * @package Konafets\DoctrineDbal\Persistence\Doctrine
 * @author  Stefano Kowalke <blueduck@gmx.net>
 */
class DatabaseConnection extends \Konafets\DoctrineDbal\Persistence\Legacy\DatabaseConnection {
	/**
	 * Returns the database username
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseUsername() {
		return $this->databaseUsername;
	}

	/**
	 * Returns database password
	 *
	 * @return string
	 * @api
	 */
	public function getDatabasePassword() {
		return $this->databaseUserPassword;
	}

	/**
	 * Returns the name of the database
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseName() {
		return $this->databaseName;
	}

	/**
	 * Returns the host of the database
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseHost() {
		return $this->databaseHost;
	}

	/**
	 * Returns the database socket
	 *
	 * @return NULL|string
	 * @api
	 */
	public function getDatabaseSocket() {
		return $this->databaseSocket;
	}

	/**
	 * Returns the database port
	 *
	 * @return int
	 * @api
	 */
	public function getDatabasePort() {
		return (int) $this->databasePort;
	}

	/**
	 * Returns the connection charset
	 *
	 * @return string
	 * @api
	 */
	public function getConnectionCharset() {
		return $this->connectionCharset;
	}

	/**
	 * Returns if the connection is set to compressed
	 *
	 * @return bool
	 */
	public function isConnectionCompressed() {
		return $this->connectionCompression;
	}

	/**
	 * Select a SQL database
	 *
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function selectDatabase() {
		return parent::sql_select_db();
	}

	/**
	 * Connects to database for TYPO3 sites:
	 *
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 *
	 * @return void
	 * @api
	 */
	public function connectDatabase() {
		// Early return if connected already
		if ($this->isConnected) {
			return;
		}

		$this->checkDatabasePreconditions();

		try {
			$this->link = $this->getConnection();
		} catch (\Exception $e) {
			echo $e->getMessage();
		}

		$this->isConnected = $this->checkConnectivity();

		if ($this->isConnected) {
			$this->initCommandsAfterConnect();
			$this->selectDatabase();
		}

		$this->prepareHooks();
	}

	/**
	 * Open a (persistent) connection to a MySQL server
	 *
	 * @return boolean|void
	 * @throws \RuntimeException
	 */
	public function getConnection() {
		if ($this->isConnected) {
			return $this->link;
		}

		if (!extension_loaded('mysqli')) {
			throw new \RuntimeException(
				'Database Error: PHP mysqli extension not loaded. This is a must have for TYPO3 CMS!',
				1271492607
			);
		}

		$host = $this->persistentDatabaseConnection
			? 'p:' . $this->getDatabaseHost()
			: $this->getDatabaseHost();

		$this->link = mysqli_init();
		$connected = $this->link->real_connect(
			$host,
			$this->getDatabaseUsername(),
			$this->getDatabasePassword(),
			NULL,
			$this->getDatabasePort(),
			$this->getDatabaseSocket(),
			$this->isConnectionCompressed() ? MYSQLI_CLIENT_COMPRESS : 0
		);

		if ($connected) {
			$this->isConnected = TRUE;

			if ($this->link->set_charset($this->connectionCharset) === FALSE) {
				GeneralUtility::sysLog(
					'Error setting connection charset to "' . $this->getConnectionCharset() . '"',
					'Core',
					GeneralUtility::SYSLOG_SEVERITY_ERROR
				);
			}

			foreach ($this->initializeCommandsAfterConnect as $command) {
				if ($this->query($command) === FALSE) {
					GeneralUtility::sysLog(
						'Could not initialize DB connection with query "' . $command . '": ' . $this->getErrorMessage(),
						'Core',
						GeneralUtility::SYSLOG_SEVERITY_ERROR
					);
				}
			}
			$this->setSqlMode();
			$this->checkConnectionCharset();
		} else {
			// @TODO: This should raise an exception. Would be useful especially to work during installation.
			$errorMsg = $this->link->connect_error;
			$this->link = NULL;
			GeneralUtility::sysLog(
				'Could not connect to MySQL server ' . $host . ' with user ' . $this->getDatabaseUsername() . ': ' . $errorMsg,
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_FATAL
			);
		}

		return $this->link;
	}

	/**
	 * @throws \RuntimeException
	 * @return void
	 */
	private function checkDatabasePreConditions() {
		if (!$this->getDatabaseName()) {
			throw new \RuntimeException(
				'TYPO3 Fatal Error: No database specified!',
				1270853882
			);
		}
	}

	/**
	 * @throws \RuntimeException
	 * @return bool
	 */
	private function checkConnectivity() {
		$connected = FALSE;
		if ($this->isConnected()) {
			$connected = TRUE;
		} else {
			GeneralUtility::sysLog(
				'Could not connect to MySQL server ' . $this->getDatabaseHost() . ' with user ' . $this->getDatabaseUsername() . ': ' . $this->sqlErrorMessage(),
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_FATAL
			);

			$this->close();

			throw new \RuntimeException(
				'TYPO3 Fatal Error: The current username, password or host was not accepted when the connection to the database was attempted to be established!',
				1270853884
			);
		}

		return $connected;
	}

	/**
	 * Prepare user defined objects (if any) for hooks which extend query methods
	 *
	 * @throws \UnexpectedValueException
	 * @return void
	 */
	private function prepareHooks() {
		$this->preProcessHookObjects = array();
		$this->postProcessHookObjects = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_db.php']['queryProcessors'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_db.php']['queryProcessors'] as $classRef) {
				$hookObject = GeneralUtility::getUserObj($classRef);
				if (!(
					$hookObject instanceof \TYPO3\CMS\Core\Database\PreProcessQueryHookInterface
					|| $hookObject instanceof \TYPO3\CMS\Core\Database\PostProcessQueryHookInterface
				)) {
					throw new \UnexpectedValueException(
						'$hookObject must either implement interface TYPO3\\CMS\\Core\\Database\\PreProcessQueryHookInterface or interface TYPO3\\CMS\\Core\\Database\\PostProcessQueryHookInterface',
						1299158548
					);
				}
				if ($hookObject instanceof \TYPO3\CMS\Core\Database\PreProcessQueryHookInterface) {
					$this->preProcessHookObjects[] = $hookObject;
				}
				if ($hookObject instanceof \TYPO3\CMS\Core\Database\PostProcessQueryHookInterface) {
					$this->postProcessHookObjects[] = $hookObject;
				}
			}
		}
	}

	public function close() {
		$this->link->close();
		$this->isConnected = FALSE;

	}

	/**
	 * Send initializing query to the database to prepare the database for TYPO3
	 *
	 * @return void
	 */
	private function initCommandsAfterConnect() {
		foreach ($this->initializeCommandsAfterConnect as $command) {
			if ($this->query($command) === FALSE) {
				GeneralUtility::sysLog(
					'Could not initialize DB connection with query "' . $command . '": ' . $this->getErrorMessage(),
					'Core',
					GeneralUtility::SYSLOG_SEVERITY_ERROR
				);
			}
		}

		$this->setSqlMode();
	}

	/**
	 * mysqli() wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
	 *
	 * @param string $query Query to execute
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function adminQuery($query) {
		return parent::admin_query($query);
	}

	/**
	 * Creates and executes an INSERT SQL-statement for $table from the array with field/value pairs $data.
	 * Using this function specifically allows us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string  $table         Table name
	 * @param array   $data          Field values as key=>value pairs. Values will be escaped internally.
	 *                               Typically you would fill an array like "$insertFields" with 'fieldname'=>'value'
	 *                               and pass it to this function as argument.
	 * @param boolean $noQuoteFields See fullQuoteArray()
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function executeInsertQuery($table, array $data, $noQuoteFields = FALSE) {
		return parent::exec_INSERTquery($table, $data, $noQuoteFields);
	}

	/**
	 * Creates and executes an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string  $table         Table name
	 * @param array   $data          Field names
	 * @param array   $rows          Table rows. Each row should be an array with field values mapping to $data
	 * @param boolean $noQuoteFields See fullQuoteArray()
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function executeInsertMultipleRows($table, array $data, array $rows, $noQuoteFields = FALSE) {
		return parent::exec_INSERTmultipleRows($table, $data, $rows, $noQuoteFields);
	}

	/**
	 * Creates and executes an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array
	 * with field/value pairs $data
	 * Using this function specifically allow us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string  $table         Database table name
	 * @param string  $where         WHERE clause, eg. "uid=1".
	 *                               NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param array   $data          Field values as key=>value pairs. Values will be escaped internally.
	 *                               Typically you would fill an array like "$updateFields" with 'fieldname'=>'value'
	 *                               and pass it to this function as argument.
	 * @param boolean $noQuoteFields See fullQuoteArray()
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function executeUpdateQuery($table, $where, array $data, $noQuoteFields = FALSE) {
		return parent::exec_UPDATEquery($table, $where, $data, $noQuoteFields);
	}

	/**
	 * Creates and executes a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1".
	 *                      NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function executeDeleteQuery($table, $where) {
		return parent::exec_DELETEquery($table, $where);
	}

	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param string $selectFields List of fields to select from the table. This is what comes right after
	 *                             "SELECT ...". Required value.
	 * @param string $fromTable    Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param string $whereClause  Additional WHERE clauses put in the end of the query. NOTICE: You must escape values in
	 *                             this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy      Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy      Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit        Optional LIMIT value ([begin,]max), if none, supply blank string.
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @api
	 */
	public function executeSelectQuery($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $limit = '') {
		return parent::exec_SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Creates and executes a SELECT query, selecting fields ($select) from two/three tables joined
	 * Use $mm_table together with $localTable or $foreignTable to select over two tables. Or use all three tables
	 * to select the full MM-relation.
	 * The JOIN is done with [$localTable].uid <--> [$mmTable].uid_local  / [$mmTable].uid_foreign <--> [$foreignTable].uid
	 * The function is very useful for selecting MM-relations between tables adhering to the MM-format used by
	 * TCE (TYPO3 Core Engine). See the section on $GLOBALS['TCA'] in Inside TYPO3 for more details.
	 *
	 * @param string $select       Field list for SELECT
	 * @param string $localTable   Tablename, local table
	 * @param string $mmTable      Tablename, relation table
	 * @param string $foreignTable Tablename, foreign table
	 * @param string $whereClause  Optional additional WHERE clauses put in the end of the query.
	 *                             NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 *                             DO NOT PUT IN GROUP BY, ORDER BY or LIMIT! You have to prepend 'AND ' to this parameter
	 *                             yourself!
	 * @param string $groupBy      Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy      Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit        Optional LIMIT value ([begin,]max), if none, supply blank string.
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see executeSelectQuery()
	 * @api
	 */
	public function executeSelectMmQuery($select, $localTable, $mmTable, $foreignTable, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '') {
		return parent::exec_SELECT_mm_query($select, $localTable, $mmTable, $foreignTable, $whereClause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Executes a select based on input query parts array
	 *
	 * @param array $queryParts Query parts array
	 *
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see executeSelectQuery()
	 * @api
	 */
	public function executeSelectQueryArray(array $queryParts) {
		return parent::exec_SELECT_queryArray($queryParts);
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND traverse result set and returns array with records in.
	 *
	 * @param string $selectFields  See executeSelectQuery()
	 * @param string $fromTable     See executeSelectQuery()
	 * @param string $whereClause   See executeSelectQuery()
	 * @param string $groupBy       See executeSelectQuery()
	 * @param string $orderBy       See executeSelectQuery()
	 * @param string $limit         See executeSelectQuery()
	 * @param string $uidIndexField If set, the result array will carry this field names value as index.
	 *                              Requires that field to be selected of course!
	 *
	 * @return array|NULL Array of rows, or NULL in case of SQL error
	 * @api
	 */
	public function executeSelectGetRows($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $limit = '', $uidIndexField = '') {
		return parent::exec_SELECTgetRows($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit, $uidIndexField);
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND gets a result set and returns an array with a single record in.
	 * LIMIT is automatically set to 1 and can not be overridden.
	 *
	 * @param string  $selectFields List of fields to select from the table.
	 * @param string  $fromTable    Table(s) from which to select.
	 * @param string  $whereClause  Optional additional WHERE clauses put in the end of the query.
	 *                              NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param string  $groupBy      Optional GROUP BY field(s), if none, supply blank string.
	 * @param string  $orderBy      Optional ORDER BY field(s), if none, supply blank string.
	 * @param boolean $numIndex     If set, the result will be fetched with sql_fetch_row, otherwise sql_fetch_assoc will be used.
	 *
	 * @return array Single row or NULL if it fails.
	 * @api
	 */
	public function executeSelectGetSingleRow($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $numIndex = FALSE) {
		return parent::exec_SELECTgetSingleRow($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $numIndex);
	}

	/**
	 * Counts the number of rows in a table.
	 *
	 * @param string $field Name of the field to use in the COUNT() expression (e.g. '*')
	 * @param string $table Name of the table to count rows for
	 * @param string $where (optional) WHERE statement of the query
	 *
	 * @return mixed Number of rows counter (integer) or FALSE if something went wrong (boolean)
	 * @api
	 */
	public function executeSelectCountRows($field, $table, $where = '') {
		return parent::exec_SELECTcountRows($field, $table, $where);
	}

	/**
	 * Truncates a table.
	 *
	 * @param string $table Database tablename
	 *
	 * @return mixed Result from handler
	 * @api
	 */
	public function executeTruncateQuery($table) {
		return parent::exec_TRUNCATEquery($table);
	}

	/**
	 * Creates an INSERT SQL-statement for $table from the array with field/value pairs $data.
	 *
	 * @param string  $table         See executeInsertQuery()
	 * @param array   $data          See executeInsertQuery()
	 * @param boolean $noQuoteFields See fullQuoteArray()
	 *
	 * @return string|NULL Full SQL query for INSERT, NULL if $fields_values is empty
	 * @api
	 */
	public function createInsertQuery($table, array $data, $noQuoteFields = FALSE) {
		return parent::INSERTquery($table, $data, $noQuoteFields);
	}

	/**
	 * Creates an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string  $table         Table name
	 * @param array   $fields        Field names
	 * @param array   $rows          Table rows. Each row should be an array with field values mapping to $fields
	 * @param boolean $noQuoteFields See fullQuoteArray()
	 *
	 * @return string|NULL Full SQL query for INSERT, NULL if $rows is empty
	 * @api
	 */
	public function createInsertMultipleRowsQuery($table, array $fields, array $rows, $noQuoteFields = FALSE) {
		return parent::INSERTmultipleRows($table, $fields, $rows, $noQuoteFields);
	}

	/**
	 * Creates an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array
	 * with field/value pairs $fields_values.
	 *
	 * @param string  $table See executeUpdateQuery()
	 * @param string  $where See executeUpdateQuery()
	 * @param array   $data  See executeUpdateQuery()
	 * @param boolean $noQuoteFields
	 *
	 * @return string Full SQL query for UPDATE
	 * @throws \InvalidArgumentException
	 * @api
	 */
	public function createUpdateQuery($table, $where, array $data, $noQuoteFields = FALSE) {
		return parent::UPDATEquery($table, $where, $data, $noQuoteFields);
	}

	/**
	 * Creates a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table See executeDeleteQuery()
	 * @param string $where See executeDeleteQuery()
	 *
	 * @return string Full SQL query for DELETE
	 * @throws \InvalidArgumentException
	 * @api
	 */
	public function createDeleteQuery($table, $where) {
		return parent::DELETEquery($table, $where);
	}

	/**
	 * Creates a SELECT SQL-statement
	 *
	 * @param string $selectFields See executeSelectQuery()
	 * @param string $fromTable    See executeSelectQuery()
	 * @param string $whereClause  See executeSelectQuery()
	 * @param string $groupBy      See executeSelectQuery()
	 * @param string $orderBy      See executeSelectQuery()
	 * @param string $limit        See executeSelectQuery()
	 *
	 * @return string Full SQL query for SELECT
	 * @api
	 */
	public function createSelectQuery($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $limit = '') {
		return parent::SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Creates a SELECT SQL-statement to be used as sub query within another query.
	 * BEWARE: This method should not be overridden within DBAL to prevent quoting from happening.
	 *
	 * @param string $selectFields List of fields to select from the table.
	 * @param string $fromTable    Table from which to select.
	 * @param string $whereClause  Conditional WHERE statement
	 *
	 * @return string Full SQL query for SELECT
	 * @api
	 */
	public function createSelectSubQuery($selectFields, $fromTable, $whereClause) {
		return parent::SELECTsubquery($selectFields, $fromTable, $whereClause);
	}

	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See executeTruncateQuery()
	 *
	 * @return string Full SQL query for TRUNCATE TABLE
	 * @api
	 */
	public function createTruncateQuery($table) {
		return parent::TRUNCATEquery($table);
	}

	/**
	 * Creates a SELECT prepared SQL statement.
	 *
	 * @param string $selectFields     See executeSelectQuery()
	 * @param string $fromTable        See executeSelectQuery()
	 * @param string $whereClause      See executeSelectQuery()
	 * @param string $groupBy          See executeSelectQuery()
	 * @param string $orderBy          See executeSelectQuery()
	 * @param string $limit            See executeSelectQuery()
	 * @param array  $input_parameters An array of values with as many elements as there are bound parameters in the SQL
	 *                                 statement being executed. All values are treated as
	 *                                 \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 *
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 * @api
	 */
	public function prepareSelectQuery($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array()) {
		return parent::prepare_SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit, $input_parameters);
	}

	/**
	 * Creates a SELECT prepared SQL statement based on input query parts array
	 *
	 * @param array $queryParts      Query parts array
	 * @param array $inputParameters An array of values with as many elements as there are bound parameters in the SQL statement
	 *                               being executed. All values are treated as
	 *                               \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 *
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 * @api
	 */
	public function prepareSelectQueryArray(array $queryParts, array $inputParameters = array()) {
		return parent::prepare_SELECTqueryArray($queryParts, $inputParameters);
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query           The query to execute
	 * @param array  $queryComponents The components of the query to execute
	 *
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 *
	 * @return \mysqli_stmt|object MySQLi statement / DBAL object
	 */
	public function preparePreparedQuery($query, array $queryComponents) {
		return parent::prepare_PREPAREDquery($query, $queryComponents);
	}

	/**
	 * Escaping and quoting values for SQL statements.
	 *
	 * @param string  $string    Input string
	 * @param string  $table     Table name for which to quote string. Just enter the table that the field-value is selected
	 *                           from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @param boolean $allowNull Whether to allow NULL values
	 *
	 * @return string Output string; Wrapped in single quotes and quotes in the string (" / ') and \ will be backslashed
	 *                (or otherwise based on DBAL handler)
	 * @see quoteString()
	 */
	public function fullQuoteString($string, $table, $allowNull = FALSE) {
		return parent::fullQuoteStr($string, $table, $allowNull);
	}

	/**
	 * Substitution for PHP function "addslashes()"
	 * Use this function instead of the PHP addslashes() function when you build queries - this will prepare your code for DBAL.
	 * NOTICE: You must wrap the output of this function in SINGLE QUOTES to be DBAL compatible. Unless you have to apply the
	 *         single quotes yourself you should rather use ->fullQuoteStr()!
	 *
	 * @param string $string Input string
	 * @param string $table  Table name for which to quote string. Just enter the table that the field-value is selected from
	 *                       (and any DBAL will look up which handler to use and then how to quote the string!).
	 *
	 * @return string Output string; Quotes (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 */
	public function quoteString($string, $table) {
		return parent::quoteStr($string, $table);
	}

	/**
	 * Escaping values for SQL LIKE statements.
	 *
	 * @param string $string Input string
	 * @param string $table  Table name for which to escape string. Just enter the table that the field-value is selected from
	 *                       (and any DBAL will look up which handler to use and then how to quote the string!).
	 *
	 * @return string Output string; % and _ will be escaped with \ (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function escapeStringForLike($string, $table) {
		return parent::escapeStrForLike($string, $table);
	}

	/**
	 * Will convert all values in the one-dimensional array to integers.
	 * Useful when you want to make sure an array contains only integers before imploding them in a select-list.
	 *
	 * @param array $integerArray Array with values
	 *
	 * @return array The input array with all values cast to (int)
	 * @see cleanIntList()
	 */
	public function cleanIntegerArray(array $integerArray) {
		return parent::cleanIntArray($integerArray);
	}

	/**
	 * Will force all entries in the input comma list to integers
	 * Useful when you want to make sure a commalist of supposed integers really contain only integers; You want to know that
	 * when you don't trust content that could go into an SQL statement.
	 *
	 * @param string $list List of comma-separated values which should be integers
	 *
	 * @return string The input list but with every value cast to (int)
	 * @see cleanIntArray()
	 */
	public function cleanIntegerList($list) {
		return parent::cleanIntList($list);
	}

	/**
	 * Returns the error number on the last query() execution
	 *
	 * @return integer PDO error number
	 * @api
	 */
	public function getErrorCode() {
		return (int)parent::sql_errno();
	}

	/**
	 * Returns the error status on the last query() execution
	 *
	 * @return string PDO error string.
	 * @api
	 */
	public function getErrorMessage() {
		return parent::sql_error();
	}

	/**
	 * Returns the number of selected rows.
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 *
	 * @return integer Number of resulting rows
	 * @api
	 */
	public function getResultRowCount($res) {
		return parent::sql_num_rows($res);
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @return integer The uid of the last inserted record.
	 * @api
	 */
	public function getLastInsertId() {
		return (int)parent::sql_insert_id();
	}

	/**
	 * Returns the number of rows affected by the last INSERT, UPDATE or DELETE query
	 *
	 * @return integer Number of rows affected by last query
	 */
	public function getAffectedRows() {
		return (int)parent::sql_affected_rows();
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * MySQLi fetch_assoc() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 *
	 * @return array|boolean Associative array of result row.
	 * @api
	 */
	public function fetchAssoc($res) {
		return parent::sql_fetch_assoc($res);
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * MySQLi fetch_row() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 *
	 * @return array|boolean Array with result rows.
	 * @api
	 */
	public function fetchRow($res) {
		return parent::sql_fetch_row($res);
	}

	/**
	 * Free result memory
	 * free_result() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 *
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 * @api
	 */
	public function freeResult($res) {
		return parent::sql_free_result($res);
	}

	/**
	 * Move internal result pointer
	 *
	 * @param boolean|\mysqli_result|object $res  MySQLi result object / DBAL object
	 * @param integer                       $seek Seek result number.
	 *
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 * @api
	 */
	public function dataSeek($res, $seek) {
		return parent::sql_data_seek($res, $seek);
	}

	/**
	 * Get the type of the specified field in a result
	 * mysql_field_type() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res     MySQLi result object / DBAL object
	 * @param integer                       $pointer Field index.
	 *
	 * @return string Returns the name of the specified field index, or FALSE on error
	 * @api
	 */
	public function getFieldType($res, $pointer) {
		return parent::sql_field_type($res, $pointer);
	}

	/**
	 * Listing databases from current MySQL connection. NOTICE: It WILL try to select those databases and thus break selection
	 * of current database.
	 * This is only used as a service function in the (1-2-3 process) of the Install Tool.
	 * In any case a lookup should be done in the _DEFAULT handler DBMS then.
	 * Use in Install Tool only!
	 *
	 * @return array Each entry represents a database name
	 * @throws \RuntimeException
	 */
	public function listDatabases() {
		return parent::admin_get_dbs();
	}

	/**
	 * Returns the list of tables from the default database, TYPO3_db (quering the DBMS)
	 * In a DBAL this method should 1) look up all tables from the DBMS  of
	 * the _DEFAULT handler and then 2) add all tables *configured* to be managed by other handlers
	 *
	 * @return array Array with tablenames as key and arrays with status information as value
	 */
	public function listTables() {
		return parent::admin_get_tables();
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
	 *
	 * @return array Field information in an associative array with fieldname => field row
	 */
	public function listFields($tableName) {
		return parent::admin_get_fields($tableName);
	}

	/**
	 * Returns information about each index key in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 *
	 * @param string $tableName Table name
	 *
	 * @return array Key information in a numeric array
	 */
	public function listKeys($tableName) {
		return parent::admin_get_keys($tableName);
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
	 */
	public function listDatabaseCharsets() {
		return parent::admin_get_charsets();
	}

	/**
	 * Checks if record set is valid and writes debugging information into devLog if not.
	 *
	 * @param boolean|\mysqli_result|object MySQLi result object / DBAL object
	 *
	 * @return boolean TRUE if the  record set is valid, FALSE otherwise
	 */
	private function debugCheckRecordset($res) {
		return parent::debug_check_recordset($res);
	}
}