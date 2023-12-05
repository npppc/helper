<?php
namespace Npc\Helper\Phalcon\Model;

use Phalcon\Mvc\Model;

/**
 * Class ModelBase
 * @package Npc\Helper\Phalcon\Model
 */
class ModelBase extends Model
{
    /**
     * 所有资源如果涉及到操作权限均需要实现此方法
     * 并且资源必需带有 admin_id 字段属性
     *
     * @param $user_id
     * @return bool
     */
    public function hasPrivilege(string $user_id)
    {
        return false;
    }

    public function getErrorMessage()
    {
        foreach($this->getMessages() as $message)
        {
            return $message->getMessage();
        }
    }

}