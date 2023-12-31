<?php namespace Phpcmf;
/**
 * www.xunruicms.com
 * 迅睿内容管理框架系统（简称：迅睿CMS）
 * 本文件是框架系统文件，二次开发时不可以修改本文件
 **/


// 内容操作类
class Table extends \Phpcmf\Common {
    
    public $dfield; // 自定义字段对象
    public $init; // 数据表初始化 [ fmode init方法参数 ]
    public $is_get_catid; // 当期栏目id

    public $mytable; // 表格列表属性
    protected $my_field; // 预定义变量

    protected $model; // 模型类
    protected $db_source; // 数据源
    protected $field; // 自定义字段 [ 1 = [主表], 0 = [附表]]
    protected $sys_field; // 系统字段 [ 1 = [主表], 0 = [附表]]
    protected $not_field; // 无用的字段
    protected $form_rule; // 表单配置规则
    protected $is_data; // 是否支持附表
    protected $is_post_code; // 是否提交验证码
    protected $is_module_index; // 是否支持模块索引
    protected $is_category_data_field; // 是否支持模块栏目模型字段

    protected $list_where; // 列表数据时的条件
    protected $edit_where; // 修改数据时的条件
    protected $delete_where; // 删除数据时的条件
    protected $is_diy_where_list; // 是否支持自定义参数查询的条件

    protected $name; // 定义一个操作显示名称
    protected $tpl_name; // 模板命名名称
    protected $tpl_prefix; // 模板前缀
    protected $list_pagesize; // 模板列表分页量

    protected $auto_save; // 自动存储
    protected $replace_id; // 替换主id(link_id)

    protected $url_params; // url参数固定
    protected $admin_tpl_path; // 后台模板指定目录
    protected $fix_admin_tpl_path; // 修正值的后台模板指定目录

    protected $fix_table_list; //
    protected $is_ajax_list; // 是否作为ajax请求列表数据，不进行第一次查询
    protected $is_search; // 是否开启列表上方的搜索功能
    protected $is_fixed_columns; // 是否开启列表右侧一行浮动固定
    protected $is_show_search_bar; // 是否默认显示搜索区域

    public function __construct() {
        parent::__construct();
        $this->is_data = 0;
        $this->tpl_name = '';
        $this->auto_save = 1;
        $this->is_search = 1;
        $this->is_show_search_bar = 1;
        $this->tpl_prefix = \Phpcmf\Service::L('Router')->class.'_';
        $this->delete_where = '';
        $this->is_module_index = 0;
        $this->is_category_data_field = 0;
        $this->is_diy_where_list = 0;
        $this->admin_tpl_path = (APP_DIR ? APPPATH.'Views/' : COREPATH.'View/');
    }

    // 数据库对象
    protected function _db() {

        if ($this->db_source) {
            return \Phpcmf\Service::M()->db_source($this->db_source);
        }

        return \Phpcmf\Service::M();
    }
    
    // 数据表初始化
    protected function _init($data) {
        !$data['show_field'] && $data['show_field'] = 'id';
        $this->field = $data['field'] ? $data['field'] : $this->field;
        $this->not_field = [];
        $this->sys_field = $data['sys_field'] ? \Phpcmf\Service::L('Field')->sys_field($data['sys_field']) : [];
        $data['field'] = $this->sys_field && $this->field ? $this->field + $this->sys_field : ($this->field ? $this->field : $this->sys_field);
        $data['is_diy_where_list'] = $this->is_diy_where_list;
        $this->init = $data;
        $this->db_source = isset($data['db_source']) ? $data['db_source'] : '';
        return $this;
    }

