<?php
session_start();

if (!isset($_SESSION['admin'])) {
    die("未登录");
}

$log_file  = "../data/access.log";
$stat_file = "../data/stat.json";

$logs = file_exists($log_file) ? file($log_file) : [];
$stat = file_exists($stat_file) ? json_decode(file_get_contents($stat_file), true) : [];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>接口调用统计</title>
</head>

<body>

<h2>接口调用统计</h2>

<p><a href="index.php">返回后台</a></p>

<h3>汇总统计</h3>

<p>总调用次数：<?php echo $stat["total"] ?? 0; ?></p>
<p>今日调用次数：<?php echo $stat["today"] ?? 0; ?></p>

<h3>各接口调用次数</h3>

<table border="1" cellpadding="6">
<tr>
    <th>接口文件</th>
    <th>调用次数</th>
</tr>

<?php
if (isset($stat["per_api"])) {
    foreach ($stat["per_api"] as $api => $count) {
        echo "<tr><td>$api</td><td>$count</td></tr>";
    }
}
?>
</table>

<h3>访问日志</h3>

<pre style="border:1px solid #ccc; padding:10px; max-height:400px; overflow-y:scroll">
<?php
foreach (array_reverse($logs) as $line) {
    echo htmlspecialchars($line);
}
?>
</pre>

</body>
</html>
