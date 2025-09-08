<?php
/**
 * صفحة رفع / نشر إنستجرام متعددة الملفات (نسخة محدثة لدمج منطق التوقيت المحلي → UTC)
 *
 * الجديد / التعديلات:
 * - إصلاح اختيار الحساب: استبدال label بحاوية div لمنع "القلب المزدوج".
 * - إضافة مربع اختيار ظاهر لكل حساب + ضغط على الكارت يغيّر حالته.
 * - مزامنة ig_user_id (الحساب الأساسي) تلقائياً بأول حساب محدد.
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نشر محتوى إنستجرام</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
:root {
  --c-bg:#f8fafc; --c-panel:#ffffff; --c-panel-border:#d9e3ed; --c-text:#1d2b39;
  --c-text-soft:#5c6f81; --c-accent:#0d6efd; --c-accent-hover:#0b5ed7;
  --c-danger:#dc3545; --c-success:#198754; --c-yellow:#ffc107;
  --c-radius:16px; --c-muted-bg:#f1f5f9; --c-input-bg:#fff; --c-shadow:0 4px 16px -6px rgba(0,0,0,.12);
  font-size:15px;
}
.theme-dark {
  --c-bg:#0f1822; --c-panel:#182430; --c-panel-border:#253747; --c-text:#f1f5f9;
  --c-text-soft:#9cb1c4; --c-accent:#0d6efd; --c-accent-hover:#0b5ed7;
  --c-danger:#ff5d66; --c-success:#3bc77b; --c-yellow:#ffc107;
  --c-muted-bg:#1f2f3d; --c-input-bg:#223240; --c-shadow:0 6px 20px -6px rgba(0,0,0,.55);
}
html,body{background:var(--c-bg);color:var(--c-text);font-family:"Tahoma","Segoe UI",Arial;}
body{min-height:100vh;}
a{color:var(--c-accent);} a:hover{color:var(--c-accent-hover);}
h1{font-size:26px;font-weight:700;margin:0;}
.app-shell{max-width:1700px;margin:0 auto;padding:28px 28px 80px;}
.top-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;margin-bottom:28px;}
.top-actions .btn{font-weight:600;}
.layout{display:grid;grid-template-columns:330px 1fr;gap:28px;}
@media (max-width:1200px){.layout{grid-template-columns:1fr;}}
.panel{background:var(--c-panel);border:1px solid var(--c-panel-border);border-radius:var(--c-radius);padding:20px 20px 18px;box-shadow:var(--c-shadow);position:relative;}
.panel-header{font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;}
.small-note{font-size:11.5px;color:var(--c-text-soft);}
.search-box{position:relative;} .search-box input{padding-inline-start:34px;}
.search-box .ico{position:absolute;top:50%;transform:translateY(-50%);right:10px;font-size:14px;color:var(--c-text-soft);}
.accounts-list{max-height:470px;overflow:auto;padding-right:4px;}
.accounts-list::-webkit-scrollbar{width:8px;} .accounts-list::-webkit-scrollbar-thumb{background:#c4d2df;border-radius:4px;}
.theme-dark .accounts-list::-webkit-scrollbar-thumb{background:#314556;}
/* حسابات */
.account-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--c-panel-border);background:var(--c-muted-bg);border-radius:14px;margin-bottom:10px;cursor:pointer;transition:.22s; user-select:none;}
.account-item:hover{border-color:var(--c-accent);}
.account-item.selected{border-color:var(--c-accent);background:rgba(13,110,253,.08);box-shadow:0 0 0 2px rgba(13,110,253,.18);}
.theme-dark .account-item.selected{background:#22374b;}
.acc-check{transform:scale(1.15); cursor:pointer; margin-inline-start:4px;}
.acc-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--c-panel-border);background:#eee;flex:0 0 auto;}
.theme-dark .acc-avatar{background:#111d27;}
.acc-info{display:flex;flex-direction:column;gap:2px;}
.acc-info .uname{font-weight:700;font-size:13px;direction:ltr;text-align:left;}
.muted{color:var(--c-text-soft)!important;font-size:11.3px;}
/* الملفات */
.file-drop{border:2px dashed #b9c9d6;border-radius:18px;background:var(--c-muted-bg);padding:26px;text-align:center;cursor:pointer;transition:.25s;}
.file-drop:hover{border-color:var(--c-accent);background:#e9f2fb;}
.theme-dark .file-drop{border-color:#395265;background:#1e2f3b;}
.theme-dark .file-drop:hover{background:#274050;border-color:var(--c-accent);}
.file-drop.dragover{background:#dceefd;border-color:var(--c-accent);}
.theme-dark .file-drop.dragover{background:#2d4a5d;}
.file-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:22px;margin-top:10px;}
@media (max-width:820px){.file-grid{grid-template-columns:repeat(auto-fill,minmax(270px,1fr));}}
.file-card{background:var(--c-panel);border:1px solid var(--c-panel-border);border-radius:22px;padding:16px 18px 20px;display:flex;flex-direction:column;gap:14px;position:relative;box-shadow:var(--c-shadow);transition:.25s;}
.file-card:hover{border-color:var(--c-accent);}
.file-head{display:flex;align-items:center;justify-content:space-between;gap:14px;}
.file-name{font-size:13px;font-weight:700;word-break:break-all;flex:1;}
.file-remove{color:var(--c-danger);font-weight:700;cursor:pointer;font-size:22px;line-height:1;}
.media-preview img,.media-preview video{max-width:100%;border-radius:16px;border:1px solid var(--c-panel-border);max-height:230px;object-fit:cover;background:#fff;}
.theme-dark .media-preview img,.theme-dark .media-preview video{background:#0d1821;}
.tabs{display:flex;flex-wrap:wrap;gap:8px;}
.tab-btn{background:var(--c-muted-bg);border:1px solid var(--c-panel-border);color:var(--c-text-soft);font-size:11.5px;font-weight:600;padding:6px 14px;border-radius:30px;cursor:pointer;transition:.2s;letter-spacing:.3px;}
.tab-btn:hover{color:var(--c-text);border-color:var(--c-accent);}
.tab-btn.active{background:var(--c-accent);border-color:var(--c-accent);color:#fff;box-shadow:0 4px 14px -4px rgba(13,110,253,.45);}
.tab-pane{display:none;animation:fade .25s;} .tab-pane.active{display:block;}
@keyframes fade{from{opacity:0;transform:translateY(5px);}to{opacity:1;transform:translateY(0);} }
.caption-field{background:var(--c-input-bg);border:1px solid var(--c-panel-border);color:var(--c-text);border-radius:14px;resize:vertical;min-height:110px;font-size:13px;}
.caption-field:focus{outline:none;border-color:var(--c-accent);box-shadow:0 0 0 3px rgba(13,110,253,.15);}
.theme-dark .caption-field{background:#223344;color:#dfe8ef;border-color:#314556;}
.caption-counter{font-size:11px;color:var(--c-text-soft);margin-top:3px;text-align:left;direction:ltr;}
.comment-item textarea{background:var(--c-input-bg);border:1px solid var(--c-panel-border);color:var(--c-text);border-radius:12px;font-size:12.5px;resize:vertical;min-height:56px;}
.comment-item textarea:focus{outline:none;border-color:var(--c-accent);box-shadow:0 0 0 3px rgba(13,110,253,.15);}
.theme-dark .comment-item textarea{background:#223344;color:#e8eef3;border-color:#324757;}
.schedule-box{border:1px solid var(--c-panel-border);background:var(--c-muted-bg);border-radius:16px;padding:14px 16px;margin-top:6px;}
.theme-dark .schedule-box{background:#243746;border-color:#345162;}
.schedule-box .help-sched{background:#fff3cd;color:#775e12;border:1px solid #efd28c;font-size:11px;padding:6px 10px;border-radius:10px;margin-top:6px;}
.theme-dark .schedule-box .help-sched{background:#4d3b14;color:#ffe9a5;border-color:#8a6d1d;}
.schedule-row{display:grid;grid-template-columns:1fr 140px 1fr 40px;gap:14px;border:1px dashed #b7c8d5;padding:10px 12px;border-radius:14px;background:#f4f9fc;margin-top:10px;align-items:end;position:relative;}
.theme-dark .schedule-row{background:#2c4252;border-color:#466578;}
.schedule-row input[type=datetime-local], .recurrence-select{background:var(--c-input-bg);border:1px solid var(--c-panel-border);border-radius:10px;font-size:12.5px;padding:6px 10px;direction:ltr;}
.schedule-row input[type=datetime-local]:focus,.recurrence-select:focus{outline:none;border-color:var(--c-accent);box-shadow:0 0 0 3px rgba(13,110,253,.15);}
.theme-dark .schedule-row input[type=datetime-local],.theme-dark .recurrence-select{background:#203443;color:#dbe5ec;border-color:#31495a;}
.sched-preview{font-size:10px;color:#445f75;direction:ltr;margin-top:4px;line-height:1.4;}
.theme-dark .sched-preview{color:#c2d6e5;}
.hashtag-wrap textarea{background:var(--c-input-bg);border:1px solid var(--c-panel-border);color:var(--c-text);border-radius:14px;resize:vertical;min-height:70px;font-size:12.5px;}
.hashtag-tags span{display:inline-block;background:#e7f1fb;color:#204a72;margin:4px 4px 0 0;padding:5px 10px;border-radius:11px;font-size:11.5px;font-weight:600;cursor:pointer;transition:.2s;}
.hashtag-tags span:hover{background:#d5e8f9;}
.theme-dark .hashtag-wrap textarea{background:#223544;color:#dce6ed;border-color:#324e61;}
.theme-dark .hashtag-tags span{background:#2c4b61;color:#cde4f4;}
.theme-dark .hashtag-tags span:hover{background:#37627e;}
.hash-tools .btn{font-size:11px;padding:4px 10px;font-weight:600;}
.inline-note{font-size:11px;color:var(--c-text-soft);}
.badge-counter{background:#eef4fa;border:1px solid var(--c-panel-border);color:var(--c-text-soft);font-size:11px;padding:4px 12px;border-radius:20px;font-weight:600;}
.theme-dark .badge-counter{background:#2a3d4d;border-color:#3d586b;color:#a7bccd;}
.progress-wrap{margin-top:40px;display:none;}
.progress{height:22px;border-radius:14px;overflow:hidden;background:#d9e5f0;}
.theme-dark .progress{background:#2d4352;}
.progress-bar{background:linear-gradient(90deg,var(--c-accent),#54a3ff);font-size:12px;font-weight:700;}
.results-log{background:var(--c-muted-bg);border:1px solid var(--c-panel-border);border-radius:20px;padding:18px 20px;margin-top:34px;max-height:380px;overflow:auto;font-size:13px;display:none;}
.results-log::-webkit-scrollbar{width:8px;} .results-log::-webkit-scrollbar-thumb{background:#c2d3e0;border-radius:6px;}
.theme-dark .results-log{background:#233543;border-color:#344c5d;}
.theme-dark .results-log::-webkit-scrollbar-thumb{background:#395366;}
.results-log .ok{color:var(--c-success);font-weight:600;}
.results-log .scheduled{color:var(--c-yellow);font-weight:600;}
.results-log .err{color:var(--c-danger);font-weight:600;}
.action-bar{display:flex;flex-wrap:wrap;gap:14px;margin-top:34px;}
.action-bar .btn{min-width:140px;font-weight:600;border-radius:14px;padding:10px 18px;font-size:14px;}
.theme-toggle{position:fixed;top:12px;left:12px;z-index:99;}
.theme-toggle button{border-radius:40px;font-size:12px;font-weight:600;}
.debug-pill{position:fixed;bottom:14px;left:14px;background:var(--c-accent);color:#fff;font-size:11px;padding:6px 12px;border-radius:30px;display:none;z-index:999;font-weight:600;letter-spacing:.5px;}
.toast-msg{position:fixed;bottom:16px;right:16px;background:var(--c-accent);color:#fff;padding:10px 16px;border-radius:14px;font-size:12.5px;z-index:9999;box-shadow:0 6px 18px -6px rgba(0,0,0,.25);animation:toastIn .4s;}
@keyframes toastIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);} }
.btn-sched-test{font-size:11px;padding:5px 8px;line-height:1;border-radius:10px;}
.sch-time.is-invalid{border-color:var(--c-danger);box-shadow:0 0 0 2px rgba(220,53,69,.25);}
</style>
</head>
<body id="themeRoot">
<div class="theme-toggle">
  <button type="button" class="btn btn-sm btn-outline-primary" id="btnTheme">🌙 الوضع الداكن</button>
</div>
<div class="app-shell">
    <div class="top-bar">
        <h1 class="mb-0">نشر محتوى إنستجرام</h1>
        <div class="d-flex flex-wrap gap-2 top-actions">
             <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary btn-sm">الرئيسيه</a>
            <a href="<?= site_url('instagram/listing') ?>" class="btn btn-outline-secondary btn-sm">المحتوى</a>
            <span id="selCount" class="badge-counter" style="display:none;"></span>
        </div>
    </div>

    <div id="tzInfo" class="alert alert-info py-2 mb-3 small" style="font-size:12.5px;">
        جارٍ تحديد منطقتك...
    </div>

    <?php if(empty($accounts)): ?>
        <div class="alert alert-warning">
            لا توجد حسابات متصلة. <a href="<?= site_url('reels/pages') ?>">ربط الآن</a>
        </div>
    <?php else: ?>
    <form id="igForm" action="<?= site_url('instagram/publish') ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="ig_user_id" id="primaryAccountField">
        <input type="hidden" name="debug" id="debugFlag" value="0">
        <input type="hidden" name="_tz_name" id="tz_name_field">
        <input type="hidden" name="_tz_offset" id="tz_offset_field">
        <div class="layout">

            <!-- الحسابات -->
            <div class="panel">
                <div class="panel-header">
                    <span>الحسابات</span>
                    <span class="badge bg-primary">IG</span>
                </div>

                <div class="search-box mb-2">
                    <input type="text" id="accountSearch" class="form-control form-control-sm" placeholder="بحث">
                    <span class="ico">🔍</span>
                </div>

                <div class="d-flex gap-2 flex-wrap mb-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-primary" id="btnSelectAllAccounts">تحديد الكل</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearAccounts">إلغاء</button>
                </div>

                <div class="accounts-list" id="accountsList">
                    <?php foreach($accounts as $acc):
                        $pic = $acc['ig_profile_picture'] ?: '';
                        $fallback = 'https://via.placeholder.com/80x80?text=IG';
                        $uname = $acc['ig_username'] ?: $acc['ig_user_id'];
                        $filterKey = strtolower(($uname ?: '').' '.($acc['page_name'] ?: ''));
                        $uid = (string)$acc['ig_user_id'];
                    ?>
                    <div class="account-item" data-filter="<?= htmlspecialchars($filterKey) ?>" data-ig-card data-ig-id="<?= htmlspecialchars($uid) ?>" tabindex="0" role="button" aria-pressed="false">
                        <input type="checkbox" class="form-check-input acc-check acc-multi" name="ig_user_ids[]" value="<?= htmlspecialchars($uid) ?>">
                        <img class="acc-avatar" src="<?= htmlspecialchars($pic ?: $fallback) ?>" onerror="this.onerror=null;this.src='<?= $fallback ?>'" alt="">
                        <div class="acc-info">
                            <div class="uname">@<?= htmlspecialchars($uname) ?></div>
                            <div class="muted"><?= htmlspecialchars($acc['page_name'] ?: '') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr>

                <div class="mb-3">
                    <div class="small-note mb-1 fw-bold">نوع المحتوى</div>
                    <div class="btn-group">
                        <input type="radio" class="btn-check" name="media_kind" id="kindReel" value="reel" checked>
                        <label class="btn btn-sm btn-outline-primary" for="kindReel">Reels</label>
                        <input type="radio" class="btn-check" name="media_kind" id="kindStory" value="story">
                        <label class="btn btn-sm btn-outline-primary" for="kindStory">Stories</label>
                    </div>
                </div>

                <div class="small-note mb-2">إعدادات كل ملف مستقلة؛ أضف الحسابات ثم الملفات.</div>

                <div class="panel-header" style="font-size:14px;margin-bottom:10px;">إضافة الملفات</div>
                <div class="file-drop" id="fileDrop">
                    <div>اسحب أو اضغط لاختيار الملفات</div>
                    <div class="small-note mt-1">MP4 للريل – MP4 / JPG / PNG للستوري</div>
                    <input type="file" id="filesInput" name="media_files[]" multiple accept=".mp4,.jpg,.jpeg,.png" style="display:none;">
                </div>
            </div>

            <!-- الملفات -->
            <div class="panel">
                <div class="panel-header">
                    <span>الملفات</span>
                    <span class="badge bg-secondary" id="filesBadge">0</span>
                </div>

                <div id="filesContainer" class="file-grid"></div>

                <div class="progress-wrap" id="progressWrap">
                    <label class="form-label mb-1">التقدم</label>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadBar" style="width:0%">0%</div>
                    </div>
                    <div class="small-note mt-1" id="uploadStatus">في انتظار...</div>
                </div>

                <div class="action-bar">
                    <button type="button" class="btn btn-primary" id="btnAjax">تنفيذ</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnResetAll">مسح الكل</button>
                </div>

                <div class="results-log" id="resultsLog"></div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div id="debugPill" class="debug-pill">DEBUG MODE</div>

<script>
/* ========= إعداد المنطقة الزمنية ========= */
(function initTZ(){
  const tzNameField=document.getElementById('tz_name_field');
  const tzOffsetField=document.getElementById('tz_offset_field');
  const badge=document.getElementById('tzInfo');
  try{
    const off = new Date().getTimezoneOffset();
    tzOffsetField.value = off;
    tzNameField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    if(badge){
      badge.innerHTML = 'منطقتك: <strong>'+(tzNameField.value||'Local')+'</strong> | الإزاحة (دقائق) عن UTC: <strong>'+off+'</strong><br>'+
                        '<span class="small">الأوقات التي تدخلها محلية وسيتم تحويلها إلى UTC في السيرفر. (UTC = Local + offsetMinutes)</span>';
    }
  }catch(e){
    if(badge) badge.textContent='تعذر تحديد المنطقة الزمنية، سيتم اعتبار المدخلات محلية.';
  }
})();

/* ========= دوال تحويل الوقت للمعاينة ========= */
function toUtcFromLocalInput(localStr){
  if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(localStr)) return null;
  const d = new Date(localStr);
  if(isNaN(d.getTime())) return null;
  const pad=n=> String(n).padStart(2,'0');
  return d.getUTCFullYear()+'-'+pad(d.getUTCMonth()+1)+'-'+pad(d.getUTCDate())+' '+pad(d.getUTCHours())+':'+pad(d.getUTCMinutes())+':00';
}
function minutesUntil(localStr){
  const d=new Date(localStr);
  if(isNaN(d)) return null;
  return Math.round((d.getTime()-Date.now())/60000);
}

/* ========= تبديل الثيم ========= */
const themeRoot=document.getElementById('themeRoot');
const btnTheme=document.getElementById('btnTheme');
(function restoreTheme(){
  if(localStorage.getItem('ig_theme')==='dark'){
    themeRoot.classList.add('theme-dark');
    btnTheme.textContent='☀️ الوضع الفاتح';
  }
})();
btnTheme?.addEventListener('click',()=>{
  themeRoot.classList.toggle('theme-dark');
  const dark=themeRoot.classList.contains('theme-dark');
  btnTheme.textContent= dark? '☀️ الوضع الفاتح':'🌙 الوضع الداكن';
  localStorage.setItem('ig_theme',dark?'dark':'light');
});

/* ========= الحسابات ========= */
const primaryAccountField=document.getElementById('primaryAccountField');
const selCount=document.getElementById('selCount');
const accountSearch=document.getElementById('accountSearch');
const accountsContainer=document.getElementById('accountsList');

function getAllBoxes(){
  return Array.from(accountsContainer.querySelectorAll('input.acc-multi[name="ig_user_ids[]"]'));
}
function getCheckedBoxes(){
  return getAllBoxes().filter(b=>b.checked);
}
function syncCardVisuals(){
  getAllBoxes().forEach(b=>{
    const card=b.closest('[data-ig-card]');
    if(card){
      card.classList.toggle('selected', b.checked);
      card.setAttribute('aria-pressed', b.checked ? 'true' : 'false');
    }
  });
}
function updatePrimaryAccount(){
  const checked=getCheckedBoxes();
  primaryAccountField.value = checked.length ? checked[0].value : '';
  if(checked.length){
    selCount.style.display='inline-block';
    selCount.textContent='حسابات: '+checked.length;
  } else {
    selCount.style.display='none';
  }
  syncCardVisuals();
}

// الضغط على الكارت = توجل الشيك بوكس بدون تعارض
accountsContainer?.addEventListener('click', (e)=>{
  const card = e.target.closest('[data-ig-card]');
  if(!card || !accountsContainer.contains(card)) return;
  const inputClicked = e.target.closest('input.acc-multi');
  if(inputClicked){
    // لو الكليك على الشيك بوكس نفسه، التغيير هيحصل من حدث change
    return;
  }
  const box = card.querySelector('input.acc-multi');
  if(!box) return;
  box.checked = !box.checked;
  // اطلق change لنحافظ على مسار واحد للمزامنة
  box.dispatchEvent(new Event('change', {bubbles:true}));
});

// كيبورد: Enter/Space على الكارت
accountsContainer?.addEventListener('keydown', (e)=>{
  if(!(e.key==='Enter' || e.key===' ')) return;
  const card = e.target.closest('[data-ig-card]');
  if(!card) return;
  e.preventDefault();
  const box = card.querySelector('input.acc-multi');
  if(!box) return;
  box.checked = !box.checked;
  box.dispatchEvent(new Event('change',{bubbles:true}));
});

// مزامنة عند تغير أي شيك بوكس
accountsContainer?.addEventListener('change', (e)=>{
  const box = e.target.closest('input.acc-multi');
  if(!box) return;
  updatePrimaryAccount();
});

// أزرار التحكم
document.getElementById('btnSelectAllAccounts')?.addEventListener('click',()=>{
  const boxes=getAllBoxes();
  const all=boxes.every(b=>b.checked);
  boxes.forEach(b=>b.checked=!all);
  updatePrimaryAccount();
});
document.getElementById('btnClearAccounts')?.addEventListener('click',()=>{
  getAllBoxes().forEach(b=>b.checked=false);
  updatePrimaryAccount();
});

// بحث
accountSearch?.addEventListener('input',e=>{
  const q=(e.target.value||'').toLowerCase();
  accountsContainer.querySelectorAll('[data-ig-card]').forEach(card=>{
    const f=(card.getAttribute('data-filter')||'').toLowerCase();
    card.style.display = f.includes(q) ? 'flex' : 'none';
  });
});

// اختيار تلقائي لأول حساب في أول مرة لو ماحدّدش
(function autoSelectFirstIfNone(){
  const boxes=getAllBoxes();
  if(boxes.length && getCheckedBoxes().length===0){
    boxes[0].checked=true;
  }
  updatePrimaryAccount();
})();

/* ========= نوع المحتوى ========= */
const kindReel=document.getElementById('kindReel');
const kindStory=document.getElementById('kindStory');
[kindReel,kindStory].forEach(r=>r?.addEventListener('change',applyKindModeToAll));
function applyKindModeToAll(){
  const isStory=kindStory.checked;
  document.querySelectorAll('.file-card').forEach(card=>{
    const tabs=card.querySelectorAll('.tab-btn');
    tabs.forEach(tb=>{
      if(['caption','comments','hashtags'].includes(tb.dataset.tab)){
        tb.style.display=isStory?'none':'inline-block';
        if(isStory && tb.classList.contains('active')){
          card.querySelector('.tab-btn[data-tab="publish"]').click();
        }
      }
    });
    card.querySelectorAll('.tab-pane').forEach(p=>{
      if(['caption','comments','hashtags'].includes(p.dataset.pane)){
        p.style.display=isStory?'none':'';
        if(isStory) p.classList.remove('active');
      }
    });
  });
}

/* ========= الملفات ========= */
const fileDrop=document.getElementById('fileDrop');
const filesInput=document.getElementById('filesInput');
const filesContainer=document.getElementById('filesContainer');
const filesBadge=document.getElementById('filesBadge');
let masterFileList=[];
fileDrop?.addEventListener('click',()=>filesInput.click());
fileDrop?.addEventListener('dragover',e=>{e.preventDefault();fileDrop.classList.add('dragover');});
fileDrop?.addEventListener('dragleave',()=>fileDrop.classList.remove('dragover'));
fileDrop?.addEventListener('drop',e=>{
  e.preventDefault();fileDrop.classList.remove('dragover');
  appendFiles([...e.dataTransfer.files]);
});
filesInput?.addEventListener('change',()=>appendFiles([...filesInput.files],true));
function appendFiles(fs){
  fs.forEach(f=>masterFileList.push(f));
  renderFiles();
}
function removeFile(i){
  masterFileList=masterFileList.filter((_,idx)=>idx!==i);
  renderFiles();
}
function buildPublish(i){
  return `
    <div class="mb-2 fw-bold small-note" style="color:var(--c-text);">وضع النشر</div>
    <div class="d-flex gap-3 flex-wrap mb-2">
      <label class="form-check small-note">
        <input type="radio" class="form-check-input" name="media_cfg[${i}][publish_mode]" value="immediate" checked> الآن
      </label>
      <label class="form-check small-note">
        <input type="radio" class="form-check-input pub-mode-scheduled" name="media_cfg[${i}][publish_mode]" value="scheduled"> جدولة
      </label>
    </div>
    <div class="schedule-box" id="schBox-${i}" style="display:none;">
      <div class="help-sched">
        الأوقات محلية؛ سيحوّلها السيرفر تلقائياً إلى UTC ويخزن + يحفظ الأصل للعرض.
      </div>
      <div class="d-flex align-items-end gap-3 flex-wrap mt-2">
          <div>
            <label class="form-label small mb-1">عدد الجداول</label>
            <select class="form-select form-select-sm sch-count" name="media_cfg[${i}][schedule_count]" data-file="${i}" style="width:auto;">
              ${Array.from({length:10},(_,k)=>`<option value="${k+1}">${k+1}</option>`).join('')}
            </select>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sched-test" data-test-sched="${i}" title="فحص التوقيت">فحص التوقيت</button>
          <div class="inline-note">30 ثانية حد أدنى قبل التنفيذ.</div>
      </div>
      <div class="schedule-rows mt-2" id="schRows-${i}"></div>
    </div>`;
}
function buildCaption(i){
  return `
    <label class="form-label small mb-1">الوصف</label>
    <textarea name="media_cfg[${i}][caption]" class="form-control caption-field" maxlength="2200" placeholder="وصف الريل ..."></textarea>
    <div class="caption-counter"><span class="cap-len">0</span> / 2200</div>`;
}
function buildComments(i){
  return `
    <div class="d-flex align-items-center gap-3 mb-2">
      <label class="form-label small mb-1">عدد التعليقات</label>
      <select class="form-select form-select-sm comm-count" data-file="${i}" style="width:auto;">
        ${Array.from({length:21},(_,k)=>`<option value="${k}">${k}</option>`).join('')}
      </select>
      <button type="button" class="btn btn-sm btn-outline-secondary comm-clear" data-file="${i}">مسح</button>
    </div>
    <div class="comments-list" id="commentsList-${i}" style="display:none;"></div>
    <div class="inline-note mt-1">تعليقات تُنشر بعد نجاح الريل.</div>`;
}
function buildHashtags(i){
  return `
    <div class="hash-tools d-flex flex-wrap gap-2 mb-2">
      <button class="btn btn-sm btn-primary hash-fetch" data-file="${i}" type="button">تريند</button>
      <button class="btn btn-sm btn-outline-success hash-add" data-file="${i}" type="button">دمج للوصف</button>
      <button class="btn btn-sm btn-outline-secondary hash-clear" data-file="${i}" type="button">مسح</button>
      <span class="inline-note" id="hashStatus-${i}"></span>
    </div>
    <div class="hashtag-wrap">
      <textarea class="form-control form-control-sm hash-field" data-file="${i}" id="hashField-${i}" placeholder="#tags ..." rows="2"></textarea>
    </div>
    <div class="hashtag-tags mt-2" id="tagsList-${i}" style="display:none;"></div>`;
}
/* ==== جدولة ==== */
function initSchedule(card,i){
  const radioSched=card.querySelector('.pub-mode-scheduled');
  const box=card.querySelector('#schBox-'+i);
  const countSel=card.querySelector('.sch-count');
  const rows=card.querySelector('#schRows-'+i);
  card.querySelectorAll(`input[name="media_cfg[${i}][publish_mode]"]`).forEach(r=>{
    r.addEventListener('change',()=>{
      box.style.display=radioSched.checked?'':'none';
      if(radioSched.checked) rebuild();
    });
  });
  countSel.addEventListener('change',rebuild);
  function attachPreview(inp){
    const preview=inp.closest('.schedule-row').querySelector('.sched-preview');
    const warn30 = '<span class="text-danger"> (&lt;30s)</span>';
    function update(){
      const v=inp.value.trim();
      inp.classList.remove('is-invalid');
      if(!v){ preview.textContent='—'; return; }
      const utc=toUtcFromLocalInput(v);
      if(!utc){ preview.innerHTML='<span class="text-danger">تنسيق غير صالح</span>'; inp.classList.add('is-invalid'); return; }
      const minsUntil=minutesUntil(v);
      const less30 = (minsUntil!==null && (minsUntil*60)<30) ? warn30 : '';
      preview.innerHTML='UTC: <code>'+utc+'</code>'+less30;
    }
    inp.addEventListener('input',update);
    update();
  }
  function rebuild(){
    rows.innerHTML='';
    const c=parseInt(countSel.value||'1',10);
    for(let s=1;s<=c;s++){
      const div=document.createElement('div');
      div.className='schedule-row';
      div.innerHTML=`
        <div>
          <label class="form-label small mb-1">وقت #${s}</label>
          <input type="datetime-local" class="form-control form-control-sm sch-time" name="media_cfg[${i}][schedules][${s}][time]" required>
          <div class="sched-preview"></div>
        </div>
        <div>
          <label class="form-label small mb-1">تكرار</label>
          <select class="form-select form-select-sm recurrence-select" name="media_cfg[${i}][schedules][${s}][recurrence_kind]">
            <option value="none">بدون</option>
            <option value="daily">يومي</option>
            <option value="weekly">أسبوعي</option>
            <option value="monthly">شهري</option>
            <option value="quarterly">كل 3 شهور</option>
          </select>
        </div>
        <div class="rec-until" style="display:none;">
          <label class="form-label small mb-1">حتى تاريخ (اختياري)</label>
          <input type="datetime-local" class="form-control form-control-sm rec-until-input" name="media_cfg[${i}][schedules][${s}][recurrence_until]">
        </div>
        <div class="text-center">
          <span class="badge bg-light text-dark" style="font-size:10px;">#${s}</span>
        </div>`;
      rows.appendChild(div);
    }
    const minLocal = new Date(Date.now()+2*60000);
    const minStr = minLocal.toISOString().slice(0,16);
    rows.querySelectorAll('.sch-time').forEach(inp=>{ inp.min=minStr; attachPreview(inp); });
    rows.querySelectorAll('.recurrence-select').forEach(sel=>{
      sel.addEventListener('change',()=>{
        const wrap=sel.closest('.schedule-row').querySelector('.rec-until');
        wrap.style.display= sel.value!=='none' ? '' : 'none';
      });
    });
  }
  // زر فحص التوقيت
  box.addEventListener('click',e=>{
    const testBtn=e.target.closest('[data-test-sched]');
    if(!testBtn) return;
    const times=[...box.querySelectorAll('.sch-time')].map(t=>t.value).filter(Boolean);
    if(!times.length){ alert('لا يوجد أوقات مُدخلة بعد.'); return; }
    const offset = parseInt(document.getElementById('tz_offset_field').value||'0',10);
    const lines = times.map(l=>{
      const utc=toUtcFromLocalInput(l);
      return l+'  ==>  '+(utc?utc+' (UTC)':'خطأ');
    });
    alert('تحويل محلي → UTC (مع offset '+offset+' دقيقة):\n'+lines.join('\n'));
  });
}
/* ==== التعليقات ==== */
function initComments(card,i){
  const countSel=card.querySelector('.comm-count');
  const list=card.querySelector('#commentsList-'+i);
  const clear=card.querySelector('.comm-clear');
  countSel.addEventListener('change',rebuild);
  clear.addEventListener('click',()=>{countSel.value=0;rebuild();});
  function rebuild(){
    const n=parseInt(countSel.value||'0',10);
    list.innerHTML='';
    if(n>0){
      list.style.display='';
      for(let k=1;k<=n;k++){
        const d=document.createElement('div');
        d.className='comment-item mb-2';
        d.innerHTML=`<label class="form-label small mb-1">تعليق ${k}</label>
                     <textarea maxlength="2200" class="form-control" name="media_cfg[${i}][comments][]" placeholder="تعليق ${k}"></textarea>`;
        list.appendChild(d);
      }
    } else list.style.display='none';
  }
}
/* ==== الهاشتاج ==== */
function initHashtags(card,i){
  const fetchBtn=card.querySelector('.hash-fetch');
  const addBtn=card.querySelector('.hash-add');
  const clearBtn=card.querySelector('.hash-clear');
  const status=card.querySelector('#hashStatus-'+i);
  const field=card.querySelector('#hashField-'+i);
  const list=card.querySelector('#tagsList-'+i);
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
              const cap=card.querySelector(`textarea[name="media_cfg[${i}][caption]"]`);
              if(cap && !cap.value.includes('#'+t)){
                if(cap.value && !cap.value.endsWith(' ')) cap.value+=' ';
                cap.value+='#'+t;
                cap.dispatchEvent(new Event('input'));
              }
            };
            list.appendChild(sp);
          });
          list.style.display='block';
          status.textContent='تم جلب '+j.tags.length;
        } else status.textContent='فشل';
      }).catch(()=>status.textContent='خطأ');
  });
  addBtn.addEventListener('click',()=>{
    if(!field.value.trim()) return;
    const cap=card.querySelector(`textarea[name="media_cfg[${i}][caption]"]`);
    if(cap){
      if(cap.value && !cap.value.endsWith(' ')) cap.value+=' ';
      cap.value+=field.value.trim();
      cap.dispatchEvent(new Event('input'));
    }
  });
  clearBtn.addEventListener('click',()=>{
    field.value='';list.innerHTML='';list.style.display='none';status.textContent='مسح';
  });
  const capField=card.querySelector(`textarea[name="media_cfg[${i}][caption]"]`);
  if(capField){
    const counter=card.querySelector('.cap-len');
    capField.addEventListener('input',()=>counter.textContent=capField.value.length);
  }
}

function renderFiles(){
  filesContainer.innerHTML='';
  filesBadge.textContent=masterFileList.length;
  masterFileList.forEach((f,idx)=>{
    const ext=f.name.split('.').pop().toLowerCase();
    const isImg=['jpg','jpeg','png'].includes(ext);
    const isVideo=ext==='mp4';
    const card=document.createElement('div');
    card.className='file-card';
    card.dataset.index=idx;
    card.innerHTML=`
      <div class="file-head">
        <div class="file-name" title="${f.name}">${f.name}</div>
        <div class="file-remove" data-remove="${idx}" title="إزالة">×</div>
      </div>
        <div class="media-preview">
          ${isImg?'<img src="'+URL.createObjectURL(f)+'">':(isVideo?'<video controls preload="metadata"><source src="'+URL.createObjectURL(f)+'" type="video/mp4"></video>':'<div class="small-note">لا معاينة</div>')}
        </div>
      <div class="tabs">
        <div class="tab-btn active" data-tab="publish">النشر</div>
        <div class="tab-btn" data-tab="caption">الوصف</div>
        <div class="tab-btn" data-tab="comments">التعليقات</div>
        <div class="tab-btn" data-tab="hashtags">هاشتاج</div>
      </div>
      <div class="tab-pane active" data-pane="publish">${buildPublish(idx)}</div>
      <div class="tab-pane" data-pane="caption">${buildCaption(idx)}</div>
      <div class="tab-pane" data-pane="comments">${buildComments(idx)}</div>
      <div class="tab-pane" data-pane="hashtags">${buildHashtags(idx)}</div>
    `;
    card.querySelectorAll('.tab-btn').forEach(btn=>btn.addEventListener('click',()=>activateTab(btn)));
    initSchedule(card,idx);
    initComments(card,idx);
    initHashtags(card,idx);
    filesContainer.appendChild(card);
  });
  applyKindModeToAll();
  document.querySelectorAll('[data-remove]').forEach(btn=>{
    btn.addEventListener('click',()=>removeFile(parseInt(btn.dataset.remove)));
  });
}
function activateTab(btn){
  const card=btn.closest('.file-card');
  card.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  const target=btn.dataset.tab;
  card.querySelectorAll('.tab-pane').forEach(p=>p.classList.toggle('active',p.dataset.pane===target));
}

/* ========== تحقق الجدولة ========== */
function validateSchedules(){
  const now=Date.now();
  const errors=[];
  document.querySelectorAll('.file-card').forEach(card=>{
    const idx=card.dataset.index;
    const fileName = masterFileList[idx]?.name || ('#'+(idx+1));
    const isSched = card.querySelector(`input[name="media_cfg[${idx}][publish_mode]"][value="scheduled"]`)?.checked;
    if(!isSched) return;
    const times=[...card.querySelectorAll('.sch-time')];
    if(!times.length){ errors.push(`(${fileName}) لا يوجد وقت`); return; }
    times.forEach((inp,i)=>{
      inp.classList.remove('is-invalid');
      if(!inp.value){ errors.push(`(${fileName}) وقت ${i+1} فارغ`); inp.classList.add('is-invalid'); return; }
      if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(inp.value)){ errors.push(`(${fileName}) وقت ${i+1} تنسيق خاطئ`); inp.classList.add('is-invalid'); return; }
      const ms = new Date(inp.value).getTime();
      if(isNaN(ms)){ errors.push(`(${fileName}) وقت ${i+1} غير قابل للقراءة`); inp.classList.add('is-invalid'); return; }
      if(ms - now < 30000){ errors.push(`(${fileName}) وقت ${i+1} أقل من 30 ثانية مستقبلية`); inp.classList.add('is-invalid'); return; }
    });
  });
  return errors;
}

/* ========== AJAX تنفيذ ========== */
const btnAjax=document.getElementById('btnAjax');
const progressWrap=document.getElementById('progressWrap');
const uploadBar=document.getElementById('uploadBar');
const uploadStatus=document.getElementById('uploadStatus');
const resultsLog=document.getElementById('resultsLog');
const debugFlag=document.getElementById('debugFlag');
const debugPill=document.getElementById('debugPill');

document.addEventListener('keydown',e=>{
  if(e.altKey && e.shiftKey && e.code==='KeyD'){
    debugFlag.value = debugFlag.value==='1' ? '0':'1';
    debugPill.style.display = debugFlag.value==='1' ? 'block':'none';
    toast('Debug '+(debugFlag.value==='1'?'ON':'OFF'));
  }
});

btnAjax?.addEventListener('click',()=>{
  updatePrimaryAccount();
  if(!primaryAccountField.value){ alert('اختر حساباً'); return; }
  if(masterFileList.length===0){ alert('اختر ملفات'); return; }
  const schedErrors=validateSchedules();
  if(schedErrors.length){
    alert('أخطاء الجدولة:\n- '+schedErrors.join('\n- '));
    return;
  }
  resultsLog.style.display='none';resultsLog.innerHTML='';
  progressWrap.style.display='block';
  uploadBar.style.width='0%';uploadBar.textContent='0%';
  uploadStatus.textContent='بدء...';
  btnAjax.disabled=true;

  const formEl=document.getElementById('igForm');
  const fd=new FormData();
  [...formEl.querySelectorAll('input,select,textarea')].forEach(el=>{
    if(!el.name) return;
    if(el.type==='file') return;
    if(['checkbox','radio'].includes(el.type) && !el.checked) return;
    fd.append(el.name, el.value);
  });
  fd.append('_client_file_count', masterFileList.length);
  masterFileList.forEach(f=>fd.append('media_files[]', f, f.name));

  const firstTime = document.querySelector('.sch-time')?.value;
  if(firstTime){
    const utcPrev = toUtcFromLocalInput(firstTime);
    console.log('[SchedulePreview]', {first_local:firstTime, calc_utc:utcPrev});
  }

  const xhr=new XMLHttpRequest();
  xhr.open('POST', formEl.action + (debugFlag.value==='1'?(formEl.action.includes('?')?'&':'?')+'debug=1':''), true);
  xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
  xhr.upload.addEventListener('progress',ev=>{
    if(ev.lengthComputable){
      const pc=Math.round((ev.loaded/ev.total)*100);
      uploadBar.style.width=pc+'%';
      uploadBar.textContent=pc+'%';
      uploadStatus.textContent='رفع: '+pc+'%';
    }
  });
  xhr.onreadystatechange=()=>{
    if(xhr.readyState===4){
      btnAjax.disabled=false;
      if(xhr.status===200){
        let j;
        try{ j=JSON.parse(xhr.responseText); }
        catch(e){ console.error('RAW',xhr.responseText); uploadStatus.textContent='استجابة غير صالحة'; alert('استجابة غير صالحة'); return; }
        if(j.debug){
          uploadStatus.textContent='DEBUG';
          resultsLog.style.display='block';
          resultsLog.innerHTML='<pre style="white-space:pre-wrap;direction:ltr;font-size:11px;text-align:left;">'+
            JSON.stringify(j,null,2)+'</pre>';
          return;
        }
        if(j.status==='ok'){
          uploadStatus.textContent='انتهى';
          showResults(j.results||[]);
          if(j.redirect_url) setTimeout(()=>window.location.href=j.redirect_url,1300);
        } else {
          uploadStatus.textContent='فشل';
          alert('فشل: '+(j.message||''));
          showResults(j.results||[],true);
        }
      } else {
        uploadStatus.textContent='HTTP '+xhr.status;
        alert('خطأ شبكة');
      }
    }
  };
  xhr.send(fd);
});

function showResults(list, forceError=false){
  resultsLog.style.display='block';
  resultsLog.innerHTML='<div class="fw-bold mb-2">النتائج:</div>';
  if(!list.length && forceError){ resultsLog.innerHTML+='<div class="err">لا تفاصيل</div>'; }
  list.forEach(r=>{
    if(r.status==='ok'){
      resultsLog.innerHTML+='<div class="ok">✔ '+(r.file||'')+' '+(r.ig_user_id?('('+r.ig_user_id+')'):'')+'</div>';
    } else if(r.status==='scheduled'){
      resultsLog.innerHTML+='<div class="scheduled">⏱ '+(r.file||'')+' [مجدول]</div>';
    } else {
      resultsLog.innerHTML+='<div class="err">✖ '+(r.file||'')+' '+(r.ig_user_id?('('+r.ig_user_id+') '):'')+'('+(r.error||'خطأ')+')</div>';
    }
  });
}

document.getElementById('btnResetAll')?.addEventListener('click',()=>{
  masterFileList=[]; filesInput.value=''; filesContainer.innerHTML=''; filesBadge.textContent='0';
  getAllBoxes().forEach(c=>c.checked=false);
  updatePrimaryAccount();
  progressWrap.style.display='none'; resultsLog.style.display='none'; resultsLog.innerHTML='';
});
document.getElementById('igForm')?.addEventListener('submit',e=>e.preventDefault());

function toast(msg){
  const t=document.createElement('div');
  t.className='toast-msg'; t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';t.style.transform='translateY(10px)';
    setTimeout(()=>t.remove(),350);
  },2000);
}

/* ====== تفعيل البداية ====== */
renderFiles();
</script>
</body>
</html>
