<?php
// +----------------------------------------------------------------------
// | think_lishaoen [基于ThinkPHP_V5.1开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 lishaoen.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: lishaoen <lishaoenbh@qq.com>
// +----------------------------------------------------------------------
namespace lishaoen\log;

use Exception;

// 实现日志接口
if (interface_exists('Psr\Log\LoggerInterface')) {
    interface LoggerInterface extends \Psr\Log\LoggerInterface
    {}
} else {
    interface LoggerInterface
    {}
}

class Log implements LoggerInterface
{
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    const SQL       = 'sql';
    const LOGIN     = 'login';
    const OPERATION = 'operation';

    /**
     * 日志信息
     * @var array
     */
    protected $log = [];

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 日志写入驱动
     * @var object
     */
    protected $driver;

    /**
     * 日志授权key
     * @var string
     */
    protected $key;

    /**
     * 是否允许日志写入
     * @var bool
     */
    protected $allowWrite = true;

    /**
     * 自定义数组日志信息
     * @var array
     */
    protected $log_custom = [];

    /**
     * 实例化并传入参数
     * @param array $config [description]
     */
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        return $this->init($this->config);
    }

    /**
     * 日志初始化
     * @access public
     * @param  array $config
     * @return $this
     */
    public function init($config = [])
    {
        $type = isset($config['type']) ? $config['type'] : 'File';

        $this->config = $config;
        unset($config['type']);

        if (!empty($config['close'])) {
            $this->allowWrite = false;
        }

        $class = false !== strpos($type, '\\') ? $type : '\\lishaoen\\log\\driver\\' . ucwords($type);
        if (class_exists($class)) {
            $this->driver = new $class($config);
        }else{
            throw new Exception('class not exists:' . $class, $class);
        }

        return $this;
    }

    /**
     * 获取日志信息
     * @access public
     * @param  string $type 信息类型
     * @return array
     */
    public function getLog($type = '')
    {
        return $type ? $this->log[$type] : $this->log;
    }

    /**
     * 记录日志信息
     * @access public
     * @param  mixed  $msg       日志信息
     * @param  string $type      日志级别
     * @param  array  $context   替换内容
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @return $this
     */
    public function record($msg, $type = 'info', $log_custom = [], $context = [])
    {
        if (!$this->allowWrite) {
            return;
        }
        if (is_string($msg) && !empty($context)) {
            $replace = [];
            foreach ($context as $key => $val) {
                $replace['{' . $key . '}'] = $val;
            }
            $msg = strtr($msg, $replace);
        }
        
        if (PHP_SAPI == 'cli') {
            // 命令行日志实时写入
            $this->write($msg, $type, $log_custom,true);
        } else {
            $this->log[$type][]           = $msg;
            $this->log_custom[$type][]    = $log_custom;
        }
        
        return $this;
    }

    /**
     * 清空日志信息
     * @access public
     * @return $this
     */
    public function clear()
    {
        $this->log = [];

        return $this;
    }

    /**
     * 当前日志记录的授权key
     * @access public
     * @param  string  $key  授权key
     * @return $this
     */
    public function key($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * 检查日志写入权限
     * @access public
     * @param  array  $config  当前日志配置参数
     * @return bool
     */
    public function check($config)
    {
        if ($this->key && !empty($config['allow_key']) && !in_array($this->key, $config['allow_key'])) {
            return false;
        }

        return true;
    }

    /**
     * 关闭本次请求日志写入
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->allowWrite = false;
        $this->log        = [];

        return $this;
    }

    /**
     * 保存日志调试信息
     * @access public
     * @return bool
     */
    public function save()
    {
        if (empty($this->log) || !$this->allowWrite) {
            return true;
        }

        if (!$this->check($this->config)) {
            // 检测日志写入权限
            return false;
        }
        if (empty($this->config['level'])) { 
            // 获取全部日志
            $log        = $this->log;
            $log_custom = $this->log_custom;
        } else {
            // 记录允许级别
            $log        = [];
            $log_custom = [];
            foreach ($this->config['level'] as $level) {
                if (isset($this->log[$level])) {
                    $log[$level]        = $this->log[$level];
                    $log_custom[$level] = $this->log_custom[$level];
                }

            }
        }

        $result = $this->driver->save($log, $log_custom, true);

        if ($result) {
            $this->log = [];
        }

        return $result;
    }

    /**
     * 实时写入日志信息 并支持行为
     * @access public
     * @param  mixed  $msg   调试信息
     * @param  string $type  日志级别
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  bool   $force 是否强制写入
     * @return bool
     */
    public function write($msg, $type = 'info', $log_custom = [], $force = false)
    {
        // 封装日志信息
        if (empty($this->config['level'])) {
            $force = true;
        }

        if (true === $force || in_array($type, $this->config['level'])) {
            $log[$type][]        = $msg;
            $log_custom[$type][] = $log_custom;
        } else {
            return false;
        }
        // 写入日志
        return $this->driver->save($log, $log_custom, false);
    }

    /**
     * 记录日志信息
     * @access public
     * @param  string $level     日志级别
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function log($level, $message, array $context = [], $log_custom = [])
    {
        $this->record($message, $level, $context, $log_custom);
    }

    /**
     * 记录emergency信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function emergency($message, array $context = [],$log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录警报信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function alert($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录紧急情况
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function critical($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录错误信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function error($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录warning信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function warning($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录notice信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function notice($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录一般信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function info($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function debug($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录sql信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function sql($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录登录信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function login($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    /**
     * 记录操作信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $log_custom 日志追加自定义数组字段信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function operation($message, array $context = [], $log_custom = [])
    {
        $this->log(__FUNCTION__, $message, $context, $log_custom);
    }

    public function __debugInfo()
    {
        $data = get_object_vars($this);

        return $data;
    }
}