    // 获取入库时的字段
    protected function _field_save($catid = 0) {

        $field = $this->sys_field ? dr_array22array($this->sys_field, $this->field) : $this->field;

        // 栏目模型字段
        if ($this->is_category_data_field && $catid) {
            $cat = dr_cat_value($this->module['mid'], $catid);
            if (!$cat['ismain']) {
                // 非主栏目继承上级
                $cat = dr_cat_value(
                    $this->module['mid'],
                    \Phpcmf\Service::L('category', 'module')->get_ismain_id($this->module['mid'], $cat)
                );
            }
            if ($cat && $cat['field']) {
                foreach ($cat['field'] as $f) {
                    if ($this->module['category_data_field'][$f]) {
                        $field[$f] = $this->module['category_data_field'][$f];
                    }
                }
            }
        }

        if ($field) {
            foreach ($field as $i => $t) {
                if (!IS_ADMIN && !$t['ismember']) {
                    // 非管理平台验证字段显示权限
                    $this->not_field[$i] = $t;
                    unset($field[$i]);
                } elseif (IS_ADMIN && $t['setting']['show_admin'] && !dr_in_array(1, $this->admin['roleid'])
                    && dr_array_intersect($this->admin['roleid'], $t['setting']['show_admin'])) {
                    // 后台时 判断管理员权限
                    $this->not_field[$i] = $t;
                    unset($field[$i]);
                }
            }
        }

        return $field;
    }

    /**
     * 字段进行分组
     * */ 
    protected function _field_group($data) {

        $field = $this->field;
        $my_field = $sys_field = $diy_field = $cat_field = [];

        // 栏目模型字段
        if ($this->is_category_data_field && $data['catid']) {
            $cat = dr_cat_value($this->module['mid'], $data['catid']);
            if (!$cat['ismain']) {
                // 非主栏目继承上级
                $cat = dr_cat_value(
                    $this->module['mid'],
                    \Phpcmf\Service::L('category', 'module')->get_ismain_id($this->module['mid'], $cat)
                );
            }
            if ($cat && $cat['field']) {
                foreach ($cat['field'] as $f) {
                    if ($this->module['category_data_field'][$f]) {
                        $field[$f] = $this->module['category_data_field'][$f];
                    }
                }
            }
        }

        $field && uasort($field, function($a, $b){
            if($a['displayorder'] == $b['displayorder']){
                return 0;
            }
            return($a['displayorder']<$b['displayorder']) ? -1 : 1;
        });

        foreach ($field as $i => $t) {
            if ($t['setting']['is_right'] == 1) {
                // 右边字段归类为系统字段
                if (IS_ADMIN) {
                    $sys_field[$i] = $t;
                } else {
                    $my_field[$i] = $t;
                }

            } elseif ($t['setting']['is_right'] == 2) {
                // diy字段
                $diy_field[$i] = $t;
            } else {
                $my_field[$i] = $t;
            }
        }

        $this->sys_field && $sys_field = $this->sys_field + $sys_field ;

        return [$my_field, $sys_field, $diy_field, $cat_field];
    }

    /**
     * 获取内容
     * $id      内容id,新增为0
     * */
    protected function _Data($id = 0) {

        if (!$id) {
            return [];
        }

        $row = $this->_db()->init($this->init)->get($id);
        if (!$row) {
            return [];
        }

        // 附表存储
        if ($this->is_data) {
            $r = $this->_db()->table($this->init['table'] . '_data_'.intval($row['tableid']))->get($id);
            $row = $r ? $r + $row : $row;
        }

        return $row;
    }

    /**
     * 排序值操作
     * $id      内容id
     * */
    protected function _Display_Order($id, $value, $after = null) {
        $this->_Save_Value($id, 'displayorder', $value, $after);
    }

    /**
     * 单个字段存储值
     * $id      内容id
     * $name    字段名称
     * $value   字段值
     * */
    protected function _Save_Value($id, $name, $value, $after = null, $before = null) {

        $table = $this->init['table'];
        if (!$this->_db()->is_table_exists($table)) {
            $this->_json(0, dr_lang('数据表（%s）不存在', $this->init['table']));
        } elseif (!$this->_db()->is_field_exists($table, $name)) {
            if (isset($this->init['stable']) && $this->init['stable']
                && $this->_db()->is_table_exists($this->init['stable'])
                && $this->_db()->is_field_exists($this->init['stable'], $name)) {
                $table = $this->init['stable'];
            } else {
                $this->_json(0, dr_lang('数据表（%s）字段（%s）不存在', $this->init['table'], $name));
            }
        }

        // 查询数据
        $row = $this->_db()->table($table)->get($id);
        if (!$row) {
            $this->_json(0, dr_lang('数据%s不存在', $id));
        } elseif ($row[$name] == $value) {
            $this->_json(1, dr_lang('没有变化'));
        }

        // 存储之前
        if ($before) {
            $rt = call_user_func_array($before, [$row]);
            if (!$rt['code']) {
                $this->_json(0, $rt['msg']);
            }
            $rt['data'] && $value = $rt['data'];
        }

        $rt = $this->_db()->table($table)->save($id, $name, $value, $this->edit_where);
        if (!$rt['code']) {
            $this->_json(0, $rt['msg']);
        }

        \Phpcmf\Service::L('input')->system_log($this->name.'：修改('.$row[$this->init['show_field']].')表字段('.$name.')的值为'.$value);

        // 自动更新缓存
        IS_ADMIN && \Phpcmf\Service::M('cache')->sync_cache();

        // 提交之后的操作
        if ($after) {
            call_user_func_array($after, [$row]);
        }

        $this->_json(1, dr_lang('操作成功'));
    }

