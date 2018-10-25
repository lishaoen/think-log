# think-log

写入LOG日志，支持文件。

安装
~~~
composer require lishaoen/think-log
~~~

用法：
~~~php
$config = [
    //应用名称
    'app_name'     => '',
    // 日志记录方式，内置 file socket 支持扩展
    'type'         => 'File',
    // 日志保存目录
    'path'         => './runtime/logs/',
    //日志的时间格式，默认是` c `
    'time_format'   =>'Y-m-d H:i:s',
    // 是否JSON格式记录
    'json'         => true,
    //单个日志文件的大小限制，超过后会自动记录到第二个文件
    'file_size'     =>2097152,
    //是否关闭日志写入
    'close'        => true,
    //允许日志写入的授权key
    'allow_key'    => '',
    // 日志记录级别
    'level'        => [],
    //是否单一文件日志
    'single'      => false,
    //独立记录的日志级别
    'apart_level' => [],
    //最大日志文件数（超过自动清理)
    'max_files'   => 0,
];

$log = new \lishaoen\log\Log($config,$custominfo);

$log->record('error info','error', $context = [],$custominfo = []);
$log->error('error info', $context = [],$custominfo = []);
$log->info('log info', $context = [],$custominfo = []);
$log->save();
或
$log->record($msg='error info',$type ='error', $context = [],$custominfo = [])->save();
$log->error($msg='error info',$context = [],$custominfo = [])->save();


方法参数说明：

/**
 * @param  string $msg       日志信息
 * @param  string $type      日志级别
 * @param  array  $log_custom 日志追加自定义数组字段信息
 * @param  array  $context   替换内容
 */

~~~

