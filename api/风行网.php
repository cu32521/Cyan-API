<?php
/*
风行视频解析接口
功能：解析风行视频链接，获取视频直链和标题
/api/风行网.php?url=https://www.fun.tv/vplay/g-1114887/
参数：
url=风行视频链接（支持多种格式）
返回示例：
{
    "code": 200,
    "msg": "解析成功",
    "data": {
        "title": "视频标题",
        "url": "视频直链地址"
    }
}
或错误时：
{
    "code": 400,
    "msg": "错误信息"
}
*/

error_reporting(0);
header('Access-Control-Allow-Origin:*');
header('Content-Type:application/json;charset=utf-8');

/**
 * 生成随机中国大陆IP地址
 * 用于模拟真实用户请求
 */
function fake_china_ip() {
    $prefixes = [
        '116.78', '117.136', '120.232', '121.33', '123.157',
        '183.230', '223.104', '112.97', '114.247', '101.226',
        '27.184', '60.217', '124.160', '119.147', '221.238',
        '113.240', '125.88', '182.140', '111.173', '106.89'
    ];
    $prefix = $prefixes[array_rand($prefixes)];
    return $prefix . '.' . rand(0, 255) . '.' . rand(0, 255);
}

// 获取并解码URL参数
$url = isset($_REQUEST['url']) ? urldecode($_REQUEST['url']) : '';

