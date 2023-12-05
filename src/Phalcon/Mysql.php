<?php


namespace Npc\Helper\Phalcon;

use Throwable;

class Mysql extends \Phalcon\Db\Adapter\Pdo\Mysql
{
    public function showDatabases()
    {
        return $this->fetchAll('show databases');
    }

    /**
     * 获取表
     * @param string $TABLE_SCHEMA
     * @return array
     */
    public function showTables($TABLE_SCHEMA = '')
    {
        return $this->fetchAll('select TABLE_NAME,TABLE_COMMENT from information_schema.TABLES where TABLE_SCHEMA = ' . $this->escapeString($TABLE_SCHEMA));

        $tables = $this->fetchAll('SELECT TABLE_NAME,TABLE_COMMENT from information_schema.TABLES where TABLE_SCHEMA = ' . $this->escapeString($TABLE_SCHEMA));
        if (!$tables) {
            $rows = $this->fetchAll('show tables from ' . $this->escapeIdentifier($database));

            foreach ($rows as $row) {
                $tables[] = [
                    'TABLE_NAME' => $row['Tables_in_' . $database],
                ];
            }
        }
    }

    /**
     * 获取DDL
     * @param string $TABLE_SCHEMA
     * @return mixed
     */
    public function showCreate($TABLE_SCHEMA = '')
    {
        try {
            return $this->query('show create table ' . $this->escapeIdentifier($TABLE_SCHEMA))->fetch()['Create Table'];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 获取索引
     *
     * @param string $TABLE_SCHEMA
     * @return array
     */
    public function showIndex($TABLE_SCHEMA = '')
    {
        try {
            return $this->fetchAll('show index from ' . $this->escapeIdentifier($TABLE_SCHEMA) . '');
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 解析 索引
     * @param string $table
     * @return array
     */
    public function showTableIndex($table = '')
    {
        $index = [];
        $query = $this->showIndex($table);
        if ($query) {
            foreach ($query as $k => $v) {
                if ($v['Key_name'] == 'PRIMARY') {
                    $index['PRIMARY KEY `' . $v['Column_name'] . '`'] = $v;
                } else if ($v['Non_unique'] == 0) {

                    $index['UNIQUE KEY `' . $v['Key_name'] . '`'] = $v;
                } else {
                    $index['KEY `' . $v['Key_name'] . '`'] = $v;
                }
            }
        }
        return $index;
    }

    /**
     * 获取字段
     * @param string $TABLE_SCHEMA
     * @return array
     */
    public function showFullFields($TABLE_SCHEMA = '')
    {
        try {
            $fields = $this->fetchAll('show full fields from ' . $this->escapeIdentifier($TABLE_SCHEMA) . '');

            foreach ($fields as $key => $val) {
                list($type, $value) = explode('(', $val['Type']);

                $fields[$key] = $val;
                $fields[$key]['ID'] = $val['Field'];
                $fields[$key]['Type'] = $type;
                $fields[$key]['Value'] = $value ? str_replace(array('(', ')'), '', $value) : '';
                //这里有个BUG 没有PRI 的时候 mysql 会把 UNI 显示为 PRI
                $fields[$key]['Index'] = $val['Key'] == 'PRI' ? '主键' : ($val['Key'] == 'UNI' ? '唯一' : ($val['Key'] == 'MUL' ? '索引' : ''));
                $fields[$key]['A_I'] = stripos($val['Extra'], 'auto_increment') !== false ? '是' : '否';
                $fields[$key]['Null'] = $val['Null'] == 'YES' ? '是' : '否';
                $fields[$key]['Collation'] = $val['Collation'] == 'utf8_general_ci' ? 'utf8' : '';
            }

            return $fields;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 获取字段
     * @param string $TABLE_SCHEMA
     * @return array
     */
    public function showFullFieldsAssociate($TABLE_SCHEMA = '')
    {
        try {
            $fields = $this->fetchAll('show full fields from ' . $this->escapeIdentifier($TABLE_SCHEMA) . '');

            $return = [];
            foreach ($fields as $key => $val) {
                @list($type, $value) = explode('(', $val['Type']);

                $return[$val['Field']] = $val;
                $return[$val['Field']]['ID'] = $val['Field'];
                $return[$val['Field']]['Type'] = $type;
                $return[$val['Field']]['Value'] = $value ? str_replace(array('(', ')'), '', $value) : '';
                //这里有个BUG 没有PRI 的时候 mysql 会把 UNI 显示为 PRI
                $return[$val['Field']]['Index'] = $val['Key'] == 'PRI' ? '主键' : ($val['Key'] == 'UNI' ? '唯一' : ($val['Key'] == 'MUL' ? '索引' : ''));
                $return[$val['Field']]['A_I'] = stripos($val['Extra'], 'auto_increment') !== false ? '是' : '否';
                $return[$val['Field']]['Null'] = $val['Null'] == 'YES' ? '是' : '否';
                $return[$val['Field']]['Collation'] = $val['Collation'] == 'utf8_general_ci' ? 'utf8' : '';

                //尝试从备注中提取结构定义
                @list($comment,$extra) = explode(' ',$val['Comment']);
                $return[$val['Field']]['comment'] = $comment;
                $return[$val['Field']]['comment_extra'] = substr($val['Comment'],strlen($comment)+1);
                $code = null;
                preg_match_all('#.*?code=([^\s]*)#is', $val['Comment'], $matches);
                if ($matches[1]) {
                    $code = $matches[1][0];
                }
                if (substr($val['Field'], 0, 3) == 'is_' || substr($val['Field'], 0, 5) == 'with_' || stripos($val['Comment'], '是否') !== false) {
                    $code = 'yesno';
                }
                $return[$val['Field']]['code'] = $code;
            }

            return $return;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 生成 alter table 语句 -- 从 pma 代码拷贝创意
     *
     * @param $oldcol
     * @param $newcol
     * @param $type
     * @param $length
     * @param $attribute
     * @param $collation
     * @param $null
     * @param $default_type
     * @param $default_value
     * @param $extra
     * @param string $comment
     * @param $field_primary
     * @param $index
     * @param $default_orig
     * @return string
     */
    public function generateAlter($oldcol, $newcol, $type, $length,
                                  $attribute, $collation, $null, $default_type, $default_value,
                                  $extra, $comment = '', &$field_primary, $index, $default_orig)
    {
        return $this->escapeIdentifier($oldcol) . ' '
            . $this->generateFieldDefinition(
                $newcol, $type, $length, $attribute,
                $collation, $null, $default_type, $default_value, $extra,
                $comment, $field_primary, $index, $default_orig
            );
    }

    /**
     * 生成 charset 逻辑 -- 从 pma 代码拷贝创意
     *
     * @param $collation
     * @return string
     */
    public static function PMA_generateCharsetQueryPart($collation)
    {
        if (!0) {
            list($charset) = explode('_', $collation);
            return ' CHARACTER SET ' . $charset . ($charset == $collation ? '' : ' COLLATE ' . $collation);
        } else {
            return ' COLLATE ' . $collation;
        }
    }

    /**
     * 生成 字段定义 语句 -- 从 pma 代码拷贝创意
     * @param $name
     * @param $type
     * @param string $length
     * @param string $attribute
     * @param string $collation
     * @param bool $null
     * @param string $default_type
     * @param string $default_value
     * @param string $extra
     * @param string $comment
     * @param $field_primary
     * @param $index
     * @param $default_orig
     * @return string
     */
    public function generateFieldDefinition($name, $type, $length = '', $attribute = '',
                                            $collation = '', $null = false, $default_type = 'USER_DEFINED',
                                            $default_value = '', $extra = '', $comment = '',
                                            &$field_primary, $index, $default_orig)
    {
        $is_timestamp = strpos(strtoupper($type), 'TIMESTAMP') !== false;

        //加入 '' 强制转换下 不然数字报错
        $query = $this->escapeIdentifier('' . $name) . ' ' . $type;

        if ($length != ''
            && !preg_match('@^(DATE|DATETIME|TIME|TINYBLOB|TINYTEXT|BLOB|TEXT|'
                . 'MEDIUMBLOB|MEDIUMTEXT|LONGBLOB|LONGTEXT|SERIAL|BOOLEAN|UUID)$@i', $type)
        ) {

            //支持  int（10） unsigned
            list($length, $def) = explode(' ', $length);

            $query .= '(' . $length . ') ' . $def;
        }

        if ($attribute != '') {
            $query .= ' ' . $attribute;
        }

        if (!empty($collation) && $collation != 'NULL'
            && preg_match('@^(TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|VARCHAR|CHAR|ENUM|SET)$@i', $type)
        ) {
            $query .= $this->PMA_generateCharsetQueryPart($collation);
        }

        if ($null !== false) {
            if ($null == 'NULL') {
                $query .= ' NULL';
            } else {
                $query .= ' NOT NULL';
            }
        }

        switch ($default_type) {
            case 'USER_DEFINED' :
                if ($is_timestamp && $default_value === '0') {
                    // a TIMESTAMP does not accept DEFAULT '0'
                    // but DEFAULT 0 works
                    $query .= ' DEFAULT 0';
                } elseif ($type == 'BIT') {
                    $query .= ' DEFAULT b\''
                        . preg_replace('/[^01]/', '0', $default_value)
                        . '\'';
                } elseif ($type == 'BOOLEAN') {
                    if (preg_match('/^1|T|TRUE|YES$/i', $default_value)) {
                        $query .= ' DEFAULT TRUE';
                    } elseif (preg_match('/^0|F|FALSE|NO$/i', $default_value)) {
                        $query .= ' DEFAULT FALSE';
                    } else {
                        // Invalid BOOLEAN value
                        $query .= ' DEFAULT ' . $this->escapeString($default_value) . '';
                    }
                } else {
                    $query .= ' DEFAULT ' . $this->escapeString($default_value) . '';
                }
                break;
            case 'NULL' :
            case 'CURRENT_TIMESTAMP' :
                $query .= ' DEFAULT ' . $default_type;
                break;
            case 'NONE' :
            default :
                break;
        }

        if (!empty($extra)) {
            $query .= ' ' . $extra;
        }
        if (!empty($comment)) {
            $query .= " COMMENT " . $this->escapeString($comment) . "";
        }
        return $query;
    }

    /**
     * 资源表修改逻辑
     *
     * 表存在 支持字段修正、字段新增、索引新增（不支持新增组合索引）
     * 表不存在 支持字段创建、索引新增、创建组合主键（不支持组合索引）
     *
     * @param string $table
     * @param array $posts
     * @param string $table_comment
     * @return bool
     */
    public function alter($table = '', $posts = [], $table_comment = '')
    {
        $definitions = [];
        $field_primary = [];
        $field_index = [];
        $field_unique = [];
        $field_fulltext = [];

        try {
            //尝试判断表是否存在
            $this->query('show create table ' . $this->escapeIdentifier($table));

            //表存在 修改逻辑
            foreach ($posts as $field => $values) {
                parse_str($values, $fields);
                $fields['Comment'] = $fields['basic_name'] . ($fields['comment'] ? ' ' . $fields['comment'] : '');

                if ($fields['Index'] == '主键' || $fields['A_I'] == '是') {
                    $field_primary[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '唯一') {
                    $field_unique[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '索引') {
                    $field_index[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '全文检索') {
                    $field_fulltext[$field] = $this->escapeIdentifier($field);
                }

                if ($fields['ID']) {
                    $definitions[] = ' CHANGE ' . $this->generateAlter(
                            $fields['ID'],
                            $field,
                            $fields['Type'],
                            $fields['Value'],
                            '', //属性字段
                            $fields['Collation'],
                            $fields['Null'] == '是'
                                ? 'NULL'
                                : 'NOT NULL',
                            $fields['Default'] == '' ? 'NONE' : 'USER_DEFINED',
                            $fields['Default'] == '' ? false : $fields['Default'],
                            $fields['A_I'] == '是'
                                ? 'AUTO_INCREMENT'
                                : false,
                            $fields['Comment'],
                            $ref,
                            $field,
                            ''
                        );
                } else {
                    $definitions[] = ' ADD ' . $this->generateFieldDefinition(
                            $field,
                            $fields['Type'],
                            $fields['Value'],
                            '',
                            $fields['Collation'],
                            $fields['Null'] == '是'
                                ? 'NULL'
                                : 'NOT NULL',
                            $fields['Default'] == '' ? 'NONE' : 'USER_DEFINED',
                            $fields['Default'] == '' ? false : $fields['Default'],
                            $fields['A_I'] == '是'
                                ? 'AUTO_INCREMENT'
                                : false,
                            $fields['Comment'],
                            $ref,
                            $field,
                            ''
                        );
                }
            }

            //尝试获取索引
            try {
                $index = $this->showIndex($table);
                foreach ($index as $k => $v) {
                    //防止主键冲突
                    if ($v['Key_name'] == 'PRIMARY') unset($field_primary[$v['Column_name']]);
                    //防止反复添加唯一索引
                    if ($v['Non_unique'] == 0) unset($field_unique[$v['Column_name']]);
                    //防止已有索引的更新请求
                    unset($field_index[$v['Column_name']]);
                }
            } catch (Exception $e) {

            }

            if (count($field_primary)) {
                $definitions[] = ' ADD PRIMARY KEY (' . implode(', ', $field_primary) . ') ';
            }

            if (count($field_index)) {
                foreach ($field_index as $index) {
                    $definitions[] = ' ADD INDEX (' . $index . ') ';
                }
            }

            if (count($field_unique)) {
                foreach ($field_unique as $index) {
                    $definitions[] = ' ADD UNIQUE (' . $index . ') ';
                }
            }

            if (count($field_fulltext)) {
                foreach ($field_fulltext as $index) {
                    $definitions[] = ' ADD FULLTEXT (' . $index . ') ';
                }
            }

            try {
                $definitions && $this->query('ALTER TABLE ' . $this->escapeIdentifier($table) . implode(' , ', $definitions));
            } catch (Exception $e) {
                throw new Exception($e->getMessage() . "\n" . 'ALTER TABLE ' . $this->escapeIdentifier($table) . implode(' , ', $definitions));
            }
        } catch (Exception $e) {
            foreach ($posts as $field => $values) {
                parse_str($values, $fields);
                $fields['Comment'] = $fields['basic_name'] . ($fields['comment'] ? ' ' . $fields['comment'] : '');

                if ($fields['Index'] == '主键' || $fields['A_I'] == '是') {
                    $field_primary[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '唯一') {
                    $field_unique[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '索引') {
                    $field_index[$field] = $this->escapeIdentifier($field);
                }
                if ($fields['Index'] == '全文检索') {
                    $field_fulltext[$field] = $this->escapeIdentifier($field);
                }

                $definitions[] = $this->generateFieldDefinition(
                    $field,
                    $fields['Type'],
                    $fields['Value'],
                    '',
                    $fields['Collation'],
                    $fields['Null'] == '是'
                        ? 'NULL'
                        : 'NOT NULL',
                    $fields['Default'] == '' ? 'NONE' : 'USER_DEFINED',
                    $fields['Default'] == '' ? false : $fields['Default'],
                    $fields['A_I'] == '是'
                        ? 'AUTO_INCREMENT'
                        : false,
                    $fields['Comment'],
                    $ref,
                    $field,
                    ''
                );
            }
            if (count($field_primary)) {
                $definitions[] = ' PRIMARY KEY (' . implode(', ', $field_primary) . ') ';
            }

            if (count($field_index)) {
                foreach ($field_index as $index) {
                    $definitions[] = ' INDEX (' . $index . ') ';
                }
            }

            if (count($field_unique)) {
                foreach ($field_unique as $index) {
                    $definitions[] = ' UNIQUE (' . $index . ') ';
                }
            }

            if (count($field_fulltext)) {
                foreach ($field_fulltext as $index) {
                    $definitions[] = ' FULLTEXT (' . $index . ') ';
                }
            }

            $this->query('CREATE TABLE ' . $this->escapeIdentifier($table) . ' (' . implode(',', $definitions) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT=\'' . $table_comment . '\'');
        }

        return true;
    }
}