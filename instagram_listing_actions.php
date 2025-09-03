<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة محتوى إنستجرام</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body{background:#f1f4f8;font-family:Tahoma,Arial;}
h1{font-size:24px;font-weight:700;color:#0d4e96;margin-bottom:18px;}
.table-wrap{background:#fff;border:1px solid #d7e3ef;border-radius:18px;padding:0;overflow:hidden;}
.status-pill{padding:4px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.status-published{background:#d9f7e7;color:#0d6b3b;}
.status-failed{background:#ffd9d9;color:#a30000;}
.status-pending{background:#fff8d9;color:#936d00;}
.status-uploading{background:#e2efff;color:#115d9e;}
.media-kind-badge{font-size:10px;padding:4px 7px;border-radius:6px;background:#eef4fb;color:#215178;font-weight:600;}
.preview-img{width:60px;height:60px;object-fit:cover;border:1px solid #cdd7e0;border-radius:10px;}
.preview-video{width:80px;height:60px;object-fit:cover;border:1px solid #cdd7e0;border-radius:10px;}
.filter-box{background:#fff;border:1px solid #d7e3ef;border-radius:18px;padding:16px;margin-bottom:18px;}
.bulk-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;}
.bulk-bar .btn{border-radius:18px;font-size:13px;font-weight:600;}
.small-txt{font-size:11px;color:#6a7e90;}
.badge-acc{background:#eef5fb;color:#19507f;font-size:11px;font-weight:600;border-radius:16px;padding:4px 10px;}
thead th{vertical-align:middle;}
.copyDesc{font-size:11px;}
</style>
</head>
<body>
<div class="container py-4" style="max-width:1700px;">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
      <h1 class="m-0">إدارة محتوى إنستجرام</h1>
      <div class="d-flex gap-2">
          <a href="<?= site_url('instagram/upload') ?>" class="btn btn-primary btn-sm">+ نشر جديد</a>
          <a href="<?= site_url('reels/pages') ?>" class="btn btn-outline-secondary btn-sm">الصفحات</a>
      </div>
  </div>

  <?php if($this->session->flashdata('ig_error')): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('ig_error')) ?></div>
  <?php elseif($this->session->flashdata('ig_success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('ig_success')) ?></div>
  <?php endif; ?>

  <div class="bulk-bar">
    <button class="btn btn-outline-danger btn-sm" id="bulkDeleteSoft">حذف (مؤقت)</button>
    <button class="btn btn-danger btn-sm" id="bulkDeleteHard">حذف نهائي</button>
    <button class="btn btn-outline-warning btn-sm" id="bulkRetry">إعادة محاولة للفاشل</button>
    <button class="btn btn-outline-success btn-sm" id="bulkExport">تصدير المحدد CSV</button>
    <button class="btn btn-outline-secondary btn-sm" id="selectAll">تحديد / إلغاء الكل</button>
  </div>

  <!-- فلاتر -->
  <div class="filter-box">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label">الحساب</label>
        <select name="ig_user_id" class="form-select">
          <option value="">الكل</option>
          <?php foreach($accounts as $acc): ?>
            <option value="<?= htmlspecialchars($acc['ig_user_id']) ?>" <?= (!empty($filter['ig_user_id']) && $filter['ig_user_id']==$acc['ig_user_id'])?'selected':'' ?>>
              @<?= htmlspecialchars($acc['ig_username'] ?: $acc['ig_user_id']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">الحالة</label>
        <select name="status" class="form-select">
          <option value="">الكل</option>
          <?php foreach(['published'=>'نُشر','pending'=>'قيد الإنتظار','failed'=>'فشل','uploading'=>'جاري'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (!empty($filter['status']) && $filter['status']==$k)?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">النوع</label>
        <select name="media_kind" class="form-select">
          <option value="">الكل</option>
          <option value="ig_reel" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_reel')?'selected':'' ?>>ريل</option>
          <option value="ig_story_image" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_story_image')?'selected':'' ?>>ستوري صورة</option>
          <option value="ig_story_video" <?= (!empty($filter['media_kind']) && $filter['media_kind']=='ig_story_video')?'selected':'' ?>>ستوري فيديو</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">من تاريخ</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter['date_from'] ?? '') ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">إلى تاريخ</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter['date_to'] ?? '') ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">بحث</label>
        <input type="text" name="q" value="<?= htmlspecialchars($filter['q'] ?? '') ?>" class="form-control" placeholder="وصف / هاشتاج">
      </div>
      <div class="col-md-2">
        <label class="form-label">ترتيب</label>
        <select name="order" class="form-select">
          <?php foreach(['id'=>'ID','created_at'=>'تاريخ الإنشاء','published_at'=>'تاريخ النشر','status'=>'الحالة','media_kind'=>'النوع'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($order==$k)?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label">اتجاه</label>
        <select name="dir" class="form-select">
          <option value="DESC" <?= ($dir==='DESC')?'selected':'' ?>>تنازلي</option>
          <option value="ASC" <?= ($dir==='ASC')?'selected':'' ?>>تصاعدي</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100">فلترة</button>
        <a href="<?= site_url('instagram/listing') ?>" class="btn btn-outline-secondary w-100">إعادة</a>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <form id="tableForm">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th><input type="checkbox" id="chkAll"></th>
          <th>#</th>
          <th>معاينة</th>
          <th>النوع</th>
          <th>الوصف</th>
          <th>الحالة</th>
          <th>التعليق الأول</th>
          <th>الحساب</th>
          <th>Media ID</th>
            <th>Creation ID</th>
          <th>الملف</th>
          <th>إنشاء</th>
          <th>نشر</th>
          <th>أدوات</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($items)): ?>
        <tr><td colspan="14" class="text-center text-muted py-4">لا نتائج</td></tr>
      <?php else: foreach($items as $row):
        $statusClass='status-'.$row['status'];
        $kindLabel=($row['media_kind']=='ig_reel'?'ريل':($row['media_kind']=='ig_story_image'?'ستوري صورة':($row['media_kind']=='ig_story_video'?'ستوري فيديو':$row['media_kind'])));
      ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
          <td><?= $row['id'] ?></td>
          <td>
            <?php if($row['file_type']=='image'): ?>
              <img src="<?= base_url($row['file_path']) ?>" class="preview-img">
            <?php else: ?>
              <video class="preview-video" muted><source src="<?= base_url($row['file_path']) ?>"></video>
            <?php endif; ?>
          </td>
          <td><span class="media-kind-badge"><?= $kindLabel ?></span></td>
          <td style="max-width:210px;">
            <?php if($row['description']): ?>
              <div class="small text-break"><?= nl2br(htmlspecialchars(mb_strimwidth($row['description'],0,230,'…'))) ?></div>
              <button type="button" class="btn btn-sm btn-outline-secondary mt-1 copyDesc" data-desc="<?= htmlspecialchars($row['description']) ?>">نسخ</button>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="status-pill <?= $statusClass ?>"><?= $row['status']=='published'?'نُشر':($row['status']=='failed'?'فشل':($row['status']=='pending'?'قيد الإنتظار':'جاري')) ?></span>
            <?php if($row['status']=='failed' && $row['last_error']): ?>
              <div class="small-txt text-danger" title="<?= htmlspecialchars($row['last_error']) ?>"><?= htmlspecialchars(mb_strimwidth($row['last_error'],0,40,'…')) ?></div>
            <?php endif; ?>
          </td>
          <td class="small">
            <?= $row['first_comment_status'] ? htmlspecialchars($row['first_comment_status']) : '<span class="text-muted">—</span>' ?>
            <?php if(!empty($row['first_comment_error'])): ?>
              <div class="small-txt text-danger" title="<?= htmlspecialchars($row['first_comment_error']) ?>">خطأ</div>
            <?php endif; ?>
          </td>
          <td><span class="badge-acc"><?= htmlspecialchars($row['ig_user_id']) ?></span></td>
          <td class="small text-break" style="max-width:140px;"><?= $row['media_id'] ?: '<span class="text-muted">—</span>' ?></td>
          <td class="small text-break" style="max-width:140px;"><?= $row['creation_id'] ?: '<span class="text-muted">—</span>' ?></td>
          <td class="small">
            <div><?= htmlspecialchars($row['file_name']) ?></div>
            <div class="small-txt"><?= @round(@filesize(FCPATH.$row['file_path'])/1024/1024,2) ?> MB</div>
          </td>
          <td class="small"><?= $row['created_at'] ?></td>
          <td class="small"><?= $row['published_at'] ?: '<span class="text-muted">—</span>' ?></td>
          <td class="small">
            <div class="d-flex flex-column gap-1">
              <a href="<?= site_url('instagram/download/'.$row['id']) ?>" class="btn btn-sm btn-outline-primary">تنزيل</a>
              <a href="<?= site_url('instagram/delete/'.$row['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف؟')">حذف</a>
              <?php if($row['status']=='failed'): ?>
                <button type="button" class="btn btn-sm btn-warning retrySingle" data-id="<?= $row['id'] ?>">إعادة</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    </form>
  </div>

  <?php if($pages>1): ?>
    <nav class="mt-3 d-flex flex-wrap gap-2">
      <?php for($i=1;$i<=$pages;$i++):
        $qs=$_GET; $qs['page']=$i; $url=site_url('instagram/listing').'?'.http_build_query($qs);
      ?>
        <a class="btn btn-sm <?= ($i==$page)?'btn-primary':'btn-outline-primary' ?>" href="<?= $url ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<script>
const chkAll=document.getElementById('chkAll');
chkAll?.addEventListener('change',()=>{
  document.querySelectorAll('input[name="ids[]"]').forEach(c=> c.checked=chkAll.checked);
});
document.getElementById('selectAll')?.addEventListener('click',()=>{
  const boxes=[...document.querySelectorAll('input[name="ids[]"]')];
  const allSel=boxes.every(b=>b.checked);
  boxes.forEach(b=>b.checked=!allSel);
  if(chkAll) chkAll.checked=!allSel;
});
function bulk(action,confirmMsg=null){
  if(confirmMsg && !confirm(confirmMsg)) return;
  const form=document.getElementById('tableForm');
  const fd=new FormData(form);
  fd.append('action',action);
  fetch('<?= site_url('instagram/bulk_action') ?>',{
    method:'POST',
    headers:{'X-Requested-With':'XMLHttpRequest'},
    body:fd
  }).then(r=>r.json()).then(j=>{
    if(j.status==='ok'){
      if(j.csv_url) window.location=j.csv_url;
      else location.reload();
    } else {
      alert(j.message||'خطأ');
    }
  }).catch(()=>alert('خطأ شبكة'));
}
document.getElementById('bulkDeleteSoft')?.addEventListener('click',()=>bulk('delete_soft','تأكيد حذف مؤقت؟'));
document.getElementById('bulkDeleteHard')?.addEventListener('click',()=>bulk('delete_hard','تحذير: حذف نهائي!'));
document.getElementById('bulkRetry')?.addEventListener('click',()=>bulk('retry'));
document.getElementById('bulkExport')?.addEventListener('click',()=>bulk('export_csv'));

document.querySelectorAll('.retrySingle').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const fd=new FormData(); fd.append('action','retry'); fd.append('ids[]',btn.dataset.id);
    fetch('<?= site_url('instagram/bulk_action') ?>',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
      .then(r=>r.json()).then(()=>location.reload());
  });
});
document.querySelectorAll('.copyDesc').forEach(btn=>{
  btn.addEventListener('click',()=>{
    navigator.clipboard.writeText(btn.dataset.desc).then(()=>{
      btn.textContent='تم النسخ';
      setTimeout(()=>btn.textContent='نسخ',1200);
    });
  });
});
</script>
</body>
</html>