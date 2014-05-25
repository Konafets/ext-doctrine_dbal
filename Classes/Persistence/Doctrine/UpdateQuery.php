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

use Doctrine\DBAL\Query\QueryException;
use Konafets\DoctrineDbal\Persistence\Database\UpdateQueryInterface;

/**
 * Class UpdateQuery
 *
 * This code is inspired by the database integration of ezPublish
 * from Benjamin Eberlei.
 *
 * @package Konafets\DoctrineDbal\Persistence\Doctrine
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Stefano Kowalke <blueduck@gmx.net>
 */
class UpdateQuery extends AbstractQuery implements UpdateQueryInterface {
	/**
	 * Returns the type of the query
	 *
	 * @return int
	 */
	public function getType() {
		return self::UPDATE;
	}

	/**
	 * Set the table to update
	 *
	 * @param string $table
	 *
	 * @throws \Doctrine\DBAL\Query\QueryException
	 * @return UpdateQueryInterface
	 */
	public function update($table) {
		if ($table === '' || is_numeric($table)) {
			throw new QueryException('No table name found in UPDATE statement.');
		}

		$this->queryBuilder->update($table);

		return $this;
	}

	/**
	 * Set the columns and values to update
	 *
	 * @param string     $column
	 * @param string|int $value
	 *
	 * @throws \Doctrine\DBAL\Query\QueryException
	 * @return UpdateQueryInterface
	 */
	public function set($column, $value) {
		if (!count($column) || !count($value)) {
			throw new QueryException('No columns or values found in UPDATE statement.');
		}

		if (count($column) !== count($value)) {
			throw new QueryException('The amount of values of $columns and $values must be equal in UPDATE statement.');
		}

		$values = array();

		if (is_array($column) && is_array($value)) {
			for ($i = 0; $i < count($column); ++$i) {
				$values[$column[$i]] = $value[$i];
			}
		} else {
			$values[$column] = $value;
		}

		foreach ($values as $column => $value) {
			$this->queryBuilder->set($column, $value);
		}

		return $this;
	}

	/**
	 * Set the where constraint
	 *
	 * @return UpdateQueryInterface
	 */
	public function where() {
		$constraints = $this->validateConstraints(func_get_args());

		$where = array();

		foreach ($constraints as $constraint) {
			if ($constraint !== '') {
				$where[] = $constraint;
			}
		}

		if (count($where)) {
			call_user_func_array(array($this->queryBuilder, 'where'), $where);
		}

		return $this;
	}

	/**
	 * Returns the sql statement of this query
	 *
	 * @throws \Doctrine\DBAL\Query\QueryException
	 * @return string
	 */
	public function getSql() {
		if (!count($this->queryBuilder->getQueryPart('set'))) {
			throw new QueryException('No columns or values found in UPDATE statement.');
		}

		return $this->queryBuilder->getSQL();
	}
}

