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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Konafets\DoctrineDbal\Exception\ConnectionException;
use Konafets\DoctrineDbal\Exception\InvalidArgumentException;
use Konafets\DoctrineDbal\Persistence\Doctrine\PreparedStatement;
use PDO;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DatabaseConnection
 *
 * @package Konafets\DoctrineDbal\Persistence\Doctrine
 * @author  Stefano Kowalke <blueduck@gmx.net>
 */
class DatabaseConnection {

	/**
	 * @var int $affectedRows The affected rows from the last UPDATE, INSERT or DELETE query
	 */
	protected $affectedRows = -1;

	/**
	 * @var int $lastInsertId The last id which is inserted
	 */
	protected $lastInsertId = -1;

	/**
	 * Set to last built query (not necessarily executed...)
	 *
	 * @param string $debug_lastBuiltQuery
	 */
	public $debug_lastBuiltQuery = '';

	/**
	 * Set "TRUE" if you want the last built query to be stored in $debug_lastBuiltQuery independent of $this->debugOutput
	 *
	 * @var bool $store_lastBuiltQuery
	 */
	public $store_lastBuiltQuery = FALSE;

	/**
	 * Set "TRUE" or "1" if you want database errors outputted. Set to "2" if you also want successful database actions outputted.
	 *
	 * @param int $debugOutput
	 */
	public $debugOutput = FALSE;

	/**
	 * @var array $connectionParams The connection settings for Doctrine
	 */
	protected $connectionParams = array(
		'dbname'   => '',
		'user'     => '',
		'password' => '',
		'host'     => 'localhost',
		'driver'   => 'pdo_mysql',
		'port'     => 3306,
		'charset'  => 'utf8',
	);

	/**
	 * @var \Doctrine\DBAL\Connection $link Database connection object
	 */
	protected $link = NULL;

	/**
	 * @var \Doctrine\DBAL\Logging\SQLLogger
	 */
	protected $logger = NULL;

	/**
	 * @var boolean TRUE if database connection is established
	 */
	protected $isConnected = FALSE;

	/**
	 * The database schema
	 *
	 * @var \Doctrine\DBAL\Schema\Schema $schema
	 */
	protected $schema = NULL;

	/**
	 * The database schema
	 *
	 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 */
	protected $schemaManager = NULL;

	/**
	 * The current database platform
	 *
	 * @var \Doctrine\DBAL\Platforms\AbstractPlatform
	 */
	protected $platform = NULL;

	/**
	 * Set "TRUE" or "1" if you want database errors outputted. Set to "2" if you also want successful database actions outputted.
	 *
	 * @var int $isDebugMode
	 */
	protected $isDebugMode = FALSE;

	/**
	 * Set this to 1 to get queries explained (devIPmask must match). Set the value to 2 to the same but disregarding the devIPmask.
	 * There is an alternative option to enable explain output in the admin panel under "TypoScript", which will produce much nicer output, but only works in FE.
	 *
	 * @param int $explainOutput
	 */
	public $explainOutput = 0;

	/**
	 * @var boolean TRUE if database connection should be persistent
	 * @see http://php.net/manual/de/mysqli.persistconns.php
	 */
	protected $persistentDatabaseConnection = FALSE;

	/**
	 * @var boolean TRUE if connection between client and sql server is compressed
	 */
	protected $connectionCompression = FALSE;

	/**
	 * @var array List of commands executed after connection was established
	 */
	protected $initializeCommandsAfterConnect = array();

	/**
	 * @var array<PostProcessQueryHookInterface> $preProcessHookObjects
	 */
	protected $preProcessHookObjects = array();

	/**
	 * @var array<PreProcessQueryHookInterface> $postProcessHookObjects
	 */
	protected $postProcessHookObjects = array();

	/**
	 * Set database username
	 *
	 * @param string $username
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseUsername($username) {
		$this->disconnectIfConnected();
		$this->connectionParams['user'] = $username;

		return $this;
	}

	/**
	 * Returns the database username
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseUsername() {
		return $this->connectionParams['user'];
	}

	/**
	 * Set database password
	 *
	 * @param string $password
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabasePassword($password) {
		$this->disconnectIfConnected();
		$this->connectionParams['password'] = $password;

		return $this;
	}

	/**
	 * Returns database password
	 *
	 * @return string
	 * @api
	 */
	public function getDatabasePassword() {
		return $this->connectionParams['password'];
	}

	/**
	 * Set database name
	 *
	 * @param string $name
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseName($name) {
		$this->disconnectIfConnected();
		$this->connectionParams['dbname'] = $name;

		return $this;
	}

	/**
	 * Returns the name of the database
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseName() {
		return $this->connectionParams['dbname'];
	}

	/**
	 * Set the database driver for Doctrine
	 *
	 * @param string $driver
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseDriver($driver = 'pdo_mysql') {
		$this->connectionParams['driver'] = $driver;

		return $this;
	}

	/**
	 * Returns the database driver
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseDriver() {
		return $this->connectionParams['driver'];
	}

	/**
	 * Set database host
	 *
	 * @param string $host
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseHost($host = 'localhost') {
		$this->disconnectIfConnected();
		$this->connectionParams['host'] = $host;

		return $this;
	}

	/**
	 * Returns the host of the database
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseHost() {
		return $this->connectionParams['host'];
	}

	/**
	 * Set database socket
	 *
	 * @param string|NULL $socket
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseSocket($socket = NULL) {
		$this->disconnectIfConnected();
		$this->connectionParams['unix_socket'] = $socket;

		return $this;
	}

	/**
	 * Returns the database socket
	 *
	 * @return NULL|string
	 * @api
	 */
	public function getDatabaseSocket() {
		return $this->connectionParams['unix_socket'];
	}

	/**
	 * Set database port
	 *
	 * @param integer $port
	 *
	 * @throws \Konafets\DoctrineDbal\Exception\InvalidArgumentException
	 * @return $this
	 * @api
	 */
	public function setDatabasePort($port = 3306) {
		if (!is_numeric($port)) {
			throw new InvalidArgumentException('The argument for port must be an integer.');
		}
		$this->disconnectIfConnected();
		$this->connectionParams['port'] = (int) $port;

		return $this;
	}

