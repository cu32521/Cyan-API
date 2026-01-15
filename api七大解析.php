<?php
/*
七大解析视频解析接口
功能：七个平台
/api/七大解析.php?id=https://v.douyin.com/Wi9XXye2R0k/
*/


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');


// ===== 访问统计 START =====
$log_dir = dirname(__DIR__) . "/data";
if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);

// 访问日志
$log_file = $log_dir . "/access.log";

// 统计文件
$stat_file = $log_dir . "/stat.json";

// 日志内容
$line = date("Y-m-d H:i:s") . " | IP=" . $_SERVER['REMOTE_ADDR'] . " | URL=" . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents($log_file, $line, FILE_APPEND);

// 统计汇总
$stat = file_exists($stat_file) ? json_decode(file_get_contents($stat_file), true) : [
    "total" => 0,
    "today" => 0,
    "per_api" => []
];

// 总次数 +1
$stat["total"]++;

// 今日统计
$today = date("Y-m-d");
if (!isset($stat["day"]) || $stat["day"] !== $today) {
    $stat["day"] = $today;
    $stat["today"] = 0;
}
$stat["today"]++;

// 当前接口名称
$api = basename(__FILE__);

if (!isset($stat["per_api"][$api])) $stat["per_api"][$api] = 0;
$stat["per_api"][$api]++;

// 保存
file_put_contents($stat_file, json_encode($stat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
// ===== 访问统计 END =====
// ===== 用户调用记录 =====
session_start();

if (isset($_SESSION["user"])) {
    $user = $_SESSION["user"];

    $log_dir = dirname(__DIR__) . "/data/user_logs";
    if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);

    $file = $log_dir . "/$user.log";

    $line = date("Y-m-d H:i:s")
          . " | IP=" . $_SERVER["REMOTE_ADDR"]
          . " | URL=" . $_SERVER["REQUEST_URI"]
          . "\n";

    file_put_contents($file, $line, FILE_APPEND);
}


// 工具函数
function extractMiddleText($text, $startStr, $endStr) {
    $startIndex = strpos($text, $startStr);
    if ($startIndex === false) {
        return null;
    }
    $startIndex += strlen($startStr);
    $endIndex = strpos($text, $endStr, $startIndex);
    return ($endIndex !== false) ? substr($text, $startIndex, $endIndex - $startIndex) : null;
}

function createStandardResponse($success, $mediaType, $items, $title = "", $author = null, $coverUrl = "", $errorMsg = "") {
    $response = [
        "success" => $success,
        "media_type" => $mediaType,
        "items" => $items,
        "title" => $title,
        "author" => $author ?: ["nickname" => "", "avatar" => ""],
        "cover_url" => $coverUrl,
        "timestamp" => date("Y-m-d H:i:s"),
        "tips" => "免费提供，仅供测试"
    ];
    
    if (!$success && $errorMsg) {
        $response["error"] = $errorMsg;
    }
    
    return $response;
}

function httpRequest($url, $headers = [], $cookies = [], $method = 'GET', $data = null, $allowRedirects = true) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    if ($cookies) {
        $cookieStr = '';
        foreach ($cookies as $key => $value) {
            $cookieStr .= "$key=$value; ";
        }
        curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieStr, '; '));
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }
    
    if (!$allowRedirects) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'text' => $response,
        'code' => $httpCode
    ];
}

// 解析器基类
abstract class Parser {
    abstract public function parse($url);
}

