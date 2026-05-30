<?php
$urls = array(
    'https://www.58.tl/city/beijing.html',
    'https://www.58.tl/city/hangzhou.html',
	'https://www.58.tl/city/shenzhen.html',
	'https://www.58.tl/city/shanghai.html',
	'https://www.58.tl/city/guangzhou.html',
	'https://www.58.tl/city/chengdu.html',
	'https://www.58.tl/city/chongqing.html',
	'https://www.58.tl/city/xian.html',
	'https://www.58.tl/city/suzhou.html',
	'https://www.58.tl/city/taiyuan.html',
	'https://www.58.tl/city/wuhan.html',
	'https://www.58.tl/city/nanjing.html',
	'https://www.58.tl/city/hefei.html',
	'https://www.58.tl/city/jinan.html',
	'https://www.58.tl/city/ningbo.html',
	'https://www.58.tl/city/changsha.html',
	'https://www.58.tl/city/qingdao.html',
	'https://www.58.tl/city/tianjin.html',
);
$api = 'http://data.zz.baidu.com/urls?site=https://www.58.tl&token=YOUR_BAIDU_TOKEN';
$ch = curl_init();
$options =  array(
    CURLOPT_URL => $api,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => implode("\n", $urls),
    CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
);
curl_setopt_array($ch, $options);
$result = curl_exec($ch);
echo $result;

?>