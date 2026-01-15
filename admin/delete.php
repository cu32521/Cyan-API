<?php
session_start();

if (!isset($_SESSION['admin'])) {
    die("未登录");
}

if (!isset($_GET['file'])) {
    die("缺少参数");
}

$file = "../api/" . basename($_GET['file']);

if (file_exists($file)) {
    unlink($file);
    echo "删除成功";
} else {
    echo "文件不存在";
}

echo "<br><a href='index.php'>返回后台</a>";
?>