// 小红书解析器
class XiaohongshuParser extends Parser {
    public function parse($url) {
        try {
            $headers = [
                'User-Agent: Dalvik/2.1.0 (Linux; U; Android 14; V2417A Build/UP1A.231005.007) Resolution/1260*2800 Version/8.69.5 Build/8695125 Device/(vivo;V2417A) discover/8.69.5 NetType/WiFi'
            ];
            
            // 获取重定向信息
            $response = httpRequest($url, $headers, [], 'GET', null, false);
            
            $itemId = extractMiddleText($response['text'], "item/", "?");
            $token = extractMiddleText($response['text'], "token=", "&");
            
            if (!$itemId || !$token) {
                return createStandardResponse(false, "", [], "", null, "", "无法提取必要的参数");
            }
            
            // 获取详细信息
            $detailUrl = "https://www.xiaohongshu.com/discovery/item/{$itemId}?app_platform=android&ignoreEngage=true&app_version=8.69.5&share_from_user_hidden=true&xsec_source=app_share&type=video&xsec_token={$token}";
            $detailHeaders = [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/536.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 Edg/132.0.0.0"
            ];
            
            $response = httpRequest($detailUrl, $detailHeaders);
            
            preg_match('/<script>window\.__INITIAL_STATE__=(.*?)<\/script>/', $response['text'], $matches);
            
            if (!$matches) {
                return createStandardResponse(false, "", [], "", null, "", "无法找到数据");
            }
            
            $jsonStr = str_replace("undefined", "null", $matches[1]);
            $data = json_decode($jsonStr, true);
            $noteData = $data["note"]["noteDetailMap"][$itemId]["note"];
            $noteType = $noteData["type"];
            
            $authorInfo = [
                "nickname" => $noteData["user"]["nickname"],
                "avatar" => $noteData["user"]["avatar"] ?? ""
            ];
            
            if ($noteType == "video") {
                $videoUrl = $noteData["video"]["media"]["stream"]["h264"][0]["masterUrl"];
                return createStandardResponse(
                    true,
                    "video",
                    [[
                        "url" => $videoUrl,
                        "resolution" => ($noteData['video']['width'] ?? '') . 'x' . ($noteData['video']['height'] ?? '')
                    ]],
                    $noteData["title"] ?? "",
                    $authorInfo,
                    $noteData["video"]["cover"] ?? ""
                );
            } elseif ($noteType == "normal") {
                $items = [];
                $coverUrl = "";
                $hasVideo = false;
                
                foreach ($noteData["imageList"] as $i => $image) {
                    if (isset($image["livePhoto"]) && $image["livePhoto"]) {
                        $imgUrl = $image["stream"]["h264"][0]["masterUrl"];
                        $itemType = "video";
                        $hasVideo = true;
                    } else {
                        $imgUrl = $image["infoList"][1]["url"];
                        $itemType = "image";
                    }
                    
                    $item = [
                        "url" => $imgUrl,
                        "type" => $itemType,
                        "resolution" => ($image['width'] ?? '') . 'x' . ($image['height'] ?? '')
                    ];
                    $items[] = $item;
                    
                    // 取第一张作为封面
                    if ($i == 0 && !$coverUrl) {
                        $coverUrl = $imgUrl;
                    }
                }
                
                $mediaType = $hasVideo ? "mixed" : "image";
                
                return createStandardResponse(
                    true,
                    $mediaType,
                    $items,
                    $noteData["title"] ?? "",
                    $authorInfo,
                    $coverUrl
                );
            }
            
            return createStandardResponse(false, "", [], "", null, "", "未知的内容类型: {$noteType}");
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "小红书解析失败: " . $e->getMessage());
        }
    }
}