// 检查URL参数是否为空
if (empty($url)) {
    exit(json_encode(
        ['code' => 403, 'msg' => '缺少参数 url'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ));
}

$mid = $num = $vid = null;

// 解析 URL 类型
// 格式1: http://www.fun.tv/vplay/?g=XXXX
if (strpos($url, 'http://www.fun.tv/vplay/?g=') !== false) {
    $queryPart = parse_url($url, PHP_URL_QUERY);
    parse_str($queryPart, $queryArray);
    $mid = $queryArray['g'] ?? null;
    $num = $queryArray['e'] ?? 1;
} 
// 格式2: https://www.fun.tv/vplay/g-XXXX.v-YYYY/
elseif (strpos($url, 'https://www.fun.tv/vplay/g-') !== false) {
    $path = parse_url($url, PHP_URL_PATH);
    if (preg_match('#/vplay/g-(\d+)\.v-(\d+)/#', $path, $matches)) {
        $mid = $matches[1];
        $vid = $matches[2];
    } elseif (preg_match('#/vplay/g-(\d+)#', $path, $matches)) {
        $mid = $matches[1];
        $num = 1;
    }
} 
// 格式3: http://m.fun.tv/mplay/?mid=XXXX
elseif (strpos($url, 'http://m.fun.tv/mplay/?mid=') !== false) {
    $queryPart = parse_url($url, PHP_URL_QUERY);
    parse_str($queryPart, $queryArray);
    $mid = $queryArray['mid'] ?? null;
    $num = $queryArray['num'] ?? 1;
}

// 若无 vid，则通过 mid + num 获取
if (empty($vid) && !empty($mid)) {
    $vid = get_vid($mid, $num ?: 1);
}

if (empty($vid)) {
    exit(json_encode(
        ['code' => -200, 'msg' => '无法获取视频ID'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ));
}

// 获取视频标题
$GETUrl = curl("https://api1.fun.tv/ajax/new_playinfo/gallery/" . $vid);
$json_data = json_decode($GETUrl, true);
$title = $json_data['data']['name_cn'] ?? '未知视频';

// 获取 infohash
$hashUrl = 'https://pm.funshion.com/v5/media/play?id=' . $vid;
$json_data = json_decode(curl($hashUrl), true);
$hash = null;
if (!empty($json_data['mp4'])) {
    $hash = $json_data['mp4'][1]['infohash'] ?? $json_data['mp4'][0]['infohash'];
}

if (empty($hash)) {
    exit(json_encode(
        ['code' => -200, 'msg' => '解析失败，请等待维护'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ));
}

// 获取CDN地址
$cdnUrl = "https://papi.funshion.com/api/edgeips?customer=funshion&fudid=&ve=3.1.5&os=ios&cl=wx_miniapp_fun&cp=fnbu98k&uc=99&infohash=" . urlencode($hash) . "&codec=h.264&mtype=media";
$GETUrl = curl($cdnUrl);
$json_data = json_decode($GETUrl, true);
$MP4_url = null;
if (!empty($json_data['cdn_urls'])) {
    $MP4_url = $json_data['cdn_urls'][0]['url'] ?? ($json_data['cdn_urls'][1]['url'] ?? null);
}

if (empty($MP4_url)) {
    exit(json_encode(
        ['code' => -200, 'msg' => '解析失败'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ));
}

// 返回成功结果
exit(json_encode([
    'code' => 200,
    'msg' => '解析成功',
    'data' => [
        'title' => $title,
        'url' => $MP4_url
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));


// ==================== 工具函数 ====================

/**
 * 根据剧集ID和集数获取视频ID
 * @param string $mid 剧集ID
 * @param int $num 集数
 * @return string|null 视频ID
 */
function get_vid($mid, $num) {
    $url = "https://pm.funshion.com/v5/media/episode?id=" . $mid . "&num=" . $num;
    $res = curl($url);
    $data = json_decode($res, true);
    if (!empty($data['episodes']) && isset($data['episodes'][$num - 1]['id'])) {
        return $data['episodes'][$num - 1]['id'];
    }
    return null;
}

/**
 * 提取文本中间部分
 * @param string $原文本 原始文本
 * @param string $左边文本 左边分隔符
 * @param string $右边文本 右边分隔符
 * @return string 提取的文本
 */
function 取文本中间($原文本, $左边文本, $右边文本) {
    $parts = explode($左边文本, $原文本);
    if (count($parts) < 2) return '';
    $text_one = $parts[1];
    $parts2 = explode($右边文本, $text_one);
    return $parts2[0];
}

/**
 * CURL请求函数
 * @param string $url 请求URL
 * @param array $paras 请求参数
 * @return mixed 请求结果
 */
function curl($url, $paras = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $fakeIP = fake_china_ip();
    if (isset($paras['Header'])) {
        $Header = $paras['Header'];
    } else {
        $Header = [
            "Accept: */*",
            "Accept-Encoding: gzip,deflate,sdch",
            "Accept-Language: zh-CN,zh;q=0.8",
            "Connection: close",
            "X-Forwarded-For: " . $fakeIP,
            "Client-IP: " . $fakeIP
        ];
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $Header);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $paras['ctime'] ?? 30);
    if (isset($paras['rtime'])) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $paras['rtime']);
    }
    if (isset($paras['post'])) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paras['post']);
    }
    if (isset($paras['header'])) {
        curl_setopt($ch, CURLOPT_HEADER, true);
    }
    if (isset($paras['cookie'])) {
        curl_setopt($ch, CURLOPT_COOKIE, $paras['cookie']);
    }
    if (isset($paras['refer'])) {
        $referer = ($paras['refer'] == 1) ? 'http://m.qzone.com/infocenter?g_f=' : $paras['refer'];
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    if (isset($paras['ua'])) {
        curl_setopt($ch, CURLOPT_USERAGENT, $paras['ua']);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36");
    }
    if (isset($paras['nobody'])) {
        curl_setopt($ch, CURLOPT_NOBODY, 1);
    }
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if (isset($paras['GetCookie'])) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $result = curl_exec($ch);
        preg_match_all("/Set-Cookie: (.*?);/m", $result, $matches);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($result, $headerSize);
        $ret = [
            "cookie" => $matches,
            "body" => $body,
            "Header" => substr($result, 0, $headerSize),
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        ];
        curl_close($ch);
        return $ret;
    }

    $ret = curl_exec($ch);
    if (isset($paras['loadurl'])) {
        $Headers = curl_getinfo($ch);
        $ret = $Headers['redirect_url'] ?? false;
    }
    curl_close($ch);
    return $ret;
}
?>