    // 用于控制器的存储
    public function save_value_edit() {

        $cache_uid = $this->session()->get('function_list_save_text_value');
        if (!$cache_uid) {
            $this->_json(0, dr_lang('权限认证过期，请重试'));
        } elseif ($this->uid != $cache_uid) {
            $this->_json(0, dr_lang('权限认证失败，请重试'));
        }

        $id = intval(\Phpcmf\Service::L('input')->get('id'));
        $name = dr_safe_filename(\Phpcmf\Service::L('input')->get('name'));
        $value = urldecode((string)\Phpcmf\Service::L('input')->get('value'));
        $after = dr_safe_filename(\Phpcmf\Service::L('input')->get('after'));
        $before = dr_safe_filename(\Phpcmf\Service::L('input')->get('before'));
        if ($before) {
            if (strpos($before, 'dr_') === 0 or strpos($before, 'my_') === 0) {

            } else {
                $this->_json(0, '函数【'.$before.'】必须以dr_或者my_开头');
            }
        }

        if (!$id) {
            $this->_json(0, dr_lang('缺少id参数'));
        } elseif (!$name) {
            $this->_json(0, dr_lang('缺少name参数'));
        }

        $this->_Save_Value($id, $name, $value, $after, $before);
    }
    
    // 格式化保存数据
    protected function _Format_Data($id, $data, $old) {
        return $data;
    }

    /**
     * 保存内容
     * $id      内容id,新增为0
     * $data    提交内容数组,留空为自动获取
     * $old     老数据
     * $func    格式化提交的数据 提交前   
     * $func    格式化提交的数据 保存后
     * */ 
    protected function _Save($id = 0, $data = [], $old = [], $before = null, $after = null) {

        // 附表id号
        $this->is_data && $tid = intval($old['tableid']);

        // 格式化提交的数据
        if ($before) {
            $rt = call_user_func_array($before, [$id, $data, $old]);
            if (!$rt['code']) {
                return $rt;
            }
            $data = $rt['data'];
        }

        // 模块数据
        if ($this->is_module_index) {
            $rt = $this->content_model->save_content($id, $data, $old);
            if (!$rt['code']) {
                return $rt;
            }
            $data = $rt['data'];
            $data[1]['id'] = $data[0]['id'] = $id = $rt['code'];
        } else {
            // 主表数据
            $main = isset($data[1]) ? $data[1] : $data;
            if ($id) {
                // 更新数据
                $rt = $this->_db()->table($this->init['table'])->update($id, $main, $this->edit_where);
                if (!$rt['code']) {
                    return $rt;
                }
            } else {
                // 新增数据
                $rt = $this->_db()->table($this->init['table'])->replace($main);
                if (!$rt['code']) {
                    return $rt;
                }
                // 新增获取id
                $_id = $rt['code'];
                // 副表据量无限分表
                if ($this->is_data) {
                    $tid = \Phpcmf\Service::M()->get_table_id($_id);
                    $this->_db()->table($this->init['table'])->update($_id, ['tableid' => $tid], $this->edit_where);
                }
            }
            // 附表存储
            if ($this->is_data) {
                // 判断附表是否存在,不存在则创建
                $this->_db()->is_data_table($this->init['table'].'_data_', $tid);
                $table = $this->init['table'].'_data_'.$tid;
                if ($id) {
                    if ($data[0]) {
                        $rt = $this->_db()->table($table)->update($id, $data[0], $this->edit_where);
                        if ($rt['msg']) {
                            // 删除主表
                            $this->_db()->table($this->init['table'])->delete($id);
                            // 删除索引
                            $this->is_module_index && $this->_db()->table($this->init['table'].'_index')->delete($id);
                            return $rt;
                        } 
                    } else {
                        // 有种情况就是附表没有数据;
                    }
                } else {
                    $data[0]['id'] = $_id; // 录入主表id
                    $rt = $this->_db()->table($table)->replace($data[0]);
                    if ($rt['msg']) {
                        // 删除主表
                        $this->_db()->table($this->init['table'])->delete($_id);
                        // 删除索引
                        $this->is_module_index && $this->_db()->table($this->init['table'].'_index')->delete($_id);
                        return $rt;
                    }
                }
            }

            // 获取真实id
            $data[1]['id'] = $data[0]['id'] = $id = $id ? $id : $_id;
        }

        // 提交之后的操作
        if ($after) {
            $rt = call_user_func_array($after, [$id, $data, $old]);
            if ($rt && isset($rt['code'])) {
                return $rt;
            }
        }

        return dr_return_data($id, 'ok', $data);
    }

