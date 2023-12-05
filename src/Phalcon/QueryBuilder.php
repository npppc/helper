<?php
namespace Npc\Helper\Phalcon;

use Phalcon\Di;
use Phalcon\Http\Request;
use \Phalcon\Mvc\Model\Query\BuilderInterface;
use \Phalcon\Paginator\Adapter\QueryBuilder as Paginator;
use \Exception;

/**
 * Class QueryBuilder
 * @package Npc\Helper
 * @property Request $request
 */
class QueryBuilder
{
    public $request = null;
    public $builder = null;

    private $sortField = 'sort';
    private $orderField = 'order';
    private $defaultOrderBy = 'a.id desc';
    private $sortedFields = [];
    private $orderMapper = [
        'asc' => 'ASC',
        'ASC' => 'ASC',
        'desc' => 'DESC',
        'DESC' => 'DESC',
    ];
    private $rowsFiled = 'rows';
    private $pageField = 'page';

    private $search = [];

    public function __construct(BuilderInterface $builder)
    {
        $this->builder = $builder;
        $this->request = Di::getDefault()->getService('request')->resolve();
    }

    /**
     * 设置排序字段
     * @param string $sortField
     * @return $this
     */
    public function setSortField($sortField = '')
    {
        $this->sortField = $sortField;
        return $this;
    }

    /**
     * 设置排序顺序
     * @param string $orderField
     * @return $this
     */
    public function setOrderField($orderField = '')
    {
        $this->orderField = $orderField;
        return $this;
    }

    /**
     * 设置返回条数
     * @param string $rowsField
     * @return $this
     */
    public function setRowsField($rowsField = '')
    {
        $this->rowsFiled = $rowsField;
        return $this;
    }

    /**
     * 设置页数
     * @param string $pageField
     * @return $this
     */
    public function setPageField($pageField = '')
    {
        $this->pageField = $pageField;
        return $this;
    }
    /**
     * @param array $sortedFields = ['id' => 'a.id','name' => 'b.fieldName']
     * @return $this
     */
    public function setSortedFields($sortedFields = [])
    {
        $this->sortedFields = $sortedFields;
        return $this;
    }

    public function setDefaultOrderBy($defaultOrderBy = '')
    {
        $this->defaultOrderBy = $defaultOrderBy;
        return $this;
    }

    /**
     * [
     *  'id' => 'a.id,
     *  'ids' => ['a.id','in'], #1,2,3,4
     *  'find_in_set' => ['a.id','set']
     *  'like' => ['b.name','like'],
     *  'date_start' => ['a.create_time','>=','date'], #2020-01-01 2020-01-31[ 23:59:59]
     *  'date_end' => ['a.create_time', '<','date'],
     *  'datetime_start' => ['a.create_time','>=','datetime'], #2020-01-01 00:00:00 2020-01-31 23:59:59
     *  'datetime_end' => ['a.create_time', '<','datetime'],
     *  'name' => 'b.name'
     * ]
     * @param array $search
     * @return $this
     */
    public function setSearch($search = [])
    {
        $this->search = $search;
        return $this;
    }

