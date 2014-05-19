<?php
namespace Konafets\DoctrineDbal\Install\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2013 Christian Kuhn <lolli@schwarzbu.ch>
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

/**
 * Verify TYPO3 DB table structure. Mainly used in install tool
 * compare wizard and extension manager.
 */
class SqlSchemaMigrationService {
	/**
	 * @constant Maximum field width of MySQL
	 */
	const MYSQL_MAXIMUM_FIELD_WIDTH = 64;

	/**
	 * @var string Prefix of deleted tables
	 */
	protected $deletedPrefixKey = 'zzz_deleted_';

	/**
	 * @var array Caching output of $GLOBALS['TYPO3_DB']->listDatabaseCharsets()
	 */
	protected $character_sets = array();

	/**
	 * @return \Konafets\DoctrineDbal\Persistence\Legacy\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
}

	/**
	 * Set prefix of deleted tables
	 *
	 * @param string $prefix Prefix string
	 */
	public function setDeletedPrefixKey($prefix) {
		$this->deletedPrefixKey = $prefix;
	}

	/**
	 * Get prefix of deleted tables
	 *
	 * @return string
	 */
	public function getDeletedPrefixKey() {
		return $this->deletedPrefixKey;
	}

	/**
	 * Returns the sql statements of the difference
	 *
	 * @param $currentSchema
	 * @param $expectedSchema
	 *
	 * @return array
	 */
	public function getDifferenceBetweenDatabaseAndExpectedSchemaAsSql($currentSchema, $expectedSchema) {
		$sql = $currentSchema->getMigrateToSql($expectedSchema, $this->getDatabaseConnection()->getPlatform());

		return $sql;
	}

	/**
	 * Returns the current schema
	 * @return mixed
	 */
	public function getCurrentSchemaFromDatabase() {
		$currentSchema = $this->getDatabaseConnection()->getSchemaManager()->createSchema();

		return $currentSchema;
	}

	/**
	 * @param array $differencesAsSql
	 *
	 * @return array
	 */
	public function getUpdateSuggestionDoctrine(array $differencesAsSql) {
		$statements = array();
		foreach ($differencesAsSql as $statement) {
			if (strpos($statement, 'CREATE INDEX') !== FALSE) {
				$statements['create_index'][md5($statement)] = $statement;
			}

			if (strpos($statement, 'DROP INDEX') !== FALSE) {
				$statements['drop_index'][md5($statement)] = $statement;
			}

			if (strpos($statement, 'ALTER TABLE') !== FALSE) {
				$singleStatements = explode(',', $statement);
				if (count($singleStatements) > 1) {
					$firstPartOfStatementArray = explode(' ', $singleStatements[0]);
					$alterTable = $firstPartOfStatementArray[0] . ' ' . $firstPartOfStatementArray[1] . ' ';
					$table = $firstPartOfStatementArray[2];
					for ($i = 1; $i < count($singleStatements); ++$i) {
						if (strpos($statement, 'CHANGE') !== FALSE) {
							$statements['change'][md5($alterTable . $table . $singleStatements[$i])] = $alterTable . $table . $singleStatements[$i];
						}
					}
				} else {
					if (strpos($statement, 'ADD') !== FALSE) {
						$statements['add'][md5($statement)] = $statement;
					}
					if (strpos($statement, 'DROP') !== FALSE) {
						$statements['drop'][md5($statement)] = $statement;
					}
				}
			}
		}

		return $statements;
	}

	/**
	 * @return int
	 */
	public function getDefaultInsertStatements() {
		$insertQuery = new \TYPO3\CMS\Extensionmanager\Schema\DefaultData($this->getDatabaseConnection());

		return $insertQuery->insertDefaultData();
	}

	/**
	 * Returns list of tables in the database
	 *
	 * @return array List of tables.
	 * @see \TYPO3\CMS\Core\Database\DatabaseConnection::listTables()
	 */
	public function getListOfTables() {
		$whichTables = $this->getDatabaseConnection()->listTables(TYPO3_db);
		foreach ($whichTables as $key => &$value) {
			$value = $key;
		}
		unset($value);
		return $whichTables;
	}
}
