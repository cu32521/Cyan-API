<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION["user"];

$user_file = "../data/users/$user.json";
$log_file  = "../data/user_logs/$user.log";

$info = json_decode(file_get_contents($user_file), true);

$logs = file_exists($log_file) ? file($log_file) : [];
?>
<!DOCTYPE html>
<html>
<meta charset="utf-8">
<body>

<h2>用户中心</h2>

<p>用户名：<?php echo $info["username"]; ?></p>
<p>注册时间：<?php echo $info["reg_time"]; ?></p>
<p>注册IP：<?php echo $info["reg_ip"]; ?></p>

<hr>

<p>最近登录时间：<?php echo $info["last_login_time"]; ?></p>
<p>最近登录IP：<?php echo $info["last_login_ip"]; ?></p>

<p><a href="logout.php">退出登录</a></p>

<hr>

<h3>我的接口调用记录</h3>

<pre style="border:1px solid #ccc; padding:10px; max-height:400px; overflow-y:scroll">
<?php
foreach (array_reverse($logs) as $line) {
    echo htmlspecialchars($line);
}
?>
</pre>

</body>
</html>