    /**
     * 获取查询助手
     * @return BuilderInterface|null
     * @throws Exception
     */
    public function parse()
    {
        $sort = (isset($this->sortedFields[$this->request->get($this->sortField)]) ? $this->sortedFields[$this->request->get($this->sortField)] : $this->request->get($this->sortField) );
        $order = (isset($this->orderMapper[$this->request->get($this->orderField)]) ? $this->orderMapper[$this->request->get($this->orderField)] : 'desc');
        if($sort)
        {
            $this->builder->orderBy(stripos($sort,'%s') !== false ?  sprintf($sort,$order) : $sort.' '.$order );
        }
        else
        {
            $this->builder->orderBy($this->defaultOrderBy);
        }

        foreach($this->search as $name => $fields) {
            if(!is_string($name))
            {
                if(is_string($fields))
                {
                    //尝试从字段定义中获取参数名称
                    $name = $this->getFieldName($fields);
                    $field = $fields;
                    $val = $this->request->get($name,'trim');
                    if(isset($val) && $val != '')
                    {
                        $this->builder->andWhere($field.' = :'.$name.':', [$name => $val]);
                    }
                }
                else
                {
                    throw new Exception('搜索定义错误！');
                }
            }
            else
            {
                if(is_string($fields))
                {
                    $val = $this->request->get($name,'trim');
                    if(isset($val) && $val != '')
                    {
                        $this->builder->andWhere($fields.' = :'.$name.':', [$name => $val]);
                    }
                }
                else if(is_array($fields))
                {
                    $field = $fields[0] ?? '';
                    $op = $fields[1] ?? '';
                    $format = $fields[2] ?? '';
                    $default = $fields[3] ?? '';

                    $val = $this->request->get($name,'trim',$default);
                    if(isset($val) && $val !== '')
                    {
                        if($op == 'like')
                        {
                            $this->builder->andWhere($field.' '.$op.' :'.$name.':', [$name => '%'.$val.'%']);
                        }
                        else if($op == 'in')
                        {
                            $this->builder->inWhere($field,self::explode($val));
                        }
                        else if($op == 'not_in')
                        {
                            $this->builder->andWhere($field.' not in ('.$val.')');
                        }
                        else if($op == 'set')
                        {
                            //$this->builder->andWhere('FIND_IN_SET(:'.$name.':,'.$field.')', [$name => self::explodeImplode($val)]);

                            $vals = explode(',',$val);
                            $condition = [];
                            foreach($vals as $val)
                            {
                                //存在缺陷 用户名代"
                                $condition[] = 'FIND_IN_SET("'.$val.'",'.$field.')';
                            }
                            $this->builder->andWhere(implode(' OR ',$condition));
                        }
                        else if($format == 'date')
                        {
                            if($op == '<=') {
                                $val .= ' 23:59:59';
                            }
                            $this->builder->andWhere('UNIX_TIMESTAMP('.$field.') '.$op.' :'.$name.':', [$name => strtotime($val) ]);
                        }
                        else if($format == 'datetime')
                        {
                            $this->builder->andWhere('UNIX_TIMESTAMP('.$field.') '.$op.' :'.$name.':', [$name => strtotime($val) ]);
                        }
                        else
                        {
                            $this->builder->andWhere($field.' '.$op.' :'.$name.':', [$name => $val]);
                        }
                    }
                }
                else
                {
                    throw new Exception('搜索定义错误！');
                }
            }
        }

        if($this->request->get('Debug') == 1)
        {
            var_dump($this->builder->getPhql());
            exit();
        }

        return $this->builder;
    }

    /**
     * 获取查询助手
     * @return BuilderInterface|null
     * @throws Exception
     */
    public function getBuilder()
    {
        return $this->parse();
    }

    /**
     * 获取分页助手
     * @param int $defaultRows
     * @param int $defaultPage
     * @return Paginator
     * @throws Exception
     */
    public function getPaginator($defaultRows = 10 , $defaultPage = 1)
    {
        $this->parse();
        return new Paginator([
            'builder' => $this->builder,
            'limit' => $this->request->get($this->rowsFiled, 'int', $defaultRows),
            'page' => $this->request->get($this->pageField, 'int', $defaultPage),
        ]);
    }

    private function getFieldName($field = '')
    {
        if(stripos($field,'.') !== false)
        {
            list($alias , $name) = explode('.',$field);
            return $name;
        }
        return $field;
    }

    public function explodeImplode($str = '')
    {
        $a = explode(',',$str);
        $r = [];
        foreach($a as $v)
        {
            $v = trim($v);
            $v !== ''  && $r[$v] = $v;
        }
        return implode(',',array_values($r));
    }

    public function explode($str = '')
    {
        $a = explode(',',$str);
        $r = [];
        foreach($a as $v)
        {
            $v = trim($v);
            $v !== '' && $r[$v] = $v;
        }
        return $r;
    }

    /**
     * @param string $model
     * @param string|null $alias
     * @return $this
     */
    public function addFrom(string $model, string $alias = null)
    {
        $this->builder->addFrom($model, $alias);
        return $this;
    }

