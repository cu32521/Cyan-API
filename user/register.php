<?php
session_start();

$user_dir = "../data/users";
if (!is_dir($user_dir)) mkdir($user_dir, 0777, true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user = trim($_POST["user"] ?? "");
    $pass = trim($_POST["pass"] ?? "");

    if ($user === "" || $pass === "") {
        $err = "账号与密码不能为空";
    } else {
        $file = "$user_dir/$user.json";

        if (file_exists($file)) {
            $err = "该用户名已存在";
        } else {
            $info = [
                "username" => $user,
                "password" => md5($pass),
                "reg_time" => date("Y-m-d H:i:s"),
                "reg_ip"   => $_SERVER["REMOTE_ADDR"],
                "last_login_time" => "",
                "last_login_ip"   => ""
            ];

            file_put_contents($file, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<meta charset="utf-8">
<body>

<h2>用户注册</h2>

<form method="post">
账号：<input name="user"><br>
密码：<input name="pass" type="password"><br>
<button type="submit">注册</button>
</form>

<?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>

<p><a href="login.php">已有账号？去登录</a></p>

</body>
</html>
