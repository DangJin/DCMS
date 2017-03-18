<?php
namespace app\admin\controller;

use app\admin\controller\Common;
use think\Db;
use think\Cache;
use think\Request;
use app\admin\model\User as U;        //使用 User 模型
use app\admin\model\Group as G;        //使用 Group 模型
/**
 * 用户控制器
 */
class User extends Common
{

    function __construct()
    {
        parent::__construct();
    }

    //添加用户
    public function addUser()
    {
        if (Request::instance()->isGet())
        {
            $groupList = Db::table('think_group')->select();

            return jsonp(['type' => 0, $groupList]);
        } elseif (Request::instance()->isPost())
        {
            $name = input('post.name');
            $email = input('post.email');
            //用户名和邮箱唯一
            $this->checkUnique($name, $email);
            $password = md5(input('post.password'));
            if ($password != md5(input('post.confirm')))
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '确认密码不一致']);
            }
            //是否启用账户
            $status = input('post.status') != null ? 1 : -1;
            //所属的多个分组，接受数组参数
            $group = input('post.group/a');
            //若没有分组，则自动分组为‘游客’
            if (empty($group))
            {
                $group = Db::table('think_group')->where('title', '游客')->field('id')->select()[0]['id'];
            }
            $time = date('Y-m-d H:i:s');
            $User = new \app\admin\model\User();

            $User->save(['name' => $name, 'email' => $email, 'password' => $password, 'status' => $status, 'create_time' => $time]);
            $uid = $User->id;
            if ($uid)
            {
                if (is_array($group))
                {
                    foreach (input('post.group/a') as $key => $value)
                    {
                        Db::table('think_group_access')->insert(['uid' => $uid, 'groupid' => $value]);
                    }
                } else
                {
                    Db::table('think_group_access')->insert(['uid' => $uid, 'groupid' => $group]);
                }

                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                return jsonp(['type' => 3, 'value' => false, 'message' => $User->getError()]);
            }

        }
        //return $this->view->fetch();
    }

    //编辑用户
    public function editUser($id)
    {
        if (Request::instance()->isGet())
        {
            //此用户的分组
            $groups = [];
            $userInfo = Db::table('think_user')->where('id', $id)->field('name,email,status')->select()[0];
            foreach (U::find($id)->getGroup as $key => $value)
            {
                $groups[] = $value->groupid;
            }
            $userInfo['groups'] = $groups;

            $groupList = Db::table('think_group')->where('status = 1')->field('id, title')->select();

            foreach ($groupList as $key => $value)
            {
                if (in_array($value['id'], $userInfo['groups']))
                {
                    $groupList[$key]['checked'] = '1';
                } else
                {
                    $groupList[$key]['checked'] = '0';
                }
            }

            return jsonp(['type' => 0, 'userinfo' => $userInfo, 'groupList' => $groupList]);
        } elseif (Request::instance()->isPost())
        {
            $User = new \app\admin\model\User();
            $name = input('post.name');
            $email = input('post.email');
            $this->checkUnique($name, $email, $id);
            if (input('post.password') != '')
            {
                if (input('post.password') != input('post.confirm'))
                {
                    return jsonp(['type' => 1, 'value' => false, 'message' => '确认密码不一致']);
                }
                $password = md5(input('post.password'));
            }
            if (input('post.status') != null)
            {
                $status = 1;
            } else
            {
                $status = -1;
            }

            $group = input('post.group/a');
            //可以不设密码（游客）
            if (isset($password))
            {
                $data = ['name' => $name, 'email' => $email, 'password' => $password, 'status' => $status];
            } else
            {
                $data = ['name' => $name, 'email' => $email, 'status' => $status];
            }
            $result = $User->validate(
                [
                    'name'  => 'require|max:25',
                    'email' => 'email',
                ],
                [
                    'name.require' => '名称必须',
                    'name.max'     => '名称最多不能超过25个字符',
                    'email'        => '邮箱格式错误',
                ]
            )->save($data, ['id' => $id]);
            if ($result == false)
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => $User->getError()]);
            }
            if ($group == null)
            {
                $group[] = Db::table('think_group')->where('title', '游客')->field('id')->select()[0]['id'];
            }

            $data = [];
            Db::startTrans();
            Db::table('think_group_access')->where('uid', $id)->delete();
            foreach ($group as $key => $value)
            {
                $data[$key]['uid'] = $id;
                $data[$key]['groupid'] = $value;
            }
            if (Db::table('think_group_access')->insertAll($data))
            {
                Db::commit();

                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                Db::rollback();

                return jsonp(['type' => 3, 'value' => false, 'message' => '权限修改失败']);
            }
        }
    }

    //删除用户
    public function delUser($id)
    {
        if ($id == 1)
        {
            return jsonp(['type' => 1, 'value' => false, 'message' => '超级管理员不可删除']);
        }
        Db::startTrans();
        if (Db::table(think_user)->where('id', $id)->delete())
        {
            if (Db::table(think_group_access)->where('uid', $id)->delete())
            {
                Db::commit();

                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                Db::rollback();

                return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
            }
        } else
        {
            Db::rollback();

            return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
        }
    }

    //分组列表
    public function groupList()
    {
        $groupList = Db::table('think_group')->field('id,title,status,rules,create_time')->select();

        return jsonp(['type' => 0, 'groupList' => $groupList]);
    }

    //添加分组
    public function addGroup()
    {
        if (Request::instance()->isGet())
        {
            $rules = Db::table('think_rule')->field('id,title')->where('status', 1)->select();

            return jsonp(['type' => 0, 'rules' => $rules]);
        } elseif (Request::instance()->isPost())
        {
            $title = input('post.title');
            if (Db::table('think_group')->where('title', $title)->find())
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '组名已存在']);
            }
            $rules = '';
            $time = date('Y-m-d H:i:s');
            if (input('post.rules/a') != null)
            {
                foreach (input('post.rules/a') as $key => $value)
                {
                    $rules .= $value . ',';
                }
                $rules = substr($rules, 0, -1);
            } else
            {
                $rules = Db::table('think_rule')->where('title', '系统首页')->field('id')->select[0]['id'];
            }
            $status = input('post.status') != null ? 1 : -1;
            if (Db::table('think_group')->insert(['title' => $title, 'status' => $status, 'rules' => $rules, 'create_time' => $time]))
            {
                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
            }
        }
    }

    //编辑分组信息
    public function editGroup($id)
    {
        if (Request::instance()->isGet())
        {
            $groupInfo = Db::table('think_group')->where('id', $id)->field('title,status,rules')->select()[0];

            $groupInfo['rules'] = explode(',', $groupInfo['rules']);

            $rulesInfo = Db::table('think_rule')->where('status=1')->select();

            foreach ($rulesInfo as $key => $value)
            {
                if (in_array($value['id'], $groupInfo['rules']))
                {
                    $rulesInfo[$key]['checked'] = 1;
                } else
                {
                    $rulesInfo[$key]['checked'] = 0;
                }
            }

            return jsonp(['type' => 0, 'groupInfo' => $groupInfo, 'rulesInfo' => $rulesInfo]);
        } elseif (Request::instance()->isPost())
        {
            $title = input('post.title');
            $titles = Db::table('think_group')->where('id', '<>', $id)->select();
            foreach ($titles as $key => $value)
            {
                if (in_array($title, $value))
                {
                    return jsonp(['type' => 1, 'value' => false, 'message' => '组名已存在']);
                }
            }
            $rules = '';
            if (input('post.rules/a') != null)
            {
                foreach (input('post.rules/a') as $key => $value)
                {
                    $rules .= $value . ',';
                }
                $rules = substr($rules, 0, -1);
            } else
            {
                $rules = Db::table('think_rule')->where('title', '系统首页')->field('id')->select[0]['id'];
            }
            $time = date('Y-m-d H:i:s');
            if (Db::table('think_group')->where('id', $id)->update(['title' => $title, 'rules' => $rules, 'update_time' => $time]))
            {
                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                return jsonp(['type' => 3, 'value' => false, 'message' => '信息未修改']);
            }

        }
    }

    //删除分组
    public function delGroup($id)
    {
        if ($id == 1)
        {
            return jsomp(['type' => false, 'message' => '非法操作']);
        }
        if (Db::table('think_group_access')->where('groupid', $id)->find())
        {
            return jsonp(['type' => 1, 'value' => false, 'message' => '当前分组下有用户存在，暂时无法删除']);
        }
        if (Db::table('think_group')->where('id', $id)->delete())
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
        } else
        {
            return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
        }
    }

    //锁定分组
    public function lockGroup($id, $lock)
    {
        if ($id == 1)
        {
            return jsomp(['type' => false, 'message' => '非法操作']);
        }
        if (Db::table('think_group')->where('id', $id)->setField('status', $lock))
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
        } else
        {
            return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
        }
    }

    //锁定用户
    public function lockUser($id, $lock)
    {
        if ($id == 1)
        {
            return jsonp(['type' => 1, 'value' => false, 'message' => '超管不能锁定']);
        }
        if (Db::table('think_user')->where('id', $id)->setField('status', $lock))
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
        } else
        {
            return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
        }
    }

    //检查用户信息唯一性
    public function checkUnique($name, $email, $id = 0)
    {
        if ($id == 0)
        {
            $originInfo = Db::table('think_user')->field('name,email')->select();
        } else
        {
            $originInfo = Db::table('think_user')->where("id != $id")->field('name,email')->select();
        }
        foreach ($originInfo as $key => $value)
        {
            if ($name == $key || $email == $value)
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '用户名或邮箱已被占用']);
            }
        }
    }
}