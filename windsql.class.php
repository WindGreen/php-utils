<?php
/**
 * 数据库辅助类
 * 待完善功能：
 * > 根据数据库表字段过滤输入字段
 */

//echo M('test')->alias('tt')->join('right','user','ut',['tt.id'=>'ut.id','tt.name'=>'ut.name'])->order(['time'=>'desc','id'=>'asc'])->buildSql('select');
//echo M('test')->data([['name'=>1],['name'=>'haha']])->buildSql('insert');

function M($table,$db=null,$actionList=null){
    if(is_null($db)){
        global $db;
    }
    $wind = new WindSql($db,$actionList);
    return $wind->table($table);
}

/**
 * 将一个二维数组的某个字段设置为主键
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2016-11-16T14:49:32+0800
 * @param    [type]                     $array [description]
 * @param    [type]                     $key    [description]
 * @param    [type]                     $multiply    false二维数组 true三维数组 需要重复数据用true
 * @return   [type]                            [description]
 */
function selectKey($array,$key,$multiply=false){
    $temp=array();
    foreach ($array as $value) {
        if($multiply)
            $temp[$value[$key]][]=$value;
        else
            $temp[$value[$key]]=$value;
    }
    return $temp;
}

function convertArrayEncoding($arr,$toEncoding='utf-8',$fromEncoding=null){
    foreach ($arr as $key => $value) {
        $newKey=mb_convert_encoding($key, $toEncoding,$fromEncoding);
        if(is_array($value)){
            $arr[$newKey]=convertArrayEncoding($value,$toEncoding,$fromEncoding);
        }else $arr[$newKey]=mb_convert_encoding($value, $toEncoding,$fromEncoding);
        if($newKey!=$key) unset($arr[$key]);
    }
    return $arr;
}

class WindSql{
    public $db;
    protected $sql='';
    protected $sqlArr=array(
        'table_alias'=>'',
        'join'=>'',
    );

    public $actionList=array(
        'select'=>'getall',
        'find'=>'getone',
        'query'=>'query',
        'insert_id'=>'insert_id',
        'insert'=>'',
        'update'=>''
    );

    public function __construct($db=null,$actionList=array()){
        if(!is_null($db)) $this->db=$db;
        if(!empty($actionList))
            $this->actionList=$actionList;
    }

    public function table($tableName,$alias=''){
        $str='';
        if(is_string($tableName)) $str="`$tableName`";
        else if(is_array($tableName)){
            foreach ($tableName as $key => $value) {
                $str.="`{$value}`,";
            }
            $str=rtrim($str,',');
        }
        $this->sqlArr['table']=$str;
        if(!empty($alias)) $this->sqlArr['table_alias']=$alias;
        return $this;
    }

    public function alias($name){
        $this->sqlArr['table_alias']="`$name`";
        return $this;
    }

    public function where($condition){
        $str=$this->condition($condition);
        if(!empty($str)) $this->sqlArr['condition']=$str;
        return $this;
    }