// 抖音解析器
class DouyinParser extends Parser {
    public function parse($url) {
        try {
            $headers = [
                "User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
                "Cookie: ttwid=1%7CdjUiwt-8iojVf89TbwdaPcsDLpn1fU00mKYaYCBRiHg%7C1710746734%7Ccd7960b547be86bd14c56832ffea3ec035af1704696960274f2ba4017cb0c420; bd_ticket_guard_client_web_domain=2; xgplayer_user_id=300976970825; odin_tt=cd1484c33777a5b6033eb2d704acf1325c6fa8f87f298761b34d502b2bc72e0e063bb76cafae6eda77504b92388a945495fa1bee99afaece54fadb48bd1e2eef65754e14fcd52875cf4e859f9f2797a1; xgplayer_device_id=33693820609; SEARCH_RESULT_LIST_TYPE=%22single%22; s_v_web_id=verify_lwllt9d5_131z6m2c_JOGv_4TDi_Aoje_kOMojbHCPw0e; passport_csrf_token=acfb568a5e849c00aae32c504ddcf720; passport_csrf_token_default=acfb568a5e849c00aae32c504ddcf720; UIFID_TEMP=c4683e1a43ffa6bc6852097c712d14b81f04bc9b5ca6d30214b0e66b4e3852802afe10dc759a4840b81140431eb63f5b7b9bf48388d5b2ea51d2c5499bf93eed4f464fc4a76e1d4f480f11523a92ed21; FORCE_LOGIN=%7B%22videoConsumedRemainSeconds%22%3A180%7D; fpk1=U2FsdGVkX1+zE2LbMIyeNz1bUAgXGI+GV9C9WyJchdXBQ+btbZOeBnttBI4FeWUjU8NDIweP6c2iFxNRAl9NzA==; fpk2=5f4591689f71924dbd1e95e47aec4ed7; UIFID=c4683e1a43ffa6bc6852097c712d14b81f04bc9b5ca6d30214b0e66b4e3852802afe10dc759a4840b81140431eb63f5b25c36f37f88bb35edf57e7b457b5f0552d48a4805370c354b88614ee3785e7a8d8360ba6238aea0fe85f7065584d0a57c40df70e202458dc7c81352a7d3040448ff6ed7106b36bc97733c48387da93953c97d5d7d7e128afc2d0497e2a51e4da5cae0c627ce32ce055c1b4e50a7c6b2f; vdg_s=1; pwa2=%220%7C0%7C3%7C0%22; download_guide=%223%2F20240702%2F1%22; douyin.com; device_web_cpu_core=12; device_web_memory_size=8; architecture=amd64; strategyABtestKey=%221719937555.264%22; csrf_session_id=6a4f4bf33581bf51380386b4904f13f7; __live_version__=%221.1.2.1533%22; live_use_vvc=%22false%22; webcast_leading_last_show_time=1719937582984; webcast_leading_total_show_times=1; webcast_local_quality=sd; xg_device_score=7.666140284295324; dy_swidth=1920; dy_sheight=1080; stream_recommend_feed_params=%22%7B%5C%22cookie_enabled%5C%22%3Atrue%2C%5C%22screen_width%5C%22%3A1920%2C%5C%22screen_height%5C%22%3A1080%2C%5C%22browser_online%5C%22%3Atrue%2C%5C%22cpu_core_num%5C%22%3A12%2C%5C%22device_memory%5C%22%3A8%2C%5C%22downlink%5C%22%3A10%2C%5C%22effective_type%5C%22%3A%5C%224g%5C%22%2C%5C%22round_trip_time%5C%22%3A50%7D%22; stream_player_status_params=%22%7B%5C%22is_auto_play%5C%22%3A0%2C%5C%22is_full_screen%5C%22%3A0%2C%5C%22is_full_webscreen%5C%22%3A0%2C%5C%22is_mute%5C%22%3A1%2C%5C%22is_speed%5C%22%3A1%2C%5C%22is_visible%5C%22%3A0%7D%22; WallpaperGuide=%7B%22showTime%22%3A1719918712666%2C%22closeTime%22%3A0%2C%22showCount%22%3A1%2C%22cursor1%22%3A35%2C%22cursor2%22%3A0%7D; live_can_add_dy_2_desktop=%221%22; msToken=wBlz-TD-Cxna5YP6Y4ev4-eiEy-vGNFvolT7yI6yCKrpljM0RfSXq2FE3zJSO3S19IL12WpOk-iQJCiau92GwBq0S2mK0PAxO0gIC4_EorlQk9_QAPsv; __ac_nonce=06684349d007e745bd7f4; __ac_signature=_02B4Z6wo00f01WoVPKAAAIDBXTH4.RkCqt1qNTgAADwF7SNYjgKYp2UYvulOkhbQ86-sAkiKejYGuMUddCSw4ObrljbN7dHpr-y5cdIiQpGVmJnE4aFoBhAVrazgiovkBqJ-ktLn2BQRGzSV1b; x-web-secsdk-uid=2e929dd5-0973-4520-846d-9417b0badc6f; home_can_add_dy_2_desktop=%221%22; IsDouyinActive=true; volume_info=%7B%22isUserMute%22%3Afalse%2C%22isMute%22%3Afalse%2C%22volume%22%3A0.943%7D; biz_trace_id=c3335c50; bd_ticket_guard_client_data=eyJiZC10aWNrZXQtZ3VhcmQtdmVyc2lvbiI6MiwiYmQtdGlja2V0LWd1YXJkLWl0ZXJhdGlvbi12ZXJzaW9uIjoxLCJiZC10aWNrZXQtZ3VhcmQtcmVlLXB1YmxpYy1rZXkiOiJCQXpEQjRsSlMvUndUZkg0RC9MN2RCTnduN1ZRdStjU0J1YUsvQTVzZ2YyamovaWlzakpVWWgzRDY0QUE4eit5Smx5T0hmOGF6aEFWWWhEbGhRbmE3Y0E9IiwiYmQtdGlja2V0LWd1YXJkLXdlYi12ZXJzaW9uIjoxfQ%3D%3D"
            ];
            
            // 获取重定向信息
            $response = httpRequest($url, $headers, [], 'GET', null, false);
            
            $itemText = extractMiddleText($response['text'], "/", "/?");
            
            if (!$itemText) {
                return createStandardResponse(false, "", [], "", null, "", "无法提取视频ID");
            }
            
            // 提取数字部分
            preg_match_all('/\d+/', $itemText, $matches);
            $videoId = implode('', $matches[0]);
            
            // 获取详细信息
            $detailUrl = "https://www.douyin.com/user/self?modal_id={$videoId}&showTab=like";
            $response = httpRequest($detailUrl, $headers, [], 'GET', null, true);
            
            // 提取JSON数据
            $startStr = '<script id="RENDER_DATA" type="application/json">';
            $endStr = "</script>";
            
            if (strpos($response['text'], $startStr) === false) {
                return createStandardResponse(false, "", [], "", null, "", "无法找到数据");
            }
            
            $parts = explode($startStr, $response['text']);
            $parts2 = explode($endStr, $parts[1]);
            $jsonStr = urldecode($parts2[0]);
            $data = json_decode($jsonStr, true);
            
            if (!isset($data["app"]["videoDetail"])) {
                return createStandardResponse(false, "", [], "", null, "", "数据格式错误");
            }
            
            // 处理结果
            $mediaType = $data["app"]["videoDetail"]["mediaType"];
            $videoDesc = preg_replace('/\r?\n/', "\n", $data["app"]["videoDetail"]["desc"]);
            $authorInfo = [
                "nickname" => $data["app"]["videoDetail"]["authorInfo"]["nickname"],
                "avatar" => $data["app"]["videoDetail"]["authorInfo"]["avatarThumb"] ?? ""
            ];
            $coverUrl = $data["app"]["videoDetail"]["coverUrl"] ?? "";
            
            if ($mediaType == 4) { // 视频
                $videoUrl = explode("&aid", $data["app"]["videoDetail"]["video"]["playApi"])[0];
                $redirectResp = httpRequest($videoUrl, $headers, [], 'GET', null, false);
                $location = isset($redirectResp['headers']['Location']) ? $redirectResp['headers']['Location'] : '';
                
                if ($location) {
                    $videoUrl = explode("&btag", $location)[0];
                }
                
                return createStandardResponse(
                    true,
                    "video",
                    [[
                        "url" => $videoUrl,
                        "resolution" => ($data['app']['videoDetail']['video']['width'] ?? '') . 'x' . ($data['app']['videoDetail']['video']['height'] ?? '')
                    ]],
                    $videoDesc,
                    $authorInfo,
                    $coverUrl
                );
            } elseif ($mediaType == 2) { // 图片
                $images = $data["app"]["videoDetail"]["images"];
                $items = [];
                foreach ($images as $img) {
                    $items[] = [
                        "url" => $img["urlList"][0],
                        "type" => "image",
                        "resolution" => ($img['width'] ?? '') . 'x' . ($img['height'] ?? '')
                    ];
                }
                
                return createStandardResponse(
                    true,
                    "image",
                    $items,
                    $videoDesc,
                    $authorInfo,
                    $coverUrl ?: ($items[0]["url"] ?? "")
                );
            } elseif ($mediaType == 42) { // 混合内容
                $images = $data["app"]["videoDetail"]["images"];
                $items = [];
                
                foreach ($images as $i => $image) {
                    $videoInfo = $data["app"]["videoDetail"]["images"][$i]["video"];
                    if ($videoInfo === null) {
                        $imgUrl = $image["urlList"][0];
                        $itemType = "image";
                    } else {
                        $imgUrl = explode("&aid", $videoInfo["playApi"])[0];
                        $itemType = "video";
                    }
                    
                    $items[] = [
                        "url" => $imgUrl,
                        "type" => $itemType,
                        "resolution" => ($image['width'] ?? '') . 'x' . ($image['height'] ?? '')
                    ];
                }
                
                return createStandardResponse(
                    true,
                    "mixed",
                    $items,
                    $videoDesc,
                    $authorInfo,
                    $coverUrl ?: ($items[0]["url"] ?? "")
                );
            }
            
            return createStandardResponse(false, "", [], "", null, "", "未知的媒体类型: {$mediaType}");
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "抖音解析失败: " . $e->getMessage());
        }
    }
}

