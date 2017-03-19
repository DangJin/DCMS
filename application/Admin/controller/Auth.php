<?php
namespace app\admin\controller;

use app\admin\controller\Common;
use think\Request;
use think\Db;

/**
 * 权限管理控制器
 */
class Auth extends Common
{
    function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->redirect('authList');
    }

    //权限列表
    public function authList()
    {
        $authList = Db::table('think_rule')->field('id,rules,title,status,create_time')->paginate();

        return jsonp(['type' => 0, 'authList' => $authList]);
    }

    //添加权限
    public function addAuth()
    {
        if (Request::instance()->isGet())
        {
            return jsonp(['type' => 2, 'value' => true, 'message' => '跳转调用方法对应的页面']);
        } elseif (Request::instance()->isPost())
        {
            $title = input('post.title');
            $titles = Db::table('think_rule')->field('title')->select();
            foreach ($titles as $key => $value)
            {
                if ($title == $value['title'])
                {
                    return jsonp(['type' => 1, 'value' => false, 'message' => '权限名已被使用']);
                }
            }
            $status = input('post.status') != null ? 1 : -1;

            $rules = input('post.rules');

            $create_time = date('Y-m-d H:i:s');

            if (Db::table('think_rule')->insert(['title' => $title, 'status' => $status, 'rules' => $rules, 'create_time' => $create_time]))
            {
                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
            }
        }
    }

    public function editAuth($id)
    {
        if (Request::instance()->isGet())
        {
            $authInfo = Db::table('think_rule')->where('status=1')->field('title,status,rules')->select()[0];

            $authInfo['rules'] = str_replace(',', ',' . PHP_EOL, $authInfo['rules']);
            $authInfo['model'] = substr($authInfo['rules'], 0, 5);

            return jsonp(['type' => 0, 'authInfo' => $authInfo]);
        } elseif (Request::instance()->isPost())
        {
            $title = input('post.title');
            $titles = Db::table('think_rule')->where('id', '<>', $id)->field('title')->select();
            foreach ($titles as $key => $value)
            {
                if ($value['title'] == $title)
                {
                    return jsonp(['type' => 1, 'value' => false, 'message' => '权限名已被使用']);
                }
            }
            $status = input('post.status') != null ? 1 : -1;
            $rules = input('post.rules');
            if (Db::table('think_rule')->where('id', $id)->update(['title' => $title, 'status' => $status, 'rules' => $rules]))
            {
                return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
            } else
            {
                return jsonp(['type' => 3, 'value' => false, 'message' => '操作失败']);
            }
        }
    }

    public function delAuth($id)
    {
        $userAuth = Db::table('think_group')->field('rules')->select();
        foreach ($userAuth as $key => $value)
        {
            if (in_array($id, explode(',', $value['rules'])))
            {
                return jsonp(['type' => 1, 'value' => false, 'message' => '有分组使用该权限，无法删除']);
            }
        }
        if (Db::table('think_rule')->delete($id))
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '删除成功']);
        } else
        {
            return jsonp(['type' => 3, 'value' => false, 'message' => '删除失败']);
        }
    }

    public function lockAuth($id, $status)
    {
        if (Db::table('think_rule')->where('id', $id)->setField('status', $status))
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '操作成功']);
        } else
        {
            return jsonp(['type' => 3, 'value' => true, 'message' => '操作失败']);
        }
    }
}