    /**
     * 提交内容
     * $id      内容id,新增为0,否则视为修改
     * $draft   草稿数据
     * $is_data 将内容数据返回到data数组里面
     * $is_post 强制post执行
     * */
    protected function _Post($id = 0, $draft = [], $is_data = 0, $is_post = 0) {

        $uri =\Phpcmf\Service::L('Router')->uri();
        $name = md5($id.$uri); // 当前页面唯一标识

        // 表单操作类
        \Phpcmf\Service::L('Form')->id($id); // 初始化id

        // 获取数据
        $data = $this->_Data($id);
        $this->replace_id && $id = $this->replace_id; // 替换主id

        // 初始化自定义字段类
        \Phpcmf\Service::L('Field')->app(APP_DIR);

        if (IS_AJAX_POST || $is_post) {
            // 内容不存在
            if (!$data && $id) {
                $this->_json(0, dr_lang('数据#%s不存在', $id));
            } elseif ($this->is_post_code && !\Phpcmf\Service::L('Form')->check_captcha('code')) {
                // 验证码验证
                $this->_json(0, dr_lang('图片验证码不正确'), ['field' => 'code']);
            }
            // 验证数据
            \Phpcmf\Service::L('field')->value = $data;
            $post = \Phpcmf\Service::L('input')->post('data', false);
            list($post, $return, $attach) = \Phpcmf\Service::L('Form')->validation(
                $post, 
                $this->form_rule,
                $this->_field_save(intval(\Phpcmf\Service::L('input')->post('catid'))),
                $data
            );
            // 输出错误
            if ($return) {
                $this->_json(0, $return['error'], ['field' => $return['name']]);
            }
            // 格式化数据
            $post = $this->_Format_Data($id, $post, $id ? $data : []);
            // 保存数据
            $rt = $this->_Save($id, $post, $id ? $data : []);
            if (!$rt['code']) {
                $this->_json(0, $rt['msg'], $rt['data']);
            }
            $post['id'] = $rt['code'];
            // 记录日志
            $logname = 'id：'.$post['id'];
            if (isset($post[$this->init['show_field']]) && $post[$this->init['show_field']]) {
                $logname = ($this->init['show_field'] != 'id' ? $logname.' ' : '').$this->init['show_field'].'：'.$post[$this->init['show_field']];
            } elseif (isset($data[$this->init['show_field']]) && $data[$this->init['show_field']]) {
                $logname = ($this->init['show_field'] != 'id' ? $logname.' ' : '').$this->init['show_field'].'：'.$data[$this->init['show_field']];
            }
            \Phpcmf\Service::L('input')->system_log(
                $this->name . dr_lang($id ? '修改' : '新增').' ('.$logname.')',
                0,
                $post
            );
            // 获取新的存储id
            $id = $rt['code'];
            // 附件归档
            if (SYS_ATTACHMENT_DB && $attach) {
                \Phpcmf\Service::M('Attachment')->handle(
                    isset($data['uid']) ? $data['uid'] : $this->member['id'],
                    \Phpcmf\Service::M()->dbprefix($this->init['table']).'-'.$id,
                    $attach
                );
            }
            // 删除临时存储数据
            \Phpcmf\Service::L('Form')->auto_form_data_delete($name);
            // 执行回调方法
            $cp = $this->_Call_Post($rt['data']);
            $this->_json($cp['code'], $cp['msg'], $cp['data']);
        }

        // 内容不存在
        if (!$data && $id) {
            IS_ADMIN ? $this->_admin_msg(0, dr_lang('数据#%s不存在', $id)) : $this->_msg(0, dr_lang('数据#%s不存在', $id));
            return [null, null];
        }

        // 默认获取表单自动存储的数据
        if (defined('SYS_AUTO_FORM') && SYS_AUTO_FORM && !$id && $this->auto_save) {
			$data = \Phpcmf\Service::L('Form')->auto_form_data($name, $data);
		}

        // 当存在草稿时系统默认加载草稿数据
        $draft && $data = $draft;

        // 主要数据
        $mydata = $data;

        // 获取从get中栏目参数
        $this->is_get_catid && $data['catid'] = $mydata['catid'] = $this->is_get_catid;

        // 是否包在data里面
        //$is_data && $data['data'] = $mydata;

        // 获取自定义字段表单控件
        list($my_field, $sys_field, $diy_field, $cat_field) = $this->_field_group($mydata);
        $data['myfield'] = \Phpcmf\Service::L('Field')->toform($id, $my_field, $mydata);
        $data['sysfield'] = \Phpcmf\Service::L('Field')->toform($id, $sys_field, $mydata);
        $data['diyfield'] = \Phpcmf\Service::L('Field')->toform($id, $diy_field, $mydata);
        $data['catfield'] = \Phpcmf\Service::L('Field')->toform($id, $cat_field, $mydata);
        $data['mymerge'] = \Phpcmf\Service::L('Field')->merge;

        // 动态实时存储表单值
        if (defined('SYS_AUTO_FORM') && SYS_AUTO_FORM && !$id && $this->auto_save) {
			$data['auto_form_data_ajax'] = \Phpcmf\Service::L('Form')->auto_form_data_ajax($name);
		}

        // 表单隐藏域
        $data['form'] = dr_form_hidden([
            'id' => $id,
            'table' => IS_ADMIN ? $this->init['table'] : '',
        ]);

        // 获取添加URL
        $data['post_url'] = IS_MEMBER ? \Phpcmf\Service::L('Router')->member_url(\Phpcmf\Service::L('Router')->uri('add'), $this->url_params) :  \Phpcmf\Service::L('Router')->url(\Phpcmf\Service::L('Router')->uri('add'), $this->url_params);

        // 获取返回URL
        $data['reply_url'] = \Phpcmf\Service::L('Router')->get_back(\Phpcmf\Service::L('Router')->uri('index'), $this->url_params, true);
        $data['uriprefix'] = trim(APP_DIR.'/'.\Phpcmf\Service::L('Router')->class, '/'); // uri前缀部分
        
        // 判断是否是编辑,返回id号
        $data['is_edit'] = $id;

        \Phpcmf\Service::V()->assign($data);

        return [$this->_tpl_filename('post', $id ? 'edit' : ''), $data];
    }