// 快手解析器
class KuaishouParser extends Parser {
    public function parse($url) {
        try {
            $headers = [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36 Edg/133.0.0.0"
            ];
            
            $cookies = [
                "kpf" => "PC_WEB",
                "clientid" => "3",
                "did" => "web_a95da6b615032b1aa95f1c6f680cfe4f",
                "kwpsecproductname" => "kuaishou-vision",
                "kpn" => "KUAISHOU_VISION",
                "kwssectoken" => "DY1jxtheim0NNYGmnaZwQXWaUkjEz1hXDdgLyws7uchBa7Vg5A8ujOKmoat/vr8oUCtntNbbR/SaZkIFZ9bKSg==",
                "kwscode" => "8aad2d2b324d894df87afb11e3e354954b83d602bf936b28b41ffd3a68336160",
                "kwfv1" => "PnGU+9+Y8008S+nH0U+0mjPf8fP08f+98f+nLlwnrIP9+Y8/HFPBzj80D9+fcMPfHAPBG7PfHlPBHEwBHl+/Sj+fGEG0qE+f8Sw/ZA8eHE+/b0P/H98BGF80+Y+ADMP0PA8B+j+0qh80HU+/ZU8/ZIwnLlP/ZM+nbY+/WMw/cU8frAwBLUGfPl+/WE+nbS8/qIG/DA+04SPebf+9QYPfGAGc=="
            ];
            
            $response = httpRequest($url, $headers, $cookies);
            
            // 尝试解析视频
            if (preg_match('/window\.__APOLLO_STATE__\s*=\s*({.*?});/s', $response['text'], $matches)) {
                $videoData = json_decode($matches[1], true);
                $defaultClient = $videoData["defaultClient"] ?? [];
                
                // 提取作者信息
                $authorKey = null;
                foreach (array_keys($defaultClient) as $key) {
                    if (strpos($key, "VisionVideoDetailAuthor:") === 0) {
                        $authorKey = $key;
                        break;
                    }
                }
                
                $authorInfo = ["nickname" => "", "avatar" => ""];
                if ($authorKey) {
                    $authorInfo = [
                        "nickname" => $defaultClient[$authorKey]["name"] ?? "",
                        "avatar" => $defaultClient[$authorKey]["headerUrl"] ?? ""
                    ];
                }
                
                // 提取视频信息
                $videoKey = null;
                foreach (array_keys($defaultClient) as $key) {
                    if (strpos($key, "VisionVideoDetailPhoto:") === 0) {
                        $videoKey = $key;
                        break;
                    }
                }
                
                if ($videoKey) {
                    $videoInfo = $defaultClient[$videoKey];
                    $title = $videoInfo["caption"] ?? "";
                    $coverUrl = $videoInfo["coverUrl"] ?? "";
                    
                    // 提取视频URL
                    $videoResource = $videoInfo["videoResource"] ?? [];
                    $jsonData = $videoResource["json"] ?? [];
                    $h264 = $jsonData["h264"] ?? [];
                    $adaptationSets = $h264["adaptationSet"] ?? [];
                    
                    if ($adaptationSets && isset($adaptationSets[0]["representation"]) && count($adaptationSets[0]["representation"]) > 0) {
                        $representation = $adaptationSets[0]["representation"][0];
                        if (isset($representation["backupUrl"]) && count($representation["backupUrl"]) > 0) {
                            return createStandardResponse(
                                true,
                                "video",
                                [[
                                    "url" => $representation["backupUrl"][0],
                                    "resolution" => ($representation['width'] ?? '') . 'x' . ($representation['height'] ?? '')
                                ]],
                                $title,
                                $authorInfo,
                                $coverUrl
                            );
                        }
                    }
                }
            }
            
            // 尝试解析图片
            if (preg_match('/INIT_STATE = (.*?)<\/script>/s', $response['text'], $matches)) {
                $imageData = json_decode($matches[1], true);
                
                // 查找图片信息对象
                $imageObj = null;
                foreach ($imageData as $item) {
                    if (is_array($item) && isset($item["atlas"])) {
                        $imageObj = $item;
                        break;
                    }
                }
                
                if ($imageObj) {
                    $photo = $imageObj["photo"] ?? [];
                    $title = $photo["caption"] ?? "";
                    $coverUrl = $photo["coverUrl"] ?? "";
                    
                    $authorInfo = [
                        "nickname" => $photo["userName"] ?? "",
                        "avatar" => $photo["headUrl"] ?? ""
                    ];
                    
                    // 提取图片URLs
                    $atlas = $imageObj["atlas"] ?? [];
                    $cdnList = $atlas["cdnList"] ?? [];
                    $items = [];
                    
                    if ($cdnList) {
                        $cdn = $cdnList[0]["cdn"] ?? "";
                        $imageList = $atlas["list"] ?? [];
                        $sizeList = $atlas["size"] ?? [];
                        
                        $count = min(count($imageList), count($sizeList));
                        for ($i = 0; $i < $count; $i++) {
                            $imgUrl = $cdn ? "https://{$cdn}{$imageList[$i]}" : $imageList[$i];
                            $size = $sizeList[$i];
                            $items[] = [
                                "url" => $imgUrl,
                                "type" => "image",
                                "resolution" => ($size['w'] ?? '') . 'x' . ($size['h'] ?? '')
                            ];
                        }
                    }
                    
                    if ($items) {
                        return createStandardResponse(
                            true,
                            "image",
                            $items,
                            $title,
                            $authorInfo,
                            $coverUrl ?: $items[0]["url"]
                        );
                    }
                }
            }
            
            return createStandardResponse(false, "", [], "", null, "", "未找到任何媒体资源");
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "快手解析错误: " . $e->getMessage());
        }
    }
}

