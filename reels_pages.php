<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>الصفحات المتصلة</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body{background:#f1f4f8;font-family:Tahoma,Arial;}
h1{font-weight:600;color:#0d4e96;font-size:26px;}
.summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px;}
.summary-cards .card{border:1px solid #d4e2f1;border-radius:14px;padding:14px;background:#fff;}
.summary-cards .card h6{font-size:12px;font-weight:600;color:#445b78;margin:0 0 6px;}
.summary-cards .card .big{font-size:20px;font-weight:700;color:#0d4e96;}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:18px;}
.toolbar .search-box{flex:1;min-width:240px;position:relative;}
.toolbar .search-box input{padding-inline-start:34px;}
.toolbar .search-box .icon{position:absolute;top:50%;transform:translateY(-50%);right:10px;color:#5281b5;}
.badge-filter{cursor:pointer;background:#e7eef7;color:#1a4e82;font-weight:500;border-radius:20px;padding:6px 14px;font-size:12px;border:1px solid #c6d7e7;}
.badge-filter.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.view-toggle button{border:1px solid #bcd1e3;background:#fff;color:#2f5b83;padding:6px 12px;border-radius:8px;font-size:13px;}
.view-toggle button.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.pages-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}
.page-card{background:#fff;border:1px solid #d6e1ee;border-radius:16px;padding:14px;display:flex;flex-direction:column;gap:12px;position:relative;transition:.25s;}
.page-card:hover{box-shadow:0 4px 16px rgba(0,30,70,.07);border-color:#b9d2e9;}
.page-avatar{width:54px;height:54px;border-radius:50%;overflow:hidden;border:2px solid #dee8f3;background:#f4f7fb;position:relative;}
.page-avatar img{width:100%;height:100%;object-fit:cover;}
.pin-btn{position:absolute;top:8px;left:8px;font-size:15px;color:#999;cursor:pointer;}
.pin-btn.pinned{color:#ff9800;}
.health-badge{font-size:11px;font-weight:600;padding:4px 8px;border-radius:8px;display:inline-flex;align-items:center;gap:4px;}
.health-ok{background:#e7f7ed;color:#187c42;}
.health-expiring{background:#fff4d6;color:#b97800;}
.health-expired{background:#ffe4e4;color:#a40000;}
.quick-actions{display:flex;flex-wrap:wrap;gap:6px;}
.quick-actions button{flex:1 1 48%;font-size:11px;padding:6px 8px;border-radius:10px;border:1px solid #c7d7e9;background:#f2f7fc;color:#1c5287;font-weight:600;cursor:pointer;}
.quick-actions button:hover{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.quick-actions button.danger{color:#b30000;background:#fde8e8;border-color:#f5bdbd;}
.quick-actions button.danger:hover{background:#dc3545;color:#fff;border-color:#dc3545;}
.select-box{position:absolute;top:8px;right:8px;}
.bulk-bar{position:sticky;bottom:0;left:0;right:0;background:#ffffffd9;border-top:1px solid #d3e2f0;padding:10px 14px;display:none;z-index:30;}
.toast-box{position:fixed;bottom:20px;left:20px;z-index:999;display:flex;flex-direction:column;gap:10px;}
.toast-msg{background:#0d6efd;color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;box-shadow:0 4px 10px rgba(0,0,0,.15);animation:fadeIn .3s;}
.toast-msg.error{background:#dc3545;}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
.empty-state{background:#fff;border:2px dashed #bcd0e2;border-radius:16px;padding:50px;text-align:center;color:#5f7085;font-size:14px;}

/* Instagram Badges */
.ig-block{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:6px;}
.badge-ig{font-size:10px;font-weight:600;padding:4px 8px;border-radius:20px;display:inline-flex;align-items:center;gap:4px;line-height:1;}
.badge-ig-linked{background:#fde7ff;color:#ad1fb7;border:1px solid #e5b8ef;}
.badge-ig-detected{background:#f2f6fb;color:#1d4e85;border:1px dashed #b6cee4;cursor:pointer;}
.badge-ig-none{background:#edf1f5;color:#6b7d8f;}
.badge-ig-health-ok{background:#d9f9e6;color:#117a3d;}
.badge-ig-health-expiring{background:#fff4d2;color:#9d6d00;}
.badge-ig-health-bad{background:#ffe1e1;color:#a40000;}
.badge-ig-health-revoked{background:#ececec;color:#555;}
.btn-ig-upload{background:#ff4fa4;color:#fff;border:1px solid #ff4fa4;font-size:11px;padding:4px 10px;border-radius:8px;cursor:pointer;}
.btn-ig-upload:hover{background:#ff2f93;color:#fff;}
.ig-avatar{width:22px;height:22px;border-radius:50%;object-fit:cover;border:1px solid #ddd;}

/* QUICK IG MULTI MODAL (ADVANCED) */
#igQuickModal .modal-content{border-radius:20px;border:1px solid #d6e4f2;}
#igQuickModal .modal-header{background:#0d4e96;color:#fff;border-top-left-radius:20px;border-top-right-radius:20px;}
.q-file-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.q-file-card{background:#f7fbff;border:1px solid #d3e3f2;border-radius:18px;padding:14px 16px;display:flex;flex-direction:column;gap:12px;position:relative;}
.q-file-card:hover{border-color:#b8d2ea;box-shadow:0 4px 18px -4px rgba(0,40,90,.1);}
.q-file-head{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.q-file-name{font-weight:600;font-size:12.5px;color:#0d4e96;flex:1;word-break:break-all;}
.q-file-remove{cursor:pointer;color:#c40000;font-weight:700;font-size:18px;}
.q-media-preview img,.q-media-preview video{max-width:100%;border-radius:14px;border:1px solid #d3e3f2;max-height:200px;object-fit:cover;background:#fff;}
.q-tabs{display:flex;flex-wrap:wrap;gap:6px;}
.q-tab-btn{background:#eef5fb;border:1px solid #c9d8e7;color:#1d558b;font-size:11px;font-weight:600;padding:5px 10px;border-radius:22px;cursor:pointer;transition:.2s;}
.q-tab-btn.active{background:#0d6efd;border-color:#0d6efd;color:#fff;box-shadow:0 4px 12px -4px rgba(13,110,253,.6);}
.q-pane{display:none;animation:qfade .25s;}
.q-pane.active{display:block;}
@keyframes qfade{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:translateY(0);}}
.q-caption{background:#fff;border:1px solid #d3e3f2;border-radius:14px;resize:vertical;min-height:90px;font-size:13px;}
.q-caption:focus{border-color:#0d6efd;outline:none;box-shadow:0 0 0 2px rgba(13,110,253,.2);}
.q-caption-counter{font-size:11px;color:#6d8094;margin-top:4px;text-align:left;direction:ltr;}
.q-comments-count{width:auto;}
.q-comment-item textarea{background:#fff;border:1px solid #d5e5f3;border-radius:12px;resize:vertical;min-height:55px;font-size:12px;}
.q-comment-item textarea:focus{border-color:#0d6efd;outline:none;box-shadow:0 0 0 2px rgba(13,110,253,.18);}
.q-hash-tools .btn{font-size:11px;padding:4px 8px;font-weight:600;}
.q-hash-field{background:#fff;border:1px solid #d4e5f3;border-radius:14px;resize:vertical;min-height:60px;font-size:12.5px;}
.q-hashtag-tags span{display:inline-block;background:#e5f2ff;color:#155081;margin:4px 4px 0 0;padding:4px 8px;border-radius:10px;font-size:11px;font-weight:600;cursor:pointer;}
.q-hashtag-tags span:hover{background:#d3e9ff;}
.q-publish-box{border:1px solid #d4e5f3;background:#fafdff;border-radius:16px;padding:12px 14px;margin-top:4px;}
.q-schedule-rows .q-srow{border:1px dashed #c2d7eb;background:#fff;border-radius:14px;padding:10px 12px;margin-top:10px;}
.q-srow input[type=datetime-local], .q-srow select{background:#fff;border:1px solid #c7dbec;border-radius:10px;font-size:12.5px;padding:6px 10px;}
.q-srow input[type=datetime-local]:focus, .q-srow select:focus{outline:none;border-color:#0d6efd;box-shadow:0 0 0 2px rgba(13,110,253,.2);}
@supports (-moz-appearance:none){
  .q-srow input[type=datetime-local]{min-height:38px;}
}
.q-progress-wrap{display:none;margin-top:20px;}
.q-progress-wrap .progress{height:20px;}
.q-results{display:none;background:#f6faff;border:1px solid #d3e4f2;padding:14px 16px;border-radius:16px;font-size:12.5px;max-height:280px;overflow:auto;margin-top:20px;}
.q-results .ok{color:#0a7b2c;font-weight:600;}
.q-results .scheduled{color:#e39600;font-weight:600;}
.q-results .err{color:#cc1d1d;font-weight:600;}
.q-action-bar{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;}
.q-action-bar .btn{min-width:120px;font-weight:600;border-radius:14px;}
.inline-note{font-size:11px;color:#667a8f;}
.caption-counter{font-size:11px;color:#666;}
.progress{height:18px;}
.results-log{max-height:190px;overflow:auto;background:#f6faff;border:1px solid #d2e3f4;padding:8px 10px;border-radius:10px;font-size:12px;display:none;}
.results-log .ok{color:#0a7b2c;font-weight:600;}
.results-log .err{color:#c01d1d;font-weight:600;}
.small-badge{font-size:10px;padding:3px 6px;border-radius:8px;background:#eef4fb;color:#184d7a;font-weight:600;display:inline-block;margin:2px 4px 2px 0;}
.inline-note-global{font-size:11px;color:#6c8194;}
</style>
</head>
<body class="mode-grid" id="bodyRoot">

<div class="container py-4" style="max-width:1400px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h1 class="m-0">الصفحات المتصلة</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-primary">الرئيسيه</a>
            <button class="btn btn-primary" id="btnConnect">ربط صفحات جديدة</button>
            <a href="<?= site_url('reels/upload') ?>" class="btn btn-outline-primary">رفع ريلز/ستوري (فيسبوك)</a>
            <button class="btn btn-outline-danger" id="btnOpenIGMulti" disabled>نشر إنستجرام (المحدد)</button>
        </div>
    </div>

    <div class="summary-cards">
        <div class="card"><h6>إجمالي الصفحات</h6><div class="big" id="sumTotal"><?= count($pages ?? []) ?></div></div>
        <div class="card"><h6>المجدول الآن</h6><div class="big" id="sumScheduled">0</div></div>
        <div class="card"><h6>تنتهي قريباً</h6><div class="big" id="sumExpiring">0</div></div>
        <div class="card"><h6>منتهية</h6><div class="big" id="sumExpired">0</div></div>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <span class="icon">🔍</span>
            <input type="text" id="searchInput" class="form-control" placeholder="بحث عن صفحة...">
        </div>
        <div class="d-flex flex-wrap gap-2" id="filters">
            <div class="badge-filter active" data-filter="all">الكل</div>
            <div class="badge-filter" data-filter="favorite">مفضلة</div>
            <div class="badge-filter" data-filter="expiring">تنتهي قريباً</div>
            <div class="badge-filter" data-filter="expired">منتهية</div>
            <div class="badge-filter" data-filter="healthy">سليمة</div>
            <div class="badge-filter" data-filter="ig_linked">لها إنستجرام</div>
        </div>
        <div class="view-toggle d-flex gap-2">
            <button id="btnGrid" class="active" title="عرض شبكي">🔲</button>
            <button id="btnList" title="عرض قائمة">📋</button>
        </div>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">ترتيب</button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item sort-option" data-sort="name">الاسم</a></li>
                <li><a class="dropdown-item sort-option" data-sort="last_posted">آخر نشر</a></li>
                <li><a class="dropdown-item sort-option" data-sort="scheduled_count">عدد المجدول</a></li>
                <li><a class="dropdown-item sort-option" data-sort="health">الحالة</a></li>
                <li><a class="dropdown-item sort-option" data-sort="ig">إنستجرام</a></li>
            </ul>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary" id="btnSelectAll">تحديد الكل (المعروض)</button>
            <button type="button" class="btn btn-outline-secondary" id="btnUnselectAll" style="display:none;">إلغاء الكل</button>
        </div>
    </div>

    <div id="pagesContainer">
        <?php if(!empty($pages)): ?>
        <div class="pages-grid" id="gridMode">
            <?php foreach($pages as $p):
                $health     = $p['health_status'] ?? 'ok';
                $fav        = !empty($p['is_favorite']);
                $scheduled  = (int)($p['scheduled_count'] ?? 0);
                $pic        = $p['page_picture'] ?: 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
                $fallback   = 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
                if(!empty($p['page_access_token'])) $fallback .= '&access_token='.urlencode($p['page_access_token']);
                $ig_state = 'none';
                if (!empty($p['ig_linked']) && !empty($p['ig_user_id'])) {
                    $ig_state = 'linked';
                } elseif (!empty($p['ig_detected_user_id']) && empty($p['ig_linked'])) {
                    $ig_state = 'detected';
                }
                $ig_health = $p['ig_health_status'] ?? '';
                $ig_badge_health_class = '';
                if ($ig_state==='linked') {
                    switch ($ig_health) {
                        case 'ok':       $ig_badge_health_class='badge-ig-health-ok'; break;
                        case 'expiring': $ig_badge_health_class='badge-ig-health-expiring'; break;
                        case 'expired':
                        case 'error':    $ig_badge_health_class='badge-ig-health-bad'; break;
                        case 'revoked':  $ig_badge_health_class='badge-ig-health-revoked'; break;
                    }
                }
            ?>
            <div class="page-card"
                 data-name="<?= htmlspecialchars(mb_strtolower($p['page_name'])) ?>"
                 data-health="<?= htmlspecialchars($health) ?>"
                 data-fav="<?= $fav?1:0 ?>"
                 data-scheduled="<?= $scheduled ?>"
                 data-last_posted="<?= htmlspecialchars($p['last_posted_at'] ?? '') ?>"
                 data-id="<?= htmlspecialchars($p['fb_page_id']) ?>"
                 data-ig_state="<?= $ig_state ?>"
                 data-ig_user="<?= htmlspecialchars($p['ig_user_id'] ?? '') ?>"
                 data-ig_username="<?= htmlspecialchars($p['ig_username'] ?? '') ?>"
                 data-ig_pic="<?= htmlspecialchars($p['ig_profile_picture'] ?? '') ?>"
                 data-ig_health="<?= htmlspecialchars($ig_health) ?>">

                <div class="select-box form-check">
                    <input class="form-check-input select-page" type="checkbox" value="<?= htmlspecialchars($p['fb_page_id']) ?>">
                </div>

                <div class="pin-btn <?= $fav?'pinned':'' ?>" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>" title="<?= $fav?'إزالة من المفضلة':'تثبيت في الأعلى' ?>">📌</div>

                <div class="d-flex align-items-center gap-3">
                    <div class="page-avatar">
                        <img src="<?= htmlspecialchars($pic) ?>"
                             data-fallback="<?= htmlspecialchars($fallback) ?>"
                             onerror="if(!this.dataset.retry){this.dataset.retry=1;this.src=this.dataset.fallback;}">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong style="color:#164d84;"><?= htmlspecialchars($p['page_name']) ?></strong>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($p['fb_page_id']) ?></span>
                            <span class="health-badge <?= $health==='ok'?'health-ok':($health==='expiring'?'health-expiring':($health==='expired'?'health-expired':'')) ?>">
                                <?= $health==='ok'?'سليم':($health==='expiring'?'قارب الانتهاء':($health==='expired'?'منتهي':'غير معروف')) ?>
                            </span>
                        </div>

                        <div class="ig-block">
                            <?php if($ig_state==='linked'): ?>
                                <span class="badge-ig badge-ig-linked" title="Instagram Linked">
                                    IG @<?= htmlspecialchars($p['ig_username'] ?: ($p['ig_user_id'] ?? '')) ?>
                                </span>
                                <?php if(!empty($ig_badge_health_class)): ?>
                                    <span class="badge-ig <?= $ig_badge_health_class ?>" title="الحالة: <?= htmlspecialchars($ig_health) ?>">
                                        <?= $ig_health ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($p['ig_profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($p['ig_profile_picture']) ?>" class="ig-avatar" alt="IG">
                                <?php endif; ?>
                                <button class="btn-ig-upload" data-ig="<?= htmlspecialchars($p['ig_user_id']) ?>" data-ig-username="<?= htmlspecialchars($p['ig_username'] ?: $p['ig_user_id']) ?>">IG نشر</button>
                            <?php elseif($ig_state==='detected'): ?>
                                <span class="badge-ig badge-ig-detected link-ig-btn"
                                      data-page="<?= htmlspecialchars($p['fb_page_id']) ?>"
                                      title="تم اكتشاف حساب إنستجرام متصل - ربط الآن">
                                      ربط IG
                                </span>
                                <span class="badge-ig badge-ig-none" title="لم يتم الربط">غير مربوط</span>
                            <?php else: ?>
                                <span class="badge-ig badge-ig-none" title="لا يوجد IG متصل">لا IG</span>
                            <?php endif; ?>
                        </div>

                        <div class="text-muted small mt-1">
                            مجدول: <?= $scheduled ?>
                            <?php if(!empty($p['last_posted_at'])): ?>
                                | آخر نشر: <?= date('Y-m-d H:i',strtotime($p['last_posted_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <button data-action="upload" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>">رفع</button>
                    <button data-action="schedule" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>">جدولة</button>
                    <button data-action="view_scheduled" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>">المجدول</button>
                    <button data-action="sync" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>">مزامنة</button>
                    <button class="danger" data-action="unlink" data-page="<?= htmlspecialchars($p['fb_page_id']) ?>">حذف الربط</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">لا توجد صفحات متصلة حالياً.</div>
        <?php endif; ?>
    </div>
</div>

<div class="bulk-bar" id="bulkBar">
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div>
            <span class="selected-count" id="bulkSelectedCount">0</span> صفحة محددة
            <span id="bulkIGLinkedInfo" class="inline-note-global ms-2"></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-primary" id="bulkSync">مزامنة</button>
            <button class="btn btn-sm btn-outline-secondary" id="bulkFav">مفضلة</button>
            <button class="btn btn-sm btn-outline-secondary" id="bulkUnfav">إزالة مفضلة</button>
            <button class="btn btn-sm btn-outline-danger" id="bulkUnlink">حذف الربط</button>
            <button class="btn btn-sm btn-primary" id="bulkGoUpload">رفع للمحدد</button>
            <button class="btn btn-sm btn-danger" id="bulkGoIG" disabled>نشر IG للمحدد</button>
            <button class="btn btn-sm btn-dark" id="bulkClear">إلغاء</button>
        </div>
    </div>
</div>

<!-- Modal المحتوى المجدول -->
<div class="modal fade" id="scheduledModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">المحتوى المجدول</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="scheduledBody"><div class="text-center text-muted py-4">جاري التحميل...</div></div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button></div>
    </div>
  </div>
</div>

<!-- ADVANCED IG QUICK MULTI-FILE MODAL -->
<div class="modal fade" id="igQuickModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">نشر إنستجرام متعدد (سريع)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <form id="igQuickForm" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="ig_user_id" id="quickPrimaryIG">
          <!-- إضافات التوافق مع منطق فيسبوك داخل publish() -->
          <input type="hidden" name="tz_offset_minutes" id="tz_offset_minutes">
          <input type="hidden" name="_tz_offset" id="_tz_offset">
          <input type="hidden" name="_tz_name" id="_tz_name">

          <div class="row g-4">
            <!-- إعدادات عامة للحسابات و إضافة الملفات -->
            <div class="col-xl-3">
              <div class="p-3 border rounded bg-white">
                <h6 class="fw-bold mb-2">الحسابات المحددة</h6>
                <div id="quickAccountsBox" class="small mb-2"></div>
                <div class="small text-muted mb-3">أول حساب = الأساسي (ig_user_id). سيتم النشر على الجميع.</div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">نوع المحتوى</label>
                  <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="media_kind" id="qKindReel" value="reel" checked>
                    <label class="btn btn-sm btn-outline-primary" for="qKindReel">Reels</label>
                    <input type="radio" class="btn-check" name="media_kind" id="qKindStory" value="story">
                    <label class="btn btn-sm btn-outline-primary" for="qKindStory">Stories</label>
                  </div>
                </div>

                <div>
                  <label class="form-label small fw-bold">الملفات</label>
                  <div id="qFileDrop" class="border border-2 border-primary rounded text-center p-3 mb-2" style="cursor:pointer;background:#f5faff;">
                    <div>اسحب أو اضغط لاختيار</div>
                    <div class="small text-muted mt-1">MP4 / JPG / PNG</div>
                    <input type="file" id="qFilesInput" name="media_files[]" multiple accept=".mp4,.jpg,.jpeg,.png" style="display:none;">
                  </div>
                  <div class="small text-muted">لكل ملف إعدادات مستقلة أسفل.</div>
                </div>

                <hr>
                <div class="small text-muted">الوصف / التعليقات / الهاشتاج تظهر في الريل فقط.</div>
              </div>
            </div>

            <!-- شبكة الملفات -->
            <div class="col-xl-9">
              <div class="q-file-grid" id="qFilesContainer"></div>

              <!-- تقدم -->
              <div class="q-progress-wrap" id="qProgressWrap">
                <label class="form-label small mb-1">التقدم</label>
                <div class="progress">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" id="qUploadBar" style="width:0%">0%</div>
                </div>
                <div class="small text-muted mt-1" id="qUploadStatus">في انتظار...</div>
              </div>

              <!-- أزرار -->
              <div class="q-action-bar">
                <button type="button" class="btn btn-primary" id="qBtnPublish">تنفيذ</button>
                <button type="button" class="btn btn-outline-secondary" id="qBtnReset">مسح</button>
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">إغلاق</button>
              </div>

              <!-- النتائج -->
              <div class="q-results" id="qResultsBox"></div>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ================== الأدوات العامة ================== */
function buildForm(obj){
    const fd=new FormData();
    Object.keys(obj).forEach(k=>{
        if(Array.isArray(obj[k])) obj[k].forEach(v=>fd.append(k,v));
        else fd.append(k,obj[k]);
    });
    return fd;
}
function toast(msg,type){
    const box=document.getElementById('toastBox');
    const el=document.createElement('div');
    el.className='toast-msg'+(type==='error'?' error':'');
    el.textContent=msg;
    box.appendChild(el);
    setTimeout(()=>{el.style.opacity=0;setTimeout(()=>el.remove(),300);},2500);
}

/* ================== فلترة وترتيب الصفحات ================== */
const searchInput=document.getElementById('searchInput');
const pages=[...document.querySelectorAll('.page-card')];
const filterBadges=document.querySelectorAll('.badge-filter');
let currentFilter='all', currentSort='name';

searchInput?.addEventListener('input', filterAndSort);
filterBadges.forEach(b=>b.addEventListener('click',()=>{
    filterBadges.forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    currentFilter=b.dataset.filter;
    filterAndSort();
}));
document.querySelectorAll('.sort-option').forEach(opt=>{
    opt.addEventListener('click',()=>{currentSort=opt.dataset.sort;filterAndSort();});
});

function filterAndSort(){
    const q=searchInput.value.trim().toLowerCase();
    pages.forEach(c=>{
        let show=true;
        const name=c.dataset.name;
        const fav=c.dataset.fav==='1';
        const health=c.dataset.health;
        const igState=c.dataset.ig_state;
        if(q && !name.includes(q)) show=false;
        if(currentFilter==='favorite' && !fav) show=false;
        if(currentFilter==='expiring' && health!=='expiring') show=false;
        if(currentFilter==='expired' && health!=='expired') show=false;
        if(currentFilter==='healthy' && health!=='ok') show=false;
        if(currentFilter==='ig_linked' && igState!=='linked') show=false;
        c.style.display=show?'':'none';
    });
    const vis=pages.filter(c=>c.style.display!=='none');
    vis.sort((a,b)=>{
        if(currentSort==='name') return a.dataset.name.localeCompare(b.dataset.name,'ar');
        if(currentSort==='scheduled_count') return (b.dataset.scheduled|0)-(a.dataset.scheduled|0);
        if(currentSort==='last_posted') return new Date(b.dataset.last_posted||0)-new Date(a.dataset.last_posted||0);
        if(currentSort==='health'){
            const order={expired:3,expiring:2,ok:1,error:4};
            return (order[b.dataset.health]||0)-(order[a.dataset.health]||0);
        }
        if(currentSort==='ig'){
            const av=a.dataset.ig_state==='linked'?1:0;
            const bv=b.dataset.ig_state==='linked'?1:0;
            return bv-av;
        }
        return 0;
    });
    const container=document.getElementById('gridMode');
    vis.forEach(v=>container.appendChild(v));
    calcSummary();
}
function calcSummary(){
    const vis=pages.filter(c=>c.style.display!=='none');
    let scheduled=0,expiring=0,expired=0;
    vis.forEach(c=>{
        scheduled += (+c.dataset.scheduled)||0;
        if(c.dataset.health==='expiring') expiring++;
        if(c.dataset.health==='expired') expired++;
    });
    document.getElementById('sumScheduled').textContent=scheduled;
    document.getElementById('sumExpiring').textContent=expiring;
    document.getElementById('sumExpired').textContent=expired;
}
filterAndSort();

/* ================== تفضيل ================== */
document.querySelectorAll('.pin-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const pageId=btn.dataset.page;
        fetch('<?= site_url('reels/ajax/toggle_favorite') ?>',{
            method:'POST',
            headers:{'X-Requested-With':'XMLHttpRequest'},
            body:buildForm({page_id:pageId})
        }).then(r=>r.json()).then(j=>{
            if(j.status==='ok'){
                const card=btn.closest('.page-card');
                card.dataset.fav=j.favorite?'1':'0';
                btn.classList.toggle('pinned',!!j.favorite);
                toast('تم التحديث');
                filterAndSort();
            }else toast('فشل','error');
        }).catch(()=>toast('خطأ شبكة','error'));
    });
});

/* ================== ربط IG لاحق ================== */
document.querySelectorAll('.link-ig-btn').forEach(el=>{
    el.addEventListener('click',()=>toast('سيتم تفعيل الربط المباشر لاحقاً'));
});

/* ================== تحديد الصفحات ================== */
pages.forEach(card=>{
    card.querySelector('.select-page').addEventListener('change',updateBulkBar);
});
function getSelectedCards(){
    return [...document.querySelectorAll('.select-page:checked')].map(cb=>cb.closest('.page-card'));
}
function getSelectedIds(){
    return getSelectedCards().map(c=>c.dataset.id);
}
function updateBulkBar(){
    const selected=document.querySelectorAll('.select-page:checked');
    document.getElementById('bulkSelectedCount').textContent=selected.length;
    document.getElementById('bulkBar').style.display=selected.length?'block':'none';
    let igLinked=0;
    selected.forEach(cb=>{
        const card=cb.closest('.page-card');
        if(card.dataset.ig_state==='linked' && card.dataset.ig_user) igLinked++;
    });
    document.getElementById('bulkIGLinkedInfo').textContent = igLinked?('إنستجرام: '+igLinked):'';
    document.getElementById('bulkGoIG').disabled = igLinked===0;
    document.getElementById('btnOpenIGMulti').disabled = igLinked===0;
}
document.getElementById('bulkClear').addEventListener('click',()=>{
    document.querySelectorAll('.select-page:checked').forEach(cb=>cb.checked=false);
    updateBulkBar();
});
document.getElementById('btnSelectAll').addEventListener('click',()=>{
    pages.forEach(c=>{
        if(c.style.display==='none') return;
        const cb=c.querySelector('.select-page');
        if(cb) cb.checked=true;
    });
    updateBulkBar();
    document.getElementById('btnSelectAll').style.display='none';
    document.getElementById('btnUnselectAll').style.display='inline-block';
});
document.getElementById('btnUnselectAll').addEventListener('click',()=>{
    pages.forEach(c=>{const cb=c.querySelector('.select-page'); if(cb) cb.checked=false;});
    updateBulkBar();
    document.getElementById('btnUnselectAll').style.display='none';
    document.getElementById('btnSelectAll').style.display='inline-block';
});

/* ================== Bulk Actions ================== */
function bulkAction(action){
    const ids=getSelectedIds();
    if(!ids.length) return;
    fetch('<?= site_url('reels/ajax/bulk_action') ?>',{
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body:buildForm({'ids[]':ids,action:action})
    }).then(r=>r.json()).then(j=>{
        if(j.status==='ok'){
            if(action==='unlink'){
                ids.forEach(id=>{
                    const card=pages.find(c=>c.dataset.id===id);
                    if(card) card.remove();
                });
            } else if(action==='favorite'){
                ids.forEach(id=>{
                    const card=pages.find(c=>c.dataset.id===id);
                    if(card){card.dataset.fav='1'; const p=card.querySelector('.pin-btn'); p&&p.classList.add('pinned');}
                });
            } else if(action==='unfavorite'){
                ids.forEach(id=>{
                    const card=pages.find(c=>c.dataset.id===id);
                    if(card){card.dataset.fav='0'; const p=card.querySelector('.pin-btn'); p&&p.classList.remove('pinned');}
                });
            }
            toast('تم');
            updateBulkBar(); filterAndSort();
        } else toast('فشل العملية','error');
    }).catch(()=>toast('خطأ شبكة','error'));
}
document.getElementById('bulkFav').onclick=()=>bulkAction('favorite');
document.getElementById('bulkUnfav').onclick=()=>bulkAction('unfavorite');
document.getElementById('bulkUnlink').onclick=()=>{if(confirm('تأكيد حذف الربط؟')) bulkAction('unlink');};
document.getElementById('bulkSync').onclick=()=>bulkAction('sync');

/* ================== أزرار فردية ================== */
document.querySelectorAll('.quick-actions button').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const action=btn.dataset.action;
        const pageId=btn.dataset.page;
        if(action==='upload'){
            window.location.href='<?= site_url('reels/upload') ?>?page='+encodeURIComponent(pageId);
        }else if(action==='schedule'){
            window.location.href='<?= site_url('reels/upload') ?>?page='+encodeURIComponent(pageId)+'#schedule';
        }else if(action==='view_scheduled'){
            loadScheduled(pageId);
        }else if(action==='sync'){
            fetch('<?= site_url('reels/ajax/sync_page') ?>',{
                method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:buildForm({page_id:pageId})
            }).then(r=>r.json()).then(j=>toast(j.status==='ok'?'تمت المزامنة':'فشل','error'&&(j.status!=='ok'))).catch(()=>toast('خطأ شبكة','error'));
        }else if(action==='unlink'){
            if(!confirm('تأكيد حذف الربط؟')) return;
            fetch('<?= site_url('reels/ajax/unlink_page') ?>',{
                method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:buildForm({page_id:pageId})
            }).then(r=>r.json()).then(j=>{
                if(j.status==='ok'){btn.closest('.page-card').remove();toast('تم');filterAndSort();}
                else toast('فشل','error');
            }).catch(()=>toast('خطأ شبكة','error'));
        }
    });
});

/* ================== فتح النشر السريع IG من زر الصفحة ================== */
document.querySelectorAll('.btn-ig-upload').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const account=[
          {
            ig_user_id: btn.dataset.ig,
            ig_username: btn.dataset.igUsername||btn.dataset.ig,
            ig_pic: btn.closest('.page-card').dataset.ig_pic||''
          }
        ];
        openQuickIGModal(account);
    });
});

/* Bulk open IG */
document.getElementById('bulkGoIG').addEventListener('click',()=>{
    const cards=getSelectedCards().filter(c=>c.dataset.ig_state==='linked' && c.dataset.ig_user);
    if(!cards.length){toast('حدد حسابات لديها IG','error');return;}
    const arr=cards.map(c=>({ig_user_id:c.dataset.ig_user,ig_username:c.dataset.ig_username||c.dataset.ig_user,ig_pic:c.dataset.ig_pic||''}));
    openQuickIGModal(arr);
});
document.getElementById('btnOpenIGMulti').addEventListener('click',()=>{
    const cards=getSelectedCards().filter(c=>c.dataset.ig_state==='linked' && c.dataset.ig_user);
    if(!cards.length){toast('حدد حسابات لديها IG','error');return;}
    const arr=cards.map(c=>({ig_user_id:c.dataset.ig_user,ig_username:c.dataset.ig_username||c.dataset.ig_user,ig_pic:c.dataset.ig_pic||''}));
    openQuickIGModal(arr);
});

/* ================== المجدول ================== */
const scheduledModal=new bootstrap.Modal(document.getElementById('scheduledModal'));
function loadScheduled(pageId){
    document.getElementById('scheduledBody').innerHTML='<div class="text-center text-muted py-4">جاري التحميل...</div>';
    fetch('<?= site_url('reels/ajax/scheduled_list') ?>?page_id='+encodeURIComponent(pageId),{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.json()).then(j=>{
        if(j.status==='ok'){
            if(!j.items.length){
                document.getElementById('scheduledBody').innerHTML='<div class="text-center text-muted py-4">لا يوجد محتوى مجدول.</div>';
            } else {
                let html='<table class="table table-sm"><thead><tr><th>#</th><th>الوصف</th><th>الوقت</th><th>الحالة</th></tr></thead><tbody>';
                j.items.forEach((it,i)=>{
                    html+=`<tr><td>${i+1}</td><td>${(it.description||'').substring(0,60)}</td><td>${it.scheduled_time||''}</td><td>${it.status||''}</td></tr>`;
                });
                html+='</tbody></table>';
                document.getElementById('scheduledBody').innerHTML=html;
            }
        } else document.getElementById('scheduledBody').innerHTML='<div class="text-danger">خطأ في تحميل البيانات</div>';
      }).catch(()=>document.getElementById('scheduledBody').innerHTML='<div class="text-danger">تعذر الاتصال</div>');
    scheduledModal.show();
}

/* ================== ربط صفحات جديدة ================== */
document.getElementById('btnConnect').addEventListener('click',()=>{
    location.href='<?= site_url('auth/login') ?>';
});

/* ===================================================================
   IG QUICK MULTI-FILE MODAL (ADVANCED) 
   =================================================================== */
const igQuickModalEl=document.getElementById('igQuickModal');
const igQuickModal=new bootstrap.Modal(igQuickModalEl);
const quickAccountsBox=document.getElementById('quickAccountsBox');
const quickPrimaryIG=document.getElementById('quickPrimaryIG');
const qKindReel=document.getElementById('qKindReel');
const qKindStory=document.getElementById('qKindStory');
const qFileDrop=document.getElementById('qFileDrop');
const qFilesInput=document.getElementById('qFilesInput');
const qFilesContainer=document.getElementById('qFilesContainer');
const qProgressWrap=document.getElementById('qProgressWrap');
const qUploadBar=document.getElementById('qUploadBar');
const qUploadStatus=document.getElementById('qUploadStatus');
const qBtnPublish=document.getElementById('qBtnPublish');
const qBtnReset=document.getElementById('qBtnReset');
const qResultsBox=document.getElementById('qResultsBox');

let quickFiles=[];

function openQuickIGModal(accounts){
    if(!accounts || !accounts.length){toast('لا حسابات IG','error');return;}
    quickAccountsBox.innerHTML='';
    quickPrimaryIG.value=accounts[0].ig_user_id;
    accounts.forEach((a,i)=>{
        quickAccountsBox.innerHTML+=`
          <div class="d-flex align-items-center gap-2 mb-1">
            ${a.ig_pic?'<img src="'+a.ig_pic+'" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">'
                      :'<div style="width:26px;height:26px;background:#d7e6f2;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;">IG</div>'}
            <span>@${a.ig_username||a.ig_user_id}</span>
            ${i===0?'<span class="badge bg-primary">أساسي</span>':''}
            <input type="hidden" name="ig_user_ids[]" value="${a.ig_user_id}">
          </div>`;
    });
    resetQuickModal(true);
    setTZHidden(); // ضبط المنطقة الزمنية المخفية
    igQuickModal.show();
}

[qKindReel,qKindStory].forEach(r=>r.addEventListener('change',applyKindToCards));
function applyKindToCards(){
    const story=qKindStory.checked;
    document.querySelectorAll('.q-file-card').forEach(card=>{
        card.querySelectorAll('.q-tab-btn').forEach(b=>{
            if(['caption','comments','hashtags'].includes(b.dataset.tab)){
                b.style.display=story?'none':'inline-block';
                if(story && b.classList.contains('active')){
                    activateTab(card.querySelector('.q-tab-btn[data-tab="publish"]'));
                }
            }
        });
        card.querySelectorAll('.q-pane').forEach(p=>{
            if(['caption','comments','hashtags'].includes(p.dataset.pane)){
                if(story){p.classList.remove('active');p.style.display='none';}
                else p.style.display='';
            }
        });
    });
}

qFileDrop.addEventListener('click',()=>qFilesInput.click());
qFileDrop.addEventListener('dragover',e=>{e.preventDefault();qFileDrop.classList.add('border-warning');});
qFileDrop.addEventListener('dragleave',()=>qFileDrop.classList.remove('border-warning'));
qFileDrop.addEventListener('drop',e=>{
    e.preventDefault();
    qFileDrop.classList.remove('border-warning');
    appendQuickFiles([...e.dataTransfer.files]);
});
qFilesInput.addEventListener('change',()=>appendQuickFiles([...qFilesInput.files],true));

function appendQuickFiles(files, reset){
    files.forEach(f=>quickFiles.push(f));
    const dt=new DataTransfer();
    quickFiles.forEach(f=>dt.items.add(f));
    qFilesInput.files=dt.files;
    if(reset) qFilesInput.value='';
    renderQuickFiles();
}
function removeQuickFile(index){
    quickFiles=quickFiles.filter((_,i)=>i!==index);
    const dt=new DataTransfer();
    quickFiles.forEach(f=>dt.items.add(f));
    qFilesInput.files=dt.files;
    renderQuickFiles();
}
function renderQuickFiles(){
    qFilesContainer.innerHTML='';
    quickFiles.forEach((f,idx)=>{
        const ext=f.name.split('.').pop().toLowerCase();
        const isImg=['jpg','jpeg','png'].includes(ext);
        const isVideo=ext==='mp4';
        const card=document.createElement('div');
        card.className='q-file-card';
        card.dataset.index=idx;
        card.innerHTML=`
          <div class="q-file-head">
            <div class="q-file-name" title="${f.name}">${f.name}</div>
            <div class="q-file-remove" data-remove="${idx}" title="إزالة">×</div>
          </div>
          <div class="q-media-preview">
            ${isImg?'<img src="'+URL.createObjectURL(f)+'">':(isVideo?'<video controls><source src="'+URL.createObjectURL(f)+'" type="video/mp4"></video>':'<div class="text-muted small">لا معاينة</div>')}
          </div>
          <div class="q-tabs">
            <div class="q-tab-btn active" data-tab="publish">النشر</div>
            <div class="q-tab-btn" data-tab="caption">الوصف</div>
            <div class="q-tab-btn" data-tab="comments">التعليقات</div>
            <div class="q-tab-btn" data-tab="hashtags">هاشتاج</div>
          </div>
          <div class="q-pane active" data-pane="publish">${buildPublishPane(idx)}</div>
          <div class="q-pane" data-pane="caption">${buildCaptionPane(idx)}</div>
          <div class="q-pane" data-pane="comments">${buildCommentsPane(idx)}</div>
          <div class="q-pane" data-pane="hashtags">${buildHashtagsPane(idx)}</div>
        `;
        qFilesContainer.appendChild(card);
    });

    // Events per card
    document.querySelectorAll('.q-file-remove').forEach(r=>r.addEventListener('click',()=>removeQuickFile(+r.dataset.remove)));
    document.querySelectorAll('.q-file-card').forEach(card=>{
        card.querySelectorAll('.q-tab-btn').forEach(btn=>{
            btn.addEventListener('click',()=>activateTab(btn));
        });
        initScheduleBox(card,card.dataset.index);
        initCommentsBox(card,card.dataset.index);
        initHashtagsBox(card,card.dataset.index);
    });
    applyKindToCards();
}
function activateTab(btn){
    const card=btn.closest('.q-file-card');
    card.querySelectorAll('.q-tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const target=btn.dataset.tab;
    card.querySelectorAll('.q-pane').forEach(p=>{
        p.classList.toggle('active',p.dataset.pane===target);
    });
}

/* ==== Pane Builders ==== */
function buildPublishPane(i){
    return `
      <div class="mb-2 small fw-bold">وضع النشر</div>
      <div class="d-flex gap-3 flex-wrap mb-2">
        <label class="form-check"><input type="radio" class="form-check-input" name="media_cfg[${i}][publish_mode]" value="immediate" checked> <span class="small">الآن</span></label>
        <label class="form-check"><input type="radio" class="form-check-input q-pub-sched" name="media_cfg[${i}][publish_mode]" value="scheduled"> <span class="small">جدولة</span></label>
      </div>
      <div class="q-publish-box" id="qScheduleBox-${i}" style="display:none;">
        <div class="d-flex align-items-end gap-2 flex-wrap">
          <div>
            <label class="form-label small mb-1">عدد الجداول</label>
            <select class="form-select form-select-sm q-schedule-count" name="media_cfg[${i}][schedule_count]" data-file="${i}" style="width:auto;">
              ${Array.from({length:10},(_,k)=>`<option value="${k+1}">${k+1}</option>`).join('')}
            </select>
          </div>
          <div class="inline-note">أوقات متعددة لهذا الملف</div>
        </div>
        <div class="q-schedule-rows mt-2" id="qScheduleRows-${i}"></div>
      </div>
    `;
}
function buildCaptionPane(i){
    return `
      <label class="form-label small mb-1">الوصف</label>
      <textarea class="q-caption" name="media_cfg[${i}][caption]" maxlength="2200" placeholder="وصف الريل..."></textarea>
      <div class="q-caption-counter"><span class="cap-len">0</span> / 2200</div>
    `;
}
function buildCommentsPane(i){
    return `
      <div class="d-flex align-items-center gap-2 mb-2">
        <label class="form-label small mb-1">عدد</label>
        <select class="form-select form-select-sm q-comments-count" data-file="${i}" style="width:auto;">
          ${Array.from({length:21},(_,k)=>`<option value="${k}">${k}</option>`).join('')}
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary q-comments-clear" data-file="${i}">مسح</button>
      </div>
      <div class="q-comments-wrap" id="qCommentsWrap-${i}" style="display:none;"></div>
      <div class="inline-note mt-1">تنشر التعليقات بعد نجاح الريل.</div>
    `;
}
function buildHashtagsPane(i){
    return `
      <div class="q-hash-tools d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-primary q-hash-fetch" data-file="${i}">تريند</button>
        <button type="button" class="btn btn-sm btn-outline-success q-hash-add" data-file="${i}">دمج للوصف</button>
        <button type="button" class="btn btn-sm btn-outline-secondary q-hash-clear" data-file="${i}">مسح</button>
        <span class="small text-muted" id="qHashStatus-${i}"></span>
      </div>
      <textarea class="q-hash-field" data-file="${i}" id="qHashField-${i}" placeholder="#tags ..." rows="2"></textarea>
      <div class="q-hashtag-tags mt-2" id="qTagsList-${i}" style="display:none;"></div>
    `;
}

/* ==== Schedule Logic ==== */
function initScheduleBox(card,i){
    const radioSched=card.querySelector('.q-pub-sched');
    const box=card.querySelector('#qScheduleBox-'+i);
    const countSel=card.querySelector('.q-schedule-count');
    const rows=card.querySelector('#qScheduleRows-'+i);
    card.querySelectorAll(`input[name="media_cfg[${i}][publish_mode]"]`).forEach(r=>{
        r.addEventListener('change',()=>{
            box.style.display=radioSched.checked?'':'none';
            if(radioSched.checked) rebuild();
        });
    });
    countSel.addEventListener('change',rebuild);
    function rebuild(){
        rows.innerHTML='';
        const c=parseInt(countSel.value||'1',10);
        for(let s=1;s<=c;s++){
            const div=document.createElement('div');
            div.className='q-srow';
            div.innerHTML=`
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label small mb-1">وقت #${s}</label>
                  <input type="datetime-local" name="media_cfg[${i}][schedules][${s}][time]" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">تكرار</label>
                  <select class="form-select form-select-sm q-rec-kind" name="media_cfg[${i}][schedules][${s}][recurrence_kind]">
                    <option value="none">بدون</option>
                    <option value="daily">يومي</option>
                    <option value="weekly">أسبوعي</option>
                    <option value="monthly">شهري</option>
                    <option value="quarterly">كل 3 شهور</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">حتى تاريخ (اختياري)</label>
                  <input type="datetime-local" name="media_cfg[${i}][schedules][${s}][recurrence_until]" class="form-control form-control-sm q-rec-until" style="display:none;">
                </div>
              </div>
            `;
            rows.appendChild(div);
        }
        rows.querySelectorAll('.q-rec-kind').forEach(sel=>{
            sel.addEventListener('change',()=>{
                const until=sel.closest('.q-srow').querySelector('.q-rec-until');
                until.style.display = sel.value!=='none' ? '' : 'none';
            });
        });
    }
}

/* ==== Comments Logic ==== */
function initCommentsBox(card,i){
    const countSel=card.querySelector('.q-comments-count');
    const wrap=card.querySelector('#qCommentsWrap-'+i);
    const clear=card.querySelector('.q-comments-clear');
    countSel.addEventListener('change',rebuild);
    clear.addEventListener('click',()=>{countSel.value=0;rebuild();});
    function rebuild(){
        const n=parseInt(countSel.value||'0',10);
        wrap.innerHTML='';
        if(n>0){
            wrap.style.display='';
            for(let k=1;k<=n;k++){
                const d=document.createElement('div');
                d.className='q-comment-item mb-2';
                d.innerHTML=`
                  <label class="form-label small mb-1">تعليق ${k}</label>
                  <textarea name="media_cfg[${i}][comments][]" maxlength="2200" class="form-control form-control-sm" placeholder="تعليق ${k}"></textarea>
                `;
                wrap.appendChild(d);
            }
        } else wrap.style.display='none';
    }
}

/* ==== Hashtags Logic ==== */
function initHashtagsBox(card,i){
    const fetchBtn=card.querySelector('.q-hash-fetch');
    const addBtn=card.querySelector('.q-hash-add');
    const clearBtn=card.querySelector('.q-hash-clear');
    const status=card.querySelector('#qHashStatus-'+i);
    const field=card.querySelector('#qHashField-'+i);
    const list=card.querySelector('#qTagsList-'+i);
    const captionField=card.querySelector(`textarea[name="media_cfg[${i}][caption]"]`);
    if(captionField){
        const counter=card.querySelector('.cap-len');
        captionField.addEventListener('input',()=>counter.textContent=captionField.value.length);
    }

    fetchBtn.addEventListener('click',()=>{
        status.textContent='...';
        fetch('<?= site_url('instagram/hashtags_trend') ?>',{headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(r=>r.json()).then(j=>{
            if(j.status==='ok'){
                field.value='#'+j.tags.slice(0,10).join(' #');
                list.innerHTML='';
                j.tags.forEach(t=>{
                    const sp=document.createElement('span');
                    sp.textContent='#'+t;
                    sp.onclick=()=>{
                        if(captionField && !captionField.value.includes('#'+t)){
                            if(!captionField.value.endsWith(' ') && captionField.value!=='') captionField.value+=' ';
                            captionField.value+='#'+t;
                            captionField.dispatchEvent(new Event('input'));
                        }
                    };
                    list.appendChild(sp);
                });
                list.style.display='block';
                status.textContent='تم';
            } else status.textContent='فشل';
          }).catch(()=>status.textContent='خطأ');
    });
    addBtn.addEventListener('click',()=>{
        if(!field.value.trim()) return;
        if(captionField){
            if(!captionField.value.endsWith(' ') && captionField.value!=='') captionField.value+=' ';
            captionField.value+=field.value.trim();
            captionField.dispatchEvent(new Event('input'));
        }
    });
    clearBtn.addEventListener('click',()=>{
        field.value='';list.innerHTML='';list.style.display='none';status.textContent='مسح';
    });
}

/* ==== Reset Modal ==== */
function resetQuickModal(full){
    quickFiles=[];
    qFilesInput.value='';
    qFilesContainer.innerHTML='';
    qProgressWrap.style.display='none';
    qUploadBar.style.width='0%'; qUploadBar.textContent='0%';
    qUploadStatus.textContent='في انتظار...';
    qResultsBox.style.display='none'; qResultsBox.innerHTML='';
    if(full) document.getElementById('igQuickForm').reset();
}

/* ==== TZ helpers (لتوافق منطق فيسبوك) ==== */
function setTZHidden(){
    try{
        const off = new Date().getTimezoneOffset();
        const tz  = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        document.getElementById('tz_offset_minutes').value = off;
        document.getElementById('_tz_offset').value = off;
        document.getElementById('_tz_name').value = tz;
    }catch(e){}
}

/* ==== Publish ==== */
qBtnPublish.addEventListener('click',()=>{
    if(!quickPrimaryIG.value){toast('لا حساب أساسي','error');return;}
    if(!quickFiles.length){toast('اختر ملفات','error');return;}

    qResultsBox.style.display='none'; qResultsBox.innerHTML='';
    qProgressWrap.style.display='block';
    qUploadBar.style.width='0%'; qUploadBar.textContent='0%';
    qUploadStatus.textContent='بدء...';
    qBtnPublish.disabled=true;

    // اضبط قيم المنطقة الزمنية قبل إنشاء الـ FormData
    setTZHidden();

    const formEl=document.getElementById('igQuickForm');
    const fd=new FormData(formEl);

    // Facebook-compat scheduler params:
    // نرسِلها فقط عند اختيار Reels حتى لا تؤثر على Stories
    if(qKindReel.checked){
        // schedule_times_fb[] + descriptions_fb[] + comments_fb[INDEX][]
        const cards=[...document.querySelectorAll('.q-file-card')];
        // اجعل المصفوفات بنفس ترتيب الملفات
        const scheduleTimes=[];
        const descs=[];
        const commentsByIndex={};
        cards.forEach(card=>{
            const i=parseInt(card.dataset.index,10);
            // وصف
            const cap=card.querySelector(`textarea[name="media_cfg[${i}][caption]"]`);
            descs[i]=cap?cap.value.trim():'';

            // وضع النشر
            const mode=card.querySelector(`input[name="media_cfg[${i}][publish_mode]"]:checked`)?.value || 'immediate';
            let localTime='';
            if(mode==='scheduled'){
                // أول وقت فقط (توافق فيسبوك)
                const timeInput=card.querySelector(`input[name="media_cfg[${i}][schedules][1][time]"]`) || card.querySelector(`input[name^="media_cfg[${i}][schedules]"][name$="[time]"]`);
                if(timeInput && timeInput.value){
                    localTime=timeInput.value.slice(0,16); // YYYY-MM-DDTHH:MM
                }
            }
            scheduleTimes[i]=localTime;

            // التعليقات
            const cts=card.querySelectorAll(`textarea[name="media_cfg[${i}][comments][]"]`);
            if(cts && cts.length){
                const arr=[...cts].map(t=>t.value.trim()).filter(Boolean).slice(0,20);
                if(arr.length) commentsByIndex[i]=arr;
            }
        });

        // Append keeping indices aligned مع ترتيب الملفات
        scheduleTimes.forEach(v=>fd.append('schedule_times_fb[]', v || ''));
        descs.forEach(v=>fd.append('descriptions_fb[]', v || ''));
        Object.keys(commentsByIndex).forEach(i=>{
            commentsByIndex[i].forEach(c=>{
                fd.append(`comments_fb[${i}][]`, c);
            });
        });
    }

    const xhr=new XMLHttpRequest();
    xhr.open('POST','<?= site_url('instagram/publish') ?>',true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.upload.addEventListener('progress',e=>{
        if(e.lengthComputable){
            const pc=Math.round((e.loaded/e.total)*100);
            qUploadBar.style.width=pc+'%';
            qUploadBar.textContent=pc+'%';
            qUploadStatus.textContent='رفع: '+pc+'%';
        }
    });
    xhr.onreadystatechange=()=>{
        if(xhr.readyState===4){
            qBtnPublish.disabled=false;
            if(xhr.status===200){
                try{
                    const j=JSON.parse(xhr.responseText);
                    qUploadStatus.textContent = j.status==='ok' ? 'تم' : 'فشل';
                    qResultsBox.style.display='block';
                    qResultsBox.innerHTML='<div class="fw-bold mb-2">النتائج:</div>';
                    (j.results||[]).forEach(r=>{
                        if(r.status==='ok'){
                            qResultsBox.innerHTML+='<div class="ok">✔ '+r.file+' '+(r.ig_user_id?('('+r.ig_user_id+')'):'')+'</div>';
                        } else if(r.status==='scheduled'){
                            qResultsBox.innerHTML+='<div class="scheduled">⏱ '+r.file+' [مجدول]</div>';
                        } else {
                            qResultsBox.innerHTML+='<div class="err">✖ '+r.file+' '+(r.ig_user_id?('('+r.ig_user_id+') '):'')+'('+ (r.error||'خطأ') +')</div>';
                        }
                    });
                    if(j.redirect_url){
                        setTimeout(()=>window.location.href=j.redirect_url,1400);
                    }
                }catch(ex){
                    qUploadStatus.textContent='استجابة غير صالحة';
                    console.error(ex,xhr.responseText);
                }
            } else {
                qUploadStatus.textContent='HTTP '+xhr.status;
                toast('خطأ شبكة','error');
            }
        }
    };
    xhr.send(fd);
});

/* ==== Reset Button ==== */
qBtnReset.addEventListener('click',()=>resetQuickModal(true));

/* ================== نهاية منطق المودال ================== */
</script>
</body>
</html>