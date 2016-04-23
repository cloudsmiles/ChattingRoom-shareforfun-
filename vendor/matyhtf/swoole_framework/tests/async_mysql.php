<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$config = array(
    'host' => '127.0.0.1',
    'user' => 'root',
    'password' => 'root',
    'database' => 'test',
);
$pool = new Swoole\Async\MySQL($config, 100);
for ($i = 0; $i < 10; $i++)
{
    $pool->query("INSERT INTO `test`.`userinfo` (`id`, `name`, `passwd`, `regtime`, `lastlogin_ip`) VALUES ('0', 'womensss', 'world', '2015-06-15 13:50:34', '4')", function ($mysqli, $result)
    {
        var_dump($result);
    });
}