    /**
     * 回调保存或添加结果
     * */
    protected function _Call_Post($data) {
        return dr_return_data(1, dr_lang('操作成功'), $data);
    }

    /**
     * 显示内容
     * $id      内容id,新增为0,否则视为修改
     * */
    protected function _Show($id) {

        // 获取数据
        $data = $this->_Data($id);
        // 内容不存在
        if (!$data) {
            return [null, null];
        }

        // 初始化自定义字段类
        \Phpcmf\Service::L('Field')->app(APP_DIR);

        // 获取自定义字段表单控件
        list($my_field, $sys_field, $diy_field, $cat_field) = $this->_field_group($data);
        $data['myfield'] = \Phpcmf\Service::L('Field')->toform($id, $my_field, $data, 1);
        $data['sysfield'] = \Phpcmf\Service::L('Field')->toform($id, $sys_field, $data, 1);
        $data['diyfield'] = \Phpcmf\Service::L('Field')->toform($id, $diy_field, $data, 1);
        $data['catfield'] = \Phpcmf\Service::L('Field')->toform($id, $cat_field, $data, 1);

        $fields = $this->field;
        $fields['inputtime'] = ['fieldtype' => 'Date'];
        $fields['updatetime'] = ['fieldtype' => 'Date'];

        // 格式化字段
        $page = max(1, (int)\Phpcmf\Service::L('input')->get('page'));
        $data = \Phpcmf\Service::L('Field')->format_value($fields, $data, $page);

        // 获取返回URL
        $data['reply_url'] = \Phpcmf\Service::L('Router')->get_back(\Phpcmf\Service::L('Router')->uri('index'), $this->url_params);
        $data['uriprefix'] = trim(APP_DIR.'/'.\Phpcmf\Service::L('Router')->class, '/'); // uri前缀部分

        \Phpcmf\Service::V()->assign($data);

        return [$this->_tpl_filename('show'), $data];
    }

