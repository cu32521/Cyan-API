<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$api_dir = "../api";

$files = glob($api_dir . "/*.php");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>后台管理</title>
</head>

<body>

<h2>接口管理后台</h2>

<p><a href="add.php">➕ 新增接口</a></p>
<p><a href="logout.php">🚪 退出登录</a></p>
<p><a href="stat.php">📊 调用统计</a></p>

<table border="1" cellpadding="6">
<tr>
    <th>接口文件</th>
    <th>操作</th>
</tr>

<?php
foreach ($files as $f) {
    $name = basename($f);

    // 注意：前台在 public 下，所以访问路径是 /api
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>
            <a href='../api/$name' target='_blank'>直接访问</a> |
            <a href='../public/detail.php?file=$name' target='_blank'>详情页</a> |
            <a href='delete.php?file=$name' onclick='return confirm(\"确认删除？\")'>删除</a>
          </td>";
    echo "</tr>";
}
?>

</table>

</body>
</html>