    /**
     * 解析where子句
     * 默认：
     * array('a'=>1,'b'=>2) 解析成 a=1 AND b=2
     *
     * _logic指定与或关系(默认AND)：
     * array('_logic'=>'or',a'=>1,'b'=>2) 解析成 a=1 OR b=2
     * 当数组当前层级出现_logic时所有元素之间都是改运算
     *
     * _op指定操作(默认=):
     * array('_op'=>'>','a'=>3) 解析成a>3
     * 当数组当前层级出现_logic时所有元素之间都是该操作
     *
     * _delimiter指定分隔符[用在IN和BETWWEN字句中](默认,):
     * array('a'=>array(_delimiter'=>'AND',2,3),'_op'=>'BETWEEN') 解析成 a BETWEEN ( 2 AND 3 )
     * array('a'=>array(2,3),'_op'=>'IN') 解析成 a IN ( 2 , 3 )
     *
     * 子查询:
     * array('a'=>1,array('_logic'=>'or',b'=>2,'c'=>3)) 解析成 a=1 AND (b=2 OR c=3)
     *
     * _quoto指定键值是单引号双引号反单引号还是空
     * ['sbjdet.st_id'=>'egis.st_id']               解析成`sbjdet`.`st_id`='egis.st_id'
     * ['sbjdet.st_id'=>'egis.st_id','_quote'=>'']  解析成 sbjdet.st_id = egis.st_id
     *
     * _viscol指定value是否是列名
     * ['sbjdet.st_id'=>'egis.st_id','_viscol'=>true]解析成 `sbjdet`.`st_id` = `egis`.`st_id`
     *
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-11T23:41:05+0800
     * @param    array
     * @return   string
     */
    private function condition($arr){
        if(is_string($arr)) return $arr;
        else if(!is_array($arr)) return false;
        if(empty($arr)) return false;
        $str='';
        $logic='AND';
        $op='=';
        $keyQuote="`";//' "  `
        $varQuote="'";//' "  `
        $valueIsColumn=false;
        if(isset($arr['_logic'])){
            $logic=strtoupper($arr['_logic']);
        }
        if(isset($arr['_op'])){
            $op=$arr['_op'];
        }
        if(isset($arr['_sub'])){
            if(is_array($arr['_sub'])){
                $str .= "( ". $this->condition($arr['_sub']).") {$logic} ";
            }else if(is_string($arr['_sub'])){
                $str.=$arr['_sub'];
            }
        }
        if(isset($arr['_quote'])){
            $keyQuote=$arr['_quote'];
            $varQuote=$arr['_quote'];
        }
        if(isset($arr['_vquote'])){
            $varQuote=$arr['_vquote'];
        }
        if(isset($arr['_kquote'])){
            $keyQuote=$arr['_kquote'];
        }
        if(isset($arr['_viscol']) && $arr['_viscol']){//value is column
            $valueIsColumn=true;
        }
        unset($arr['_logic'],$arr['_op'],$arr['_value'],$arr['_sub'],$arr['_quote'],$arr['_kquote'],$arr['_vquote'],$arr['_viscol']);

        foreach ($arr as $key => $value) {
            if(is_array($value)) {
                if(is_integer($key))  $str.="( ". $this->condition($value).") {$logic} ";
                else{
                    $str.=$this->tableColumn($key,$keyQuote)." {$op} ( ";
                    $delimiter=',';
                    if(isset($value['_delimiter'])) {
                        $delimiter=strtoupper($value['_delimiter']);
                        unset($value['_delimiter']);
                    }
                    foreach ($value as $v) {
                        if($valueIsColumn) $str.=$this->tableColumn($v,$keyQuote);
                        else $str.="{$varQuote}{$v}{$varQuote}";
                        $str.=" {$delimiter} ";
                    }
                    $str=rtrim($str,"{$delimiter} ").' ) ';
                    $str.="{$logic} ";
                }
            }
            else{
                if(is_integer($key)){
                    $str.=" ({$value}) ";
                }else{
                    $str.=$this->tableColumn($key,$keyQuote)." {$op} ";
                    if($valueIsColumn) $str.=$this->tableColumn($value,$keyQuote);
                    else $str.="{$varQuote}{$value}{$varQuote}";
                };
                $str.=" {$logic} ";
            }
        }
        $str=rtrim($str,"{$logic} ");

        return $str;
    }

    private function tableColumn($str,$keyQuote='`',$varQuote='\''){
        if(stripos($str,'.')!==false){
            $pos=stripos($str,'.');
            $tableName=substr($str, 0, $pos);
            $columName=substr($str, $pos+1);
            return $keyQuote.$tableName.$keyQuote.'.'.$keyQuote.$columName.$keyQuote;
        }else{
            return $keyQuote.$str.$keyQuote;
        }
    }

    /**
     * 设置查询的字段
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:27:06+0800
     * @param    [type]                     $field [description]
     * @return   [type]                            [description]
     */
    public function field($field){
        $str='';
        if(is_array($field)){
            foreach ($field as $key => $value) {
                if(is_string($key))
                    $str.=$this->tableColumn($key)." `{$value}`,";
                else
                    $str.=$this->tableColumn($value).",";
            }
            $str=rtrim($str,', ');
        }else if(is_string($field)){
            $str=$field;
        }
        $this->sqlArr['field']=$str;
        return $this;
    }

