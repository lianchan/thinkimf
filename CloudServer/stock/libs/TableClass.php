<?php
/**
 * Created by PhpStorm.
 * User: Echo
 * Date: 2018/4/9
 * Time: 13:22
 */

namespace Swoole\Libs;
class TableClass
{
    public $user;//数据表
    public $client;//数据表
    public $master;//数据表

    public $index;//保存数据表的所有键
    public $count;//计数器

    private $temp;//测试表

    public function __construct($conf = [])
    {
        $user = [];
//        $user[] = ['key' => 'loginid', 'type' => 'int', 'len' => 6];
//        $user[] = ['key' => 'token', 'type' => 'string', 'len' => 64];
//        $user[] = ['key' => 'linkfd', 'type' => 'string', 'len' => 65536];


        $user[] = ['key' => 'ip', 'type' => 'string', 'len' => 15];
        $user[] = ['key' => 'port', 'type' => 'int', 'len' => 4];
        $user[] = ['key' => 'dns', 'type' => 'string', 'len' => 30];
        $user[] = ['key' => 'pid', 'type' => 'int', 'len' => 4];
        $user[] = ['key' => 'mode', 'type' => 'int', 'len' => 2];
        $user[] = ['key' => 'screen', 'type' => 'string', 'len' => 10];
        $user[] = ['key' => 'name', 'type' => 'string', 'len' => 30];

//        $clienttype = [];
//        $client[] = ['key' => 'linktype', 'type' => 'string', 'len' => 15];
//        $client[] = ['key' => 'linkfd', 'type' => 'string', 'len' => 65536];

        $client = [];
        $client[] = ['key' => 'dns', 'type' => 'string', 'len' => 15];
        $client[] = ['key' => 'client', 'type' => 'string', 'len' => 100];

        $master = [];
        $master[] = ['key' => 'id', 'type' => 'int', 'len' => 4];
        $master[] = ['key' => 'dns', 'type' => 'string', 'len' => 15];
        $master[] = ['key' => 'lv', 'type' => 'int', 'len' => 1];

        $index = [];
        $index[] = ['key' => 'keys', 'type' => 'string', 'len' => 65536];

        $count = [];
        $count[] = ['key' => 'send', 'type' => 'int', 'len' => 8];

        $this->user = new \swoole_table(1024);
        $this->client = new \swoole_table(1024);
        $this->master = new \swoole_table(1024);
        $this->index = new \swoole_table(8);
        $this->count = new \swoole_table(8);

        $this->column($this->user, $user);
        $this->column($this->client, $client);
        $this->column($this->master, $master);
        $this->column($this->index, $index);
        $this->column($this->count, $count);

        $this->user->create();
        $this->client->create();
        $this->master->create();
        $this->index->create();
        $this->count->create();
    }

    /**
     * swoole_table的测试
     * @param string $table
     */
    public function test($table = 'temp')
    {
        $count = [];
        $count[] = ['key' => 'name', 'type' => 'string', 'len' => 50];
        $count[] = ['key' => 'title', 'type' => 'string', 'len' => 50];

        $this->{$table} = new \swoole_table(1024);
        $allType = ['int' => \swoole_table::TYPE_INT, 'string' => \swoole_table::TYPE_STRING, 'float' => \swoole_table::TYPE_FLOAT];
        foreach ($count as $row) {
            $this->{$table}->column($row['key'], $allType[$row['type']], $row['len']);
        }
        $this->{$table}->create();

        foreach ([1, 2, 3] as $val) {
            $value = ['title' => "这是第{$val}个标题"];
            $this->{$table}->set("K_{$val}", $value);
            $this->record($table, "K_{$val}");
        }

        foreach ([4, 5, 6] as $val) {
            $value = ['name' => "这是第{$val}个名字"];
            $this->{$table}->set("K_{$val}", $value);
            $this->record($table, "K_{$val}");
        }

        foreach ([7, 8, 9] as $val) {
            $value = ['name' => "这是第{$val}个名字", 'title' => "这是第{$val}个标题"];
            $this->{$table}->set("K_{$val}", $value);
            $this->record($table, "K_{$val}");
        }

        $value = [];
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $val) {
            $value["K_{$val}"] = $this->{$table}->get("K_{$val}");
        }

