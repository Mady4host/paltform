<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>رفع (Reel / Story)فيسبوك</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
<style>
body{
  background: linear-gradient(135deg,#f1f4f8 0%,#e8ecf5 80%);
  font-family: 'Cairo', Tahoma, Arial, sans-serif;
  min-height: 100vh;
}
.upload-main-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(13,78,150,0.13), 0 2px 6px rgba(0,0,0,0.04);
  max-width: 1050px;
  margin: 38px auto 32px auto;
  padding: 0;
  overflow: hidden;
  border: 1.5px solid #dde8f5;
}
@media (max-width: 991.98px) {
  .upload-main-card { border-radius: 0; margin:0; max-width:100%; }
}
.upload-header {
  background: linear-gradient(90deg,#0d4e96 80%,#4fc3f7 160%);
  color: #fff;
  padding: 30px 30px 18px 30px;
  border-bottom: 1px solid #eaf2f9;
  text-align: right;
}
.upload-header h2 {
  margin: 0 0 6px 0;
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: .5px;
}
.upload-header .desc {
  font-size: 1.08rem;
  opacity: .90;
  font-weight: 400;
}
.upload-body {
  padding: 32px 24px 24px 24px;
}
@media (max-width: 767.98px) {
  .upload-body { padding: 20px 7px 10px 7px; }
  .upload-header { padding: 22px 10px 12px 10px;}
}
.auto-hide{transition:opacity .7s;}
/* Section titles */
.upload-section-title {
  font-weight:700;
  color:#0d4e96;
  font-size:1.12rem;
  margin-bottom:8px;
  margin-top:26px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.upload-section-title .icon {
  font-size: 1.15em;
  color:#2196f3;
}

/* Pages */
.pages-wrapper{background:#f9fbfe;border:1px solid #dbe6f7;border-radius:12px;padding:12px;max-height:250px;overflow-y:auto;}
.page-row{display:flex;align-items:center;gap:12px;padding:7px 4px;border-bottom:1px solid #e9eef7;font-size:14px;cursor:pointer;transition:.12s;}
.page-row:last-child{border-bottom:none;}
.page-row:hover{background:#f4faff;}
.page-row img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:1.5px solid #c4d2e2;}
#pageSearch{width:220px;display:inline-block;}
@media (max-width: 767.98px) {
  #pageSearch { width:100%; }
}

/* Hashtag cloud + selected */
.hashtag-btn{background:linear-gradient(90deg,#f0f6ff 70%,#eaf3fa 100%);border:1px solid #b6d1fa;border-radius:16px;padding:3px 13px;font-size:13.5px;cursor:pointer;margin:2px;color:#0d4e96;font-weight:600;box-shadow:0 2px 4px rgba(33,150,243,.04);}
.hashtag-btn:hover{background:#e2ebf9;}
.selected-tags-box{background:#f9fbfe;border:1px solid #dbe6f7;border-radius:10px;padding:10px;min-height:44px;font-size:13px;display:flex;flex-wrap:wrap;gap:6px;}
.selected-tags-box span{background:#0d4e96;color:#fff;padding:3px 10px;border-radius:14px;display:inline-flex;align-items:center;gap:6px;}
.selected-tags-box span i{cursor:pointer;font-style:normal;font-weight:bold;}
/* Drop area */
#videoDrop{border:2.5px dashed #0d4e96;background:linear-gradient(90deg,#fff 85%,#e0edfa 100%);border-radius:14px;padding:32px;text-align:center;cursor:pointer;transition:.23s;}
#videoDrop:hover{background:#f5faff;box-shadow:0 6px 20px rgba(33,150,243,0.07);}
@media (max-width: 767.98px) {
  #videoDrop{padding:18px;}
}
/* Video Card */
.video-card{background:#fafdff;border:1.5px solid #e0edfa;border-radius:16px;padding:18px;margin-top:22px;position:relative;display:flex;gap:22px;box-shadow:0 2px 10px rgba(33,150,243,.05);}
@media (max-width: 767.98px) {
  .video-card { flex-direction:column; gap: 14px; padding:12px; }
}
.video-thumb video{width:190px;height:340px;object-fit:cover;background:#000;border-radius:10px;box-shadow:0 4px 10px rgba(13,78,150,0.05);}
.remove-video{position:absolute;top:10px;left:10px;font-size:13px;background:#dc3545;color:#fff;border:none;border-radius:20px;padding:6px 15px;cursor:pointer;box-shadow:0 2px 10px rgba(220,53,69,.07);transition:.15s;}
.remove-video:hover{background:#bb2332;}
.cover-actions{display:flex;gap:10px;margin-top:14px;}
.cover-actions .btn{flex:1;}
.cover-thumbs{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.cover-thumbs .thumb{width:58px;height:94px;border:2px solid transparent;border-radius:7px;overflow:hidden;cursor:pointer;background:#eee;box-shadow:0 1px 2px rgba(13,78,150,0.05);}
.cover-thumbs .thumb img{width:100%;height:100%;object-fit:cover;}
.cover-thumbs .thumb.selected{border-color:#2196f3;box-shadow:0 0 0 2px rgba(33,150,243,.14);}
.comment-block{background:#f5faff;border:1px solid #dbe6f7;border-radius:10px;padding:10px;margin-top:10px;}
.comment-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px;}
.comment-row textarea{flex:1;min-height:42px;}
.comment-row input[type=datetime-local]{width:170px;}
.comment-row .remove-comment{background:#ffe5e5;border:1px solid #ffb4b4;color:#a40000;font-size:11px;border-radius:14px;padding:2px 8px;cursor:pointer;}
.comment-row .remove-comment:hover{background:#ffcdcd;}
.progress-global{display:none;margin-top:20px;height:26px;}
.footer-actions{margin-top:32px;display:flex;gap:16px;}
@media (max-width: 767.98px) { .footer-actions { flex-direction:column; gap:8px; } }
.story-note{font-size:13px;color:#b35c00;margin-top:7px;line-height:1.5;display:none;}
.story-photo-box{display:none;background:#fff;border:1px solid #d6e1ee;border-radius:10px;padding:14px;margin-bottom:18px;}
#contentTypeWrapper{background:#fafdff;border:1.5px solid #e0edfa;border-radius:10px;padding:17px;display:flex;align-items:center;justify-content:space-between;gap:15px;}
#contentTypeBtn{min-width:230px;text-align:right;}
.dropdown-menu li button{width:100%;text-align:right;}
#story-photo-multi-block{display:none;background:#fafdff;border:1.5px solid #e0edfa;border-radius:10px;padding:14px;margin-bottom:18px;}
.story-item img{max-height:120px;display:block;margin-bottom:8px;width:100%;object-fit:cover;border-radius:6px;}
.story-item{width:170px;border:1px solid #eee;padding:8px;border-radius:7px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
@media (max-width: 576px){
    #contentTypeWrapper{flex-direction:column;align-items:stretch;}
    #contentTypeBtn{width:100%;}
    .story-item{width:100%;}
}
</style>
</head>
<body>
<?php
$preselected = isset($preselected_pages) && is_array($preselected_pages) ? $preselected_pages : [];
?>
<div class="upload-main-card">
  <div class="upload-header">
    <h2><i class="fa-solid fa-clapperboard icon"></i> رفع (Reel / Story)</h2>
    <div class="desc">ارفع الريلز أو القصص مع دعم جدولة(فيسبوك)</div>
  </div>
  <div class="upload-body">

    <?php if($this->session->flashdata('msg_success')): ?>
        <div class="alert alert-success auto-hide"><?= $this->session->flashdata('msg_success') ?></div>
    <?php endif; ?>
    <?php if($this->session->flashdata('msg')): ?>
        <div class="alert alert-danger auto-hide"><?= $this->session->flashdata('msg') ?></div>
    <?php endif; ?>

    <form id="reelsForm" method="post" action="<?= site_url('reels/process_upload') ?>" enctype="multipart/form-data">

        <!-- نوع المحتوى -->
        <div class="mb-4" id="contentTypeWrapper">
            <div class="fw-bold" style="font-size:15px;"><i class="fa-solid fa-layer-group"></i> نوع المحتوى:</div>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="contentTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    نشر ريلز (Reels)
                </button>
                <ul class="dropdown-menu" aria-labelledby="contentTypeBtn" style="min-width:230px;">
                    <li><button type="button" class="dropdown-item active" data-value="reel">نشر ريلز (Reels)</button></li>
                    <li><button type="button" class="dropdown-item" data-value="story_video">نشر قصة فيديو (Story)</button></li>
                    <li><button type="button" class="dropdown-item" data-value="story_photo">نشر قصة مصورة (Story)</button></li>
                </ul>
            </div>
            <input type="hidden" name="media_type" id="media_type_input" value="reel">
        </div>
        <div id="storyNote" class="story-note">
            <i class="fa-solid fa-circle-info"></i>
            Story Video: 3 - 60 ثانية (قد يصل 90) عمودي 9:16.<br>
            Story Photo: صور ≤ 4MB (jpg, png, gif, webp ...).<br>
            القصص: بدون تعليقات أو أغلفة أو هاشتاجات أو أوصاف.
        </div>

        <!-- Story Photo Multi Upload block -->
        <div class="form-group" id="story-photo-multi-block">
            <label for="story_photo_input" class="fw-bold mb-2 d-block"><i class="fa-solid fa-images"></i> رفع Story Photo (يمكن اختيار عدة صور)</label>
            <input type="file" id="story_photo_input" name="story_photo_file[]" accept="image/*" multiple class="form-control mb-2" />
            <small class="form-text text-muted d-block mb-2">
                يمكنك اختيار أكثر من صورة. لكل صورة يوجد حقل وصف وحقل جدولة (اختياري).
            </small>
            <div id="story_preview_gallery" style="margin-top:12px; display:flex; flex-wrap:wrap; gap:12px;"></div>
        </div>

        <!-- الحقل القديم (مخفي للحفاظ على التوافق) -->
        <div id="storyPhotoWrapper" class="story-photo-box" style="display:none;">
            <label class="fw-bold mb-2 d-block">صورة القصة:</label>
            <input type="file" name="story_photo_file" accept="image/*" class="form-control mb-3">
            <label class="form-label fw-bold mb-1">وقت الجدولة (اختياري):</label>
            <input type="datetime-local" name="story_photo_schedule" class="form-control" style="max-width:260px;">
        </div>

        <!-- الصفحات -->
        <div class="mb-4">
            <div class="upload-section-title"><i class="fa-solid fa-users icon"></i> اختر الصفحات:</div>
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <input type="text" id="pageSearch" class="form-control form-control-sm" placeholder="بحث...">
                    <label class="m-0" style="font-size:13px;">
                        <input type="checkbox" id="selectAllPages"> تحديد الكل
                    </label>
                </div>
            </div>
            <div class="pages-wrapper" id="pagesWrapper">
                <?php foreach($pages as $p):
                    $img_src = !empty($p['page_picture']) ? $p['page_picture'] : 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
                    $checked = in_array($p['fb_page_id'],$preselected) ? 'checked' : '';
                ?>
                <label class="page-row">
                    <input type="checkbox" name="fb_page_ids[]" class="page-checkbox" value="<?= htmlspecialchars($p['fb_page_id']) ?>" <?= $checked ?>>
                    <img src="<?= htmlspecialchars($img_src) ?>" alt="صورة"
                         onerror="this.onerror=null;this.src='https://graph.facebook.com/<?= htmlspecialchars($p['fb_page_id']) ?>/picture?type=normal';">
                    <span style="font-weight:600;"><?= htmlspecialchars($p['page_name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="mt-2 d-flex gap-2">
                <button type="button" id="selectAllUpload" class="btn btn-outline-primary btn-sm">تحديد كل الصفحات</button>
                <button type="button" id="unselectAllUpload" class="btn btn-outline-secondary btn-sm" style="display:none;">إلغاء الكل</button>
            </div>
        </div>

        <!-- وصف عام + هاشتاجات -->
        <div class="mb-4" id="globalDescBlock">
            <div class="upload-section-title"><i class="fa-solid fa-pencil icon"></i> وصف عام (يستخدم لو وصف الفيديو فارغ):</div>
            <div class="d-flex gap-2 mb-2" id="hashtagButtons">
                <button type="button" id="btnGenerateAllTags" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-hashtag"></i> توليد هاشتجات اليوم</button>
                <button type="button" id="btnClearTags" class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can"></i> حذف كل الهاشتاجات</button>
            </div>
            <textarea name="description" class="form-control mt-1" placeholder="اكتب وصفاً عاماً..." style="min-height:70px;"></textarea>
            <input type="hidden" name="selected_hashtags" id="selected_hashtags">
            <div class="mt-3" id="hashtagsCloud">
                <?php if(!empty($trending_hashtags)): foreach($trending_hashtags as $ht): ?>
                    <span class="hashtag-btn" data-ht="#<?= htmlspecialchars($ht) ?>">#<?= htmlspecialchars($ht) ?></span>
                <?php endforeach; else: ?>
                    <span class="text-muted" style="font-size:13px;">لا توجد هاشتاجات.</span>
                <?php endif; ?>
            </div>
            <div class="selected-tags-box mt-3" id="selectedTagsBox">
                <span style="background:#ddd;color:#333;">لا يوجد هاشتاجات مختارة حالياً</span>
            </div>
        </div>

        <input type="hidden" name="tz_offset_minutes" id="tz_offset_minutes">
        <input type="hidden" name="tz_name" id="tz_name">

        <!-- الفيديوهات -->
        <div id="videoSection">
            <div class="upload-section-title"><i class="fa-solid fa-film icon"></i> الفيديوهات</div>
            <div id="videoDrop" class="mb-3">
                <div style="font-weight:600;">اضغط أو اسحب لإضافة الفيديوهات</div>
                <div style="font-size:12px;color:#666;">(mp4 / mov / mkv / m4v) | اترك وقت الجدولة فارغ للنشر الفوري</div>
                <input type="file" id="videoInput" name="video_files[]" accept="video/*" multiple style="display:none;">
            </div>
            <div id="videosContainer"></div>
        </div>

        <div class="progress progress-global" id="globalProgress">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="globalBar" style="width:0%">0%</div>
        </div>

        <div class="footer-actions">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-cloud-arrow-up"></i> تنفيذ</button>
            <a href="<?= site_url('reels/list') ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-rotate-ccw"></i> رجوع</a>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- كل الجافاسكريبت كما هي (نفس الكود) -->
<script>
/* المنطقة الزمنية */
document.getElementById('tz_offset_minutes').value = new Date().getTimezoneOffset();
if (Intl && Intl.DateTimeFormat) {
    document.getElementById('tz_name').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/* تعريف عناصر الصفحات */
const pageSearch = document.getElementById('pageSearch');
const selectAllPages = document.getElementById('selectAllPages');

/* اختيار مسبق Scroll لأول صفحة محددة */
(function(){
    const pre = <?= json_encode($preselected, JSON_UNESCAPED_UNICODE) ?>;
    if(pre.length){
        const first = document.querySelector('.page-checkbox[value="'+pre[0]+'"]');
        if(first){
            setTimeout(()=>{ first.scrollIntoView({behavior:'smooth',block:'center'}); },300);
        }
    }
})();

/* Dropdown نوع المحتوى */
const mediaTypeInput   = document.getElementById('media_type_input');
const contentTypeBtn   = document.getElementById('contentTypeBtn');
document.querySelectorAll('.dropdown-menu [data-value]').forEach(item=>{
    item.addEventListener('click',()=>{
        document.querySelectorAll('.dropdown-menu [data-value]').forEach(i=>i.classList.remove('active'));
        item.classList.add('active');
        mediaTypeInput.value = item.getAttribute('data-value');
        contentTypeBtn.textContent = item.textContent.trim();
        toggleMediaTypeUI();
    });
});

/* هاشتاجات */
const selectedHashtagsInput = document.getElementById('selected_hashtags');
const selectedBox = document.getElementById('selectedTagsBox');
let selectedSet = new Set();
function renderSelectedTags(){
    selectedBox.innerHTML='';
    if(selectedSet.size===0){
        const span=document.createElement('span');
        span.style.background='#ddd'; span.style.color='#333';
        span.textContent='لا يوجد هاشتاجات مختارة حالياً';
        selectedBox.appendChild(span);
    } else {
        selectedSet.forEach(tag=>{
            const s=document.createElement('span'); s.textContent=tag;
            const x=document.createElement('i'); x.textContent='×'; x.title='إزالة';
            x.onclick=()=>{selectedSet.delete(tag);updateHidden();};
            s.appendChild(x); selectedBox.appendChild(s);
        });
    }
}
function updateHidden(){
    selectedHashtagsInput.value = Array.from(selectedSet).join(' ');
    renderSelectedTags();
}
document.querySelectorAll('.hashtag-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const tag=btn.dataset.ht.trim();
        if(tag){
            selectedSet.add(tag); updateHidden();
            const ta=document.querySelector('textarea[name="description"]');
            if(ta && !ta.value.includes(tag)){
                if(ta.value && !ta.value.endsWith(' ')) ta.value+=' ';
                ta.value+=tag+' ';
            }
        }
    });
});
document.getElementById('btnGenerateAllTags').addEventListener('click',()=>{
    document.querySelectorAll('.hashtag-btn').forEach(b=>{
        const tag=b.dataset.ht.trim(); if(tag) selectedSet.add(tag);
    });
    updateHidden();
});
document.getElementById('btnClearTags').addEventListener('click',()=>{selectedSet.clear();updateHidden();});
updateHidden();

/* صفحات: بحث + تحديد الكل */
pageSearch.addEventListener('input',()=>{
   const q = pageSearch.value.trim().toLowerCase();
   document.querySelectorAll('#pagesWrapper .page-row').forEach(row=>{
     const txt=row.innerText.toLowerCase();
     row.style.display = txt.indexOf(q)>-1 ? '' : 'none';
   });
});
selectAllPages.addEventListener('change',e=>{
   document.querySelectorAll('#pagesWrapper input[type=checkbox][name="fb_page_ids[]"]').forEach(cb=> cb.checked=e.target.checked);
});

/* زر تحديد الكل / إلغاء الكل (داخل صفحة الرفع) */
const selectAllUpload = document.getElementById('selectAllUpload');
const unselectAllUpload = document.getElementById('unselectAllUpload');
selectAllUpload?.addEventListener('click',()=>{
    document.querySelectorAll('.page-checkbox').forEach(cb=>cb.checked=true);
    selectAllUpload.style.display='none';
    unselectAllUpload.style.display='inline-block';
});
unselectAllUpload?.addEventListener('click',()=>{
    document.querySelectorAll('.page-checkbox').forEach(cb=>cb.checked=false);
    unselectAllUpload.style.display='none';
    selectAllUpload.style.display='inline-block';
});

/* فيديوهات */
const drop = document.getElementById('videoDrop');
const videoInput = document.getElementById('videoInput');
drop.addEventListener('click',()=> videoInput.click());
drop.addEventListener('dragover',e=>{e.preventDefault(); drop.style.background='#eef6ff';});
drop.addEventListener('dragleave',()=> drop.style.background='#fff');
drop.addEventListener('drop',e=>{
    e.preventDefault(); drop.style.background='#fff';
    handleFiles(e.dataTransfer.files);
});
videoInput.addEventListener('change',()=> handleFiles(videoInput.files));

let videoItems=[];
const videosContainer=document.getElementById('videosContainer');

function handleFiles(list){
    Array.from(list).forEach(f=>{
        if(!f.type.startsWith('video/')) return;
        addVideoCard(f);
    });
}

function addVideoCard(file){
    const idx=videoItems.length;
    const card=document.createElement('div'); card.className='video-card'; card.dataset.vindex=idx;

    const remove=document.createElement('button');
    remove.type='button'; remove.className='remove-video'; remove.textContent='حذف الفيديو';
    remove.onclick=()=>{ videoItems[idx]=null; card.remove(); };
    card.appendChild(remove);

    const left=document.createElement('div'); left.className='video-thumb';
    const vid=document.createElement('video'); vid.controls=true; vid.muted=true; vid.preload='metadata';
    vid.src=URL.createObjectURL(file); left.appendChild(vid);

    const coverActions=document.createElement('div'); coverActions.className='cover-actions';
    const genBtn=document.createElement('button'); genBtn.type='button'; genBtn.className='btn btn-sm btn-outline-primary'; genBtn.textContent='توليد أغلفة'; genBtn.onclick=()=> generateCovers(idx);
    const uploadBtn=document.createElement('button'); uploadBtn.type='button'; uploadBtn.className='btn btn-sm btn-outline-secondary'; uploadBtn.textContent='رفع غلاف';
    const coverInput=document.createElement('input'); coverInput.type='file'; coverInput.accept='image/*'; coverInput.style.display='none';
    uploadBtn.onclick=()=> coverInput.click();
    coverInput.onchange=e=>{ if(e.target.files[0]) setManualCover(idx,e.target.files[0]); };
    coverActions.appendChild(genBtn); coverActions.appendChild(uploadBtn); coverActions.appendChild(coverInput);
    const thumbs=document.createElement('div'); thumbs.className='cover-thumbs';
    left.appendChild(coverActions); left.appendChild(thumbs);
    card.appendChild(left);

    const right=document.createElement('div'); right.style.flex='1';

    const desc=document.createElement('textarea');
    desc.className='form-control mb-2 video-desc';
    desc.placeholder='وصف خاص (اختياري)';
    desc.name='descriptions['+idx+']';

    const scheduleLabel=document.createElement('label');
    scheduleLabel.className='form-label fw-bold mb-1';
    scheduleLabel.textContent='وقت الجدولة (اتركه فارغ للنشر الفوري):';

    const scheduleInput=document.createElement('input');
    scheduleInput.type='datetime-local';
    scheduleInput.className='form-control mb-3';
    scheduleInput.name='schedule_times['+idx+']';

    const commentBlock=document.createElement('div');
    commentBlock.className='comment-block comments-wrapper';
    const cHeader=document.createElement('div');
    cHeader.style.fontSize='13px'; cHeader.style.fontWeight='600';
    cHeader.textContent='تعليقات (اختياري – ضع وقت للتعليق المجدول)';
    const addCommentBtn=document.createElement('button');
    addCommentBtn.type='button';
    addCommentBtn.className='btn btn-sm btn-outline-success mb-2';
    addCommentBtn.textContent='إضافة تعليق';
    const commentsWrap=document.createElement('div');
    addCommentBtn.onclick=()=> addCommentRow(idx,commentsWrap);
    commentBlock.appendChild(cHeader);
    commentBlock.appendChild(addCommentBtn);
    commentBlock.appendChild(commentsWrap);

    right.appendChild(desc);
    right.appendChild(scheduleLabel);
    right.appendChild(scheduleInput);
    right.appendChild(commentBlock);

    card.appendChild(right);
    videosContainer.appendChild(card);

    const item={file,cover:null,frames:[],selectedFrame:null,elements:{card,vid,thumbs,coverActions,desc,commentBlock}};
    videoItems.push(item);

    applyModeToCard(item, mediaTypeInput.value);
}

function addCommentRow(vIndex,wrap){
    const row=document.createElement('div');
    row.className='comment-row';
    const ta=document.createElement('textarea');
    ta.name='comments['+vIndex+']['+wrap.children.length+'][text]';
    ta.placeholder='التعليق';
    const dt=document.createElement('input');
    dt.type='datetime-local';
    dt.name='comments['+vIndex+']['+wrap.children.length+'][schedule]';
    const rm=document.createElement('button');
    rm.type='button';
    rm.className='remove-comment';
    rm.textContent='حذف';
    rm.onclick=()=> row.remove();
    row.appendChild(ta); row.appendChild(dt); row.appendChild(rm);
    wrap.appendChild(row);
}

/* أغلفة (كما كانت) */
function generateCovers(idx){
    const item=videoItems[idx]; if(!item) return;
    const video=item.elements.vid;
    const thumbs=item.elements.thumbs;
    thumbs.innerHTML='<div style="font-size:11px;color:#555;">جاري استخراج الإطارات...</div>';
    if(video.readyState<1){
        video.addEventListener('loadedmetadata',()=> extract(),{once:true}); video.load();
    } else extract();
    function extract(){
        const duration=video.duration||0;
        if(duration===0){ thumbs.innerHTML='<div style="font-size:11px;">تعذر قراءة مدة الفيديو</div>'; return; }
        const points=[0.12,0.32,0.52,0.72,0.88];
        item.frames=[]; thumbs.innerHTML='';
        const canvas=document.createElement('canvas'); canvas.width=420; canvas.height=740;
        const ctx=canvas.getContext('2d'); let current=0;
        const capture=()=>{
            if(current>=points.length){ render(); return; }
            video.currentTime = duration*points[current]; video.pause();
            video.addEventListener('seeked', function handler(){
                video.removeEventListener('seeked',handler);
                ctx.drawImage(video,0,0,canvas.width,canvas.height);
                canvas.toBlob(blob=>{
                    const url=URL.createObjectURL(blob);
                    item.frames.push({blob,url});
                    current++; capture();
                },'image/png');
            });
        };
        capture();
        function render(){
            thumbs.innerHTML='';
            item.frames.forEach((fr,i)=>{
                const d=document.createElement('div'); d.className='thumb';
                const im=document.createElement('img'); im.src=fr.url; d.appendChild(im);
                d.onclick=()=>{
                    item.cover={type:'captured',blob:fr.blob};
                    thumbs.querySelectorAll('.thumb').forEach(t=>t.classList.remove('selected'));
                    d.classList.add('selected');
                };
                thumbs.appendChild(d);
            });
        }
    }
}
function setManualCover(idx,file){
    const item=videoItems[idx]; if(!item) return;
    item.cover={type:'uploaded',file};
    const thumbs=item.elements.thumbs;
    thumbs.innerHTML='';
    const d=document.createElement('div'); d.className='thumb selected';
    const img=document.createElement('img'); img.src=URL.createObjectURL(file);
    d.appendChild(img); thumbs.appendChild(d);
}

/* وضع الكارت حسب النوع */
function applyModeToCard(item,mode){
    if(mode==='story_video'){
        item.elements.coverActions.style.display='none';
        item.elements.thumbs.style.display='none';
        item.elements.desc.style.display='none';
        item.elements.commentBlock.style.display='none';
    } else if(mode==='reel'){
        item.elements.coverActions.style.display='flex';
        item.elements.thumbs.style.display='flex';
        item.elements.desc.style.display='block';
        item.elements.commentBlock.style.display='block';
    } else {
        // story_photo -> لا يظهر الفيديوهات
    }
}

/* Story Photo Multi Uploader - معاينة وتحكم */
(function(){
  const storyInput = document.getElementById('story_photo_input');
  const gallery = document.getElementById('story_preview_gallery');
  if(!storyInput || !gallery) return;

  function createPreview(file, idx){
    const url = URL.createObjectURL(file);
    const wrapper = document.createElement('div');
    wrapper.className = 'story-item';
    wrapper.dataset.filename = file.name;
    wrapper.dataset.filesize = String(file.size);

    const img = document.createElement('img');
    img.src = url;
    img.alt = file.name;
    wrapper.appendChild(img);

    const cap = document.createElement('input');
    cap.type = 'text';
    cap.name = 'story_photo_captions[]';
    cap.placeholder = 'وصف (اختياري)';
    cap.style = 'width:100%;padding:6px;margin-bottom:6px;border:1px solid #ddd;border-radius:4px';
    wrapper.appendChild(cap);

    const sch = document.createElement('input');
    sch.type = 'datetime-local';
    sch.name = 'story_photo_schedule[]';
    sch.style = 'width:100%;padding:6px;margin-bottom:6px;border:1px solid #ddd;border-radius:4px';
    wrapper.appendChild(sch);

    const rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'btn btn-sm btn-danger';
    rm.style = 'width:100%;';
    rm.innerText = 'إزالة';
    rm.addEventListener('click', function(){
      wrapper.remove();
    });
    wrapper.appendChild(rm);

    return wrapper;
  }

  storyInput.addEventListener('change', function(e){
    const files = Array.from(storyInput.files || []);
    gallery.innerHTML = '';
    files.forEach((file, idx) => {
      const item = createPreview(file, idx);
      gallery.appendChild(item);
    });
  });

  // Build FormData for story_photo using displayed previews (preserve caption/schedule order)
  window.buildStoryPhotoFormData = function(form){
    const fd = new FormData();
    // append non-file fields (except file input)
    const elements = Array.from(form.elements);
    elements.forEach(el=>{
        if(!el.name) return;
        // skip file input
        if(el === storyInput) return;
        // skip per-preview caption/schedule inputs here to avoid duplication; we'll add them in order with files
        if(el.name === 'story_photo_captions[]' || el.name === 'story_photo_schedule[]') return;
        // checkbox handling
        if(el.type === 'checkbox'){
            if(!el.checked) return;
            fd.append(el.name, el.value);
            return;
        }
        // for multiple selects, etc. FormData handles values; for inputs with same name (non-story per-file) append each
        if(el.name && el.multiple && el.options){
            Array.from(el.options).forEach(opt => { if(opt.selected) fd.append(el.name, opt.value); });
            return;
        }
        fd.append(el.name, el.value ?? '');
    });

    // Now append files + corresponding captions/schedules in the order of previews
    const previews = Array.from(gallery.children);
    const originalFiles = Array.from(storyInput.files || []);
    previews.forEach((wrapper, idx)=>{
      const fname = wrapper.dataset.filename;
      const fsize = parseInt(wrapper.dataset.filesize || '0',10);
      // find matching file by name+size
      let match = originalFiles.find(f => f.name === fname && f.size == fsize);
      if(!match){
        // fallback: find by name only
        match = originalFiles.find(f => f.name === fname);
      }
      if(!match) return; // cannot find, skip

      fd.append('story_photo_file[]', match, match.name);

      // append caption & schedule in same relative order
      const capEl = wrapper.querySelector('input[name="story_photo_captions[]"]');
      const schEl = wrapper.querySelector('input[name="story_photo_schedule[]"]');
      fd.append('story_photo_captions[]', capEl?capEl.value:'');
      fd.append('story_photo_schedule[]', schEl?schEl.value:'');
    });

    return fd;
  };

})();

/* عناصر واجهة النوع */
const storyPhotoWrapper=document.getElementById('storyPhotoWrapper');
const storyPhotoMultiBlock=document.getElementById('story-photo-multi-block');
const videoSection=document.getElementById('videoSection');
const storyNote=document.getElementById('storyNote');
const globalDescBlock=document.getElementById('globalDescBlock');
const hashtagButtons=document.getElementById('hashtagButtons');
const hashtagsCloud=document.getElementById('hashtagsCloud');
const selectedTagsBox=document.getElementById('selectedTagsBox');

function toggleMediaTypeUI(){
    const val=mediaTypeInput.value;
    const isStoryPhoto = val==='story_photo';
    const isStoryVideo = val==='story_video';
    const isReel = val==='reel';

    // show/hide multi uploader
    storyPhotoMultiBlock.style.display = isStoryPhoto ? 'block' : 'none';
    // keep legacy hidden (we preserve markup for backward compatibility)
    storyPhotoWrapper.style.display = 'none';

    videoSection.style.display = isStoryPhoto ? 'none':'block';
    storyNote.style.display = (isStoryPhoto||isStoryVideo)?'block':'none';

    if(isStoryPhoto || isStoryVideo){
        globalDescBlock.style.display='none';
        hashtagButtons.style.display='none';
        hashtagsCloud.style.display='none';
        selectedTagsBox.style.display='none';
    } else {
        globalDescBlock.style.display='block';
        hashtagButtons.style.display='';
        hashtagsCloud.style.display='';
        selectedTagsBox.style.display='flex';
    }

    videoItems.forEach(it=> applyModeToCard(it,val));
}
toggleMediaTypeUI();

/* الإرسال */
document.getElementById('reelsForm').addEventListener('submit',function(e){
    e.preventDefault();
    const mediaType = mediaTypeInput.value;
    if(!this.querySelector('input[name="fb_page_ids[]"]:checked')){
        alert('اختر صفحة واحدة على الأقل'); return;
    }
    if(mediaType==='story_photo'){
        // Use custom FormData builder that only includes previews' files and their captions/schedules
        const storyInput = document.getElementById('story_photo_input');
        const gallery = document.getElementById('story_preview_gallery');
        if(!storyInput || !storyInput.files || storyInput.files.length===0){
            alert('اختر صورة للستوري'); return;
        }
        if(gallery.children.length === 0){
            alert('لا توجد صور محددة (أزلت كل المعاينات)'); return;
        }
        const fd = window.buildStoryPhotoFormData(this);
        uploadWithProgress(fd,this.action);
        return;
    }

    if(mediaType==='story_video' || mediaType==='reel'){
        if(videoItems.filter(v=>v!==null).length===0){
            alert('أضف فيديو واحد على الأقل'); return;
        }
        const fd=new FormData();
        new FormData(this).forEach((val,key)=>{
            if(key.startsWith('video_files[')) return;
            fd.append(key,val);
        });
        let newIdx=0;
        videoItems.forEach((item,oldIdx)=>{
            if(!item) return;
            fd.append('video_files[]', item.file, item.file.name);
            const sched=document.querySelector('input[name="schedule_times['+oldIdx+']"]');
            fd.append('schedule_times['+newIdx+']', sched?sched.value:'');
            if(mediaType==='reel'){
                const desc=document.querySelector('textarea[name="descriptions['+oldIdx+']"]');
                fd.append('descriptions['+newIdx+']', desc?desc.value:'');
                // comments
                const rows = document.querySelectorAll('#videosContainer .video-card[data-vindex="'+oldIdx+'"] .comment-row');
                rows.forEach((row, cidx)=>{
                    const textEl = row.querySelector('textarea');
                    const schedEl = row.querySelector('input[type="datetime-local"]');
                    if(textEl && textEl.value.trim()!==''){
                        fd.append('comments['+newIdx+']['+cidx+'][text]', textEl.value);
                        fd.append('comments['+newIdx+']['+cidx+'][schedule]', schedEl?schedEl.value:'');
                    }
                });
                if(item.cover){
                    if(item.cover.type==='captured'){
                        fd.append('cover_captured['+newIdx+']', item.cover.blob, 'captured_'+newIdx+'.png');
                    } else if(item.cover.type==='uploaded'){
                        fd.append('cover_uploaded['+newIdx+']', item.cover.file, item.cover.file.name);
                    }
                }
            }
            newIdx++;
        });
        uploadWithProgress(fd,this.action);
    }
});

function basicSubmit(form){
    const fd=new FormData(form);
    uploadWithProgress(fd,form.action);
}
function uploadWithProgress(fd,actionUrl){
    const global=document.getElementById('globalProgress');
    const bar=document.getElementById('globalBar');
    global.style.display='block'; bar.style.width='0%'; bar.textContent='0%';
    const xhr=new XMLHttpRequest();
    xhr.open('POST',actionUrl,true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.upload.onprogress=e=>{
        if(e.lengthComputable){
            const p=Math.round((e.loaded/e.total)*100);
            bar.style.width=p+'%'; bar.textContent=p+'%';
        }
    };
    xhr.onreadystatechange=()=>{
        if(xhr.readyState===4){
            if(xhr.status===200){
                bar.style.width='100%'; bar.textContent='تم';
                // if JSON response includes messages, we could show them; for now redirect to list
                setTimeout(()=>window.location.href="<?= site_url('reels/list') ?>",800);
            } else {
                try {
                    const json = JSON.parse(xhr.responseText||'{}');
                    alert(json.msg || ('خطأ أثناء الإرسال (HTTP '+xhr.status+')'));
                } catch(e){
                    alert('خطأ أثناء الإرسال (HTTP '+xhr.status+')');
                }
            }
        }
    };
    xhr.send(fd);
}

/* إخفاء الرسائل */
setTimeout(()=>{
  document.querySelectorAll('.auto-hide').forEach(a=>{
     a.style.opacity=0;
     setTimeout(()=>a.remove(),700);
  });
},3500);
</script>
</body>
</html>
