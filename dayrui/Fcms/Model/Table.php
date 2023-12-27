<?php namespace Phpcmf\Model;
/**
 * www.xunruicms.com
 * 迅睿内容管理框架系统（简称：迅睿CMS）
 * 本文件是框架系统文件，二次开发时不可以修改本文件，可以通过继承类方法来重写此文件
 **/


// 数据表
class Table extends \Phpcmf\Model {

    // 表结构缓存
    public function cache($siteid = SITE_ID, $module = null) {

        $cache = [];
        $paytable = []; // 付款表名
        // 生成模块表结构
        if (dr_is_use_module()) {
            $obj = \Phpcmf\Service::M('module', 'module');
            if (method_exists($obj, 'paytable')) {
                list($cache, $paytable) = $obj->paytable($cache, $paytable, $module, $siteid);
            }
        }

        // 网站表单
        if (dr_is_app('form') && $this->is_table_exists($siteid.'_form')) {
            $obj = \Phpcmf\Service::M('form', 'form');
            if (method_exists($obj, 'paytable')) {
                list($cache, $paytable) = $obj->paytable($cache, $paytable, $siteid);
            }
        }

        // 会员表
        $table = $this->dbprefix('member');
        $cache[$table] = $this->db->getFieldNames($table);

        // 会员附表
        $table = $this->dbprefix('member_data');
        $cache[$table] = $this->db->getFieldNames($table);

        // 缓存表结构
        \Phpcmf\Service::L('cache')->set_file('table-'.$siteid, $cache);

        // 缓存的字段类型
        //$type = ['Select', 'Checkbox', 'Radio', 'Pay', 'Pays', 'File', 'Files', 'Image', 'Images', 'Ftable'];
        $cache = [];
        $field = $this->db->table('field')->where('disabled', 0)->orderBy('id ASC')->get()->getResultArray();
        if ($field) {
            foreach ($field as $f) {
                $f['setting'] = dr_string2array($f['setting']);
                $cache[$f['id']] = $f;
            }
        }
        \Phpcmf\Service::L('cache')->set_file('table-field', $cache);

        // 缓存付款表
        \Phpcmf\Service::L('cache')->set_file('table-pay-'.$siteid, $paytable);
        /*
         * $paytable字段主键为 自定义字段rname-rid
         * */
    }

    // 获取字段结构
    public function get_field($table) {
        return $this->db->getFieldNames($this->dbprefix($table));
    }

    // 获取缓存的字段结构
    public function get_cache_field($table) {

        $tableinfo = \Phpcmf\Service::L('cache')->get('table-'.SITE_ID);
        if (!$tableinfo) {
            // 没有表结构缓存时返回空
            return [];
        }

        return isset($tableinfo[$this->dbprefix($table)]) ? $tableinfo[$this->dbprefix($table)] : [];
    }
    
    // 执行批量sql
    public function _query($sql, $replace = []) {

        $replace[0][] = '{dbprefix}';
        $replace[1][] = $this->db->DBPrefix;

        $todo = [];
        $count = 0;
        $sql_data = explode(';SQL_FINECMS_EOL', trim(str_replace(array(PHP_EOL, chr(13), chr(10)), 'SQL_FINECMS_EOL', str_replace($replace[0], $replace[1], $sql))));
        if ($sql_data) {
            foreach($sql_data as $query){
                if (!$query) {
                    continue;
                }
                $ret = '';
                $queries = explode('SQL_FINECMS_EOL', trim($query));
                foreach($queries as $query) {
                    $ret.= $query[0] == '#' || $query[0].$query[1] == '--' ? '' : $query;
                }
                $ret = trim($ret);
                if (!$ret) {
                    continue;
                }
                if ($this->db->simpleQuery(dr_format_create_sql($ret))) {
                    $todo[] = $ret;
                    $count++;
                } else {
                    $rt = $this->db->error();
                    return dr_return_data(0, $rt['message'].'<br> '.$ret);
                }
            }
        }
        
        return dr_return_data(1, '', [$count, $todo]);
    }

    // 网站表单--------------------------------------------------------------------

    // 创建
    public function create_form($data) {
        if (dr_is_app('form')) {
            \Phpcmf\Service::M('form', 'form')->create_form($data);
        }
    }

    // 删除表单
    public function delete_form($data) {
        if (dr_is_app('form')) {
            \Phpcmf\Service::M('form', 'form')->delete_form_table($data);
        }
    }

    // 模块表单--------------------------------------------------------------------

    // 创建
    public function create_module_form($data) {
        if (dr_is_app('mform')) {
            \Phpcmf\Service::M('mform', 'mform')->create_module_form($data);
        }
    }
    
    // 删除模块表单
    public function delete_module_form($data) {
        if (dr_is_app('mform')) {
            \Phpcmf\Service::M('mform', 'mform')->delete_module_form($data);
        }
    }

    // 项目--------------------------------------------------------------------

    // 创建项目
    public function create_site($siteid) {



    }
    
}