// 皮皮虾解析器
class PipixParser extends Parser {
    private $BASE_URL = "https://api.pipix.com/bds/cell/cell_comment/?offset=0&cell_type=1&api_version=1&cell_id=%s&ac=wifi&channel=huawei_1319_64&aid=1319&app_name=super";
    private $PHONE_USER_AGENTS = [
        "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36",
        "Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1",
        "Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1"
    ];
    
    private function getRandomUserAgent() {
        return $this->PHONE_USER_AGENTS[array_rand($this->PHONE_USER_AGENTS)];
    }
    
    private function getRedirectUrl($url) {
        try {
            $headers = ["User-Agent: " . $this->getRandomUserAgent()];
            $response = httpRequest($url, $headers, [], 'HEAD', null, true);
            return $response['effective_url'] ?? $url;
        } catch (Exception $e) {
            return $url;
        }
    }
    
    private function getId($url) {
        $parsed = parse_url($url);
        $pathSegments = explode('/', $parsed['path']);
        
        $index = array_search("item", $pathSegments);
        if ($index !== false && $index + 1 < count($pathSegments)) {
            return $pathSegments[$index + 1];
        }
        
        return null;
    }
    
    public function parse($url) {
        try {
            // 提取视频ID
            $videoId = $this->getId($url);
            if (!$videoId) {
                $redirectedUrl = $this->getRedirectUrl($url);
                $videoId = $this->getId($redirectedUrl);
                if (!$videoId) {
                    return createStandardResponse(false, "", [], "", null, "", "无法获取视频ID");
                }
            }
            
            // 请求API
            $headers = ["User-Agent: " . $this->getRandomUserAgent()];
            $response = httpRequest(sprintf($this->BASE_URL, $videoId), $headers);
            
            if (!$response['text']) {
                return createStandardResponse(false, "", [], "", null, "", "解析失败");
            }
            
            // 解析内容
            $jsonData = json_decode($response['text'], true);
            $cellComments = $jsonData["data"]["cell_comments"] ?? [];
            
            if (!$cellComments) {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体信息");
            }
            
            $itemObject = $cellComments[0]["comment_info"]["item"] ?? [];
            if (!$itemObject) {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体信息");
            }
            
            // 提取基本信息
            $title = $itemObject["content"] ?? "";
            $authorInfo = [
                "nickname" => $itemObject["author"]["name"] ?? "",
                "avatar" => $itemObject["author"]["avatar"]["url_list"][0]["url"] ?? ""
            ];
            $coverUrl = "";
            $items = [];
            $hasVideo = false;
            $hasImage = false;
            
            // 提取视频
            $videoHigh = $itemObject["video"]["video_high"] ?? [];
            if ($videoHigh) {
                // 设置封面
                $coverImages = $videoHigh["cover_image"]["url_list"] ?? [];
                $coverUrl = $coverImages[0]["url"] ?? "";
                
                // 添加视频
                $resolution = ($videoHigh['width'] ?? '') . 'x' . ($videoHigh['height'] ?? '');
                foreach ($videoHigh["url_list"] ?? [] as $urlNode) {
                    $items[] = [
                        "url" => $urlNode["url"] ?? "",
                        "type" => "video",
                        "resolution" => $resolution
                    ];
                    $hasVideo = true;
                }
            }
            
            // 提取图片
            $multiImage = $itemObject["note"]["multi_image"] ?? [];
            foreach ($multiImage as $image) {
                $urlList = $image["url_list"] ?? [];
                $imgUrl = $urlList[0]["url"] ?? "";
                if ($imgUrl) {
                    $items[] = [
                        "url" => $imgUrl,
                        "type" => "image",
                        "resolution" => ($image['width'] ?? '') . 'x' . ($image['height'] ?? '')
                    ];
                    $hasImage = true;
                }
            }
            
            // 确定媒体类型
            if ($hasVideo && $hasImage) {
                $mediaType = "mixed";
            } elseif ($hasVideo) {
                $mediaType = "video";
            } elseif ($hasImage) {
                $mediaType = "image";
            } else {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体内容");
            }
            
            // 重置封面
            if (!$coverUrl && $items) {
                foreach ($items as $media) {
                    if ($media["type"] == "image") {
                        $coverUrl = $media["url"];
                        break;
                    }
                }
            }
            
            return createStandardResponse(
                true,
                $mediaType,
                $items,
                $title,
                $authorInfo,
                $coverUrl
            );
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "皮皮虾解析失败: " . $e->getMessage());
        }
    }
}