    /**
     * @param string $conditions
     * @param array $bindParams
     * @param array $bindTypes
     * @return $this
     */
    public function andWhere(string $conditions, array $bindParams = array(), array $bindTypes = array())
    {
        $this->builder->andWhere($conditions, $bindParams,$bindTypes);
        return $this;
    }

    /**
     * @param string $expr
     * @param $minimum
     * @param $maximum
     * @param string $operator
     * @return $this
     */
    public function betweenWhere(string $expr, $minimum, $maximum, string $operator = BuilderInterface::OPERATOR_AND)
    {
        $this->builder->betweenWhere($expr, $minimum,$maximum,$operator);
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function columns($columns)
    {
        $this->builder->columns($columns);
        return $this;
    }

    /**
     * Sets SELECT DISTINCT / SELECT ALL flag
     *
     * ```php
     * $builder->distinct("status");
     * $builder->distinct(null);
     * ```
     *
     * @param mixed $distinct
     * @return $this
     */
    public function distinct($distinct)
    {
        $this->builder->distinct($distinct);
        return $this;
    }

    /**
     * Sets a FOR UPDATE clause
     *
     * ```php
     * $builder->forUpdate(true);
     * ```
     *
     * @param bool $forUpdate
     * @return $this
     */
    public function forUpdate(bool $forUpdate)
    {
        $this->builder->forUpdate($forUpdate);
        return $this;
    }

    /**
     * Sets the models who makes part of the query
     *
     * @param string|array $models
     * @return $this
     */
    public function from($models)
    {
        $this->builder->from($models);
        return $this;
    }

    /**
     * Returns default bind params
     *
     * @return array
     */
    public function getBindParams(): array
    {
        return $this->builder->getBindParams();
    }

    /**
     * Returns default bind types
     *
     * @return array
     */
    public function getBindTypes(): array
    {
        return $this->builder->getBindTypes();
    }

    /**
     * Return the columns to be queried
     *
     * @return string|array
     */
    public function getColumns()
    {
        return $this->builder->getColumns();
    }

    /**
     * Returns SELECT DISTINCT / SELECT ALL flag
     *
     * @return bool
     */
    public function getDistinct(): bool
    {
        return $this->builder->getDistinct();
    }

    /**
     * Return the models who makes part of the query
     *
     * @return string|array
     */
    public function getFrom()
    {
        return $this->builder->getFrom();
    }

    /**
     * Returns the GROUP BY clause
     *
     * @return array
     */
    public function getGroupBy(): array
    {
        return $this->builder->getGroupBy();
    }

    /**
     * Returns the HAVING condition clause
     *
     * @return string
     */
    public function getHaving(): string
    {
        return $this->builder->getHaving();
    }

    /**
     * Return join parts of the query
     *
     * @return array
     */
    public function getJoins(): array
    {
        return $this->builder->getJoins();
    }

    /**
     * Returns the current LIMIT clause
     *
     * @return string|array
     */
    public function getLimit()
    {
        return $this->builder->getLimit();
    }

    /**
     * Returns the current OFFSET clause
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->builder->getOffset();
    }

    /**
     * Return the set ORDER BY clause
     *
     * @return string|array
     */
    public function getOrderBy()
    {
        return $this->builder->getOrderBy();
    }

    /**
     * Returns a PHQL statement built based on the builder parameters
     *
     * @return string
     */
    public function getPhql(): string
    {
        return $this->builder->getPhql();
    }

    /**
     * Returns the query built
     *
     * @return $this
     */
    public function getQuery()
    {
        $this->builder->getQuery();
        return $this;
    }

    /**
     * Return the conditions for the query
     *
     * @return string|array
     */
    public function getWhere()
    {
        return $this->builder->getWhere();
    }

    /**
     * Sets a GROUP BY clause
     *
     * @param string|array $group
     * @return $this
     */
    public function groupBy($group)
    {
        $this->builder->groupBy($group);
        return $this;
    }

    /**
     * Sets a HAVING condition clause
     *
     * @param string $having
     * @return $this
     */
    public function having(string $having)
    {
        $this->builder->having($having);
        return $this;
    }

    /**
     * Adds an INNER join to the query
     *
     * @param string $model
     * @param string $conditions
     * @param string $alias
     * @return $this
     */
    public function innerJoin(string $model, string $conditions = null, string $alias = null)
    {
        $this->builder->innerJoin($model,$conditions,$alias);
        return $this;
    }

    /**
     * Appends an IN condition to the current conditions
     *
     * @param string $expr
     * @param array $values
     * @param string $operator
     * @return $this
     */
    public function inWhere(string $expr, array $values, string $operator = BuilderInterface::OPERATOR_AND)
    {
        $this->builder->inWhere($expr,$values,$operator);
        return $this;
    }

    /**
     * Adds an :type: join (by default type - INNER) to the query
     *
     * @param string $model
     * @param string $conditions
     * @param string $alias
     * @return $this
     */
    public function join(string $model, string $conditions = null, string $alias = null)
    {
        $this->builder->join($model,$conditions,$alias);
        return $this;
    }

    /**
     * Adds a LEFT join to the query
     *
     * @param string $model
     * @param string $conditions
     * @param string $alias
     * @return $this
     */
    public function leftJoin(string $model, string $conditions = null, string $alias = null)
    {
        $this->builder->leftJoin($model,$conditions,$alias);
        return $this;
    }

    /**
     * Sets a LIMIT clause
     *
     * @param int $offset
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit, $offset = null)
    {
        $this->builder->limit($limit,$offset);
        return $this;
    }

    /**
     * Returns the models involved in the query
     *
     * @return string|array|null
     */
    public function getModels()
    {
        return $this->builder->getModels();
    }

    /**
     * Appends a NOT BETWEEN condition to the current conditions
     *
     * @param mixed $minimum
     * @param mixed $maximum
     * @param string $expr
     * @param string $operator
     * @return $this
     */
    public function notBetweenWhere(string $expr, $minimum, $maximum, string $operator = BuilderInterface::OPERATOR_AND)
    {
        $this->builder->notBetweenWhere($expr,$minimum,$maximum,$operator);
        return $this;
    }

    /**
     * Appends a NOT IN condition to the current conditions
     *
     * @param string $expr
     * @param array $values
     * @param string $operator
     * @return $this
     */
    public function notInWhere(string $expr, array $values, string $operator = BuilderInterface::OPERATOR_AND)
    {
        $this->builder->notInWhere($expr,$values,$operator);
        return $this;
    }

    /**
     * Sets an OFFSET clause
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset)
    {
        $this->builder->offset($offset);
        return $this;
    }

    /**
     * Sets an ORDER BY condition clause
     *
     * @param string $orderBy
     * @return $this
     */
    public function orderBy(string $orderBy)
    {
        $this->builder->orderBy($orderBy);
        return $this;
    }

    /**
     * Appends a condition to the current conditions using an OR operator
     *
     * @param string $conditions
     * @param array $bindParams
     * @param array $bindTypes
     * @return $this
     */
    public function orWhere(string $conditions, array $bindParams = array(), array $bindTypes = array())
    {
        $this->builder->orWhere($conditions,$bindParams,$bindTypes);
        return $this;
    }

    /**
     * Adds a RIGHT join to the query
     *
     * @param string $model
     * @param string $conditions
     * @param string $alias
     * @return $this
     */
    public function rightJoin(string $model, string $conditions = null, string $alias = null)
    {
        $this->builder->rightJoin($model,$conditions,$alias);
        return $this;
    }

    /**
     * Set default bind parameters
     *
     * @param array $bindParams
     * @param bool $merge
     * @return $this
     */
    public function setBindParams(array $bindParams, bool $merge = false)
    {
        $this->builder->setBindParams($bindParams,$merge);
        return $this;
    }

    /**
     * Set default bind types
     *
     * @param array $bindTypes
     * @param bool $merge
     * @return $this
     */
    public function setBindTypes(array $bindTypes, bool $merge = false)
    {
        $this->builder->setBindTypes($bindTypes,$merge);
        return $this;
    }

    /**
     * Sets conditions for the query
     *
     * @param string $conditions
     * @param array $bindParams
     * @param array $bindTypes
     * @return $this
     */
    public function where(string $conditions, array $bindParams = array(), array $bindTypes = array())
    {
        $this->builder->where($conditions,$bindParams,$bindTypes);
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}