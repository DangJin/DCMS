<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 17/3/16
 * Time: 上午11:27
 */

namespace app\admin\controller;

use think\Request;
use think\Controller;
use think\Db;

class Publics extends Controller
{
    public function login()
    {
        return $this->fetch();
    }

    public function doLogin()
    {
        if (Request::instance()->isPost())
        {
            $email = Request::instance()->post('email', '', 'trim');
            $password = Request::instance()->post('password', '', 'trim');
            if ($email == '' || $password == '')
            {
                echo 'bunengweikong';
            }
            $info = Db::table('think_user')->where('email', $email)->field('id,name,password')->select();
            if (empty($info))
            {
                echo 'zhanghaobuzxhunzai';
            }
            $info = $info[0];
            if (md5($password) != $info['password'])
            {
                echo 'mimabuzhengque';
            }
            switch (Common::checkLogin($info['id']))
            {
                case 0:
                    echo 'zhanghaobeisuoding';
                    break;
                case 1:
                    echo 'yonghuzhubeisuoding';
                    break;
                case 2:
                    echo 'meiyouquanxian';
                    break;
                default:
                    break;
            }
            session('username', $info['name']);
            session('keyid', $info['password']);
            echo 'dengluchenggong';
        } else
        {
            echo 'hello world';
        }
    }

}