// 最右解析器
class ZuiyouParser extends Parser {
    public function parse($url) {
        try {
            // 提取帖子ID
            $parsed = parse_url($url);
            parse_str($parsed['query'] ?? '', $queryParams);
            $pid = $queryParams['pid'] ?? null;
            
            if (!$pid) {
                return createStandardResponse(false, "", [], "", null, "", "无法提取帖子ID");
            }
            
            // 发送请求获取内容
            $headers = [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
                "Content-Type: application/json"
            ];
            
            $data = ["pid" => intval($pid), "h_av" => "5.2.13.011"];
            $response = httpRequest(
                "https://share.xiaochuankeji.cn/planck/share/post/detail_h5",
                $headers,
                [],
                'POST',
                json_encode($data)
            );
            
            $data = json_decode($response['text'], true);
            $postData = $data["data"]["post"] ?? [];
            
            if (!$postData) {
                return createStandardResponse(false, "", [], "", null, "", "未找到帖子数据");
            }
            
            // 提取基础信息
            $title = $postData["content"] ?? "";
            $authorName = $postData["member"]["name"] ?? "";
            $authorInfo = [
                "nickname" => $authorName,
                "avatar" => $postData["member"]["avatar"] ?? ""
            ];
            $items = [];
            $hasVideo = false;
            $hasImage = false;
            $coverUrl = "";
            
            // 提取图片
            foreach ($postData["imgs"] ?? [] as $img) {
                $imgUrls = $img["urls"]["540_webp"]["urls"] ?? [];
                if ($imgUrls) {
                    $items[] = [
                        "url" => $imgUrls[0],
                        "type" => "image",
                        "resolution" => ($img['width'] ?? '') . 'x' . ($img['height'] ?? '')
                    ];
                    $hasImage = true;
                    if (!$coverUrl) {
                        $coverUrl = $imgUrls[0];
                    }
                }
            }
            
            // 提取视频
            $videos = $postData["videos"] ?? [];
            if ($videos) {
                $videoKey = array_key_first($videos);
                if ($videoKey) {
                    $videoUrl = $videos[$videoKey]["url"] ?? "";
                    if ($videoUrl) {
                        $items[] = [
                            "url" => $videoUrl,
                            "type" => "video",
                            "resolution" => ($videos[$videoKey]['width'] ?? '') . 'x' . ($videos[$videoKey]['height'] ?? '')
                        ];
                        $hasVideo = true;
                        if (!$coverUrl) {
                            $coverUrl = $videos[$videoKey]["cover"] ?? "";
                        }
                    }
                }
            }
            
            // 确定媒体类型
            if ($hasVideo && $hasImage) {
                $mediaType = "mixed";
            } elseif ($hasVideo) {
                $mediaType = "video";
            } elseif ($hasImage) {
                $mediaType = "image";
            } else {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体资源");
            }
            
            return createStandardResponse(
                true,
                $mediaType,
                $items,
                $title,
                $authorInfo,
                $coverUrl
            );
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "最右解析失败: " . $e->getMessage());
        }
    }
}