        $key = $this->record($table);
        $all = $this->getAll($table);
        print_r($value);
        print_r($key);
        print_r($all);
    }


    /**
     * 数据表定义
     * @param     $name
     * @param     $type
     * @param int $len
     */
    public function column(\swoole_table $table, $arr)
    {
        $allType = ['int' => \swoole_table::TYPE_INT, 'string' => \swoole_table::TYPE_STRING, 'float' => \swoole_table::TYPE_FLOAT];
        foreach ($arr as $row) {
            if (!isset($allType[$row['type']])) $row['type'] = 'string';
            $table->column($row['key'], $allType[$row['type']], $row['len']);
        }
    }


    /**
     * 存入【指定表】【行键】【行值】
     * @param       $key
     * @param array $array
     * @return bool
     */
    public function set($table, $key, array $array)
    {
        $this->{$table}->set($key, $this->checkArray($array));
        $this->add($table, 1);
        return $this->record($table, $key);
    }

    /**
     * 存入数据时，遍历数据，二维以上的内容转换JSON
     * @param array $array
     * @return array
     */
    private function checkArray(array $array)
    {
        $value = [];
        foreach ($array as $key => $arr) {
            $value[$key] = is_array($arr) ? json_encode($arr, 256) : $arr;
        }
        return $value;
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array
     */
    public function get($table, $key)
    {
        return $this->{$table}->get($key);
    }


    /**
     * 读取【指定表】所有行键值和记录
     * @param $table
     * @return array
     */
    public function getAll($table)
    {
        $recode = $this->record($table);
        return $this->getKeyValue($table, $recode);
    }

    /**
     * 读取【指定表】【指定键值】的记录
     * @param $table
     * @param $recode
     * @return array
     */
    public function getKeyValue($table, $recode)
    {
        $value = [];
        foreach ($recode as $i => $key) {
            $value[$key] = $this->get($table, $key);
        }
        return $value;
    }

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function getKey($table)
    {
        return $this->record($table);
    }

    /**
     * 记录【某个表】所有记录的键值，或读取【某个表】
     * @param $table
     * @param null $key 不指定为读取
     * @param bool|true $add 加，或减
     * @return array
     */
    private function record($table, $key = null, $add = true)
    {
//        $this->index->lock();
        $oldVal = $this->index->get($table);
        if (!$oldVal) {
            $tmpArr = [];
        } else {
            $tmpArr = explode(',', $oldVal['keys']);
        }
        if ($key === null) {//读取，直接返回
//            $this->index->unlock();
            return $tmpArr;
        }
        if ($add === true) {//加
            $tmpArr[] = $key;
            $tmpArr = array_unique($tmpArr);//过滤重复
        } else {//减
            $tmpArr = array_flip($tmpArr);//交换键值
            unset($tmpArr[$key]);
            $tmpArr = array_flip($tmpArr);//交换回来
        }
        $this->index->set($table, ['keys' => implode(',', $tmpArr)]);
//        return $this->index->unlock();
    }


    /**
     * 删除key
     * @param $key
     * @return bool
     */
    public function del($table, $key)
    {
        $this->{$table}->del($key);
        $this->add($table, -1);
        return $this->record($table, $key, false);
    }

    /**
     * 原子自增操作，可用于整形或浮点型列
     * @param string $TabKey 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function add($TabKey = 'count.send', $incrby = 1)
    {
        if (is_int($TabKey)) list($incrby, $TabKey) = [$TabKey, 'count.send'];
        list($table, $column, $tmp) = explode('.', $TabKey . '.send.');

        if ($incrby >= 0) {
            return $this->count->incr($table, $column, $incrby);
        } else {
            return $this->count->decr($table, $column, 0 - $incrby);
        }
    }

    /**
     * 某表行数
     * @param string $TabKey
     * @return bool
     */
    public function len($TabKey = 'count.send')
    {
        return $this->add($TabKey, 0);
    }


    /**
     * 锁定整个表
     * @return bool
     */
    public function lock($table)
    {
        return $this->{$table}->lock();
    }

    /**
     * 释放表锁
     * @return bool
     */
    public function unlock($table)
    {
        return $this->{$table}->unlock();
    }
}