    /**
     * 插入和更新数据的解析
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:23:31+0800
     * @param    string,array[],array[][]                     $data 待拆入数据，字符串或一维数组或二维数组
     * @return   [type]                           [description]
     */
    public function data($data){
        $str1='';
        $str2='';
        if(is_array($data)){
            if(is_array(current($data))){//二维数组 多组数据 只可能是插入
                //INSERT SQL
                $str1.='( `'.implode('`,`',array_keys(current($data))).'` ) ';
                $str1.=' VALUES ';
                foreach ($data as $key => $value) {
                    $str1.="( '".implode("','", $value)."' ), ";
                }
                $str1=trim($str1,', ');
            }else{//一维数组 单组数据
                //INSERT SQL
                $str1.='( `'.implode('`,`',array_keys($data)).'` ) ';
                $str1.=' VALUES ';
                $str1.="( '".implode("','", $data)."' ) ";
                //UPDATE SQL
                $str2='';
                foreach ($data as $key => $value) {
                    $str2.="`{$key}`='{$value}', ";
                }
                $str2=rtrim($str2,', ');
            }
        }else{
            $str1=$str2=$data;
        }

        $this->sqlArr['insert_values']=$str1;
        $this->sqlArr['update_values']=$str2;
        return $this;
    }

    /**
     * join语句
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-15T18:28:07+0800
     * @param    string                     $way        INNER OUTER LEFT RIGHT
     * @param    [type]                     $table      table名
     * @param    [type]                     $tableAlias table别名
     * @param    [type]                     $condition  ON条件
     * @return   [type]                                 [description]
     */
    public function join($way='INNER',$table,$tableAlias,$condition){
        $way=strtoupper($way);
        $condt='';
        if(is_array($condition)){
            /*
            foreach ($condition as $key => $value) {
                $condt.="{$key}={$value} AND ";
            }
            $condt=rtrim($condt,'AND ');
            */
           $condition['_viscol']=true;
           $condt=$this->condition($condition);
        }else if(is_string($condition)){
            $condt=$condition;
        }
        $str="{$way} JOIN `{$table}` `{$tableAlias}` ON {$condt} ";
        $this->sqlArr['join'].=$str;
        return $this;
    }

    /**
     * order('id DESC') ORDER BY id DESC
     * order(['id'=>'DESC']) ORDER BY `id` DESC
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-12-08T15:18:20+0800
     * @param    [type]                     $condition [description]
     * @return   [type]                                [description]
     */
    public function order($condition){
        $str='';
        if(is_array($condition)){
            foreach ($condition as $key => $value) {
                $str.=$this->tableColumn($key)." {$value},";
            }
            $str=rtrim($str,',');
        }else if(is_string($condition)){
            $str=$condition;
        }
        $this->sqlArr['order']=$str;
        return $this;
    }

    /**
     * group('name') GROUP BY name
     * group(['name','school']) GROPU BY `name`,`school`
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-12-08T15:19:13+0800
     * @param    [type]                     $condition [description]
     * @return   [type]                                [description]
     */
    public function group($condition){
        $str='';
        if(is_array($condition)){
            foreach ($condition as $key => $value) {
                $str.=$this->tableColumn($value).",";
            }
            $str=rtrim($str,',');
        }else if(is_string($condition)){
            $str=$condition;
        }
        $this->sqlArr['group']=$str;
        return $this;
    }

    /**
     * 分页
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-12-08T11:33:27+0800
     * @param    [type]                     $start 开始index
     * @param    integer                    $nums  数量
     * @return   [type]                            [description]
     */
    public function limit($start,$nums=-1){
        $startIndex;
        if(is_array($start)){
            $startIndex=$start[0];
            $nums=$start[1];
        }else $startIndex=$start;
        $this->sqlArr['limit_start']=$startIndex;
        $this->sqlArr['limit_nums']=$nums;
        return $this;
    }

    /**
     * 获取列表
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:26:49+0800
     * @return   [type]                     [description]
     */
    public function select(){
        $sql=$this->buildSql('select');
        if(method_exists($this->db, $this->actionList['select'])){
            $select=$this->actionList['select'];
            $result = $this->db->$select($sql);
            return $result;
        }
    }