	/**
	 * Returns the database port
	 *
	 * @return int
	 * @api
	 */
	public function getDatabasePort() {
		return (int)$this->connectionParams['port'];
	}

	/**
	 * @var \Doctrine\DBAL\Configuration $databaseConfiguration
	 */
	protected $databaseConfiguration;

	/**
	 * Set the charset that should be used for the MySQL connection.
	 *
	 * @param string $charset The connection charset that will be passed on to mysqli_set_charset() when connecting the database. Default is utf8.
	 *
	 * @return $this
	 * @api
	 */
	public function setDatabaseCharset($charset = 'utf8') {
		$this->disconnectIfConnected();
		$this->connectionParams['charset'] = $charset;

		return $this;
	}

	/**
	 * Returns default charset
	 *
	 * @return string
	 * @api
	 */
	public function getDatabaseCharset() {
		return $this->connectionParams['charset'];
	}

	/**
	 * Set the connection parameter array
	 *
	 * @param array $connectionParams
	 */
	public function setConnectionParams(array $connectionParams) {
		$this->connectionParams = $connectionParams;
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
	 * Set persistent database connection
	 *
	 * @param boolean $persistentDatabaseConnection
	 * @see http://php.net/manual/de/mysqli.persistconns.php
	 */
	public function setPersistentDatabaseConnection($persistentDatabaseConnection) {
		$this->disconnectIfConnected();
		$this->persistentDatabaseConnection = (bool)$persistentDatabaseConnection;
	}

	/**
	 * Set connection compression. Might be an advantage, if SQL server is not on localhost
	 *
	 * @param bool $connectionCompression TRUE if connection should be compressed
	 */
	public function setConnectionCompression($connectionCompression) {
		$this->disconnectIfConnected();
		$this->connectionCompression = (bool)$connectionCompression;
	}

	/**
	 * Set commands to be fired after connection was established
	 *
	 * @param array $commands List of SQL commands to be executed after connect
	 */
	public function setInitializeCommandsAfterConnect(array $commands) {
		$this->disconnectIfConnected();
		$this->initializeCommandsAfterConnect = $commands;
	}

	/**
	 * Enables/Disables the storage of the last statement
	 *
	 * @param $value
	 *
	 * @return $this
	 * @api
	 */
	public function setStoreLastBuildQuery($value) {
		$this->store_lastBuiltQuery = (bool)$value;
	}

	/**
	 * Returns the settings if the last build query should be stored
	 *
	 * @return bool
	 * @api
	 */
	public function getStoreLastBuildQuery() {
		return $this->store_lastBuiltQuery;
	}

	/**
	 * @return \Doctrine\DBAL\Driver\Statement
	 * @api
	 */
	public function getLastStatement() {
		$queries = $this->logger->queries;
		$currentQuery = $this->logger->currentQuery;
		$lastStatement = $queries[$currentQuery]['sql'];

		return $lastStatement;
	}

	/**
	 * Set the debug mode.
	 *
	 * Possible values are:
	 *
	 * - 0|FALSE: deactivate debug mode
	 * - 1|TRUE:  activate debug mode
	 * - 2     :  output also successful database actions
	 *
	 * @param int $mode
	 *
	 * @return $this
	 */
	public function setDebugMode($mode){
		$this->debugOutput = $mode;

		return $this;
	}

	/**
	 * Return the debug mode setting
	 *
	 * @return int
	 */
	public function getDebugMode(){
		return (int)$this->debugOutput;
	}

	/**
	 * Set current database handle
	 *
	 * @param \Doctrine\DBAL\Connection $handle
	 *
	 * @throws \Konafets\DoctrineDbal\Exception\InvalidArgumentException
	 * @return void
	 * @api
	 */
	public function setDatabaseHandle($handle) {
		if ($handle instanceof \Doctrine\DBAL\Connection || $handle === NULL) {
			$this->link = $handle;
		} else {
			throw new InvalidArgumentException('Wrong type of argument given to setDatabaseHandle. Need to be of type \Doctrine\DBAL\Connection.');
		}

	}

	/**
	 * Returns current database handle
	 *
	 * @return \Doctrine\DBAL\Connection|NULL
	 * @api
	 */
	public function getDatabaseHandle() {
		return $this->link;
	}

	/**
	 * Initialize the database connection
	 *
	 * @return void
	 */
	public function initialize() {
		// Intentionally blank as this will be overloaded by DBAL
	}

	/**
	 * Returns the name of the database system
	 *
	 * @return string
	 * @api
	 */
	public function getName() {
		return $this->link->getDatabasePlatform()->getName();
	}

	/**
	 * Returns the schema object
	 *
	 * @return \Doctrine\DBAL\Schema\Schema
	 */
	public function getSchema() {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		return $this->schema;
	}

	/**
	 * Returns the schema manager
	 *
	 * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	public function getSchemaManager() {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		return $this->schemaManager;
	}

	/**
	 * Returns the platform object
	 *
	 * @return \Doctrine\DBAL\Platforms\AbstractPlatform
	 */
	public function getPlatform() {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		return $this->platform;
	}

	/**
	 * Select a SQL database
	 *
	 * @throws \Konafets\DoctrineDbal\Exception\ConnectionException
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function selectDatabase() {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$isConnected = $this->isConnected();

		if (!$isConnected) {
			GeneralUtility::sysLog(
				'Could not select ' . $this->getName() . ' database ' . $this->getDatabaseName() . ': ' . $this->sqlErrorMessage(),
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_FATAL
			);

			throw new ConnectionException(
				'TYPO3 Fatal Error: Cannot connect to the current database, "' . $this->getDatabaseName() . '"!',
				1270853883
			);
		}

		return $isConnected;
	}

	public function connectDB($isInitialInstallationInProgress = FALSE) {
		$this->connectDatabase($isInitialInstallationInProgress);
	}

	/**
	 * Connects to database for TYPO3 sites:
	 *
	 * @param boolean $isInitialInstallationInProgress
	 *
	 * @throws \Konafets\DoctrineDbal\Exception\ConnectionException
	 * @return void
	 * @api
	 */
	public function connectDatabase($isInitialInstallationInProgress = FALSE) {
		// Early return if connected already
		if ($this->isConnected) {
			return;
		}

		if (!$isInitialInstallationInProgress) {
			$this->checkDatabasePreconditions();
		}

		try {
			$this->link = $this->getConnection();
		} catch (\Exception $e) {
			throw new ConnectionException($e->getMessage());
		}

		$this->isConnected = $this->checkConnectivity();

		if (!$isInitialInstallationInProgress) {
			if ($this->isConnected) {
				$this->initCommandsAfterConnect();
				$this->selectDatabase();
			}

			$this->prepareHooks();
		}
	}

	/**
	 * Initialize Doctrine
	 *
	 * @return void
	 */
	private function initDoctrine() {
		$this->databaseConfiguration = GeneralUtility::makeInstance('\\Doctrine\\DBAL\\Configuration');
		$this->databaseConfiguration->setSQLLogger(new DebugStack());
		$this->schema = GeneralUtility::makeInstance('\\Doctrine\\DBAL\\Schema\\Schema');
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

		$this->checkForDatabaseExtensionLoaded();

		$this->initDoctrine();

		// If the user want a persistent connection we have to create the PDO instance by ourself and pass it to Doctrine.
		// See http://stackoverflow.com/questions/16217426/is-it-possible-to-use-doctrine-with-persistent-pdo-connections
		// http://www.mysqlperformanceblog.com/2006/11/12/are-php-persistent-connections-evil/
		if ($this->persistentDatabaseConnection) {
			// pattern: mysql:host=localhost;dbname=databaseName
			$cdn = substr($this->getDatabaseDriver(), 3) . ':host=' . $this->getDatabaseHost() . ';dbname=' . $this->getDatabaseName();
			$pdoHandle = new \PDO($cdn, $this->getDatabaseUsername(), $this->getDatabasePassword(), array(\PDO::ATTR_PERSISTENT => true));
			$this->connectionParams['pdo'] = $pdoHandle;
		}

		$connection = DriverManager::getConnection($this->connectionParams, $this->databaseConfiguration);
		$this->platform = $connection->getDatabasePlatform();

		$connection->connect();

		$this->logger = $connection->getConfiguration()->getSQLLogger();

		// We need to map the enum type to string because Doctrine don't support it native
		// This is necessary when the installer loops through all tables of all databases it found using this connection
		// See https://github.com/barryvdh/laravel-ide-helper/issues/19
		$this->platform->registerDoctrineTypeMapping('enum', 'string');
		$this->schemaManager = $connection->getSchemaManager();

		return $connection;
	}

	/**
	 * Checks if the PDO database extension is loaded
	 *
	 * @throws \RuntimeException
	 */
	private function checkForDatabaseExtensionLoaded(){
		if (!extension_loaded('pdo')) {
			throw new \RuntimeException(
				'Database Error: PHP PDO extension not loaded. This is a must to use this extension (ext:doctrine_dbal)!',
				// TODO: Replace with current date for Thesis
				1388496499
			);
		}
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
				'Could not connect to ' . $this->getName() . ' server ' . $this->getDatabaseHost() . ' with user ' . $this->getDatabaseUsername() . ': ' . $this->getErrorMessage(),
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

	/**
	 * Checks if database is connected
	 *
	 * @return boolean
	 * @api
	 */
	public function isConnected() {
		if (is_object($this->link)) {
			return $this->link->isConnected();
		} else {
			return FALSE;
		}
	}

	/**
	 * Disconnect from database if connected
	 *
	 * @return void
	 * @api
	 */
	public function disconnectIfConnected() {
		if ($this->isConnected) {
			$this->close();
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
		$this->checkConnectionCharset();
	}

	/**
	 * Fixes the SQL mode by unsetting NO_BACKSLASH_ESCAPES if found.
	 *
	 * @return void
	 * @todo: Test the server with different modes
	 *        see http://dev.mysql.com/doc/refman/5.1/de/server-sql-mode.html
	 */
	protected function setSqlMode() {
		$resource = $this->adminQuery('SELECT @@SESSION.sql_mode;');
		if ($resource) {
			$result = $resource->fetchAll();
			if (isset($result[0]) && $result[0] && strpos($result[0]['@@SESSION.sql_mode'], 'NO_BACKSLASH_ESCAPES') !== FALSE) {
				$modes = array_diff(GeneralUtility::trimExplode(',', $result[0]['@@SESSION.sql_mode']), array('NO_BACKSLASH_ESCAPES'));
				$stmt = $this->link->prepare('SET sql_mode = :modes');
				$stmt->bindValue('modes', implode(',', $modes));
				$stmt->execute();
				GeneralUtility::sysLog(
					'NO_BACKSLASH_ESCAPES could not be removed from SQL mode: ' . $this->getErrorMessage(),
					'Core',
					GeneralUtility::SYSLOG_SEVERITY_ERROR
				);
			}
		}
	}

	/**
	 * Executes a query against the DBMS
	 *
	 * @param string $query
	 *
	 * @return \Doctrine\DBAL\Statement
	 */
	protected function query($query) {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$stmt = $this->link->query($query);

		$this->affectedRows = $this->getResultRowCount($stmt);

		return $stmt;
	}

	/**
	 * Doctrine query wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
	 *
	 * @param string $query Query to execute
	 *
	 * @return boolean|\Doctrine\DBAL\Driver\Statement A PDOStatement object
	 */
	public function adminQuery($query) {
		if (!$this->isConnected) {
				$this->connectDatabase();
			}

			$stmt = $this->link->query($query);

			if ($this->isDebugMode) {
				$this->debug('adminQuery', $query);
			}

			return $stmt;
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
	}

	/**
	 * Truncates a table.
	 *
	 * @param string $table Database table name
	 *
	 * @return integer The affected rows
	 */
	public function executeTruncateQuery($table) {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}
		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject PostProcessQueryHookInterface */
			$hookObject->exec_TRUNCATEquery_postProcessAction($table, $this);
		}

		$this->affectedRows = $this->createTruncateQuery($table)->execute();

		if ($this->getDebugMode()) {
			$this->debug('executeTruncateQuery');
		}


		return $this->affectedRows;
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
		foreach ($this->preProcessHookObjects as $hookObject) {
			/** @var $hookObject PreProcessQueryHookInterface */
			$hookObject->SELECTquery_preProcessAction($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit, $this);
		}
		// Table and fieldnames should be "SQL-injection-safe" when supplied to this function
		// Build basic query
		$query = 'SELECT ' . $selectFields . ' FROM ' . $fromTable . ((string)$whereClause !== '' ? ' WHERE ' . $whereClause : '');
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

	}


	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See exec_TRUNCATEquery()
	 *
	 * @return string|\Konafets\DoctrineDbal\Persistence\Doctrine\TruncateQuery
	 * @api
	 */
	public function createTruncateQuery($table = '') {
		foreach ($this->preProcessHookObjects as $hookObject) {
			/** @var $hookObject PreProcessQueryHookInterface */
			$hookObject->TRUNCATEquery_preProcessAction($table, $this);
		}
			return GeneralUtility::makeInstance('\\Konafets\\DoctrineDbal\\Persistence\\Doctrine\\TruncateQuery', $this->link);
	}

	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See exec_TRUNCATEquery()
	 *
	 * @return string
	 * @api
	 */
	public function truncateQuery($table) {
		foreach ($this->preProcessHookObjects as $hookObject) {
			/** @var $hookObject PreProcessQueryHookInterface */
			$hookObject->TRUNCATEquery_preProcessAction($table, $this);
		}

		// Table should be "SQL-injection-safe" when supplied to this function
		// Build basic query:
		$query = $this->createTruncateQuery()->truncate($table)->getSql();

		// Return query:
		if ($this->getDebugMode() || $this->getStoreLastBuildQuery()) {
			$this->debug_lastBuiltQuery = $query;
		}

		return $query;
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
	 * @param array  $inputParameters An array of values with as many elements as there are bound parameters in the SQL
	 *                                 statement being executed. All values are treated as
	 *                                 \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 *
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 * @api
	 */
	public function prepareSelectQuery($selectFields, $fromTable, $whereClause, $groupBy = '', $orderBy = '', $limit = '', array $inputParameters = array()) {
		$query = $this->createSelectQuery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);

		/** @var $preparedStatement \Konafets\DoctrineDbal\Persistence\Doctrine\PreparedStatement */
		$preparedStatement = GeneralUtility::makeInstance('Konafets\\DoctrineDbal\\Persistence\\Doctrine\\PreparedStatement', $query, $fromTable, array());

		// Bind values to parameters
		foreach ($inputParameters as $key => $value) {
			$preparedStatement->bindValue($key, $value, PreparedStatement::PARAM_AUTOTYPE);
		}

		// Return prepared statement
		return $preparedStatement;
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
		return $this->prepare_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT'], $inputParameters);
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query           The query to execute
	 * @param array  $queryComponents The components of the query to execute
	 *
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 *
	 * @return \Doctrine\DBAL\Statement|object MySQLi statement / DBAL object
	 */
	public function preparePreparedQuery($query, array $queryComponents) {
		if (!$this->isConnected) {
			$this->connectDB();
		}

		$stmt = $this->link->prepare($query);
		if ($this->debugOutput) {
			$this->debug('stmt_execute', $query);
		}

		if ($stmt instanceof \Doctrine\DBAL\Statement) {
			return $stmt;
		} else {
			return NULL;
		}
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
		if ($allowNull && $string === NULL) {
			return 'NULL';
		}

		return '\'' . $this->quoteString($string, $table) . '\'';
	}

	/**
	 * Will fullquote all values in the one-dimensional array so they are ready to "implode" for an sql query.
	 *
	 * @param array $array Array with values (either associative or non-associative array)
	 * @param string $table Table name for which to quote
	 * @param boolean|array $noQuote List/array of keys NOT to quote (eg. SQL functions) - ONLY for associative arrays
	 * @param boolean $allowNull Whether to allow NULL values
	 *
     * @return array The input array with the values quoted
	 * @see cleanIntArray()
	 */
	public function fullQuoteArray($array, $table, $noQuote = FALSE, $allowNull = FALSE) {
		if (is_string($noQuote)) {
			$noQuote = explode(',', $noQuote);
		} elseif (!is_array($noQuote)) {
			$noQuote = FALSE;
		}
		foreach ($array as $key => $value) {
			if ($noQuote === FALSE || !in_array($key, $noQuote)) {
				$array[$key] = $this->fullQuoteString($value, $table, $allowNull);
			}
		}
		return $array;
	}


	/**
	 * Substitution for PHP function "addslashes()"
	 * Use this function instead of the PHP addslashes() function when you build queries - this will prepare your code for DBAL.
	 * NOTICE: You must wrap the output of this function in SINGLE QUOTES to be DBAL compatible. Unless you have to apply the
	 *         single quotes yourself you should rather use ->fullQuoteStr()!
	 *
	 * @param string $string Input string
	 *
	 * @internal             param string $table Table name for which to quote string. Just enter the table that the field-value is selected from
	 *                       (and any DBAL will look up which handler to use and then how to quote the string!).
	 *
	 * @return string Output string; Quotes (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 */
	public function quoteString($string) {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$quotedResult = $this->quote($string);

		if ($quotedResult[0] == '\'') {
			$quotedResult = substr($quotedResult, 1);
		}
		if ($quotedResult[strlen($quotedResult) - 1] == '\'') {
			$quotedResult = substr($quotedResult, 0, strlen($quotedResult) - 1);
		}

		return $quotedResult;
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
		if (!$this->isConnected()) {
			$this->connectDatabase();
		}

		return $this->quote($string);
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
		return array_map('intval', $integerArray);
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
		return implode(',', GeneralUtility::intExplode(',', $list));
	}

	/**
	 * Removes the prefix "ORDER BY" from the input string.
	 * This function is used when you call the exec_SELECTquery() function and want to pass the ORDER BY parameter by can't guarantee that "ORDER BY" is not prefixed.
	 * Generally; This function provides a work-around to the situation where you cannot pass only the fields by which to order the result.
	 *
	 * @param string $string eg. "ORDER BY title, uid
	 *
*@return string eg. "title, uid
	 * @see exec_SELECTquery(), stripGroupBy()
	 */
	public function stripOrderBy($string) {
		return preg_replace('/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '', trim($string));
	}

	/**
	 * Removes the prefix "GROUP BY" from the input string.
	 * This function is used when you call the SELECTquery() function and want to pass the GROUP BY parameter by can't guarantee that "GROUP BY" is not prefixed.
	 * Generally; This function provides a work-around to the situation where you cannot pass only the fields by which to order the result.
	 *
	 * @param string $string eg. "GROUP BY title, uid
	 *
*@return string eg. "title, uid
	 * @see exec_SELECTquery(), stripOrderBy()
	 */
	public function stripGroupBy($string) {
		return preg_replace('/^(?:GROUP[[:space:]]*BY[[:space:]]*)+/i', '', trim($string));
	}

	/**
	 * Takes the last part of a query, eg. "... uid=123 GROUP BY title ORDER BY title LIMIT 5,2" and splits each part into a table (WHERE, GROUPBY, ORDERBY, LIMIT)
	 * Work-around function for use where you know some userdefined end to an SQL clause is supplied and you need to separate these factors.
	 *
	 * @param string $string Input string
	 *
*@return array
	 */
	public function splitGroupOrderLimit($string) {
		// Prepending a space to make sure "[[:space:]]+" will find a space there
		// for the first element.
		$string = ' ' . $string;
		// Init output array:
		$wgolParts = array(
			'WHERE' => '',
			'GROUPBY' => '',
			'ORDERBY' => '',
			'LIMIT' => ''
		);
		// Find LIMIT
		$reg = array();
		if (preg_match('/^(.*)[[:space:]]+LIMIT[[:space:]]+([[:alnum:][:space:],._]+)$/i', $string, $reg)) {
			$wgolParts['LIMIT'] = trim($reg[2]);
			$string = $reg[1];
		}
		// Find ORDER BY
		$reg = array();
		if (preg_match('/^(.*)[[:space:]]+ORDER[[:space:]]+BY[[:space:]]+([[:alnum:][:space:],._]+)$/i', $string, $reg)) {
			$wgolParts['ORDERBY'] = trim($reg[2]);
			$string = $reg[1];
		}
		// Find GROUP BY
		$reg = array();
		if (preg_match('/^(.*)[[:space:]]+GROUP[[:space:]]+BY[[:space:]]+([[:alnum:][:space:],._]+)$/i', $string, $reg)) {
			$wgolParts['GROUPBY'] = trim($reg[2]);
			$string = $reg[1];
		}
		// Rest is assumed to be "WHERE" clause
		$wgolParts['WHERE'] = $string;
		return $wgolParts;
	}

	/**
	 * Returns the date and time formats compatible with the given database table.
	 *
	 * @param string $table Table name for which to return an empty date. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how date and time should be formatted).
	 * @return array
	 */
	public function getDateTimeFormats($table) {
		return array(
			'date' => array(
				'empty' => '0000-00-00',
				'format' => 'Y-m-d'
			),
			'datetime' => array(
				'empty' => '0000-00-00 00:00:00',
				'format' => 'Y-m-d H:i:s'
			)
		);
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function sql_errno(){
		return $this->getErrorCode();
	}

	/**
	 * Returns the error number on the last query() execution
	 *
	 * @return integer PDO error number
	 * @api
	 */
	public function getErrorCode() {
		return $this->link->errorCode();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function sql_error(){
		return $this->getErrorMessage();
	}

	/**
	 * Returns the error status on the last query() execution
	 *
	 * @return string PDO error string.
	 * @api
	 */
	public function getErrorMessage() {
		$errorMsg = $this->link->errorInfo();

		return $errorMsg[0] === '00000' ? '' : $errorMsg;
	}

	/**
	 * Returns the number of selected rows.
	 *
	 * @param boolean|\Doctrine\DBAL\Driver\Statement $stmt
	 *
	 * @return integer Number of resulting rows
	 */
	public function getResultRowCount($stmt) {
		if ($this->debugCheckRecordset($stmt)) {
			$result = $stmt->rowCount();
		} else {
			$result = FALSE;
		}

		return $result;
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @param null $tableName
	 *
	 * @return integer The uid of the last inserted record.
	 * @api
	 */
	public function getLastInsertId($tableName = NULL) {
		if ($this->getDatabaseDriver() === 'pdo_pgsql') {
			return (int)$this->link->lastInsertId($tableName . '_uid_seq');
		} else {
			return (int)$this->link->lastInsertId();
		}
	}

	/**
	 * Returns the number of rows affected by the last INSERT, UPDATE or DELETE query
	 *
	 * @return int
	 * @api
	 */
	public function getAffectedRows() {
		return (int)$this->affectedRows;
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * Wrapper function for Statement::fetch(\PDO::FETCH_ASSOC)
	 *
	 * @param \Doctrine\DBAL\Driver\Statement $stmt A PDOStatement object
	 *
	 * @return boolean|array Associative array of result row.
	 * @api
	 */
	public function fetchAssoc($stmt) {
		if ($this->debugCheckRecordset($stmt)) {
			return $stmt->fetch(\PDO::FETCH_ASSOC);
		} else {
			return FALSE;
		}
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains only a single requested column from the next row in the result set
	 * Wrapper function for Statement::fetch(\PDO::FETCH_COLUMN)
	 *
	 * @param \Doctrine\DBAL\Driver\Statement $stmt A PDOStatement object
	 *
	 * @param                                 $index 0-indexed number of the column you wish to retrieve from the row. If no value is supplied it fetches the first column.
	 *
	 * @return boolean|array Array with result rows.
	 * @api
	 */
	public function fetchColumn($stmt, $index = 0) {
		if ($this->debugCheckRecordset($stmt)) {
			return $stmt->fetchColumn($index);
		} else {
			return FALSE;
		}
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * Wrapper function for Statement::fetch(\PDO::FETCH_NUM)
	 *
	 * @param \Doctrine\DBAL\Driver\Statement $stmt A PDOStatement object
	 *
	 * @return boolean|array Array with result rows.
	 * @api
	 */
	public function fetchRow($stmt) {
		if ($this->debugCheckRecordset($stmt)) {
			return $stmt->fetch(\PDO::FETCH_NUM);
		} else {
			return FALSE;
		}
	}

	/**
	 * Free result memory
	 * Wrapper function for Doctrine/PDO closeCursor()
	 *
	 * @param boolean|\Doctrine\DBAL\Driver\Statement $stmt A PDOStatement
	 *
	 * @return boolean Returns NULL on success or FALSE on failure.
	 * @api
	 */
	public function freeResult($stmt) {
		if ($this->debugCheckRecordset($stmt) && is_object($stmt)) {
			return $stmt->closeCursor();
		} else {
			return FALSE;
		}
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
	}

	/**
	 * Get the type of the specified field in a result
	 * mysql_field_type() wrapper function
	 *
	 * @param boolean|\Doctrine\DBAL\Driver\Statement $stmt   A PDOStatement object
	 * @param                                         $table
	 * @param integer                                 $column Field index.
	 *
	 * @return string Returns the name of the specified field index, or FALSE on error
	 */
	public function getFieldType($stmt, $table, $column) {
		// mysql_field_type compatibility map
		// taken from: http://www.php.net/manual/en/mysqli-result.fetch-field-direct.php#89117
		// Constant numbers see http://php.net/manual/en/mysqli.constants.php

		$mysqlDataTypeHash = array(
			'boolean'      => 'boolean',
			'smallint'     => 'smallint',
			'integer'      => 'int',
			'float'        => 'float',
			'double'       => 'double',
			'timestamp'    => 'timestamp',
			'bigint'       => 'bigint',
			'mediumint'    => 'mediumint',
			'date'         => 'date',
			'time'         => 'time',
			'datetime'     => 'datetime',
			'text'         => 'varchar',
			'string'       => 'varchar',
			'decimal'      => 'decimal',
			'blob'         => 'blob',
			'guid'         => 'guid',
			'object'       => 'object',
			'datetimetz'   => 'datetimetz',
			'json_array'   => 'json_array',
			'simple_array' => 'simple_array',
			'array'        => 'array',
		);

		if ($this->debugCheckRecordset($stmt)) {
			if (count($this->schema->getTables()) === 0) {
				$this->schema = $this->link->getSchemaManager()->createSchema();
			}

			$metaInfo = $this->schema
				->getTable($table)
				->getColumn($column)
				->getType()
				->getName();

			if ($metaInfo === FALSE) {
				return FALSE;
			}

			return $mysqlDataTypeHash[$metaInfo];
		} else {
			return FALSE;
		}
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
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$databases = $this->schemaManager->listDatabases();
		if (empty($databases)) {
			throw new \RuntimeException(
				'MySQL Error: Cannot get databases: "' . $this->getErrorMessage() . '"!',
				1378457171
			);
		}

		return $databases;
	}

	/**
	 * Returns the list of tables from the default database, TYPO3_db (quering the DBMS)
	 * In a DBAL this method should 1) look up all tables from the DBMS  of
	 * the _DEFAULT handler and then 2) add all tables *configured* to be managed by other handlers
	 *
	 * @return array Array with tablenames as key and arrays with status information as value
	 */
	public function listTables() {
		$tables = array();
		$stmt = $this->adminQuery('SHOW TABLE STATUS FROM `' . $this->getDatabaseName() . '`');
		if ($stmt !== FALSE) {
			// TODO: Abstract fetch here aswell
			while ($theTable = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$tables[$theTable['Name']] = $theTable;
			}
		}

		// TODO: Figure out how to use this
//		$testTables = array();
//		$tables = $this->schema->listTables();
//		if ($tables !== FALSE) {
//			foreach ($tables as $table) {
//				$testTables[$table->getName()] = array(
//													'columns' => $table->getColumns(),
//													'indices' => $table->getIndexes()
//												);
//			}
//		}

		return $tables;
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
		$fields = array();
		// TODO: Figure out if we could use the function $this->schema->listTableColumns($tableName);
		//       The result is a different from the current. We need to adjust assembleFieldDefinition() from
		//       SqlSchemaMigrationService
		$stmt = $this->adminQuery('SHOW COLUMNS FROM `' . $tableName . '`');

		if ($stmt !== FALSE) {
			while ($fieldRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$fields[$fieldRow['Field']] = $fieldRow;
			}
			$stmt->closeCursor();
		}

		return $fields;
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
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$keys = array();

		$stmt = $this->adminQuery('SHOW KEYS FROM `' . $tableName . '`');
		if ($stmt !== FALSE) {
			while ($keyRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$keys[] = $keyRow;
			}
			$stmt->closeCursor();
		}

		return $keys;
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
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$output = array();
		$stmt = $this->adminQuery('SHOW CHARACTER SET');

		if ($stmt !== FALSE) {
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$output[$row['Charset']] = $row;
			}
			$stmt->closeCursor();
		}

		return $output;
	}

	/**
	 * This returns the count of the tables from the selected database
	 *
	 * @return int
	 */
	public function countTables() {
		if (!$this->isConnected) {
			$this->connectDatabase();
		}

		$result[0] = -1;
		$sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :databaseName';

		$statement = $this->link->prepare($sql);
		$statement->bindValue('databaseName', $this->getDatabaseName());
		$isQuerySuccess = $statement->execute();

		if ($isQuerySuccess !== FALSE) {
			$result = $statement->fetchAll(\PDO::FETCH_COLUMN);
		}

		return $result[0];
	}


	/**
	 * Returns a WHERE clause that can find a value ($value) in a list field ($field)
	 * For instance a record in the database might contain a list of numbers,
	 * "34,234,5" (with no spaces between). This query would be able to select that
	 * record based on the value "34", "234" or "5" regardless of their position in
	 * the list (left, middle or right).
	 * The value must not contain a comma (,)
	 * Is nice to look up list-relations to records or files in TYPO3 database tables.
	 *
	 * @param string $field Field name
	 * @param string $value Value to find in list
	 * @param string $table Table in which we are searching (for DBAL detection of quoteStr() method)
	 * @return string WHERE clause for a query
	 * @throws \InvalidArgumentException
	 */
	public function listQuery($field, $value, $table) {
		$value = (string)$value;
		if (strpos($value, ',') !== FALSE) {
			throw new \InvalidArgumentException('$value must not contain a comma (,) in $this->listQuery() !', 1294585862);
		}
		$pattern = $this->quoteString($value, $table);
		$where = 'FIND_IN_SET(\'' . $pattern . '\',' . $field . ')';
		return $where;
	}

	/**
	 * Returns a WHERE clause which will make an AND or OR search for the words in the $searchWords array in any of the fields in array $fields.
	 *
	 * @param array $searchWords Array of search words
	 * @param array $fields Array of fields
	 * @param string $table Table in which we are searching (for DBAL detection of quoteStr() method)
	 * @param string $constraint How multiple search words have to match ('AND' or 'OR')
	 * @return string WHERE clause for search
	 */
	public function searchQuery($searchWords, $fields, $table, $constraint = self::AND_Constraint) {
		switch ($constraint) {
			case self::OR_Constraint:
				$constraint = 'OR';
				break;
			default:
				$constraint = 'AND';
		}

		$queryParts = array();
		foreach ($searchWords as $sw) {
			$like = ' LIKE \'%' . $this->quoteString($sw, $table) . '%\'';
			$queryParts[] = $table . '.' . implode(($like . ' OR ' . $table . '.'), $fields) . $like;
		}
		$query = '(' . implode(') ' . $constraint . ' (', $queryParts) . ')';

		return $query;
	}

	/**
	 * Checks if record set is valid and writes debugging information into devLog if not.
	 *
	 * @param boolean|\mysqli_result|object MySQLi result object / DBAL object
	 *
	 * @return boolean TRUE if the  record set is valid, FALSE otherwise
	 */
	protected function debugCheckRecordset($stmt) {
		if ($stmt !== FALSE) {
			return TRUE;
		}
		$msg = 'Invalid database result detected';
		$trace = debug_backtrace();
		array_shift($trace);
		$cnt = count($trace);
		for ($i = 0; $i < $cnt; $i++) {
			// Complete objects are too large for the log
			if (isset($trace['object'])) {
				unset($trace['object']);
			}
		}
		$msg .= ': function TYPO3\\CMS\\Core\\Database\\DatabaseConnection->' . $trace[0]['function'] . ' called from file ' . substr($trace[0]['file'], (strlen(PATH_site) + 2)) . ' in line ' . $trace[0]['line'];
		GeneralUtility::sysLog(
			$msg . '. Use a devLog extension to get more details.',
			'Core/t3lib_db',
			GeneralUtility::SYSLOG_SEVERITY_ERROR
		);
		// Send to devLog if enabled
		if (TYPO3_DLOG) {
			$debugLogData = array(
				'SQL Error' => $this->getErrorMessage(),
				'Backtrace' => $trace
			);
			if ($this->debug_lastBuiltQuery) {
				$debugLogData = array('SQL Query' => $this->debug_lastBuiltQuery) + $debugLogData;
			}
			GeneralUtility::devLog($msg . '.', 'Core/t3lib_db', 3, $debugLogData);
		}

		return FALSE;
	}

	/**
	 * Escaping and quoting values for SQL statements.
	 *
	 * @param string  $string    Input string
	 * @param boolean $allowNull Whether to allow NULL values
	 *
	 * @return string Output string; Wrapped in single quotes and quotes in the string (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @api
	 */
	public function quote($string, $allowNull = FALSE) {
		if ($allowNull && $string === NULL) {
			return 'NULL';
		}

		return $this->link->quote($string);
	}

	/**
	 * Returns a qualified identifier for $columnName in $tableName
	 *
	 * Example:
	 * <code><br>
	 * // if no $tableName is given it returns: `column`<br>
	 * $GLOBALS['TYPO3_DB']->quoteTable('column');<br><br>
	 *
	 * // if $tableName is given it returns: `pages`.`column`<br>
	 * $GLOBALS['TYPO3_DB']->quoteTable('column', 'pages');<br>
	 * </code>
	 *
	 * @param string $columnName
	 * @param string $tableName
	 *
	 * @return string
	 * @api
	 */
	public function quoteColumn($columnName, $tableName = NULL) {
		return ($tableName ? $this->quoteTable($tableName) . '.' : '') .
				$this->quoteIdentifier($columnName);
	}

	/**
	 * Returns a qualified identifier for $tablename
	 *
	 * Example:
	 * <code><br>
	 * // returns: `pages`<br>
	 * $GLOBALS['TYPO3_DB']->quoteTable('pages');<br>
	 * </code>
	 *
	 * @param string $tableName
	 *
	 * @return string
	 * @api
	 */
	public function quoteTable($tableName) {
		return $this->quoteIdentifier($tableName);
	}

	/**
	 * Custom quote identifier method
	 *
	 * Example:
	 * <code><br>
	 * // returns `column`<br>
	 * $GLOBALS['TYPO3_DB']->quoteIdentifier('column');<br>
	 * </code>
	 *
	 * @param string $identifier
	 *
	 * @return string
	 * @api
	 */
	public function quoteIdentifier($identifier) {
		return '`' . $identifier . '`';
	}

	/**
	 * Checks if the current connection character set has the same value
	 * as the connectionCharset variable.
	 *
	 * To determine the character set these MySQL session variables are
	 * checked: character_set_client, character_set_results and
	 * character_set_connection.
	 *
	 * If the character set does not match or if the session variables
	 * can not be read a RuntimeException is thrown.
	 *
	 * @return void
	 * @throws \RuntimeException
	 */
	protected function checkConnectionCharset() {
		$sessionResult = $this->adminQuery('SHOW SESSION VARIABLES LIKE \'character_set%\'');

		if ($sessionResult === FALSE) {
			GeneralUtility::sysLog(
				'Error while retrieving the current charset session variables from the database: ' . $this->getErrorMessage(),
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_ERROR
			);
			throw new \RuntimeException(
				'TYPO3 Fatal Error: Could not determine the current charset of the database.',
				1381847136
			);
		}

		$charsetVariables = array();
		while (($row = $this->fetchRow($sessionResult)) !== FALSE) {
			$variableName = $row[0];
			$variableValue = $row[1];
			$charsetVariables[$variableName] = $variableValue;
		}
		$this->freeResult($sessionResult);

		// These variables are set with the "Set names" command which was
		// used in the past. This is why we check them.
		$charsetRequiredVariables = array(
			'character_set_client',
			'character_set_results',
			'character_set_connection',
		);

		$hasValidCharset = TRUE;
		foreach ($charsetRequiredVariables as $variableName) {
			if (empty($charsetVariables[$variableName])) {
				GeneralUtility::sysLog(
					'A required session variable is missing in the current MySQL connection: ' . $variableName,
					'Core',
					GeneralUtility::SYSLOG_SEVERITY_ERROR
				);
				throw new \RuntimeException(
					'TYPO3 Fatal Error: Could not determine the value of the database session variable: ' . $variableName,
					1381847779
				);
			}

			if ($charsetVariables[$variableName] !== $this->getDatabaseCharset()) {
				$hasValidCharset = FALSE;
				break;
			}
		}

		if (!$hasValidCharset) {
			throw new \RuntimeException(
				'It looks like the character set ' . $this->getDatabaseCharset() . ' is not used for this connection even though it is configured as connection charset. ' .
				'This TYPO3 installation is using the $GLOBALS[\'TYPO3_CONF_VARS\'][\'SYS\'][\'setDBinit\'] property with the following value: "' .
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit'] . '". Please make sure that this command does not overwrite the configured charset. ' .
				'Please note that for the TYPO3 database everything other than utf8 is unsupported since version 4.7.',
				1389697515
			);
		}
	}

	/******************************
	 *
	 * Debugging
	 *
	 ******************************/

	/**
	 * Debug function: Outputs error if any
	 *
	 * @param string $func Function calling debug()
	 * @param string $query Last query if not last built query
	 * @return void
	 * @todo Define visibility
	 */
	public function debug($func, $query = '') {
		$error = $this->getErrorMessage();
		if ($error || (int)$this->debugOutput === 2) {
			DebugUtility::debug(
				array(
					'caller' => 'Konafets\\DoctrineDbal\\Persistence\\Doctrine\\DatabaseConnection::' . $func,
					'ERROR' => $error,
					'lastBuiltQuery' => $query ? $query : $this->debug_lastBuiltQuery,
					'debug_backtrace' => DebugUtility::debugTrail()
				),
				$func,
				is_object($GLOBALS['error']) && @is_callable(array($GLOBALS['error'], 'debug'))
					? ''
					: 'DB Error'
			);
		}
	}

	/**
	 * Explain select queries
	 * If $this->explainOutput is set, SELECT queries will be explained here. Only queries with more than one possible result row will be displayed.
	 * The output is either printed as raw HTML output or embedded into the TS admin panel (checkbox must be enabled!)
	 *
	 * TODO: Feature is not DBAL-compliant
	 *
	 * @param string $query SQL query
	 * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param integer $row_count Number of resulting rows
	 * @return boolean TRUE if explain was run, FALSE otherwise
	 */
	protected function explain($query, $from_table, $row_count) {
		$debugAllowedForIp = GeneralUtility::cmpIP(
			GeneralUtility::getIndpEnv('REMOTE_ADDR'),
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask']
		);
		if (
			(int)$this->explainOutput == 1
			|| ((int)$this->explainOutput == 2 && $debugAllowedForIp)
		) {
			// Raw HTML output
			$explainMode = 1;
		} elseif ((int)$this->explainOutput == 3 && is_object($GLOBALS['TT'])) {
			// Embed the output into the TS admin panel
			$explainMode = 2;
		} else {
			return FALSE;
		}
		$error = $this->getErrorMessage();
		$trail = \TYPO3\CMS\Core\Utility\DebugUtility::debugTrail();
		$explain_tables = array();
		$explain_output = array();
		$res = $this->adminQuery('EXPLAIN ' . $query, $this->link);
		if (is_a($res, '\\mysqli_result')) {
			while ($tempRow = $this->fetchAssoc($res)) {
				$explain_output[] = $tempRow;
				$explain_tables[] = $tempRow['table'];
			}
			$this->freeResult($res);
		}
		$indices_output = array();
		// Notice: Rows are skipped if there is only one result, or if no conditions are set
		if (
			$explain_output[0]['rows'] > 1
			|| GeneralUtility::inList('ALL', $explain_output[0]['type'])
		) {
			// Only enable output if it's really useful
			$debug = TRUE;
			foreach ($explain_tables as $table) {
				$tableRes = $this->adminQuery('SHOW TABLE STATUS LIKE \'' . $table . '\'');
				$isTable = $this->getResultRowCount($tableRes);
				if ($isTable) {
					$res = $this->adminQuery('SHOW INDEX FROM ' . $table, $this->link);
					if (is_a($res, '\\mysqli_result')) {
						while ($tempRow = $this->fetchAssoc($res)) {
							$indices_output[] = $tempRow;
						}
						$this->freeResult($res);
					}
				}
				$this->freeResult($tableRes);
			}
		} else {
			$debug = FALSE;
		}
		if ($debug) {
			if ($explainMode) {
				$data = array();
				$data['query'] = $query;
				$data['trail'] = $trail;
				$data['row_count'] = $row_count;
				if ($error) {
					$data['error'] = $error;
				}
				if (count($explain_output)) {
					$data['explain'] = $explain_output;
				}
				if (count($indices_output)) {
					$data['indices'] = $indices_output;
				}
				if ($explainMode == 1) {
					\TYPO3\CMS\Core\Utility\DebugUtility::debug($data, 'Tables: ' . $from_table, 'DB SQL EXPLAIN');
				} elseif ($explainMode == 2) {
					$GLOBALS['TT']->setTSselectQuery($data);
				}
			}
			return TRUE;
		}
		return FALSE;
	}
}