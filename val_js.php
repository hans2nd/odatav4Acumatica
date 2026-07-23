<?php
$c = file_get_contents('resources/views/menu-management/index.blade.php');
preg_match_all('/<script>(.*?)<\/script>/s', $c, $m);
$script = end($m[1]);
$script = preg_replace('/\{\{.*?\}\}/', '"BLADE_REPLACED"', $script);
file_put_contents('test_script.js', $script);
exec('node -c test_script.js 2>&1', $out, $ret);
echo implode("\n", $out);
exit($ret);
