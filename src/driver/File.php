<?php
// +----------------------------------------------------------------------
// | think_lishaoen [基于ThinkPHP_V5.1开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 lishaoen.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: lishaoen <lishaoenbh@qq.com>
// +----------------------------------------------------------------------
namespace lishaoen\log\driver;

/**
 * 本地化输出到文件
 */
class File
{
    protected $config = [
        // 时间记录格式
        'time_format' => ' c ',
        //是否单一文件日志
        'single'      => false,
        //// 日志文件大小限制（超出会生成多个文件）
        'file_size'   => 2097152,
        // 日志储存路径
        'path'        => '',
        //独立记录的日志级别
        'apart_level' => [],
        //最大日志文件数（超过自动清理)
        'max_files'   => 0,
        // 是否JSON格式记录
        'json'        => false,
    ];

    protected $request;


    // 实例化并传入参数
    public function __construct($config = [])
    {
        $this->request = new \lishaoen\log\Request();
        
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
        if (empty($this->config['path'])) {
            $this->config['path'] = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }
    }

    /**
     * 日志写入接口
     * @access public
     * @param  array    $log    日志信息
     * @param  array    $arr_custom 日志追加自定义数组字段信息
     * @param  bool     $append 是否追加请求信息
     * @return bool
     */
    public function save(array $log = [], $arr_custom = [], $append = false)
    {
        $destination = $this->getMasterLogFile();

        $path = dirname($destination);
        !is_dir($path) && mkdir($path, 0755, true);

        $info = [];
        foreach ($log as $type => $val) {
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }

                $info[$type][] = $this->config['json'] ? $msg : '[ ' . $type . ' ] ' . $msg;
            }

            if (!$this->config['json'] && (true === $this->config['apart_level'] || in_array($type, $this->config['apart_level']))) {
                // 独立记录的日志级别
                $filename = $this->getApartLevelFile($path, $type);

                $this->write($info[$type], $filename, $arr_custom, true, $append);

                unset($info[$type]);
            }
        }

        if ($info) {
            return $this->write($info, $destination, $arr_custom, false, $append);
        }

        return true;
    }

    /**
     * 日志写入
     * @access protected
     * @param  array     $message 日志信息
     * @param  string    $destination 日志文件
     * @param  array    $arr_custom 日志追加自定义数组字段信息
     * @param  bool      $apart 是否独立文件写入
     * @param  bool      $append 是否追加请求信息
     * @return bool
     */
    protected function write($message, $destination, $log_custom = [], $apart = false, $append = false)
    {
        // 检测日志文件大小，超过配置大小则备份日志文件重新生成
        $this->checkLogSize($destination);
        // 日志信息封装
        foreach ($message as $type => $msg) {
            $info['type']     = $type;
            $info['message']  = is_array($msg) ? implode("|", $msg) : $msg;

            $info[$type]      = is_array($msg) ? implode("|", $msg) : $msg;
        }

        if(!empty($log_custom)){
            $info = $info + $log_custom;
        }
        if (PHP_SAPI == 'cli') {
            $message = $this->parseCliLog($info);
        } else {
            $message = $this->parseLog($info);
        }
        return error_log($message, 3, $destination);
    }

    /**
     * 获取主日志文件名
     * @access public
     * @return string
     */
    protected function getMasterLogFile()
    {
        if ($this->config['max_files']) {
            $files = glob($this->config['path'] . '*.log');

            try {
                if (count($files) > $this->config['max_files']) {
                    unlink($files[0]);
                }
            } catch (\Exception $e) {
            }
        }

        if ($this->config['single']) {
            $name = is_string($this->config['single']) ? $this->config['single'] : 'single';

            $destination = $this->config['path'] . $name . '.log';
        } else {
            $cli = PHP_SAPI == 'cli' ? '_cli' : '';

            if ($this->config['max_files']) {
                $filename = date('Ymd') . $cli . '.log';
            } else {
                $filename = date('Ym') . DIRECTORY_SEPARATOR . date('d') . $cli . '.log';
            }

            $destination = $this->config['path'] . $filename;
        }

        return $destination;
    }

    /**
     * 获取独立日志文件名
     * @access public
     * @param  string $path 日志目录
     * @param  string $type 日志类型
     * @return string
     */
    protected function getApartLevelFile($path, $type)
    {
        $cli = PHP_SAPI == 'cli' ? '_cli' : '';

        if ($this->config['single']) {
            $name = is_string($this->config['single']) ? $this->config['single'] : 'single';

            $name .= '_' . $type;
        } elseif ($this->config['max_files']) {
            $name = date('Ymd') . '_' . $type . $cli;
        } else {
            $name = date('d') . '_' . $type . $cli;
        }

        return $path . DIRECTORY_SEPARATOR . $name . '.log';
    }

    /**
     * 检查日志文件大小并自动生成备份文件
     * @access protected
     * @param  string    $destination 日志文件
     * @return void
     */
    protected function checkLogSize($destination)
    {
        if (is_file($destination) && floor($this->config['file_size']) <= filesize($destination)) {
            try {
                rename($destination, dirname($destination) . DIRECTORY_SEPARATOR . time() . '-' . basename($destination));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * CLI日志解析
     * @access protected
     * @param  array     $info 日志信息
     * @return string
     */
    protected function parseCliLog($info)
    {
        if ($this->config['json']) {
            $message = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n";
        } else {
            $now = $info['date'];
            unset($info['date']);

            $message = implode("\r\n", $info);

            $message = "[{$now}]" . $message . "\r\n";
        }

        return $message;
    }

    /**
     * 解析日志
     * @access protected
     * @param  array     $info 日志信息
     * @return string
     */
    protected function parseLog($info)
    {
        //请求基础信息
        $request_info = [
            'date'         => date($this->config['time_format']),
            'requestid'    => md5(time() + rand(0,9999)),
            'ip'           => $this->request->ip($type = 0, $adv = true),
            'domain'       => $this->request->domain($port = true),
            'host'         => $this->request->host($strict = false),
            'method'       => $this->request->method($origin = false),
            'uri'          => $this->request->url($complete = true),
            'user_agent'   => $this->request->header('user-agent'), 
            'request_info' => $this->request->request(),
            'header_info'  => $this->request->header($name = '', $default = null),
        ];
        
        $info = $request_info + $info;
        
        if ($this->config['json']) {
            return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n";
        }

        $delimiter = "---------------------------------  [{$info['date']}] {$info['ip']} {$info['method']} {$info['host']} {$info['uri']}  ------------------------------";
        array_unshift($info,$delimiter);
        unset($info['date'],$info['ip'],$info['method'],$info['host'],$info['uri']);

        return "\r\n" . $this->array2string($info,"\r\n",":" ) . "\r\n";
    }

    /**
     * 将一个键值对数组转变成字符串
     * 
     * @param  array  $array [description]
     * @param  string $sp    键值对分隔符
     * @param  string $kv    键值分隔符
     * @return [type]        [description]
     */
    public function array2string($array=[], $sp="\r\n", $kv="=>")
    {
        $string = [];
        if($array && is_array($array)){
            foreach ($array as $key=> $value){
                if(empty($value)){
                    continue;
                }
                if(is_array($value)){
                    $string[] = $this->array2string($value,$sp,$kv);
                }else{
                    $string[] = $key.$kv.$value;
                }
            }
        }
        return implode($sp,$string);
    }


}
