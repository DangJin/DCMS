<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;
use think\dmin\model\User;
use think\admin\model\Rule;
use think\admin\model\Group;

/**
 * 公共控制器
 */
class Common extends Controller
{
    function __construct()
    {
        parent::__construct();
        if (!session('?username'))
        {
            return jsonp(['value' => 'url', 'message' => '跳转到登录页']);
        }
        $name = strtolower(request()->module() . '/' . request()->controller() . '/' . request()->action());
        //检查权限check方法，如果session中有'admin'，代表是超级管理员，不用阻止操作
        if (!$this->check($name, session('keyid')))
        {
            if (!session('?admin'))
            {
                return jsonp(['value' => false, 'message' => '没有权限']);
            }
        }
    }

    public function check($name, $uid)
    {
        if (!self::checkUserStatus($uid))
        {
            session(null);

            return jsonp(['type' => 1, 'value' => false, 'message' => '账号已被锁定，请联系管理员']);
        }

        if (is_string($name))
        {
            $groups = self::getGroupsByUid($uid);
            if (empty($groups))
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '反正是出错了，你可以到common.php 38行查看']);
            }
            if (!self::checkGroupStatus($groups))
            {
                session(null);

                return jsonp(['type' => 2, 'value' => false, 'message' => '用户组被锁定，请联系管理员，并跳转登录页']);
            }
            if (!self::checkRuleStatus($name))
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '没有权限']);
            }
            $rules = self::getRuleStatus($groups);
            $ruleId = Db::table('think_rule')->where('rules', $name)->field('id')->select()[0]['id'];
            $falg = false;

            foreach ($rule as $key => $value)
            {
                if (in_array($ruleId, exploed(',', $value['rules'])))
                {
                    $falg = true;
                    break;
                }
            }

            return $falg;
        }
    }

    public static function getGroupsByUid($uid)
    {
        return Db::table('think_group_access')->where('uid', $uid)->field('groupid')->select();
    }

    public static function getRulesByGroups($groups)
    {
        if (is_array($groups))
        {
            foreach ($groups as $key => $value)
            {
                $rules[] = Db::table('think_group')->where('id', $value['groupid'])->field('rules')->select()[0];
            }

            return $rules;
        }
    }

    public static function checkUserStatus($uid)
    {
        $status = Db::table('think_user')->where('id', $uid)->field('status')->select()[0]['status'];
        if ($status == '1')
        {
            return true;
        } else
        {
            return false;
        }
    }

    public static function checkGroupStatus($group)
    {
        if (is_array($group))
        {
            $flag = false;
            foreach ($group as $key => $value)
            {
                if ($value['groupid'] == 1)
                {
                    session('admin', '1');
                } else
                {
                    session('admin', null);
                }

                if (Db::table('think_group')->where('id', $value['groupid'])->field('status')->select()[0]['status'] == 1)
                {
                    $flag = true;
                }
            }

            return $flag;
        } else
        {
            if (Db::table('think_group')->where('id', $group)->field('status')->select()[0]['status'] == 1)
            {
                return true;
            } else
            {
                return false;
            }
        }
    }

    public static function checkRuleStatus($rule)
    {
        if (Db::table('think_rule')->where('rules', $rule)->field('status')->select()[0]['status'] == 1)
        {
            return true;
        } else
        {
            return false;
        }
    }

    public static function checkLogin($uid)
    {
        if (!self::checkUserStatus($uid))
        {
            return 0;
        }
        $groups = self::getGroupsByUid($uid);
        if (!self::checkGroupStatus($groups))
        {
            return 1;
        }
        if (empty($groups))
        {
            return 2;
        }

        return 3;
    }
}