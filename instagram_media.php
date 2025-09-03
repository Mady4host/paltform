<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>محتوى إنستجرام المنشور</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body{background:#f1f4f8;font-family:Tahoma,Arial;}
h1{font-weight:700;font-size:24px;color:#0d4e96;margin-bottom:18px;}
.filter-bar{background:#fff;padding:16px;border:1px solid #d8e3ef;border-radius:14px;margin-bottom:18px;}
.stat-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;}
.stat-cards .card{border:1px solid #d5e3f0;border-radius:14px;padding:12px;background:#fff;text-align:center;}
.stat-cards .card h6{font-size:12px;margin:0 0 6px;color:#45617f;font-weight:600;}
.stat-cards .card .num{font-size:20px;font-weight:700;color:#0d4e96;}
.table-wrap{background:#fff;border:1px solid #d8e3ef;border-radius:14px;padding:0;}
.status-pill{padding:4px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.status-published{background:#d9f7e7;color:#0d6b3b;}
.status-failed{background:#ffd9d9;color:#b30000;}
.status-pending{background:#fff8d9;color:#936d00;}
.status-uploading{background:#e2efff;color:#115d9e;}
.media-kind-badge{font-size:10px;padding:4px 7px;border-radius:6px;background:#eef4fb;color:#215178;font-weight:600;}
.preview-img{width:70px;height:70px;object-fit:cover;border:1px solid #d3dce5;border-radius:10px;}
.preview-video{width:90px;height:70px;border:1px solid #d3dce5;border-radius:10px;object-fit:cover;}
.search-input{max-width:260px;}
.highlight-new{animation:flashBg 2.5s ease-in-out;}
@keyframes flashBg{0%{background:#fff9d4;}100%{background:transparent;}}
.badge-acc{background:#eef4fb;color:#184d80;font-size:11px;font-weight:600;border-radius:20px;padding:4px 10px;}
</style>
</head>
<body>
<div class="container py-4" style="max-width:1400px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="m-0">محتوى إنستجرام المنشور</h1>
        <div class="d-flex gap-2">
            <a href="<?= site_url('instagram/upload') ?>" class="btn btn-primary">+ نشر جديد</a>
            <a href="<?= site_url('reels/pages') ?>" class="btn btn-outline-secondary">الصفحات</a>
        </div>
    </div>

    <div class="stat-cards">
        <div class="card"><h6>Published</h6><div class="num"><?= $summary['published'] ?></div></div>
        <div class="card"><h6>Pending</h6><div class="num"><?= $summary['pending'] ?></div></div>
        <div class="card"><h6>Failed</h6><div class="num"><?= $summary['failed'] ?></div></div>
        <div class="card"><h6>Uploading</h6><div class="num"><?= $summary['uploading'] ?></div></div>
        <div class="card"><h6>Total</h6><div class="num"><?= array_sum($summary) ?></div></div>
    </div>

    <div class="filter-bar">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label">حساب IG</label>
                <select name="ig_user_id" class="form-select">
                    <option value="">الكل</option>
                    <?php foreach($accounts as $acc): ?>
                        <option value="<?= htmlspecialchars($acc['ig_user_id']) ?>"
                            <?= (!empty($filter['ig_user_id']) && $filter['ig_user_id']==$acc['ig_user_id'])?'selected':'' ?>>
                            @<?= htmlspecialchars($acc['ig_username'] ?: $acc['ig_user_id']) ?> (<?= htmlspecialchars($acc['page_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select">
                    <option value="">الكل</option>
                    <?php foreach(['published'=>'Published','pending'=>'Pending','failed'=>'Failed','uploading'=>'Uploading'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= (!empty($filter['status']) && $filter['status']===$k)?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label">النوع</label>
                <select name="media_kind" class="form-select">
                    <option value="">الكل</option>
                    <option value="ig_reel" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_reel')?'selected':'' ?>>Reel</option>
                    <option value="ig_story_image" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_story_image')?'selected':'' ?>>Story صورة</option>
                    <option value="ig_story_video" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_story_video')?'selected':'' ?>>Story فيديو</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label">بحث</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filter['q'] ?? '') ?>" class="form-control search-input" placeholder="وصف، Media ID ...">
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button class="btn btn-primary w-100">فلترة</button>
                <a href="<?= site_url('instagram_media/listing') ?>" class="btn btn-outline-secondary w-100">إعادة</a>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>معاينة</th>
                        <th>النوع</th>
                        <th>الوصف</th>
                        <th>الحالة</th>
                        <th>IG User</th>
                        <th>Media ID</th>
                        <th>Creation ID</th>
                        <th>Created</th>
                        <th>Published</th>
                        <th>خطأ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($items)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">لا نتائج.</td></tr>
                <?php else: foreach($items as $row):
                    $isNew = ($just_published_id && $just_published_id == $row['id']);
                    $statusClass='status-'.$row['status'];
                    $kindLabel = $row['media_kind']=='ig_reel' ? 'Reel' :
                                 ($row['media_kind']=='ig_story_image' ? 'Story Image' :
                                 ($row['media_kind']=='ig_story_video' ? 'Story Video' : $row['media_kind']));
                ?>
                    <tr class="<?= $isNew?'highlight-new':'' ?>">
                        <td><?= $row['id'] ?></td>
                        <td>
                            <?php if($row['file_type']=='image'): ?>
                                <img src="<?= base_url($row['file_path']) ?>" class="preview-img" alt="">
                            <?php else: ?>
                                <video class="preview-video" muted>
                                    <source src="<?= base_url($row['file_path']) ?>" type="video/mp4">
                                </video>
                            <?php endif; ?>
                        </td>
                        <td><span class="media-kind-badge"><?= $kindLabel ?></span></td>
                        <td style="max-width:220px;">
                            <?php if($row['description']): ?>
                                <div class="small text-break"><?= nl2br(htmlspecialchars(mb_strimwidth($row['description'],0,300,'…'))) ?></div>
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                        </td>
                        <td><span class="status-pill <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                        <td><span class="badge-acc"><?= htmlspecialchars($row['ig_user_id']) ?></span></td>
                        <td class="small text-break" style="max-width:140px;"><?= $row['media_id']?htmlspecialchars($row['media_id']):'<span class="text-muted">—</span>' ?></td>
                        <td class="small text-break" style="max-width:140px;"><?= $row['creation_id']?htmlspecialchars($row['creation_id']):'<span class="text-muted">—</span>' ?></td>
                        <td class="small"><?= $row['created_at'] ?></td>
                        <td class="small"><?= $row['published_at'] ?: '<span class="text-muted">—</span>' ?></td>
                        <td class="small text-danger" style="max-width:180px;">
                            <?= $row['last_error'] ? htmlspecialchars(mb_strimwidth($row['last_error'],0,180,'…')) : '<span class="text-muted">—</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($pages>1): ?>
        <nav class="pagination d-flex flex-wrap gap-2 mt-3">
            <?php for($i=1;$i<=$pages;$i++):
                $qs = $_GET; $qs['page']=$i; $url = site_url('instagram_media/listing').'?'.http_build_query($qs);
            ?>
                <a class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline-primary' ?>" href="<?= $url ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <div class="mt-4">
        <a href="<?= site_url('instagram/upload') ?>" class="btn btn-outline-primary">+ نشر جديد</a>
    </div>
</div>
</body>
</html>