    /**
     * 批量删除数据
     * $ids
     * $before 删除前执行的操作
     * $after 删除后执行的操作
     * $attach 删除关联附件
     * */ 
    protected function _Del($ids, $before = null, $after = null, $attach = 0) {

        if (!$ids) {
            $this->_json(0, dr_lang('所选数据不存在'));
        }

        $rows = $this->_db()->init($this->init)->where_in('id', $ids)->getAll();
        if (!$rows) {
            $this->_json(0, dr_lang('所选数据不存在'));
        }

        // 删除之前执行
        if ($before) {
            $rt = call_user_func_array($before, [$rows]);
            if (!$rt['code']) {
                $this->_json(0, $rt['msg']);
            }
            $rt['data'] && $rows = $rt['data'];
        }
        
        // 删除数据
        $ids = [];
        foreach ($rows as $t) {
            $id = intval($t['id']);
            $rt = $this->_db()->init($this->init)->delete($id, $this->delete_where);
            if (!$rt['code']) {
                $this->_json(0, $rt['msg']);
            }
            if ($this->is_data) {
                // 附表存储
                $rt = $this->_db()->init($this->init)->table($this->init['table'].'_data_'.intval($t['tableid']))->delete($id, $this->delete_where);
                if (!$rt['code']) {
                    $this->_json(0, $rt['msg']);
                }
            }
            // 删除附件
            SYS_ATTACHMENT_DB && $attach && \Phpcmf\Service::M('Attachment')->cid_delete($this->member, $id, $attach);
            $ids[] = $id;
        }

        // 删除之后执行
        $after && call_user_func_array($after, [$rows]);

        // 写入日志
        \Phpcmf\Service::L('input')->system_log($this->name.'：删除('.implode(', ', $ids).')');

        // 返回参数
        \Phpcmf\Service::L('Router')->clear_back(\Phpcmf\Service::L('Router')->uri('index'));
        
        $this->_json(1, dr_lang('操作成功'));
    }