// 皮皮搞笑解析器
class PpgxParser extends Parser {
    private $BASE_URL = "https://h5.ippzone.com/ppapi/share/fetch_content";
    private $COVER_URL = "https://file.ippzone.com/img/frame/id/%s";
    private $TYPE = "post";
    
    private function getId($url) {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $queryParams);
        
        if (isset($queryParams['pid'])) {
            return $queryParams['pid'];
        }
        
        // 从路径获取post后的ID
        $pathSegments = explode('/', $parsed['path']);
        for ($i = 0; $i < count($pathSegments); $i++) {
            if ($pathSegments[$i] == 'post' && $i + 1 < count($pathSegments)) {
                return $pathSegments[$i + 1];
            }
        }
        
        // 尝试获取重定向后的URL再解析
        try {
            $response = httpRequest($url, [], [], 'HEAD', null, true);
            return $this->getId($response['effective_url'] ?? $url);
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function parse($url) {
        try {
            // 获取ID
            $pid = $this->getId($url);
            if (!$pid) {
                return createStandardResponse(false, "", [], "", null, "", "无法提取帖子ID");
            }
            
            // 发送请求
            $headers = [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36",
                "Content-Type: application/json"
            ];
            
            $data = [
                "pid" => intval($pid),
                "type" => $this->TYPE
            ];
            
            $response = httpRequest($this->BASE_URL, $headers, [], 'POST', json_encode($data));
            
            if (!$response['text']) {
                return createStandardResponse(false, "", [], "", null, "", "获取数据为空");
            }
            
            // 解析内容
            $jsonData = json_decode($response['text'], true);
            $dataObject = $jsonData["data"]["post"] ?? [];
            
            if (!$dataObject) {
                return createStandardResponse(false, "", [], "", null, "", "解析媒体信息失败");
            }
            
            // 提取信息
            $title = $dataObject["content"] ?? "";
            $items = [];
            $hasVideo = false;
            $hasImage = false;
            $coverUrl = "";
            
            // 提取视频
            $videos = $dataObject["videos"] ?? [];
            if ($videos && is_array($videos)) {
                $videoKey = array_key_first($videos);
                if ($videoKey) {
                    $videoInfo = $videos[$videoKey] ?? [];
                    $videoUrl = $videoInfo["url"] ?? "";
                    if ($videoUrl) {
                        $items[] = [
                            "url" => $videoUrl,
                            "type" => "video",
                            "resolution" => ($videoInfo['width'] ?? '') . 'x' . ($videoInfo['height'] ?? '')
                        ];
                        $hasVideo = true;
                        if (!$coverUrl) {
                            $coverUrl = $videoInfo["cover"] ?? "";
                        }
                    }
                }
            }
            
            // 提取图片
            $imgs = $dataObject["imgs"] ?? [];
            if ($imgs && is_array($imgs)) {
                foreach ($imgs as $img) {
                    if (is_array($img) && isset($img["id"])) {
                        $imgUrl = sprintf($this->COVER_URL, $img["id"]);
                        $resolution = ($img['w'] ?? '') . 'x' . ($img['h'] ?? '');
                        $items[] = [
                            "url" => $imgUrl,
                            "type" => "image",
                            "resolution" => $resolution
                        ];
                        $hasImage = true;
                        if (!$coverUrl) {
                            $coverUrl = $imgUrl;
                        }
                    }
                }
            }
            
            // 确定媒体类型
            if ($hasVideo && $hasImage) {
                $mediaType = "mixed";
            } elseif ($hasVideo) {
                $mediaType = "video";
            } elseif ($hasImage) {
                $mediaType = "image";
            } else {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体内容");
            }
            
            // 设置封面
            if (!$coverUrl && $items) {
                $coverUrl = $items[0]["url"];
            }
            
            return createStandardResponse(
                true,
                $mediaType,
                $items,
                $title,
                [
                    "nickname" => $dataObject["userName"] ?? "",
                    "avatar" => $dataObject["headUrl"] ?? ""
                ],
                $coverUrl
            );
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "处理错误: " . $e->getMessage());
        }
    }
}

