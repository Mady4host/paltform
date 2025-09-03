<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة الريلز / القصص</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body { background:#f4f6fa; font-family:Tahoma, Arial; }
.section-box { background:#fff; border:1px solid #dfe4ec; border-radius:8px; padding:18px; margin-bottom:28px; }
.section-title { font-size:20px; font-weight:600; color:#1f4b87; margin-bottom:15px; }
.table thead th { white-space:nowrap; }
.page-cell { display:flex; align-items:center; gap:6px; }
.page-cell img { width:34px; height:34px; border-radius:50%; object-fit:cover; border:1px solid #ccc; }
.cover-box img { width:70px; height:105px; object-fit:cover; border:1px solid #bbb; border-radius:6px; display:block; }
.badge-status.pending { background:#ffc107; }
.badge-status.uploaded, .badge-status.published { background:#28a745; }
.badge-status.failed { background:#dc3545; }
.badge-type { font-size:11px; }
.badge-type.reel { background:#0d6efd; }
.badge-type.story_video { background:#845ef7; }
.badge-type.story_photo { background:#fd7e14; }
.time-local { font-family:monospace; font-size:12px; direction:ltr; }
.filter-bar { background:#fff; border:1px solid #e0e5ec; border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
.filter-bar select, .filter-bar input { max-width:200px; }
</style>
</head>
<body>
<div class="container" style="max-width:1500px;margin-top:25px;">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2 class="text-primary m-0">إدارة الريلز / القصص</h2>
        <div class="d-flex gap-2">
            <a href="<?= site_url('dashboard') ?>" class="btn btn-secondary">الرئيسية</a>
            <a href="<?= site_url('reels/upload') ?>" class="btn btn-success">رفع جديد</a>
            <a href="<?= site_url('reels/pages') ?>" class="btn btn-secondary">الصفحات</a>
        </div>
    </div>

    <?php if ($this->session->flashdata('msg_success')): ?>
        <div class="alert alert-success auto-hide"><?= $this->session->flashdata('msg_success') ?></div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('msg')): ?>
        <div class="alert alert-danger auto-hide"><?= $this->session->flashdata('msg') ?></div>
    <?php endif; ?>

    <?php
        // هل لدينا عمود media_type؟
        $hasMediaTypeScheduled = !empty($scheduled_reels) && array_key_exists('media_type',$scheduled_reels[0]);
        $hasMediaTypePublished = !empty($reels) && array_key_exists('media_type',$reels[0]);
        $hasExpiresScheduled   = !empty($scheduled_reels) && array_key_exists('expires_at',$scheduled_reels[0]);
        $hasExpiresPublished   = !empty($reels) && array_key_exists('expires_at',$reels[0]);
        $typeLabel = function($row){
            $mt = $row['media_type'] ?? 'reel';
            if($mt==='story_video') return 'Story Video';
            if($mt==='story_photo') return 'Story Photo';
            return 'Reel';
        };
        $typeClass = function($row){
            $mt = $row['media_type'] ?? 'reel';
            if($mt==='story_video') return 'story_video';
            if($mt==='story_photo') return 'story_photo';
            return 'reel';
        };
    ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#scheduledTab" type="button">المجدولة</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#immediateTab" type="button">المنشورة / الفورية</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- مجدولة -->
        <div class="tab-pane fade show active" id="scheduledTab">
            <div class="section-box">

                <!-- فلتر بسيط (محلي JS) -->
                <div class="filter-bar" id="scheduledFilters">
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">بحث في الوصف</label>
                        <input type="text" id="schedSearch" class="form-control form-control-sm" placeholder="كلمة مفتاحية...">
                    </div>
                    <?php if($hasMediaTypeScheduled): ?>
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">نوع</label>
                        <select id="schedType" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <option value="reel">Reel</option>
                            <option value="story_video">Story Video</option>
                            <option value="story_photo">Story Photo</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">حالة</label>
                        <select id="schedStatus" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <option value="pending">معلق</option>
                            <option value="uploaded">تم</option>
                            <option value="failed">فشل</option>
                        </select>
                    </div>
                </div>

                <h3 class="section-title">العناصر المجدولة</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="scheduledTable">
                        <thead class="table-light">
                        <tr class="text-center">
                            <th>#</th>
                            <th>الصفحة</th>
                            <?php if($hasMediaTypeScheduled): ?><th>النوع</th><?php endif; ?>
                            <th>الغلاف</th>
                            <th style="max-width:230px;">الوصف</th>
                            <th>وقت الجدولة (محلي)</th>
                            <?php if($hasExpiresScheduled): ?><th>انتهاء (Story)</th><?php endif; ?>
                            <th>الحالة</th>
                            <th>محاولات</th>
                            <th>آخر خطأ</th>
                            <th>منشور عند</th>
                            <th>أكشن</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($scheduled_reels)): ?>
                            <tr><td colspan="<?= 11 + ($hasMediaTypeScheduled?1:0) + ($hasExpiresScheduled?1:0) ?>" class="text-center text-muted">لا توجد عناصر مجدولة.</td></tr>
                        <?php else: foreach ($scheduled_reels as $i=>$r):
                            $pageInfo = $pages_map[$r['fb_page_id']] ?? null;
                            $pageName = $pageInfo['name'] ?? $r['fb_page_id'];
                            $img_src = !empty($pageInfo['pic'])
                                ? $pageInfo['pic']
                                : 'https://graph.facebook.com/'.$r['fb_page_id'].'/picture?type=normal';
                            $pageLink = $pageInfo['link'] ?? 'https://facebook.com/'.$r['fb_page_id'];
                            $localStored = $r['original_local_time'];
                            $mtClass = $typeClass($r);
                            $mtLabel = $typeLabel($r);
                            $isStory = ($r['media_type'] ?? '')==='story_video' || ($r['media_type'] ?? '')==='story_photo';
                            ?>
                            <tr data-status="<?= htmlspecialchars($r['status']) ?>"
                                data-type="<?= htmlspecialchars($r['media_type'] ?? 'reel') ?>"
                                data-desc="<?= htmlspecialchars(mb_strtolower($r['description'] ?? '', 'UTF-8')) ?>">
                                <td class="text-center"><?= $i+1 ?></td>
                                <td>
                                    <a class="page-cell text-decoration-none" href="<?= htmlspecialchars($pageLink) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($img_src) ?>"
                                             alt="صفحة"
                                             onerror="this.onerror=null;this.src='https://graph.facebook.com/<?= htmlspecialchars($r['fb_page_id']) ?>/picture?type=normal';">
                                        <span><?= htmlspecialchars($pageName) ?></span>
                                    </a>
                                </td>
                                <?php if($hasMediaTypeScheduled): ?>
                                <td class="text-center">
                                    <span class="badge badge-type <?= $mtClass ?>"><?= $mtLabel ?></span>
                                </td>
                                <?php endif; ?>
                                <td class="cover-box text-center">
                                    <?php if(!empty($r['cover_path'])): ?>
                                        <img src="<?= base_url(htmlspecialchars($r['cover_path'])) ?>" alt="غلاف">
                                    <?php else: ?>
                                        <span style="font-size:11px;color:#888;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:230px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars(mb_substr($r['description'],0,150,'UTF-8')) ?>
                                </td>
                                <td>
                                    <?php if ($localStored): ?>
                                        <span class="time-local" data-utc="<?= htmlspecialchars($r['scheduled_time']) ?>"><?= htmlspecialchars($localStored) ?></span>
                                    <?php else: ?>
                                        <span class="time-local" data-utc="<?= htmlspecialchars($r['scheduled_time']) ?>"><?= htmlspecialchars($r['scheduled_time']) ?> UTC</span>
                                    <?php endif; ?>
                                </td>
                                <?php if($hasExpiresScheduled): ?>
                                <td class="text-center">
                                    <?php if(!empty($r['expires_at'])): ?>
                                        <span class="time-local" data-utc="<?= htmlspecialchars($r['expires_at']) ?>">
                                            <?= htmlspecialchars($r['expires_at']) ?> UTC
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php
                                        $cls='secondary'; $label=$r['status'];
                                        if ($r['status']==='pending'){ $cls='warning'; $label='معلق'; }
                                        elseif ($r['status']==='uploaded'){ $cls='success'; $label='تم'; }
                                        elseif ($r['status']==='failed'){ $cls='danger'; $label='فشل'; }
                                    ?>
                                    <span class="badge badge-status bg-<?= $cls ?>"><?= $label ?></span>
                                </td>
                                <td class="text-center"><?= (int)$r['attempt_count'] ?></td>
                                <td style="max-width:170px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($r['last_error'] ?? '') ?></td>
                                <td>
                                    <?php if (!empty($r['published_time'])): ?>
                                        <span class="time-local" data-utc="<?= htmlspecialchars($r['published_time']) ?>"><?= htmlspecialchars($r['published_time']) ?> (UTC)</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="min-width:155px;">
                                    <a class="btn btn-sm btn-outline-info" href="<?= site_url('reels/scheduled_logs/'.$r['id']) ?>">لوج</a>
                                    <?php if ($r['status']==='pending'): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('reels/edit_scheduled/'.$r['id']) ?>">تعديل</a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= site_url('reels/delete_scheduled/'.$r['id']) ?>" onclick="return confirm('حذف هذا العنصر؟');">حذف</a>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">---</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if($hasMediaTypeScheduled): ?>
                    <div style="font-size:11px;color:#666;margin-top:6px;">
                        ملاحظة: القصص (Story Video / Story Photo) تنتهي بعد 24 ساعة (نخزن التقدير محلياً في expires_at).
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- فورية / منشورة -->
        <div class="tab-pane fade" id="immediateTab">
            <div class="section-box">

                <div class="filter-bar" id="publishedFilters">
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">بحث في الوصف</label>
                        <input type="text" id="pubSearch" class="form-control form-control-sm" placeholder="كلمة مفتاحية...">
                    </div>
                    <?php if($hasMediaTypePublished): ?>
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">نوع</label>
                        <select id="pubType" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <option value="reel">Reel</option>
                            <option value="story_video">Story Video</option>
                            <option value="story_photo">Story Photo</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex flex-column">
                        <label style="font-size:12px;margin-bottom:2px;">حالة</label>
                        <select id="pubStatus" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <option value="published">تم</option>
                            <option value="pending">معلق</option>
                            <option value="uploaded">تم</option>
                        </select>
                    </div>
                </div>

                <h3 class="section-title">العناصر المنشورة / الفورية</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="publishedTable">
                        <thead class="table-light">
                        <tr class="text-center">
                            <th>#</th>
                            <th>الصفحة</th>
                            <?php if($hasMediaTypePublished): ?><th>النوع</th><?php endif; ?>
                            <th>الغلاف</th>
                            <th>الملف</th>
                            <th>الوصف</th>
                            <?php if($hasExpiresPublished): ?><th>انتهاء (Story)</th><?php endif; ?>
                            <th>الحالة</th>
                            <th>وقت الإنشاء (UTC)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($reels)): ?>
                            <tr><td colspan="<?= 8 + ($hasMediaTypePublished?1:0) + ($hasExpiresPublished?1:0) ?>" class="text-center text-muted">لا توجد عناصر.</td></tr>
                        <?php else: foreach ($reels as $i=>$r):
                            $pageInfo = $pages_map[$r['fb_page_id']] ?? null;
                            $pageName = $pageInfo['name'] ?? $r['fb_page_id'];
                            $img_src = !empty($pageInfo['pic'])
                                ? $pageInfo['pic']
                                : 'https://graph.facebook.com/'.$r['fb_page_id'].'/picture?type=normal';
                            $pageLink = $pageInfo['link'] ?? 'https://facebook.com/'.$r['fb_page_id'];
                            $mtClass = $typeClass($r);
                            $mtLabel = $typeLabel($r);
                        ?>
                            <tr data-status="<?= htmlspecialchars($r['status']) ?>"
                                data-type="<?= htmlspecialchars($r['media_type'] ?? 'reel') ?>"
                                data-desc="<?= htmlspecialchars(mb_strtolower($r['description'] ?? '', 'UTF-8')) ?>">
                                <td class="text-center"><?= $i+1 ?></td>
                                <td>
                                    <a class="page-cell text-decoration-none" href="<?= htmlspecialchars($pageLink) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($img_src) ?>"
                                             alt="صفحة"
                                             onerror="this.onerror=null;this.src='https://graph.facebook.com/<?= htmlspecialchars($r['fb_page_id']) ?>/picture?type=normal';">
                                        <span><?= htmlspecialchars($pageName) ?></span>
                                    </a>
                                </td>
                                <?php if($hasMediaTypePublished): ?>
                                <td class="text-center">
                                    <span class="badge badge-type <?= $mtClass ?>"><?= $mtLabel ?></span>
                                </td>
                                <?php endif; ?>
                                <td class="cover-box text-center">
                                    <?php if(!empty($r['cover_path'])): ?>
                                        <img src="<?= base_url(htmlspecialchars($r['cover_path'])) ?>" alt="غلاف">
                                    <?php else: ?>
                                        <span style="font-size:11px;color:#888;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['file_name'] ?? '') ?></td>
                                <td style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars(mb_substr($r['description'] ?? '',0,160,'UTF-8')) ?>
                                </td>
                                <?php if($hasExpiresPublished): ?>
                                <td class="text-center">
                                    <?php if(!empty($r['expires_at'])): ?>
                                        <span class="time-local" data-utc="<?= htmlspecialchars($r['expires_at']) ?>">
                                            <?= htmlspecialchars($r['expires_at']) ?> UTC
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php
                                        $cls='secondary'; $label=$r['status'];
                                        if ($r['status']==='pending'){ $cls='warning'; $label='معلق'; }
                                        elseif ($r['status']==='published' || $r['status']==='uploaded'){ $cls='success'; $label='تم'; }
                                    ?>
                                    <span class="badge badge-status bg-<?= $cls ?>"><?= $label ?></span>
                                </td>
                                <td><span class="time-local" data-utc="<?= htmlspecialchars($r['created_at']) ?>"><?= htmlspecialchars($r['created_at']) ?> UTC</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if($hasMediaTypePublished): ?>
                    <div style="font-size:11px;color:#666;margin-top:6px;">
                        يظهر وقت انتهاء القصص (إن وجد) في عمود "انتهاء (Story)".
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toLocal(utcStr){
    if(!utcStr) return '';
    const d = new Date(utcStr.replace(' ','T')+'Z');
    if (isNaN(d)) return utcStr + ' UTC';
    return d.toLocaleString();
}
document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.time-local').forEach(el=>{
        const utc = el.getAttribute('data-utc');
        if(utc) el.textContent = toLocal(utc);
    });
    setTimeout(()=>{
        document.querySelectorAll('.auto-hide').forEach(a=>{
            a.style.transition='opacity .7s'; a.style.opacity=0;
            setTimeout(()=>a.remove(),700);
        });
    },4000);

    // فلاتر مجدول
    const schedSearch = document.getElementById('schedSearch');
    const schedType   = document.getElementById('schedType');
    const schedStatus = document.getElementById('schedStatus');
    function filterScheduled(){
        const q = (schedSearch?.value || '').trim().toLowerCase();
        const t = (schedType?.value || '');
        const s = (schedStatus?.value || '');
        document.querySelectorAll('#scheduledTable tbody tr').forEach(tr=>{
            const desc = tr.getAttribute('data-desc')||'';
            const type = tr.getAttribute('data-type')||'';
            const st   = tr.getAttribute('data-status')||'';
            let ok = true;
            if(q && desc.indexOf(q)===-1) ok=false;
            if(t && type!==t) ok=false;
            if(s && st!==s) ok=false;
            tr.style.display = ok ? '' : 'none';
        });
    }
    schedSearch?.addEventListener('input',filterScheduled);
    schedType?.addEventListener('change',filterScheduled);
    schedStatus?.addEventListener('change',filterScheduled);

    // فلاتر منشور
    const pubSearch = document.getElementById('pubSearch');
    const pubType   = document.getElementById('pubType');
    const pubStatus = document.getElementById('pubStatus');
    function filterPublished(){
        const q = (pubSearch?.value || '').trim().toLowerCase();
        const t = (pubType?.value || '');
        const s = (pubStatus?.value || '');
        document.querySelectorAll('#publishedTable tbody tr').forEach(tr=>{
            const desc = tr.getAttribute('data-desc')||'';
            const type = tr.getAttribute('data-type')||'';
            const st   = tr.getAttribute('data-status')||'';
            let ok = true;
            if(q && desc.indexOf(q)===-1) ok=false;
            if(t && type!==t) ok=false;
            if(s && st!==s) ok=false;
            tr.style.display = ok ? '' : 'none';
        });
    }
    pubSearch?.addEventListener('input',filterPublished);
    pubType?.addEventListener('change',filterPublished);
    pubStatus?.addEventListener('change',filterPublished);
});
</script>
</body>
</html>