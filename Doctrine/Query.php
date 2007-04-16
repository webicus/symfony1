<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */
Doctrine::autoload('Doctrine_Hydrate');
/**
 * Doctrine_Query
 *
 * @package     Doctrine
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_Query extends Doctrine_Hydrate implements Countable {
    /**
     * @param array $subqueryAliases        the table aliases needed in some LIMIT subqueries
     */
    private $subqueryAliases  = array();
    /**
     * @param boolean $needsSubquery
     */
    private $needsSubquery    = false;
    /**
     * @param boolean $limitSubqueryUsed
     */
    private $limitSubqueryUsed = false;
    /**
     * @param boolean $isSubquery           whether or not this query object is a subquery of another 
     *                                      query object
     */
    private $isSubquery;

    private $tableStack;

    private $relationStack     = array();

    private $isDistinct        = false;
    
    protected $components      = array();
    
    private $neededTables      = array();
    /**
     * @var array $pendingFields
     */
    private $pendingFields     = array();
    /**
     * @var array $pendingSubqueries        SELECT part subqueries, these are called pending subqueries since
     *                                      they cannot be parsed directly (some queries might be correlated)
     */
    private $pendingSubqueries = array();
    /**
     * @var boolean $subqueriesProcessed    Whether or not pending subqueries have already been processed.
     *                                      Consequent calls to getQuery would result badly constructed queries
     *                                      without this variable
     */
    private $subqueriesProcessed = false;



    /**
     * create
     * returns a new Doctrine_Query object
     *
     * @return Doctrine_Query
     */
    public static function create()
    {
        return new Doctrine_Query();
    }
    /**
     * isSubquery
     * if $bool parameter is set this method sets the value of
     * Doctrine_Query::$isSubquery. If this value is set to true
     * the query object will not load the primary key fields of the selected
     * components.
     *
     * If null is given as the first parameter this method retrieves the current
     * value of Doctrine_Query::$isSubquery.
     *
     * @param boolean $bool     whether or not this query acts as a subquery
     * @return Doctrine_Query|bool
     */
    public function isSubquery($bool = null)
    {
        if ($bool === null) {
            return $this->isSubquery;
        }

        $this->isSubquery = (bool) $bool;
        return $this;
    }

    /**
     * getAggregateAlias
     * 
     * @return string
     */
    public function getAggregateAlias($dqlAlias)
    {
        if(isset($this->aggregateMap[$dqlAlias])) {
            return $this->aggregateMap[$dqlAlias];
        }
        
        return null;
    }

    public function getTableStack()
    {
        return $this->tableStack;
    }

    public function getRelationStack()
    {
        return $this->relationStack;
    }

    public function isDistinct($distinct = null)
    {
        if(isset($distinct))
            $this->isDistinct = (bool) $distinct;

        return $this->isDistinct;
    }

    public function processPendingFields($componentAlias)
    {
        $tableAlias = $this->getTableAlias($componentAlias);

        if ( ! isset($this->tables[$tableAlias]))
            throw new Doctrine_Query_Exception('Unknown component path '.$componentAlias);

        $table      = $this->tables[$tableAlias];

        if (isset($this->pendingFields[$componentAlias])) {
            $fields = $this->pendingFields[$componentAlias];

            if (in_array('*', $fields)) {
                $fields = $table->getColumnNames();
            } else {
                // only auto-add the primary key fields if this query object is not 
                // a subquery of another query object
                if ( ! $this->isSubquery) {
                    $fields = array_unique(array_merge($table->getPrimaryKeys(), $fields));
                }
            }
        }
        foreach ($fields as $name) {
            $name = $table->getColumnName($name);

            $this->parts['select'][] = $tableAlias . '.' .$name . ' AS ' . $tableAlias . '__' . $name;
        }
        
        $this->neededTables[] = $tableAlias;

    }
    /**
     * parseSelect
     * parses the query select part and
     * adds selected fields to pendingFields array
     *
     * @param string $dql
     */
    public function parseSelect($dql)
    {
        $refs = Doctrine_Query::bracketExplode($dql, ',');

        foreach ($refs as $reference) {
            if (strpos($reference, '(') !== false) {
                if (substr($reference, 0, 1) === '(') {
                    // subselect found in SELECT part
                    $this->parseSubselect($reference);
                } else {
                    $this->parseAggregateFunction2($reference);
                }
            } else {

                $e = explode('.', $reference);
                if (count($e) > 2) {
                    $this->pendingFields[] = $reference;
                } else {
                    $this->pendingFields[$e[0]][] = $e[1];
                }
            }
        }
    }
    /** 
     * parseSubselect
     *
     * parses the subquery found in DQL SELECT part and adds the
     * parsed form into $pendingSubqueries stack
     *
     * @param string $reference
     * @return void
     */
    public function parseSubselect($reference) 
    {
        $e     = Doctrine_Query::bracketExplode($reference, ' ');
        $alias = $e[1];

        if (count($e) > 2) {
            if (strtoupper($e[1]) !== 'AS') {
                throw new Doctrine_Query_Exception('Syntax error near: ' . $reference);
            }
            $alias = $e[2];
        }
        
        $subquery = substr($e[0], 1, -1);
        
        $this->pendingSubqueries[] = array($subquery, $alias);
    }
    public function parseAggregateFunction2($func)
    {
        $e    = Doctrine_Query::bracketExplode($func, ' ');
        $func = $e[0];

        $pos  = strpos($func, '(');
        $name = substr($func, 0, $pos);

        try {
            $argStr = substr($func, ($pos + 1), -1);
            $args   = explode(',', $argStr);
    
            $func   = call_user_func_array(array($this->conn->expression, $name), $args);
    
            if(substr($func, 0, 1) !== '(') {
                $pos  = strpos($func, '(');
                $name = substr($func, 0, $pos);
            } else {
                $name = $func;
            }
    
            $e2     = explode(' ', $args[0]);
    
            $distinct = '';
            if(count($e2) > 1) {
                if(strtoupper($e2[0]) == 'DISTINCT')
                    $distinct  = 'DISTINCT ';
    
                $args[0] = $e2[1];
            }
    
    
    
            $parts = explode('.', $args[0]);
            $owner = $parts[0];
            $alias = (isset($e[1])) ? $e[1] : $name;
    
            $e3    = explode('.', $alias);
    
            if(count($e3) > 1) {
                $alias = $e3[1];
                $owner = $e3[0];
            }
    
            // a function without parameters eg. RANDOM()
            if ($owner === '') {
                $owner = 0;
            }
    
            $this->pendingAggregates[$owner][] = array($name, $args, $distinct, $alias);
        } catch(Doctrine_Expression_Exception $e) {
            throw new Doctrine_Query_Exception('Unknown function ' . $func . '.');
        }
    }
    public function processPendingSubqueries() 
    {
    	if ($this->subqueriesProcessed === true) {
            return false;
    	}

    	foreach ($this->pendingSubqueries as $value) {
            list($dql, $alias) = $value;

            $sql = $this->createSubquery()->parseQuery($dql, false)->getQuery();
            
            reset($this->tableAliases);
            
            $tableAlias = current($this->tableAliases);

            reset($this->compAliases);
            
            $componentAlias = key($this->compAliases);

            $sqlAlias = $tableAlias . '__' . count($this->aggregateMap);
    
            $this->parts['select'][] = '(' . $sql . ') AS ' . $sqlAlias;
    
            $this->aggregateMap[$alias] = $sqlAlias;
            $this->subqueryAggregates[$componentAlias][] = $alias;
        }
        $this->subqueriesProcessed = true;
        
        return true;
    }
    public function processPendingAggregates($componentAlias)
    {
        $tableAlias = $this->getTableAlias($componentAlias);

        if ( ! isset($this->tables[$tableAlias])) {
            throw new Doctrine_Query_Exception('Unknown component path ' . $componentAlias);
        }
        
        $root       = current($this->tables);
        $table      = $this->tables[$tableAlias];
        $aggregates = array();

        if(isset($this->pendingAggregates[$componentAlias])) {
            $aggregates = $this->pendingAggregates[$componentAlias];
        }
        
        if ($root === $table) {
            if (isset($this->pendingAggregates[0])) {
                $aggregates += $this->pendingAggregates[0];
            }
        }

        foreach($aggregates as $parts) {
            list($name, $args, $distinct, $alias) = $parts;

            $arglist = array();
            foreach($args as $arg) {
                $e = explode('.', $arg);


                if(count($e) > 1) {
                    //$tableAlias = $this->getTableAlias($e[0]);
                    $table      = $this->tables[$tableAlias];

                    $e[1]       = $table->getColumnName($e[1]);

                    if( ! $table->hasColumn($e[1])) {
                        throw new Doctrine_Query_Exception('Unknown column ' . $e[1]);
                    }

                    $arglist[]  = $tableAlias . '.' . $e[1];
                } else {
                    $arglist[]  = $e[0];
                }
            }

            $sqlAlias = $tableAlias . '__' . count($this->aggregateMap);

            if(substr($name, 0, 1) !== '(') {
                $this->parts['select'][] = $name . '(' . $distinct . implode(', ', $arglist) . ') AS ' . $sqlAlias;
            } else {
                $this->parts['select'][] = $name . ' AS ' . $sqlAlias;
            }
            $this->aggregateMap[$alias] = $sqlAlias;
            $this->neededTables[] = $tableAlias;
        }
    }
	/**
 	 * count
     *
     * @param array $params
	 * @return integer
     */
	public function count($params = array())
    {
		$this->remove('select');
		$join  = $this->join;
		$where = $this->where;
		$having = $this->having;
		$table  = reset($this->tables);

		$q  = 'SELECT COUNT(DISTINCT ' . $this->aliasHandler->getShortAlias($table->getTableName())
            . '.' . $table->getIdentifier()
            . ') FROM ' . $table->getTableName() . ' ' . $this->aliasHandler->getShortAlias($table->getTableName());

		foreach($join as $j) {
            $q .= ' '.implode(' ',$j);
		}
        $string = $this->applyInheritance();

        if( ! empty($where)) {
            $q .= ' WHERE ' . implode(' AND ', $where);
            if( ! empty($string))
                $q .= ' AND (' . $string . ')';
        } else {
            if( ! empty($string))
                $q .= ' WHERE (' . $string . ')';
        }
			
		if( ! empty($having))
			$q .= ' HAVING ' . implode(' AND ',$having);

        if( ! is_array($params))
            $params = array($params);

        $params = array_merge($this->params, $params);

		return (int) $this->getConnection()->fetchOne($q, $params);
	}
    /**
     * loadFields
     * loads fields for a given table and
     * constructs a little bit of sql for every field
     *
     * fields of the tables become: [tablename].[fieldname] as [tablename]__[fieldname]
     *
     * @access private
     * @param object Doctrine_Table $table          a Doctrine_Table object
     * @param integer $fetchmode                    fetchmode the table is using eg. Doctrine::FETCH_LAZY
     * @param array $names                          fields to be loaded (only used in lazy property loading)
     * @return void
     */
    protected function loadFields(Doctrine_Table $table, $fetchmode, array $names, $cpath)
    {
        $name = $table->getComponentName();

        switch($fetchmode):
            case Doctrine::FETCH_OFFSET:
                $this->limit = $table->getAttribute(Doctrine::ATTR_COLL_LIMIT);
            case Doctrine::FETCH_IMMEDIATE:
                if( ! empty($names)) {
                    // only auto-add the primary key fields if this query object is not
                    // a subquery of another query object
                    $names = array_unique(array_merge($table->getPrimaryKeys(), $names));
                } else {
                    $names = $table->getColumnNames();
                }
            break;
            case Doctrine::FETCH_LAZY_OFFSET:
                $this->limit = $table->getAttribute(Doctrine::ATTR_COLL_LIMIT);
            case Doctrine::FETCH_LAZY:
            case Doctrine::FETCH_BATCH:
                $names = array_unique(array_merge($table->getPrimaryKeys(), $names));
            break;
            default:
                throw new Doctrine_Exception("Unknown fetchmode.");
        endswitch;

        $component          = $table->getComponentName();
        $tablename          = $this->tableAliases[$cpath];

        $this->fetchModes[$tablename] = $fetchmode;

        $count = count($this->tables);

        foreach($names as $name) {
            if($count == 0) {
                $this->parts['select'][] = $tablename . '.' . $name;
            } else {
                $this->parts['select'][] = $tablename . '.' . $name . ' AS ' . $tablename . '__' . $name;
            }
        }
    }
    /**
     * addFrom
     *
     * @param strint $from
     * @return Doctrine_Query
     */
    public function addFrom($from)
    {
        $class = 'Doctrine_Query_From';
        $parser = new $class($this);
        $parser->parse($from);

        return $this;
    }
    /**
     * leftJoin
     *
     * @param strint $join
     * @return Doctrine_Query
     */
    public function leftJoin($join)
    {
        $class = 'Doctrine_Query_From';
        $parser = new $class($this);
        $parser->parse('LEFT JOIN ' . $join);

        return $this;
    }
    /**
     * innerJoin
     *
     * @param strint $join
     * @return Doctrine_Query
     */
    public function innerJoin($join)
    {
        $class = 'Doctrine_Query_From';
        $parser = new $class($this);
        $parser->parse('INNER JOIN ' . $join);

        return $this;
    }
    /**
     * addOrderBy
     *
     * @param strint $orderby
     * @return Doctrine_Query
     */
    public function addOrderBy($orderby)
    {
        if (empty($orderby)) {
            return $this;
        }
        $class = 'Doctrine_Query_Orderby';
        $parser = new $class($this);
        $this->parts['orderby'][] = $parser->parse($orderby);

        return $this;
    }
    /**
     * addWhere
     *
     * @param string $where
     * @param mixed $params
     */
    public function addWhere($where, $params = array())
    {
        $class  = 'Doctrine_Query_Where';
        $parser = new $class($this);
        $this->parts['where'][] = $parser->parse($where);

        if(is_array($params)) {
            $this->params = array_merge($this->params, $params);
        } else {
            $this->params[] = $params;
        }
    }
    /**
     * addSelect
     *
     * @param string $select
     */
    public function addSelect($select)
    {
        $this->type = self::SELECT;
        
        $this->parseSelect($select);
        
        return $this;
    }
    /**
     * addHaving
     *
     * @param string $having
     */
    public function addHaving($having) 
    {
        $class = 'Doctrine_Query_Having';
        $parser = new $class($this);
        $this->parts['having'][] = $parser->parse($having);
        
        return $this;
    }
    /**
     * sets a query part
     *
     * @param string $name
     * @param array $args
     * @return void
     */
    public function __call($name, $args)
    {
        $name = strtolower($name);

        $method = 'parse' . ucwords($name);

        switch($name) {
            case 'select':
                $this->type = self::SELECT;

                if ( ! isset($args[0])) {
                    throw new Doctrine_Query_Exception('Empty select part');
                }
                $this->parseSelect($args[0]);
            break;
            case 'delete':
                $this->type = self::DELETE;
            break;
            case 'update':
                $this->type = self::UPDATE;
                $name       = 'from';
            case 'from':
                $this->parts['from']    = array();
                $this->parts['select']  = array();
                $this->parts['join']    = array();
                $this->joins            = array();
                $this->tables           = array();
                $this->fetchModes       = array();
                $this->tableIndexes     = array();
                $this->tableAliases     = array();
                $this->aliasHandler->clear();

                $class = "Doctrine_Query_".ucwords($name);
                $parser = new $class($this);

                $parser->parse($args[0]);
            break;
            case 'where':
                if(isset($args[1])) {
                    if(is_array($args[1])) {
                        $this->params = $args[1];
                    } else {
                        $this->params = array($args[1]);
                    }
                }
            case 'having':
            case 'orderby':
            case 'groupby':
                if (empty($args[0])) {
                    return $this;
                }

                $class  = 'Doctrine_Query_' . ucwords($name);
                $parser = new $class($this);

                $this->parts[$name] = array($parser->parse($args[0]));
            break;
            case 'limit':
            case 'offset':
                if($args[0] == null) {
                    $args[0] = false;
                }

                $this->parts[$name] = $args[0];
            break;
            default:
                $this->parts[$name] = array();
                $this->$method($args[0]);

            throw new Doctrine_Query_Exception("Unknown overload method");
        }


        return $this;
    }
    /**
     * returns a query part
     *
     * @param $name         query part name
     * @return mixed
     */
    public function get($name)
    {
        if( ! isset($this->parts[$name]))
            return false;

        return $this->parts[$name];
    }
    /**
     * set
     * sets a query SET part
     * this method should only be used with UPDATE queries
     *
     * @param $name             name of the field
     * @param $value            field value
     * @return Doctrine_Query
     */
    public function set($name, $value)
    {
        $class = new Doctrine_Query_Set($this);
        $this->parts['set'][] = $class->parse($name . ' = ' . $value);

        return $this;
    }
    /**
     * @return boolean
     */
    public function isLimitSubqueryUsed() {
        return $this->limitSubqueryUsed;
    }
    /**
     * getQueryBase
     * returns the base of the generated sql query
     * On mysql driver special strategy has to be used for DELETE statements
     *
     * @return string       the base of the generated sql query
     */
    public function getQueryBase()
    {
        switch ($this->type) {
            case self::DELETE:
            /**
                no longer needed? 

                if ($this->conn->getName() == 'Mysql') {
                    $q = 'DELETE '  . end($this->tableAliases) . ' FROM ';
                } else {
            */
                    $q = 'DELETE FROM ';
            //    }
            break;
            case self::UPDATE:
                $q = 'UPDATE ';
            break;
            case self::SELECT:
                $distinct = ($this->isDistinct()) ? 'DISTINCT ' : '';

                $q = 'SELECT '.$distinct.implode(', ', $this->parts['select']).' FROM ';
            break;
        }
        return $q;
    }
    /**
     * builds the sql query from the given parameters and applies things such as
     * column aggregation inheritance and limit subqueries if needed
     *
     * @param array $params             an array of prepared statement params (needed only in mysql driver
     *                                  when limit subquery algorithm is used)
     * @return string                   the built sql query
     */
    public function getQuery($params = array())
    {
        if(empty($this->parts["select"]) || empty($this->parts["from"]))
            return false;

        $needsSubQuery = false;
        $subquery = '';
        $k  = array_keys($this->tables);
        $table = $this->tables[$k[0]];

        if( ! empty($this->parts['limit']) && $this->needsSubquery && $table->getAttribute(Doctrine::ATTR_QUERY_LIMIT) == Doctrine::LIMIT_RECORDS) {
            $needsSubQuery = true;
            $this->limitSubqueryUsed = true;
        }

        // process all pending SELECT part subqueries
        $this->processPendingSubqueries();

        // build the basic query

        $str = '';
        if($this->isDistinct())
            $str = 'DISTINCT ';

        $q = $this->getQueryBase();

        $q .= $this->parts['from'];
        /**
        var_dump($this->pendingFields);
        var_dump($this->subqueryAliases);  */
        //var_dump($this->parts['join']);

        foreach($this->parts['join'] as $parts) {
            foreach($parts as $part) {
                // preserve LEFT JOINs only if needed

                if(substr($part, 0,9) === 'LEFT JOIN') {
                    $e = explode(' ', $part);

                    $aliases = array_merge($this->subqueryAliases, 
                                array_keys($this->neededTables));


                    if( ! in_array($e[3], $aliases) &&
                        ! in_array($e[2], $aliases) &&

                        ! empty($this->pendingFields)) {
                        continue;
                    }

                }

                $e = explode(' ON ', $part);
                
                // we can always be sure that the first join condition exists
                $e2 = explode(' AND ', $e[1]);

                $part = $e[0] . ' ON '
                      . array_shift($e2);
                      
                if( ! empty($e2)) {
                    $parser = new Doctrine_Query_JoinCondition($this);
                    $part  .= ' AND ' . $parser->parse(implode(' AND ', $e2));
                }

                $q .= ' ' . $part;
            }
        }
        /**
        if( ! empty($this->parts['join'])) {
            foreach($this->parts['join'] as $part) {
                $q .= ' '.implode(' ', $part);
            }
        }
        */

        if( ! empty($this->parts['set'])) {
            $q .= ' SET ' . implode(', ', $this->parts['set']);
        }

        $string = $this->applyInheritance();

        if( ! empty($string))
            $this->parts['where'][] = '('.$string.')';



        $modifyLimit = true;
        if( ! empty($this->parts["limit"]) || ! empty($this->parts["offset"])) {

            if($needsSubQuery) {
                $subquery = $this->getLimitSubquery();


                switch(strtolower($this->conn->getName())) {
                    case 'mysql':
                        // mysql doesn't support LIMIT in subqueries
                        $params   = array_merge($this->params, $params);
                        $list     = $this->conn->execute($subquery, $params)->fetchAll(PDO::FETCH_COLUMN);
                        $subquery = implode(', ', $list);
                    break;
                    case 'pgsql':
                        // pgsql needs special nested LIMIT subquery
                        $subquery = 'SELECT doctrine_subquery_alias.' . $table->getIdentifier(). ' FROM (' . $subquery . ') AS doctrine_subquery_alias';
                    break;
                }

                $field    = $this->aliasHandler->getShortAlias($table->getTableName()) . '.' . $table->getIdentifier();

                // only append the subquery if it actually contains something
                if($subquery !== '')
                    array_unshift($this->parts['where'], $field. ' IN (' . $subquery . ')');

                $modifyLimit = false;
            }
        }

        $q .= ( ! empty($this->parts['where']))?   ' WHERE '    . implode(' AND ', $this->parts['where']):'';
        $q .= ( ! empty($this->parts['groupby']))? ' GROUP BY ' . implode(', ', $this->parts['groupby']):'';
        $q .= ( ! empty($this->parts['having']))?  ' HAVING '   . implode(' AND ', $this->parts['having']):'';
        $q .= ( ! empty($this->parts['orderby']))? ' ORDER BY ' . implode(', ', $this->parts['orderby']):'';

        if($modifyLimit)
            $q = $this->conn->modifyLimitQuery($q, $this->parts['limit'], $this->parts['offset']);

        // return to the previous state
        if( ! empty($string))
            array_pop($this->parts['where']);
        if($needsSubQuery)
            array_shift($this->parts['where']);

        return $q;
    }
    /**
     * this is method is used by the record limit algorithm
     *
     * when fetching one-to-many, many-to-many associated data with LIMIT clause
     * an additional subquery is needed for limiting the number of returned records instead
     * of limiting the number of sql result set rows
     *
     * @return string       the limit subquery
     */
    public function getLimitSubquery()
    {
        $k          = array_keys($this->tables);
        $table      = $this->tables[$k[0]];

        // get short alias
        $alias      = $this->aliasHandler->getShortAlias($table->getTableName());
        $primaryKey = $alias . '.' . $table->getIdentifier();

        // initialize the base of the subquery
        $subquery   = 'SELECT DISTINCT ' . $primaryKey;

        if($this->conn->getDBH()->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
            // pgsql needs the order by fields to be preserved in select clause

            foreach($this->parts['orderby'] as $part) {
                $e = explode(' ', $part);

                // don't add primarykey column (its already in the select clause)
                if($e[0] !== $primaryKey)
                    $subquery .= ', ' . $e[0];
            }
        }

        $subquery .= ' FROM ' . $this->conn->quoteIdentifier($table->getTableName()) . ' ' . $alias;

        foreach($this->parts['join'] as $parts) {
            foreach($parts as $part) {
                // preserve LEFT JOINs only if needed
                if(substr($part,0,9) === 'LEFT JOIN') {
                    $e = explode(' ', $part);

                    if( ! in_array($e[3], $this->subqueryAliases) &&
                        ! in_array($e[2], $this->subqueryAliases)) {
                        continue;
                    }

                }

                $subquery .= ' '.$part;
            }
        }

        // all conditions must be preserved in subquery
        $subquery .= ( ! empty($this->parts['where']))?   ' WHERE '    . implode(' AND ', $this->parts['where'])  : '';
        $subquery .= ( ! empty($this->parts['groupby']))? ' GROUP BY ' . implode(', ', $this->parts['groupby'])   : '';
        $subquery .= ( ! empty($this->parts['having']))?  ' HAVING '   . implode(' AND ', $this->parts['having']) : '';
        $subquery .= ( ! empty($this->parts['orderby']))? ' ORDER BY ' . implode(', ', $this->parts['orderby'])   : '';

        // add driver specific limit clause
        $subquery = $this->conn->modifyLimitQuery($subquery, $this->parts['limit'], $this->parts['offset']);

        $parts = self::quoteExplode($subquery, ' ', "'", "'");

        foreach($parts as $k => $part) {
            if(strpos($part, "'") !== false) {
                continue;
            }

            if($this->aliasHandler->hasAliasFor($part)) {
                $parts[$k] = $this->aliasHandler->generateNewAlias($part);
            }

            if(strpos($part, '.') !== false) {
                $e = explode('.', $part);

                $trimmed = ltrim($e[0], '( ');
                $pos     = strpos($e[0], $trimmed);

                $e[0] = substr($e[0], 0, $pos) . $this->aliasHandler->generateNewAlias($trimmed);
                $parts[$k] = implode('.', $e);
            }
        }
        $subquery = implode(' ', $parts);

        return $subquery;
    }
    /**
     * query the database with DQL (Doctrine Query Language)
     *
     * @param string $query                 DQL query
     * @param array $params                 parameters
     */
    public function query($query,$params = array())
    {
        $this->parseQuery($query);

        if($this->aggregate) {
            $keys  = array_keys($this->tables);
            $query = $this->getQuery();
            $stmt  = $this->tables[$keys[0]]->getConnection()->select($query, $this->parts["limit"], $this->parts["offset"]);
            $data  = $stmt->fetch(PDO::FETCH_ASSOC);
            if(count($data) == 1) {
                return current($data);
            } else {
                return $data;
            }
        } else {
            return $this->execute($params);
        }
    }
    /**
     * splitQuery
     * splits the given dql query into an array where keys
     * represent different query part names and values are
     * arrays splitted using sqlExplode method
     *
     * example:
     *
     * parameter:
     *      $query = "SELECT u.* FROM User u WHERE u.name LIKE ?"
     * returns:
     *      array('select' => array('u.*'),
     *            'from'   => array('User', 'u'),
     *            'where'  => array('u.name', 'LIKE', '?'))
     *
     * @param string $query                 DQL query
     * @throws Doctrine_Query_Exception     if some generic parsing error occurs
     * @return array                        an array containing the query string parts
     */
    public function splitQuery($query)
    {
        $e = self::sqlExplode($query, ' ');

        foreach($e as $k=>$part) {
            $part = trim($part);
            switch(strtolower($part)) {
                case 'delete':
                case 'update':
                case 'select':
                case 'set':
                case 'from':
                case 'where':
                case 'limit':
                case 'offset':
                case 'having':
                    $p = $part;
                    $parts[$part] = array();
                break;
                case 'order':
                case 'group':
                    $i = ($k + 1);
                    if(isset($e[$i]) && strtolower($e[$i]) === "by") {
                        $p = $part;
                        $parts[$part] = array();
                    } else
                        $parts[$p][] = $part;
                break;
                case "by":
                    continue;
                default:
                    if( ! isset($p))
                        throw new Doctrine_Query_Exception("Couldn't parse query.");

                    $parts[$p][] = $part;
            }
        }
        return $parts;
    }
    /**
     * DQL PARSER
     * parses a DQL query
     * first splits the query in parts and then uses individual
     * parsers for each part
     *
     * @param string $query                 DQL query
     * @param boolean $clear                whether or not to clear the aliases
     * @throws Doctrine_Query_Exception     if some generic parsing error occurs
     * @return Doctrine_Query
     */
    public function parseQuery($query, $clear = true)
    {
        if($clear)
            $this->clear();

        $query = trim($query);
        $query = str_replace("\n", ' ', $query);
        $query = str_replace("\r", ' ', $query);

        $parts = $this->splitQuery($query);

        foreach($parts as $k => $part) {
            $part = implode(' ', $part);
            switch(strtoupper($k)) {
                case 'CREATE':
                    $this->type = self::CREATE;
                break;
                case 'INSERT':
                    $this->type = self::INSERT;
                break;
                case 'DELETE':
                    $this->type = self::DELETE;
                break;
                case 'SELECT':
                    $this->type = self::SELECT;
                    $this->parseSelect($part);
                break;
                case 'UPDATE':
                    $this->type = self::UPDATE;
                    $k = 'FROM';

                case 'FROM':
                    $class  = 'Doctrine_Query_' . ucwords(strtolower($k));
                    $parser = new $class($this);
                    $parser->parse($part);
                break;
                case 'SET':
                    $class  = 'Doctrine_Query_' . ucwords(strtolower($k));
                    $parser = new $class($this);
                    $this->parts['set'][] = $parser->parse($part);
                break;
                case 'GROUP':
                case 'ORDER':
                    $k .= 'by';
                case 'WHERE':
                case 'HAVING':
                    $class  = 'Doctrine_Query_' . ucwords(strtolower($k));
                    $parser = new $class($this);

                    $name = strtolower($k);
                    $this->parts[$name][] = $parser->parse($part);
                break;
                case 'LIMIT':
                    $this->parts['limit'] = trim($part);
                break;
                case 'OFFSET':
                    $this->parts['offset'] = trim($part);
                break;
            }
        }

        return $this;
    }
    /**
     * DQL ORDER BY PARSER
     * parses the order by part of the query string
     *
     * @param string $str
     * @return void
     */
    final public function parseOrderBy($str)
    {
        $parser = new Doctrine_Query_Part_Orderby($this);
        return $parser->parse($str);
    }
    /**
     * returns Doctrine::FETCH_* constant
     *
     * @param string $mode
     * @return integer
     */
    final public function parseFetchMode($mode)
    {
        switch(strtolower($mode)):
            case "i":
            case "immediate":
                $fetchmode = Doctrine::FETCH_IMMEDIATE;
            break;
            case "b":
            case "batch":
                $fetchmode = Doctrine::FETCH_BATCH;
            break;
            case "l":
            case "lazy":
                $fetchmode = Doctrine::FETCH_LAZY;
            break;
            case "o":
            case "offset":
                $fetchmode = Doctrine::FETCH_OFFSET;
            break;
            case "lo":
            case "lazyoffset":
                $fetchmode = Doctrine::FETCH_LAZYOFFSET;
            default:
                throw new Doctrine_Query_Exception("Unknown fetchmode '$mode'. The availible fetchmodes are 'i', 'b' and 'l'.");
        endswitch;
        return $fetchmode;
    }
    /**
     * trims brackets
     *
     * @param string $str
     * @param string $e1        the first bracket, usually '('
     * @param string $e2        the second bracket, usually ')'
     */
    public static function bracketTrim($str,$e1 = '(',$e2 = ')')
    {
        if(substr($str,0,1) == $e1 && substr($str,-1) == $e2)
            return substr($str,1,-1);
        else
            return $str;
    }
    /**
     * bracketExplode
     *
     * example:
     *
     * parameters:
     *      $str = (age < 20 AND age > 18) AND email LIKE 'John@example.com'
     *      $d = ' AND '
     *      $e1 = '('
     *      $e2 = ')'
     *
     * would return an array:
     *      array("(age < 20 AND age > 18)",
     *            "email LIKE 'John@example.com'")
     *
     * @param string $str
     * @param string $d         the delimeter which explodes the string
     * @param string $e1        the first bracket, usually '('
     * @param string $e2        the second bracket, usually ')'
     *
     */
    public static function bracketExplode($str, $d = ' ', $e1 = '(', $e2 = ')')
    {
        if(is_array($d)) {
            $a = preg_split('/('.implode('|', $d).')/', $str);
            $d = stripslashes($d[0]);
        } else
            $a = explode("$d",$str);

        $i = 0;
        $term = array();
        foreach($a as $key=>$val) {
            if (empty($term[$i])) {
                $term[$i] = trim($val);
                $s1 = substr_count($term[$i], "$e1");
                $s2 = substr_count($term[$i], "$e2");
                    if($s1 == $s2) $i++;
            } else {
                $term[$i] .= "$d".trim($val);
                $c1 = substr_count($term[$i], "$e1");
                $c2 = substr_count($term[$i], "$e2");
                    if($c1 == $c2) $i++;
            }
        }
        return $term;
    }
    /**
     * quoteExplode
     *
     * example:
     *
     * parameters:
     *      $str = email LIKE 'John@example.com'
     *      $d = ' AND '
     *
     * would return an array:
     *      array("email", "LIKE", "'John@example.com'")
     *
     * @param string $str
     * @param string $d         the delimeter which explodes the string
     */
    public static function quoteExplode($str, $d = ' ')
    {
        if(is_array($d)) {
            $a = preg_split('/('.implode('|', $d).')/', $str);
            $d = stripslashes($d[0]);
        } else
            $a = explode("$d",$str);

        $i = 0;
        $term = array();
        foreach($a as $key => $val) {
            if (empty($term[$i])) {
                $term[$i] = trim($val);

                if( ! (substr_count($term[$i], "'") & 1))
                    $i++;
            } else {
                $term[$i] .= "$d".trim($val);

                if( ! (substr_count($term[$i], "'") & 1))
                    $i++;
            }
        }
        return $term;
    }
    /**
     * sqlExplode
     *
     * explodes a string into array using custom brackets and
     * quote delimeters
     *
     *
     * example:
     *
     * parameters:
     *      $str = "(age < 20 AND age > 18) AND name LIKE 'John Doe'"
     *      $d   = ' '
     *      $e1  = '('
     *      $e2  = ')'
     *
     * would return an array:
     *      array('(age < 20 AND age > 18)',
     *            'name',
     *            'LIKE',
     *            'John Doe')
     *
     * @param string $str
     * @param string $d         the delimeter which explodes the string
     * @param string $e1        the first bracket, usually '('
     * @param string $e2        the second bracket, usually ')'
     *
     * @return array
     */
    public static function sqlExplode($str, $d = ' ', $e1 = '(', $e2 = ')')
    {
        if(is_array($d)) {
            $str = preg_split('/('.implode('|', $d).')/', $str);
            $d = stripslashes($d[0]);
        } else
            $str = explode("$d",$str);

        $i = 0;
        $term = array();
        foreach($str as $key => $val) {
            if (empty($term[$i])) {
                $term[$i] = trim($val);

                $s1 = substr_count($term[$i],"$e1");
                $s2 = substr_count($term[$i],"$e2");

                if (substr($term[$i],0,1) == "(") {
                    if($s1 == $s2) {
                        $i++;
                    }
                } else {
                    if ( ! (substr_count($term[$i], "'") & 1) &&
                         ! (substr_count($term[$i], "\"") & 1) &&
                         ! (substr_count($term[$i], "�") & 1)
                       ) { $i++; }
                }
            } else {
                $term[$i] .= "$d".trim($val);
                $c1 = substr_count($term[$i],"$e1");
                $c2 = substr_count($term[$i],"$e2");

                if(substr($term[$i],0,1) == "(") {
                    if($c1 == $c2) {
                        $i++;
                    }
                } else {
                    if ( ! (substr_count($term[$i], "'") & 1) &&
                         ! (substr_count($term[$i], "\"") & 1) &&
                         ! (substr_count($term[$i], "�") & 1)
                       ) { $i++; }
                }
            }
        }
        return $term;
    }
    /**
     * generateAlias
     *
     * @param string $tableName
     * @return string
     */
    public function generateAlias($tableName)
    {
        if(isset($this->tableIndexes[$tableName])) {
            return $tableName.++$this->tableIndexes[$tableName];
        } else {
            $this->tableIndexes[$tableName] = 1;
            return $tableName;
        }
    }

    /**
     * loads a component
     *
     * @param string $path              the path of the loadable component
     * @param integer $fetchmode        optional fetchmode, if not set the components default fetchmode will be used
     * @throws Doctrine_Query_Exception
     * @return Doctrine_Table
     */
    final public function load($path, $loadFields = true)
    {
                
        // parse custom join conditions
        $e = explode(' ON ', $path);
        
        $joinCondition = '';
        if(count($e) > 1) {
            $joinCondition = ' AND ' . $e[1];
            $path = $e[0];
        }

        $tmp            = explode(' ',$path);
        $componentAlias = (count($tmp) > 1) ? end($tmp) : false;

        $e = preg_split("/[.:]/", $tmp[0], -1);


        if(isset($this->compAliases[$e[0]])) {
            $end      = substr($tmp[0], strlen($e[0]));
            $path     = $this->compAliases[$e[0]] . $end;
            $e        = preg_split("/[.:]/", $path, -1);
        } else {
            $path     = $tmp[0];
        }



        $index = 0;
        $currPath = '';
        $this->tableStack = array();

        foreach($e as $key => $fullname) {
            try {
                $e2    = preg_split("/[-(]/",$fullname);
                $name  = $e2[0];

                $currPath .= '.' . $name;

                if($key == 0) {
                    $currPath = substr($currPath,1);

                    $this->conn = Doctrine_Manager::getInstance()
                                  ->getConnectionForComponent($name);

                    $table = $this->conn->getTable($name);


                    $tname = $this->aliasHandler->getShortAlias($table->getTableName());

                    if( ! isset($this->tableAliases[$currPath])) {
                        $this->tableIndexes[$tname] = 1;
                    }  

                    $this->parts['from'] = $this->conn->quoteIdentifier($table->getTableName());
                    
                    if ($this->type === self::SELECT) {
                         $this->parts['from'] .= ' ' . $tname;
                    }

                    $this->tableAliases[$currPath] = $tname;

                    $tableName = $tname;
                } else {

                    $index += strlen($e[($key - 1)]) + 1;
                    // the mark here is either '.' or ':'
                    $mark  = substr($path, ($index - 1), 1);

                    if(isset($this->tableAliases[$prevPath])) {
                        $tname = $this->tableAliases[$prevPath];
                    } else {
                        $tname = $this->aliasHandler->getShortAlias($table->getTableName());
                    }

                    $fk       = $table->getRelation($name);
                    $name     = $fk->getTable()->getComponentName();
                    $original = $fk->getTable()->getTableName();



                    if (isset($this->tableAliases[$currPath])) {
                        $tname2 = $this->tableAliases[$currPath];
                    } else {
                        $tname2 = $this->aliasHandler->generateShortAlias($original);
                    }

                    $aliasString = $this->conn->quoteIdentifier($original) . ' ' . $tname2;

                    switch ($mark) {
                        case ':':
                            $join = 'INNER JOIN ';
                        break;
                        case '.':
                            $join = 'LEFT JOIN ';
                        break;
                        default:
                            throw new Doctrine_Query_Exception("Unknown operator '$mark'");
                    }

                    if( ! $fk->isOneToOne()) {
                       $this->needsSubquery = true;
                    }


                    $map = $fk->getTable()->inheritanceMap;

                    if( ! $loadFields || ! empty($map) || $joinCondition) {
                        $this->subqueryAliases[] = $tname2;
                    }


                    if ($fk instanceof Doctrine_Relation_Association) {
                        $asf = $fk->getAssociationFactory();

                        $assocTableName = $asf->getTableName();

                        if( ! $loadFields || ! empty($map) || $joinCondition) {
                            $this->subqueryAliases[] = $assocTableName;
                        }
                        
                        $assocPath = $prevPath . '.' . $asf->getComponentName();

                        if (isset($this->tableAliases[$assocPath])) {
                            $assocAlias = $this->tableAliases[$assocPath];
                        } else {
                            $assocAlias = $this->aliasHandler->generateShortAlias($assocTableName);
                        }

                        $this->parts['join'][$tname][$assocTableName] = $join . $assocTableName . ' ' . $assocAlias . ' ON ' . $tname  . '.'
                                                                      . $table->getIdentifier() . ' = '
                                                                      . $assocAlias . '.' . $fk->getLocal();
                                                                      
                        if ($fk instanceof Doctrine_Relation_Association_Self) {
                            $this->parts['join'][$tname][$assocTableName] .= ' OR ' . $tname  . '.' . $table->getIdentifier() . ' = '
                                                                           . $assocAlias . '.' . $fk->getForeign();
                        }

                        $this->parts['join'][$tname][$tname2]         = $join . $aliasString    . ' ON ' . $tname2 . '.'
                                                                      . $fk->getTable()->getIdentifier() . ' = '
                                                                      . $assocAlias . '.' . $fk->getForeign()
                                                                      . $joinCondition;

                        if ($fk instanceof Doctrine_Relation_Association_Self) {
                            $this->parts['join'][$tname][$tname2] .= ' OR ' . $tname2  . '.' . $table->getIdentifier() . ' = '
                                                                           . $assocAlias . '.' . $fk->getLocal();
                        }

                    } else {
                        $this->parts['join'][$tname][$tname2]         = $join . $aliasString
                                                                      . ' ON ' . $tname .  '.'
                                                                      . $fk->getLocal() . ' = ' . $tname2 . '.' . $fk->getForeign()
                                                                      . $joinCondition;
                    }


                    $this->joins[$tname2] = $prevTable;


                    $table = $fk->getTable();

                    $this->tableAliases[$currPath] = $tname2;

                    $tableName = $tname2;

                    $this->relationStack[] = $fk;
                }

                $this->components[$currPath] = $table;

                $this->tableStack[] = $table;

                if( ! isset($this->tables[$tableName])) {
                    $this->tables[$tableName] = $table;

                    if ($loadFields) {

                        $skip = false;

                        if ( ! empty($this->pendingFields) ||
                             ! empty($this->pendingAggregates)) {
                            $skip = true;
                        }

                        if ($componentAlias) {
                            $this->compAliases[$componentAlias] = $currPath;

                            if(isset($this->pendingFields[$componentAlias])) {
                                $this->processPendingFields($componentAlias);
                                $skip = true;
                            }
                            if(isset($this->pendingAggregates[$componentAlias]) ||
                              (current($this->tables) === $table && isset($this->pendingAggregates[0]))
                               ) {
                                $this->processPendingAggregates($componentAlias);
                                $skip = true;
                            }
                        }

                        if ( ! $skip) {
                            $this->parseFields($fullname, $tableName, $e2, $currPath);
                        }
                    }
                }


                $prevPath  = $currPath;
                $prevTable = $tableName;
            } catch(Exception $e) {
                throw new Doctrine_Query_Exception($e->__toString());
            }
        }

        if($componentAlias !== false) {
            $this->compAliases[$componentAlias] = $currPath;
        }

        return $table;
    }
    /**
     * parseFields
     *
     * @param string $fullName
     * @param string $tableName
     * @param array $exploded
     * @param string $currPath
     * @return void
     */
    final public function parseFields($fullName, $tableName, array $exploded, $currPath)
    {
        $table = $this->tables[$tableName];

        $fields = array();

        if(strpos($fullName, '-') === false) {
            $fetchmode = $table->getAttribute(Doctrine::ATTR_FETCHMODE);

            if(isset($exploded[1])) {
                if(count($exploded) > 2) {
                    $fields = $this->parseAggregateValues($fullName, $tableName, $exploded, $currPath);
                } elseif(count($exploded) == 2) {
                    $fields = explode(',',substr($exploded[1],0,-1));
                }
            }
        } else {
            if(isset($exploded[1])) {
                $fetchmode = $this->parseFetchMode($exploded[1]);
            } else
                $fetchmode = $table->getAttribute(Doctrine::ATTR_FETCHMODE);

            if(isset($exploded[2])) {
                if(substr_count($exploded[2], ')') > 1) {

                } else {
                    $fields = explode(',', substr($exploded[2],0,-1));
                }
            }

        }
        if( ! $this->aggregate)
            $this->loadFields($table, $fetchmode, $fields, $currPath);
    }
    /**
     * parseAggregateFunction
     *
     * @param string $func
     * @param string $reference
     * @return string
     */
    public function parseAggregateFunction($func,$reference)
    {
        $pos = strpos($func, '(');

        if($pos !== false) {
            $funcs  = array();

            $name   = substr($func, 0, $pos);
            $func   = substr($func, ($pos + 1), -1);
            $params = Doctrine_Query::bracketExplode($func, ',', '(', ')');

            foreach($params as $k => $param) {
                $params[$k] = $this->parseAggregateFunction($param,$reference);
            }

            $funcs = $name . '(' . implode(', ', $params). ')';

            return $funcs;

        } else {
            if( ! is_numeric($func)) {

                $func = $this->getTableAlias($reference).'.'.$func;

                return $func;
            } else {

                return $func;
            }
        }
    }
    /**
     * parseAggregateValues
     */
    public function parseAggregateValues($fullName, $tableName, array $exploded, $currPath)
    {
        $this->aggregate = true;
        $pos    = strpos($fullName, '(');
        $name   = substr($fullName, 0, $pos);
        $string = substr($fullName, ($pos + 1), -1);

        $exploded     = Doctrine_Query::bracketExplode($string, ',');
        foreach($exploded as $k => $value) {
            $func         = $this->parseAggregateFunction($value, $currPath);
            $exploded[$k] = $func;

            $this->parts['select'][] = $exploded[$k];
        }
    }
}

