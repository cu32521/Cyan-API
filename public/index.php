<?php
$apiDir = dirname(__DIR__) . "/api";
$files = array_diff(scandir($apiDir), ['.', '..']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>API 接口平台</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<h1>📚 API 接口平台</h1>
<div style="position:fixed; right:20px; top:20px;">
    <a href="/user/login.php" 
       style="padding:6px 12px; background:#409EFF; color:#fff; text-decoration:none; border-radius:6px; margin-right:8px;">
       登录
    </a>
    <a href="/user/register.php" 
       style="padding:6px 12px; background:#67C23A; color:#fff; text-decoration:none; border-radius:6px;">
       注册
    </a>
</div>

<?php
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) !== "php") continue;

    $content = file($apiDir . "/" . $file);

    // 解析注释块（自动跳过 <?php）
    $inComment = false;
    $comment = [];

    foreach ($content as $line) {
        $line = trim($line);

        if (strpos($line, '/*') === 0) {        // 开始注释
            $inComment = true;
            continue;
        }

        if (strpos($line, '*/') === 0) {        // 结束注释
            break;
        }

        if ($inComment) {
            // 去掉 * 和空格
            $comment[] = ltrim($line, "* ");
        }
    }

    $name = $comment[0] ?? "未命名接口";
    $desc = $comment[1] ?? "无描述";
    ?>

    <div class="card">
        <h2><?= htmlspecialchars($name) ?></h2>
        <p><?= htmlspecialchars($desc) ?></p>
        <a href="detail.php?file=<?= urlencode($file) ?>">查看详情</a>
    </div>

<?php } ?>

</body>
</html>