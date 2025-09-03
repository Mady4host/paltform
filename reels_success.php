<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>نتيجة رفع الريلز</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width:650px;margin-top:35px;">
    <h2 class="mb-3 text-center text-success">نتائج رفع الريلز</h2>
    <?php if (!empty($responses)): ?>
        <?php foreach ($responses as $r): ?>
            <div class="alert alert-<?= $r['type']=='success'?'success':'danger' ?>"><?= $r['msg'] ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="<?= base_url('reels/upload') ?>" class="btn btn-primary mt-3">رفع ريلز جديد</a>
    <a href="<?= base_url('reels/list') ?>" class="btn btn-secondary mt-3">عرض كل الريلز</a>
</div>
</body>
</html>