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
.comment-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:4px 8px;border-radius:18px;background:#eef5ff;color:#155281;cursor:pointer;}
.comment-badge.ok{background:#dff7e8;color:#0b6a38;}
.comment-badge.partial{background:#fff3cd;color:#8a6000;}
.comment-badge.fail{background:#ffe1e1;color:#a10000;}
.comment-badge.empty{background:#f0f3f6;color:#5d6d79;cursor:default;}
.tooltip-inner{direction:rtl;text-align:right;}
.details-modal .modal-dialog{max-width:650px;}
.code-block{background:#0f172a;color:#e2e8f0;font-family:Consolas,monospace;font-size:12px;padding:8px 10px;border-radius:8px;white-space:pre-wrap;max-height:240px;overflow:auto;}
.republish-btn{font-size:11px;}
/* عرض الوقت المحلي */
.dt[data-utc]{cursor:pointer;display:inline-block;direction:ltr;font-family:Consolas,monospace;font-size:11px;background:#f6f9fc;padding:2px 5px;border-radius:4px;min-width:135px;text-align:left;}
.time-mode-hint{font-size:11px;}
.original-local{font-size:10px;}
</style>
</head>
<body>
<div class="container py-4" style="max-width:1500px;">
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
                    <?php foreach(['published'=>'Published','pending'=>'Pending','failed'=>'Failed','uploading'=>'Uploading','scheduled'=>'Scheduled'] as $k=>$v): ?>
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
                <input type="text" name="q" value="<?= htmlspecialchars($filter['q'] ?? '') ?>" class="form-control search-input" placeholder="وصف، Media ID...">
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button class="btn btn-primary w-100">فلترة</button>
                <a href="<?= site_url('instagram/listing') ?>" class="btn btn-outline-secondary w-100">إعادة</a>
            </div>
        </form>
    </div>

    <!-- شريط تبديل عرض التوقيت -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <div class="d-flex gap-2">
            <button id="btnToggleTime" type="button" class="btn btn-sm btn-outline-primary">عرض: محلي</button>
            <button id="btnCopyTimes" type="button" class="btn btn-sm btn-outline-secondary" title="نسخ الأوقات الظاهرة حالياً (أول 50)">نسخ الأوقات</button>
            <span class="time-mode-hint text-muted" id="timeModeHint">الحالي: UTC</span>
        </div>
        <small class="text-muted">جميع الأوقات مخزنة UTC. التحويل يتم داخل المتصفح فقط.</small>
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
                        <th>تعليقات</th>
                        <th>IG User</th>
                        <th>Media ID</th>
                        <th>Creation ID</th>
                        <th>Scheduled</th>
                        <th>Created</th>
                        <th>Published</th>
                        <th>خطأ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($items)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-4">لا نتائج.</td></tr>
                <?php else: foreach($items as $row):
                    $isNew = ($just_published_id && $just_published_id == $row['id']);
                    $statusClass='status-'.$row['status'];
                    $kindLabel = $row['media_kind']=='ig_reel' ? 'Reel' :
                                 ($row['media_kind']=='ig_story_image' ? 'Story Image' :
                                 ($row['media_kind']=='ig_story_video' ? 'Story Video' : $row['media_kind']));

                    $cCount = (int)($row['comments_count'] ?? 0);
                    $cResultRaw = $row['comments_publish_result_json'] ?? null;
                    $cBadgeClass = 'empty';
                    $cBadgeText  = '—';
                    $cDetailsEnc = '';
                    if($cCount > 0){
                        $ok = 0; $total = 0;
                        if($cResultRaw){
                            $decoded = @json_decode($cResultRaw,true);
                            if(is_array($decoded)){
                                $total = count($decoded);
                                foreach($decoded as $_c){ if(($_c['status']??'')==='ok') $ok++; }
                            }
                        } else {
                            $total = $cCount;
                        }
                        if($total>0){
                            if($ok===$total) { $cBadgeClass='ok'; }
                            elseif($ok===0){ $cBadgeClass='fail'; }
                            else { $cBadgeClass='partial'; }
                            $cBadgeText = $ok.'/'.$total;
                        } else {
                            $cBadgeClass='partial';
                            $cBadgeText='0/'.$cCount;
                        }
                        $cDetailsEnc = htmlspecialchars($cResultRaw ?? '[]', ENT_QUOTES,'UTF-8');
                    }
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
                        <td style="max-width:240px;">
                            <?php if($row['description']): ?>
                                <div class="small text-break"><?= nl2br(htmlspecialchars(mb_strimwidth($row['description'],0,320,'…'))) ?></div>
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                        </td>
                        <td><span class="status-pill <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>

                        <td>
                            <?php if($cCount>0): ?>
                                <span class="comment-badge <?= $cBadgeClass ?> btn-show-comments"
                                      data-comments='<?= $cDetailsEnc ?>'
                                      data-original='<?= htmlspecialchars($row['comments_json'] ?? "[]", ENT_QUOTES,'UTF-8') ?>'
                                      data-rowid="<?= $row['id'] ?>"
                                      title="تفاصيل التعليقات">
                                    💬 <?= $cBadgeText ?>
                                </span>
                            <?php else: ?>
                                <span class="comment-badge empty">—</span>
                            <?php endif; ?>
                        </td>

                        <td><span class="badge-acc"><?= htmlspecialchars($row['ig_user_id']) ?></span></td>
                        <td class="small text-break" style="max-width:140px;"><?= $row['media_id']?htmlspecialchars($row['media_id']):'<span class="text-muted">—</span>' ?></td>
                        <td class="small text-break" style="max-width:140px;"><?= $row['creation_id']?htmlspecialchars($row['creation_id']):'<span class="text-muted">—</span>' ?></td>

                        <!-- Scheduled Time -->
                        <td class="small">
                            <?php if(!empty($row['scheduled_time'])): ?>
                                <span class="dt" data-utc="<?= htmlspecialchars($row['scheduled_time']) ?>"><?= htmlspecialchars($row['scheduled_time']) ?></span>
                                <?php if(!empty($row['original_local_time'])): ?>
                                    <div class="original-local text-muted lh-1" title="الوقت المحلي المدخل">
                                        محلي: <?= htmlspecialchars($row['original_local_time']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Created -->
                        <td class="small">
                            <?php if(!empty($row['created_at'])): ?>
                                <span class="dt" data-utc="<?= htmlspecialchars($row['created_at']) ?>"><?= htmlspecialchars($row['created_at']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Published -->
                        <td class="small">
                            <?php if(!empty($row['published_at'])): ?>
                                <span class="dt" data-utc="<?= htmlspecialchars($row['published_at']) ?>"><?= htmlspecialchars($row['published_at']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="small text-danger" style="max-width:200px;">
                            <?= $row['last_error'] ? htmlspecialchars(mb_strimwidth($row['last_error'],0,200,'…')) : '<span class="text-muted">—</span>' ?>
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
                $qs = $_GET; $qs['page']=$i; $url = site_url('instagram/listing').'?'.http_build_query($qs);
            ?>
                <a class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline-primary' ?>" href="<?= $url ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <div class="mt-4">
        <a href="<?= site_url('instagram/upload') ?>" class="btn btn-outline-primary">+ نشر جديد</a>
    </div>
</div>

<!-- Modal تفاصيل التعليقات -->
<div class="modal fade details-modal" id="commentsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تفاصيل التعليقات <span id="cmRecordId" class="text-muted" style="font-size:12px;"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <h6 class="fw-bold mb-2">القائمة الأصلية:</h6>
            <div id="origComments" class="code-block small">[]</div>
        </div>
        <div>
            <h6 class="fw-bold mb-2">نتيجة النشر:</h6>
            <div id="resultComments" class="code-block small">[]</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnRetryFailed" class="btn btn-sm btn-outline-warning republish-btn" disabled>إعادة نشر الفاشل (لاحقاً)</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ====== تعليقـات ====== */
(function(){
    const modalEl = document.getElementById('commentsModal');
    const origBox = document.getElementById('origComments');
    const resBox  = document.getElementById('resultComments');
    const recordIdLbl = document.getElementById('cmRecordId');
    const retryBtn = document.getElementById('btnRetryFailed');

    document.querySelectorAll('.btn-show-comments').forEach(btn=>{
        btn.addEventListener('click',()=>{
            const raw = btn.getAttribute('data-comments') || '[]';
            const original = btn.getAttribute('data-original') || '[]';
            const rowId = btn.getAttribute('data-rowid');
            recordIdLbl.textContent = '#'+rowId;

            try{
                const origParsed = JSON.parse(original);
                origBox.textContent = JSON.stringify(origParsed, null, 2);
            }catch(e){
                origBox.textContent = original;
            }

            let failedCount = 0;
            try{
                const parsed = JSON.parse(raw);
                parsed.forEach(o=>{ if(o.status!=='ok') failedCount++; });
                resBox.textContent = JSON.stringify(parsed, null, 2);
            }catch(e){
                resBox.textContent = raw;
            }

            if(failedCount>0){
                retryBtn.disabled=false;
                retryBtn.textContent='إعادة نشر '+failedCount+' تعليق فاشل (قريباً)';
            }else{
                retryBtn.disabled=true;
                retryBtn.textContent='لا تعليقات فاشلة';
            }

            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        });
    });
})();

/* ====== تحويل الأوقات (UTC <-> محلي) ====== */
(function(){
  function toLocalString(utcStr){
    if(!utcStr || utcStr==='0000-00-00 00:00:00') return '—';
    const d = new Date(utcStr.replace(' ','T')+'Z');
    if(isNaN(d.getTime())) return utcStr;
    return d.toLocaleString([], {
      year:'numeric', month:'2-digit', day:'2-digit',
      hour:'2-digit', minute:'2-digit', second:'2-digit',
      hour12:false
    });
  }

  const els = document.querySelectorAll('.dt[data-utc]');
  let mode = 'utc';
  const btn = document.getElementById('btnToggleTime');
  const hint = document.getElementById('timeModeHint');
  const btnCopy = document.getElementById('btnCopyTimes');

  els.forEach(el=>{
    el.dataset.utc = el.getAttribute('data-utc');
    el.dataset.originalText = el.innerText.trim();
  });

  function render(){
    if(mode==='utc'){
      els.forEach(el=>{ el.textContent = el.dataset.utc || '—'; });
      if(btn){ btn.textContent='عرض: محلي'; }
      if(hint){ hint.textContent='الحالي: UTC'; }
    } else {
      els.forEach(el=>{
        el.textContent = toLocalString(el.dataset.utc);
      });
      if(btn){ btn.textContent='عرض: UTC'; }
      if(hint){ hint.textContent='الحالي: محلي (متصفحك)'; }
    }
  }

  btn?.addEventListener('click',()=>{
    mode = (mode==='utc') ? 'local' : 'utc';
    render();
    try{ localStorage.setItem('ig_time_mode',mode); }catch(e){}
  });

  btnCopy?.addEventListener('click',()=>{
    let lines = [];
    els.forEach(el=>{
      lines.push( (mode==='utc'? el.dataset.utc : toLocalString(el.dataset.utc)) );
    });
    const txt = lines.join('\n');
    navigator.clipboard.writeText(txt).then(()=>{
      btnCopy.textContent='تم النسخ ✅';
      setTimeout(()=>{ btnCopy.textContent='نسخ الأوقات'; },2000);
    }).catch(()=>{
      btnCopy.textContent='فشل النسخ';
      setTimeout(()=>{ btnCopy.textContent='نسخ الأوقات'; },2000);
    });
  });

  try{
    const saved = localStorage.getItem('ig_time_mode');
    if(saved==='local') mode='local';
  }catch(e){}
  render();
})();
</script>
</body>
</html>