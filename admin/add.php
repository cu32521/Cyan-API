<?php
session_start();

if (!isset($_SESSION['admin'])) {
    die("未登录");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $filename = trim($_POST['filename']);
    $content  = $_POST['content'];

    if ($filename === '') die("文件名不能为空");

    $path = "../api/" . $filename . ".php";

    file_put_contents($path, $content);

    echo "创建成功 → <a href='index.php'>返回后台</a>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>新增接口</title>
</head>
<body>

<h2>新增接口</h2>

<form method="post">

文件名（不含 .php）：
<input name="filename">
<br><br>

接口内容：
<br>
<textarea name="content" rows="20" cols="90">
<?php
/*
接口名称: 示例接口
接口描述: 这是一个后台创建的接口
方式: GET
*/
echo json_encode(["msg"=>"hello"]);
?>
</textarea>
<br><br>

<button type="submit">创建</button>

</form>

</body>
</html>