    /**
     * 数据列表显示
     * $p      URL指定参数
     * $size   指定分页数据量
     * */
    protected function _List($p = [], $size = 0) {

        // 分页数量控制
        if (!$this->list_pagesize) {
            if (!$size) {
                if (IS_ADMIN) {
                    $size = (int)SYS_ADMIN_PAGESIZE;
                } else {
                    $size = (int)$this->member_cache['config']['pagesize'];
                    if (IS_API_HTTP) {
                        $size = (int)$this->member_cache['config']['pagesize_api'];
                    } elseif (\Phpcmf\Service::IS_MOBILE()) {
                        $size = (int)$this->member_cache['config']['pagesize_mobile'];
                    }
                }
            }
            !$size && $size = 10;
        } else {
            $size = $this->list_pagesize;
        }

        // 按ajax返回
        if (isset($_GET['is_ajax']) && $_GET['is_ajax']) {
            // 按ajax分页
            if (isset($_GET['pagesize']) && $_GET['pagesize']) {
                $size = intval($_GET['pagesize']);
            }
            // 查询数据结果
            list($list, $total, $param) = $this->_db()->init($this->init)->limit_page($size, $this->list_where);
            $sql = $this->_db()->get_sql_query();
            // 格式化字段
            if ($this->init['list_field'] && $list) {
                $field = $this->_field_save(0);
                if ($this->not_field) {
                    $field = dr_array22array($field, $this->not_field);
                }
                $dfield = \Phpcmf\Service::L('Field')->app(APP_DIR);
                foreach ($list as $k => $v) {
                    $list[$k] = $dfield->format_value($field, $v, 1);
                    foreach ($this->init['list_field'] as $i => $t) {
                        if ($t['use']) {
                            $list[$k][$i] = dr_list_function($t['func'], $list[$k][$i], $param, $list[$k], $field[$i], $i);
                        }
                    }
                }
            }
            // 格式化结果集
            $list = $this->_Call_List($list);

            // 存储当前页URL
            unset($param['is_ajax']);
            \Phpcmf\Service::L('Router')->set_back(\Phpcmf\Service::L('Router')->uri(), $param);
            $this->_json(1, $total, $list, '', ['sql' => $sql]);
        }


        $list_field = [];
        // 筛选出可用的字段
        if ($this->init['list_field']) {
            foreach ($this->init['list_field'] as $i => $t) {
                $t['use'] && $list_field[$i] = $t;
            }
        }

        $uriprefix = trim(APP_DIR.'/'.\Phpcmf\Service::L('Router')->class, '/');

        // 默认显示字段
        !$list_field && $this->init['show_field'] && $list_field = [
            $this->init['show_field'] => [
                'name' => dr_lang('主题'),
                'func' => 'title',
                'width' => 0,
            ],
        ];

        if ($this->is_ajax_list && !CI_DEBUG) {

            $param = \Phpcmf\Service::L('input')->get();
            unset($param['s'], $param['c'], $param['m'], $param['d'], $param['page']);

            // 默认以显示字段为搜索字段
            if (!isset($param['field']) && !$param['field']) {
                $param['field'] = isset($this->init['search_first_field']) && $this->init['search_first_field'] ? $this->init['search_first_field'] : $this->init['show_field'];
            }
            if ($param['keyword']) {
                $param['keyword'] = htmlspecialchars($param['keyword']);
            }

            // 返回数据
            $data = [
                'param' => dr_htmlspecialchars($param),
                'my_file' => $this->_tpl_filename('table'),
                'uriprefix' => trim(APP_DIR.'/'.\Phpcmf\Service::L('Router')->class, '/'), // uri前缀部分
                'list_field' => $list_field, // 列表显示的可用字段
            ];
        } else {
            // 查询数据结果
            list($list, $total, $param) = $this->_db()->init($this->init)->limit_page($size, $this->list_where);
            $p && $param = $p + $param;
            $sql = $this->_db()->get_sql_query();

            // 默认以显示字段为搜索字段
            if (!isset($param['field']) && !$param['field']) {
                $param['field'] = isset($this->init['search_first_field']) && $this->init['search_first_field'] ? $this->init['search_first_field'] : $this->init['show_field'];
            }
            // 分页URL格式
            $this->url_params && $param = dr_array22array($param, $this->url_params);
            $uri = \Phpcmf\Service::L('Router')->uri();
            $url = IS_ADMIN ? \Phpcmf\Service::L('Router')->url($uri, $param) : \Phpcmf\Service::L('Router')->member_url($uri, $param);
            $url = $url.'&page={page}';

            // 分页输出样式
            $config = $this->_Page_Config();

            // 存储当前页URL
            \Phpcmf\Service::L('Router')->set_back(\Phpcmf\Service::L('Router')->uri(), $param);

            // 查询表名称
            $list_table = \Phpcmf\Service::M()->dbprefix($this->init['table']);
            if (isset($this->init['join_list'][0]) && $this->init['join_list'][0]) {
                $list_table.= ','.\Phpcmf\Service::M()->dbprefix($this->init['join_list'][0]);
            }
            // 格式化字段
            if ($list) {
                $field = $this->_field_save(0);
                if ($this->not_field) {
                    $field = dr_array22array($field, $this->not_field);
                }
                $dfield = \Phpcmf\Service::L('Field')->app(APP_DIR);
                foreach ($list as $k => $v) {
                    $list[$k] = $dfield->format_value($field, $v, 1);
                }
            }
            // 格式化结果集
            $list = $this->_Call_List($list);

            // 返回数据
            $data = [
                'list' => $list,
                'total' => $total,
                'param' => dr_htmlspecialchars($param),
                'mypages' => \Phpcmf\Service::L('input')->table_page($url, $total, $config, $size),
                'my_file' => $this->_tpl_filename('table'),
                'uriprefix' => $uriprefix, // uri前缀部分
                'list_field' => $list_field, // 列表显示的可用字段
                'list_query' => urlencode(dr_authcode($sql, 'ENCODE')), // 查询列表的sql语句
                'list_table' => $list_table, // 查询列表的数据表名称
                'extend_param' => $p, // 附加参数
            ];
        }

        !$this->mytable && $this->mytable = [
            'foot_tpl' => $this->_is_admin_auth('del') ? '<label class="table_select_all"><input onclick="dr_table_select_all(this)" type="checkbox"><span></span></label>
        <button type="button" onclick="dr_table_option(\''.(IS_ADMIN ? dr_url($uriprefix.'/del') : dr_member_url($uriprefix.'/del')).'\', \''.dr_lang('你确定要删除它们吗？').'\')" class="btn red btn-sm"> <i class="fa fa-trash"></i> '.dr_lang('删除').'</button>' : '',
            'link_tpl' => $this->_is_admin_auth('edit') ? '<label><a href="'.(IS_ADMIN ? dr_url($uriprefix.'/edit') : dr_member_url($uriprefix.'/edit')).'&id={id}" class="btn btn-xs red"> <i class="fa fa-edit"></i> '.dr_lang('修改').'</a></label>' : '',
            'link_var' => 'html = html.replace(/\{id\}/g, row.id);',
        ];
        $data['mytable'] = $this->mytable;
        $data['mytable_name'] = $this->name ? $this->name : 'mytable';
        $data['mytable_pagesize'] = $size;
        $data['is_search'] = $this->is_search;
        $data['is_show_export'] = true;
        $data['is_fixed_columns'] =  $this->is_fixed_columns;
        $data['is_show_search_bar'] = $this->is_show_search_bar;



        \Phpcmf\Service::V()->assign($data);

        return [$this->_tpl_filename('list'), $data];
    }