    /**
     * 查找单个元素
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:26:32+0800
     * @param    [type]                     $id [description]
     * @return   [type]                         [description]
     */
    public function find($id=null){
        if(!is_object(($this->db))) return 'database is empty';
        $sql=$this->buildSql('select');
        if(method_exists($this->db, $this->actionList['find'])){
            $find=$this->actionList['find'];
            return $this->db->$find($sql);
        }
    }

    /**
     * 数据更新
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:24:22+0800
     * @param    [type]                     $data [description]
     * @return   [type]                           [description]
     */
    public function update($data){
        if(!is_object(($this->db))) return 'database is empty';
        if(!empty($data)){
            $this->data($data);
        }
        $sql=$this->buildSql('update');
        if(method_exists($this->db, $this->actionList['update'])){
            $update=$this->actionList['update'];
            return $this->db->$update($sql);
        }else if(method_exists($this->db, $this->actionList['query'])){
            $query=$this->actionList['query'];
            return $this->db->$query($sql);
        }else{
            return 'no action';
        }
    }

    /**
     * 插入操作
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T10:04:18+0800
     * @param    array                      $data [description]
     * @return   [type]                           [description]
     */
    public function insert($data=array()){
        if(!is_object(($this->db))) return 'database is empty';
        if(!empty($data)){
            $this->data($data);
        }
        $sql=$this->buildSql('insert');
        if(method_exists($this->db, $this->actionList['insert'])){
            $insert=$this->actionList['insert'];
            $ret = $this->db->$insert($sql);
        }else if(method_exists($this->db, $this->actionList['query'])){
            $query=$this->actionList['query'];
            $ret = $this->db->$query($sql);
        }else{
            return 'no action';
        }
        if(method_exists($this->db, $this->actionList['insert_id'])){
            $insert_id=$this->actionList['insert_id'];
            return $this->db->$insert_id();
        }
        return $ret;
    }

    public function delete(){
        if(empty($this->sqlArr['condition'])) return false;
        $sql=$this->buildSql('delete');
        if(method_exists($this->db, $this->actionList['delete'])){
            $delete=$this->actionList['delete'];
            $ret = $this->db->$delete($sql);
        }else if(method_exists($this->db, $this->actionList['query'])){
            $query=$this->actionList['query'];
            $ret = $this->db->$query($sql);
        }else{
            return 'no action';
        }
        return $ret;
    }

    /**
     * 组合SQL
     * @Author   WindGreen<yqfwind@163.com>
     * @DateTime 2016-11-12T13:26:05+0800
     * @param    [type]                     $action [description]
     * @return   [type]                             [description]
     */
    public function buildSql($action){
        //字段检查
        if(empty($this->sqlArr['field'])) $this->sqlArr['field']='*';
        //$sql="{$this->sqlArr['action']} ";
        switch ($action) {
            case 'select':
                $sql="SELECT {$this->sqlArr['field']} FROM {$this->sqlArr['table']} {$this->sqlArr['table_alias']}".
                    " {$this->sqlArr['join']}";
                break;
            case 'insert':
                $sql="INSERT INTO {$this->sqlArr['table']} {$this->sqlArr['insert_values']}";
                break;
            case 'update':
                $sql="UPDATE {$this->sqlArr['table']} SET {$this->sqlArr['update_values']}";
                break;
            case 'delete':
                $sql="DELETE FROM {$this->sqlArr['table']}";
            default:
                # code...
                break;
        }
        //WHERE
        if(!empty($this->sqlArr['condition'])) $sql.=" WHERE {$this->sqlArr['condition']}";
        //group
        if(!empty($this->sqlArr['group'])) $sql.=" GROUP BY {$this->sqlArr['group']}";
        //order
        if(!empty($this->sqlArr['order'])) $sql.=" ORDER BY {$this->sqlArr['order']}";
        //limit
        if(!empty($this->sqlArr['limit_start'])) $sql.=" LIMIT {$this->sqlArr['limit_start']},{$this->sqlArr['limit_nums']}";
        $this->sql=$sql;
        return $this->sql;
    }

    public function query($sql){
        if(method_exists($this->db, $this->actionList['query'])){
            $query=$this->actionList['query'];
            $ret = $this->db->$query($sql);
            return $ret;
        }
        return false;
    }

}
