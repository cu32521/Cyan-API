<?php
if (!isset($_GET['file'])) {
    exit("缺少参数 file");
}

$file = basename($_GET['file']);
$path = dirname(__DIR__) . "/api/" . $file;

if (!file_exists($path)) {
    exit("接口不存在");
}

$content = file($path);

$inComment = false;

// 解析注释块
$comment = [];
foreach ($content as $line) {
    $line = trim($line);

    if (strpos($line, '/*') === 0) {
        $inComment = true;
        continue;
    }

    if (strpos($line, '*/') === 0) {
        break;
    }

    if ($inComment) {
        $comment[] = ltrim($line, "* ");
    }
}

$name = $comment[0] ?? "未命名接口";
$desc = $comment[1] ?? "无描述";
$example = $comment[2] ?? "无示例";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($name) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<h1><?= htmlspecialchars($name) ?></h1>
<p><?= htmlspecialchars($desc) ?></p>

<h3>📌 调用示例</h3>
<p><?= htmlspecialchars($example) ?></p>

<h3>🔗 实际访问</h3>
<p>
<a href=<?= $example ?> target="_blank">/api/<?= $file ?></a>
</p>

<iframe src=<?= $example ?> width="100%" height="160"></iframe>

</body>
</html>