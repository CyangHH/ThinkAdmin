<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// | 免费声明 ( https://thinkadmin.top/disclaimer )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\wechat\controller;

use app\wechat\service\WechatService;
use think\admin\Controller;
use think\admin\helper\QueryHelper;
use think\exception\HttpResponseException;

/**
 * 微信用户管理
 * Class Fans
 * @package app\wechat\controller
 */
class Fans extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    private $table = 'WechatFans';

    /**
     * 微信用户管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->_query($this->table)->layTable(function () {
            $this->title = '微信用户管理';
        }, function (QueryHelper $query) {
            $query->where(['appid' => WechatService::instance()->getAppid()]);
            $query->like('nickname')->equal('subscribe,is_black')->dateBetween('subscribe_at');
        });
    }

    /**
     * 列表数据处理
     * @param array $data
     */
    protected function _index_page_filter(array &$data)
    {
        foreach ($data as &$vo) $vo['subscribe_at'] = format_datetime($vo['subscribe_at']);
    }

    /**
     * 同步用户数据
     * @auth true
     */
    public function sync()
    {
        sysoplog('微信授权配置', '创建粉丝用户同步任务');
        $this->_queue('同步微信用户数据', "xadmin:fansall");
    }

    /**
     * 黑名单列表操作
     * @auth true
     */
    public function black()
    {
        $data = $this->_vali([
            'openid.require' => '操作用户不能为空！',
            'black.require'  => '操作类型不能为空！',
        ]);
        try {
            foreach (array_chunk(explode(',', $data['openid']), 20) as $openids) {
                if ($data['black']) {
                    WechatService::WeChatUser()->batchBlackList($openids);
                    $this->app->db->name('WechatFans')->whereIn('openid', $openids)->update(['is_black' => 1]);
                } else {
                    WechatService::WeChatUser()->batchUnblackList($openids);
                    $this->app->db->name('WechatFans')->whereIn('openid', $openids)->update(['is_black' => 0]);
                }
            }
            if (empty($data['black'])) {
                $this->success('移出黑名单成功！');
            } else {
                $this->success('拉入黑名单成功！');
            }
        } catch (HttpResponseException $exception) {
            throw  $exception;
        } catch (\Exception $exception) {
            $this->error("黑名单操作失败，请稍候再试！<br>{$exception->getMessage()}");
        }
    }

    /**
     * 删除用户信息
     * @auth true
     * @throws \think\db\exception\DbException
     */
    public function remove()
    {
        $this->_delete($this->table);
    }

    /**
     * 清空用户数据
     * @auth true
     */
    public function truncate()
    {
        try {
            $this->_query('WechatFans')->empty();
            $this->_query('WechatFansTags')->empty();
            $this->success('清空用户数据成功！');
        } catch (HttpResponseException $exception) {
            throw  $exception;
        } catch (\Exception $exception) {
            $this->error("清空用户数据失败，{$exception->getMessage()}");
        }
    }
}
