<?php
session_start();

$user_dir = "../data/users";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user = trim($_POST["user"] ?? "");
    $pass = trim($_POST["pass"] ?? "");

    $file = "$user_dir/$user.json";

    if (!file_exists($file)) {
        $err = "用户不存在";
    } else {
        $info = json_decode(file_get_contents($file), true);

        if ($info["password"] !== md5($pass)) {
            $err = "密码错误";
        } else {
            // 更新最近登录记录
            $info["last_login_time"] = date("Y-m-d H:i:s");
            $info["last_login_ip"]   = $_SERVER["REMOTE_ADDR"];

            file_put_contents($file, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $_SESSION["user"] = $user;

            header("Location: center.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<meta charset="utf-8">
<body>

<h2>用户登录</h2>

<form method="post">
账号：<input name="user"><br>
密码：<input name="pass" type="password"><br>
<button type="submit">登录</button>
</form>

<?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>

<p><a href="register.php">没有账号？去注册</a></p>

</body>
</html>