    /**
     * 回调结果集
     * */
    protected function _Call_List($data) {
        return $data;
    }

    // 分页配置文件加载
    protected function _Page_Config() {

        if (IS_ADMIN) {
            // 后台的分页配置
            $config = require CMSPATH.'Config/Apage.php';
        } else {
            // 用户中心的分页配置
            $file = 'page/'.(\Phpcmf\Service::IS_PC() ? 'pc' : 'mobile').'/member.php';
            if (is_file(WEBPATH.'config/'.$file)) {
                $config = require WEBPATH.'config/'.$file;
            } elseif (is_file(CONFIGPATH.$file)) {
                $config = require CONFIGPATH.$file;
            } else {
                //exit('无法找到分页配置文件【'.$file.'】');
                $config = require CMSPATH.'Config/Apage.php';
            }
        }

        return $config;
    }

    // 获取模板文件名 name模板文件；fname为优先的模板
    public function _tpl_filename($name, $fname = '') {

        $my_file = '';
        if (IS_ADMIN) {
            // 存在优先模板
            if ($fname) {
                $my_file = is_file($this->admin_tpl_path.$this->tpl_name.'_'.$fname.'.html') ? $this->tpl_name.'_'.$fname.'.html' : $this->tpl_prefix.$fname.'.html';
            }
            // 优先模板不存在的情况下
            if (!$my_file || !is_file($this->admin_tpl_path.$my_file)) {
                $my_file = is_file($this->admin_tpl_path.$this->tpl_name.'_'.$name.'.html') ? $this->tpl_name.'_'.$name.'.html' : $this->tpl_prefix.$name.'.html';
            }
            \Phpcmf\Service::V()->admin($this->admin_tpl_path, $this->fix_admin_tpl_path);
			return $my_file;
        } else {
            $path = dr_tpl_path();
            // 存在优先模板
            if ($fname) {
                $my_file = is_file($path.$this->tpl_name.'_'.$fname.'.html') ? $this->tpl_name.'_'.$fname.'.html' : $this->tpl_prefix.$fname.'.html';
            }
            // 优先模板不存在的情况下
            if (!$my_file || !is_file($path.$my_file)) {
                $my_file = is_file($path.$this->tpl_name.'_'.$name.'.html') ? $this->tpl_name.'_'.$name.'.html' : $this->tpl_prefix.$name.'.html';
            }
        }

        return $my_file;
    }

}