// 微视解析器
class WeishiParser extends Parser {
    private $BASE_URL = "https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage";
    private $USER_AGENTS = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36",
        "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1"
    ];
    
    public function parse($url) {
        try {
            // 提取帖子ID
            $parsed = parse_url($url);
            parse_str($parsed['query'] ?? '', $queryParams);
            $feedId = $queryParams['id'] ?? null;
            
            if (!$feedId) {
                // 处理可能的重定向链接
                $response = httpRequest($url, [], [], 'HEAD', null, true);
                $parsedRedirect = parse_url($response['effective_url'] ?? $url);
                parse_str($parsedRedirect['query'] ?? '', $redirectParams);
                $feedId = $redirectParams['id'] ?? null;
            }
            
            if (!$feedId) {
                return createStandardResponse(false, "", [], "", null, "", "无法提取微视帖子ID");
            }
            
            // 发送请求获取数据
            $headers = [
                "User-Agent: " . $this->USER_AGENTS[0],
                "Content-Type: application/json"
            ];
            
            $response = httpRequest(
                $this->BASE_URL,
                $headers,
                [],
                'POST',
                json_encode(["feedid" => $feedId])
            );
            
            if (!$response['text']) {
                return createStandardResponse(false, "", [], "", null, "", "获取数据为空");
            }
            
            // 解析JSON数据
            $jsonData = json_decode($response['text'], true);
            $feedInfo = $jsonData["data"]["feeds"][0] ?? [];
            
            if (!$feedInfo) {
                return createStandardResponse(false, "", [], "", null, "", "未找到帖子信息");
            }
            
            // 提取基础信息
            $title = $feedInfo["feed_desc"] ?? "";
            $authorInfo = [
                "nickname" => $feedInfo["poster"]["nick"] ?? "",
                "avatar" => $feedInfo["poster"]["avatar"] ?? ""
            ];
            $coverUrl = $feedInfo["video_cover"]["static_cover"]["url"] ?? "";
            $items = [];
            $hasVideo = false;
            $hasImage = false;
            
            // 提取视频
            $videoInfo = $feedInfo["video"] ?? [];
            if ($videoInfo) {
                $videoUrl = $feedInfo["video_url"] ?? "";
                if ($videoUrl) {
                    $resolution = ($videoInfo['width'] ?? '') . 'x' . ($videoInfo['height'] ?? '');
                    $items[] = [
                        "url" => $videoUrl,
                        "type" => "video",
                        "resolution" => $resolution
                    ];
                    $hasVideo = true;
                }
            }
            
            // 提取图片
            $images = $feedInfo["images"] ?? [];
            foreach ($images as $img) {
                if (is_array($img)) {
                    $imgUrl = $img["url"] ?? "";
                    if ($imgUrl) {
                        $resolution = ($img['width'] ?? '') . 'x' . ($img['height'] ?? '');
                        $items[] = [
                            "type" => "image",
                            "url" => $imgUrl,
                            "resolution" => $resolution
                        ];
                        $hasImage = true;
                    }
                }
            }
            
            // 确定媒体类型
            if ($hasVideo && $hasImage) {
                $mediaType = "mixed";
            } elseif ($hasVideo) {
                $mediaType = "video";
            } elseif ($hasImage) {
                $mediaType = "image";
            } else {
                return createStandardResponse(false, "", [], "", null, "", "未找到媒体内容");
            }
            
            // 重置封面（如果未设置）
            if (!$coverUrl && $items) {
                $coverUrl = $items[0]["url"];
            }
            
            return createStandardResponse(
                true,
                $mediaType,
                $items,
                $title,
                $authorInfo,
                $coverUrl
            );
            
        } catch (Exception $e) {
            return createStandardResponse(false, "", [], "", null, "", "处理错误: " . $e->getMessage());
        }
    }
}

// 主路由处理
if (isset($_GET['id'])) {
    $url = $_GET['id'];
    
    try {
        // 选择合适的解析器
        if (strpos($url, "douyin") !== false) {
            $parser = new DouyinParser();
        } elseif (strpos($url, "kuaishou") !== false) {
            $parser = new KuaishouParser();
        } elseif (strpos($url, "pipix") !== false) {
            $parser = new PipixParser();
        } elseif (strpos($url, "xiaochuankeji") !== false) {
            $parser = new ZuiyouParser();
        } elseif (strpos($url, "pipigx") !== false) {
            $parser = new PpgxParser();
        } elseif (strpos($url, "weishi") !== false) {
            $parser = new WeishiParser();
        } else {
            $parser = new XiaohongshuParser();
        }
        
        $result = $parser->parse($url);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        $errorResponse = createStandardResponse(
            false,
            "",
            [],
            "",
            null,
            "",
            "服务器错误: " . $e->getMessage()
        );
        http_response_code(500);
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    }
} else {
    $errorResponse = createStandardResponse(
        false,
        "",
        [],
        "",
        null,
        "",
        "未找到 id 参数"
    );
    http_response_code(400);
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}
?>
