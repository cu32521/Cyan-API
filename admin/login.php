<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($user === 'admin' && $pass === '123456') {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $err = "账号或密码错误";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>后台登录</title>
</head>
<body>

<h2>接口平台后台登录</h2>

<form method="post">
    用户名：<input name="user"><br>
    密码：<input type="password" name="pass"><br>
    <button type="submit">登录</button>
</form>

<?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>

</body>
</html>