<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نشر إنستجرام متعدد</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body{background:linear-gradient(145deg,#f3f7fb,#e8eef5);font-family:"Tahoma",Arial,sans-serif;color:#223;}
h1{font-size:24px;font-weight:700;color:#124b85;margin-bottom:22px;display:flex;align-items:center;gap:10px;}
.card-panel{background:#fff;border:1px solid #d9e3ef;border-radius:24px;padding:28px;box-shadow:0 4px 16px rgba(0,0,0,.05);}
.accounts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-top:10px;}
.acc-item{position:relative;background:#f7fbff;border:1px solid #d2e2f0;border-radius:18px;padding:12px;cursor:pointer;transition:.25s;}
.acc-item:hover{border-color:#77aee0;background:#f0f7ff;}
.acc-item.selected{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.18);}
.acc-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 0 0 1px #b3c9db;}
.acc-username{font-size:13px;font-weight:600;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;direction:ltr;text-align:left;}
.acc-page{font-size:11px;color:#5b7084;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.select-all-btn{font-size:12px;font-weight:600;border-radius:20px;padding:6px 14px;}
.section-title{font-size:15px;font-weight:700;color:#174e82;margin:18px 0 10px;display:flex;align-items:center;gap:6px;}
.badge-type{cursor:pointer;padding:6px 16px;font-weight:600;border:1px solid #bcd3e6;color:#14496f;border-radius:18px;background:#eef5fb;transition:.25s;}
.badge-type.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
textarea.form-control{resize:vertical;min-height:120px;}
.caption-footer{display:flex;justify-content:space-between;font-size:12px;color:#6a7d8f;margin-top:6px;}
.preview-box{display:none;background:#f2f8ff;border:1px dashed #b6cee3;border-radius:16px;margin-top:18px;padding:14px;font-size:13px;}
.preview-box video,.preview-box img{max-width:200px;border-radius:12px;border:1px solid #cddae4;}
.actions-bar{display:flex;flex-wrap:wrap;gap:14px;margin-top:26px;}
.btn-pill{border-radius:22px;font-weight:600;padding:10px 24px;}
.hash-suggestions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.hash-suggestions .tag{background:#eef5ff;color:#205480;font-weight:600;padding:6px 12px;border-radius:14px;font-size:12px;cursor:pointer;transition:.2s;}
.hash-suggestions .tag:hover{background:#d6ebff;}
.first-comment-note{font-size:11px;color:#617386;margin-top:4px;}
.alert-placeholder{min-height:46px;margin-bottom:10px;}
.spinner-border{width:1.2rem;height:1.2rem;}
.footer-note{text-align:center;margin-top:40px;font-size:11px;color:#7b8da1;}
.account-checkbox{position:absolute;top:8px;left:8px;transform:scale(1.1);}
.badge-small{font-size:10px;background:#eaf2f9;color:#3f6785;font-weight:600;padding:3px 8px;border-radius:10px;margin-top:4px;display:inline-block;}
@media (max-width:700px){
  .accounts-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));}
  .acc-item{padding:10px;}
}
</style>
</head>
<body>
<div class="container my-4">
  <div class="card-panel">
    <h1>نشر محتوى إنستجرام متعدد</h1>

    <div id="alertArea" class="alert-placeholder">
      <?php if($this->session->flashdata('ig_error')): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('ig_error')) ?></div>
      <?php elseif($this->session->flashdata('ig_success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('ig_success')) ?></div>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2 mb-3 flex-wrap">
      <a href="<?= site_url('instagram/listing') ?>" class="btn btn-outline-secondary btn-sm">عرض المحتوى</a>
      <a href="<?= site_url('reels/pages') ?>" class="btn btn-outline-dark btn-sm">الصفحات</a>
    </div>

    <?php if(empty($accounts)): ?>
      <div class="alert alert-warning">لا توجد حسابات إنستجرام مرتبطة.</div>
    <?php else: ?>

    <form id="publishForm" action="<?= site_url('instagram/publish') ?>" method="post" enctype="multipart/form-data">
      <!-- الحسابات -->
      <div class="section-title">الحسابات</div>
      <button type="button" class="btn btn-sm btn-primary select-all-btn" id="btnSelectAll">تحديد الكل</button>
      <div class="accounts-grid" id="accountsGrid">
        <?php foreach($accounts as $acc):
          $pic = $acc['ig_profile_picture'] ?: 'https://via.placeholder.com/60x60?text=IG';
          $uname = $acc['ig_username'] ?: $acc['ig_user_id'];
        ?>
        <div class="acc-item" data-id="<?= htmlspecialchars($acc['ig_user_id']) ?>">
          <input type="checkbox" name="ig_user_ids[]" class="account-checkbox" value="<?= htmlspecialchars($acc['ig_user_id']) ?>">
          <img src="<?= htmlspecialchars($pic) ?>" class="acc-avatar" alt="">
          <div class="acc-username">@<?= htmlspecialchars($uname) ?></div>
          <div class="acc-page"><?= htmlspecialchars($acc['page_name']) ?></div>
          <span class="badge-small"><?= htmlspecialchars(substr($acc['ig_user_id'],0,6)) ?>..</span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- نوع المحتوى -->
      <div class="section-title">نوع المحتوى</div>
      <div class="d-flex gap-3 flex-wrap" id="typeSelector">
        <span class="badge-type active" data-kind="reel">ريل</span>
        <span class="badge-type" data-kind="story">ستوري</span>
      </div>
      <input type="hidden" name="media_kind" id="mediaKind" value="reel">

      <!-- الوصف -->
      <div class="section-title" id="captionTitle">الوصف (للريل)</div>
      <textarea class="form-control" name="caption" id="captionInput" maxlength="2200" placeholder="الوصف..."></textarea>
      <div class="caption-footer">
        <div><span id="captionCount">0</span> / 2200</div>
        <div class="text-muted small">يمكن إضافة هاشتاجات #</div>
      </div>

      <!-- التعليق الأول -->
      <div class="section-title" id="firstCommentTitle">التعليق الأول (اختياري)</div>
      <textarea class="form-control" name="first_comment" id="firstCommentInput" maxlength="2200" placeholder="تعليق يُنشر بعد الريل"></textarea>
      <div class="first-comment-note">قد يفشل إذا الصلاحية instagram_manage_comments غير مفعلة – يظهر في الجدول.</div>

      <!-- مولد هاشتاج -->
      <div class="section-title d-flex align-items-center gap-2">
        <span>مولّد هاشتاج محلي</span>
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-outline-primary periodBtn active" data-period="today">اليوم</button>
          <button type="button" class="btn btn-sm btn-outline-primary periodBtn" data-period="7d">7 أيام</button>
          <button type="button" class="btn btn-sm btn-outline-primary periodBtn" data-period="30d">30 يوم</button>
        </div>
        <button type="button" class="btn btn-sm btn-success" id="btnAddAllTags">إضافة الكل للوصف</button>
        <div id="hashLoader" style="display:none">
          <div class="spinner-border text-primary" role="status"></div>
        </div>
      </div>
      <div id="hashSuggestions" class="hash-suggestions"></div>
      <div class="text-muted small mt-1">ملاحظة: هذا الترتيب من محتواك فقط. “تريند إنستجرام” الحقيقي يحتاج تكامل خارجي لاحق.</div>

      <!-- الملف -->
      <div class="section-title">الملف</div>
      <input type="file" class="form-control" name="file" id="fileInput" accept=".mp4,.jpg,.jpeg,.png" required>
      <div class="form-text">ريل: MP4 ≤ 90ث | ستوري: صورة (يفضل 9:16) أو MP4 ≤ 60ث.</div>

      <div class="preview-box" id="previewBox"></div>

      <div class="actions-bar">
        <button type="submit" class="btn btn-primary btn-pill" id="btnSubmit">نشر (ارسال)</button>
        <button type="button" class="btn btn-outline-primary btn-pill" id="btnAjax">نشر AJAX</button>
        <button type="button" class="btn btn-outline-secondary btn-pill" id="btnClear">تفريغ</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <div class="footer-note">Instagram Multi Publisher v2.0 (عربى)</div>
</div>

<script>
/* الحسابات */
document.querySelectorAll('.acc-item').forEach(it=>{
  it.addEventListener('click',e=>{
    if(e.target.classList.contains('account-checkbox')) return;
    const cb=it.querySelector('.account-checkbox');
    cb.checked=!cb.checked;
    it.classList.toggle('selected',cb.checked);
  });
  const cb=it.querySelector('.account-checkbox');
  cb.addEventListener('change',()=>it.classList.toggle('selected',cb.checked));
});
document.getElementById('btnSelectAll')?.addEventListener('click',()=>{
  const boxes=[...document.querySelectorAll('.account-checkbox')];
  const allSel=boxes.every(b=>b.checked);
  boxes.forEach(b=>{b.checked=!allSel; b.dispatchEvent(new Event('change'));});
});

/* نوع المحتوى */
const typeBadges=document.querySelectorAll('.badge-type');
const mediaKindInput=document.getElementById('mediaKind');
function updateType(kind){
  mediaKindInput.value=kind;
  typeBadges.forEach(b=>b.classList.toggle('active',b.dataset.kind===kind));
  const isReel= kind==='reel';
  document.getElementById('captionTitle').style.display=isReel?'':'none';
  document.getElementById('captionInput').parentElement.style.display=isReel?'':'none';
  document.getElementById('firstCommentTitle').style.display=isReel?'':'none';
  document.getElementById('firstCommentInput').style.display=isReel?'':'none';
}
typeBadges.forEach(b=>b.addEventListener('click',()=>updateType(b.dataset.kind)));

/* عداد الوصف */
const captionInput=document.getElementById('captionInput');
captionInput.addEventListener('input',()=>document.getElementById('captionCount').textContent=captionInput.value.length);

/* معاينة */
const fileInput=document.getElementById('fileInput');
const previewBox=document.getElementById('previewBox');
fileInput.addEventListener('change',()=>{
  previewBox.innerHTML=''; previewBox.style.display='none';
  const f=fileInput.files[0]; if(!f) return;
  const ext=f.name.split('.').pop().toLowerCase();
  if(['jpg','jpeg','png'].includes(ext)){
    previewBox.innerHTML='<div>معاينة:</div><img src="'+URL.createObjectURL(f)+'">';
  } else if(ext==='mp4'){
    previewBox.innerHTML='<div>معاينة:</div><video controls><source src="'+URL.createObjectURL(f)+'"></video>';
  }
  previewBox.style.display='block';
});

/* هاشتاج */
const hashWrap=document.getElementById('hashSuggestions');
const hashLoader=document.getElementById('hashLoader');
function getCurrentTags(){
  const first=document.getElementById('firstCommentInput').value;
  const val=captionInput.value+' '+first;
  const m=val.match(/#([\p{L}0-9_]+)/gu);
  if(!m) return [];
  return m.map(x=>x.replace('#','').toLowerCase());
}
function fetchHashtags(period){
  hashLoader.style.display='block';
  hashWrap.innerHTML='';
  fetch('<?= site_url('instagram/hashtags_suggest') ?>?period='+encodeURIComponent(period)+'&exclude='+encodeURIComponent(getCurrentTags().join(',')))
    .then(r=>r.json())
    .then(j=>{
      hashLoader.style.display='none';
      hashWrap.innerHTML='';
      if(j.status==='ok' && j.data.length){
        j.data.forEach(item=>{
          const tag=document.createElement('span');
          tag.className='tag';
          tag.textContent='#'+item.hashtag;
          tag.title='تكرار: '+item.count;
          tag.addEventListener('click',()=>appendToCaption(' #'+item.hashtag));
          hashWrap.appendChild(tag);
        });
      } else {
        hashWrap.innerHTML='<span class="text-muted small">لا نتائج (جرّب فترة أطول)</span>';
      }
    })
    .catch(_=>{
      hashLoader.style.display='none';
      hashWrap.innerHTML='<span class="text-danger small">خطأ في جلب الهاشتاج</span>';
    });
}
function appendToCaption(txt){
  if(mediaKindInput.value!=='reel') return;
  captionInput.value+=txt;
  captionInput.dispatchEvent(new Event('input'));
}
document.getElementById('btnAddAllTags').addEventListener('click',()=>{
  const tags=hashWrap.querySelectorAll('.tag');
  let add='';
  tags.forEach(t=>{ if(!captionInput.value.includes(t.textContent)) add+=' '+t.textContent; });
  captionInput.value+=add;
  captionInput.dispatchEvent(new Event('input'));
});
document.querySelectorAll('.periodBtn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.periodBtn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    fetchHashtags(btn.dataset.period);
  });
});
fetchHashtags('today');

/* نشر AJAX */
const form=document.getElementById('publishForm');
const btnAjax=document.getElementById('btnAjax');
btnAjax.addEventListener('click',e=>{
  e.preventDefault();
  publishAjax();
});
function publishAjax(){
  const fd=new FormData(form);
  btnAjax.disabled=true; btnAjax.textContent='... جاري النشر';
  fetch(form.action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json())
    .then(j=>{
      btnAjax.disabled=false; btnAjax.textContent='نشر AJAX';
      if(j.status==='ok'){
        if(j.redirect_url) window.location=j.redirect_url;
        else showAlert('success','تم النشر');
      } else {
        showAlert('danger',j.message || 'خطأ');
      }
    })
    .catch(err=>{
      btnAjax.disabled=false; btnAjax.textContent='نشر AJAX';
      showAlert('danger','خطأ شبكة');
      console.error(err);
    });
}

/* تفريغ */
document.getElementById('btnClear').addEventListener('click',()=>{
  form.reset();
  document.querySelectorAll('.acc-item').forEach(it=>{
    const cb=it.querySelector('.account-checkbox');
    cb.checked=false; it.classList.remove('selected');
  });
  captionInput.dispatchEvent(new Event('input'));
  previewBox.style.display='none';
  updateType('reel');
  fetchHashtags('today');
});

function showAlert(type,msg){
  const area=document.getElementById('alertArea');
  area.innerHTML='<div class="alert alert-'+type+'">'+msg+'</div>';
  setTimeout(()=>{area.innerHTML='';},5000);
}
</script>
</body>
</html>