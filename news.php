<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$canWrite=can_write_news();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';

    if(in_array($action,['create','edit','delete'],true) && !$canWrite){
        http_response_code(403);
        exit('Keine Berechtigung zum Schreiben von Neuigkeiten.');
    }

    if($action==='create'){
        $title=trim($_POST['title']??'');
        $body=news_sanitize_html((string)($_POST['body']??''));
        if(mb_strlen($title)<3 || mb_strlen($title)>120 || (news_body_text_length($body)<3 && !str_contains($body,'<img '))){
            flash('danger','Bitte Titel und Nachricht vollständig ausfüllen.');
            redirect('/news.php');
        }
        $insert=$pdo->prepare("INSERT INTO news_posts(title,body,author_id,published_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) RETURNING id");
        $insert->execute([$title,$body,$u['id']]);
        $postId=(int)$insert->fetchColumn();

        if(!empty($_POST['send_push'])){
            $pushText='Neue Neuigkeit: '.$title;
            if(mb_strlen($pushText)>180) $pushText=mb_substr($pushText,0,177).'…';

            $pushResult=send_push_to_all_active_users(
                $pdo,
                'Strahlemännkes · Neuigkeit',
                $pushText,
                '/news.php#news-'.$postId
            );

            if(!$pushResult['configured']){
                flash('warning','Neuigkeit wurde veröffentlicht, Push konnte aber nicht gesendet werden: '.$pushResult['error']);
            }elseif($pushResult['error']){
                flash('warning','Neuigkeit wurde veröffentlicht, beim Push-Versand trat ein Fehler auf: '.$pushResult['error']);
            }elseif($pushResult['sent']<1){
                flash('warning','Neuigkeit wurde veröffentlicht. Es ist aktuell kein Gerät für Push registriert.');
            }else{
                $message='Neuigkeit veröffentlicht. Push: '.$pushResult['sent'].' von '.$pushResult['queued'].' Gerät(en) erfolgreich zugestellt.';
                if($pushResult['failed']>0) $message.=' '.$pushResult['failed'].' Zustellung(en) fehlgeschlagen.';
                flash('success',$message);
            }
        }else{
            flash('success','Neuigkeit veröffentlicht.');
        }
        redirect('/news.php#news-'.$postId);
    }

    if(in_array($action,['edit','delete'],true)){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("SELECT id,author_id FROM news_posts WHERE id=?");
        $st->execute([$id]);
        $post=$st->fetch();
        if(!$post){
            flash('danger','Beitrag wurde nicht gefunden.');
            redirect('/news.php');
        }
        $mayModify=has_role('admin') || (int)$post['author_id']===(int)$u['id'];
        if(!$mayModify){
            http_response_code(403);
            exit('Du darfst nur eigene Beiträge bearbeiten.');
        }

        if($action==='delete'){
            $pdo->prepare("DELETE FROM news_posts WHERE id=?")->execute([$id]);
            flash('success','Neuigkeit gelöscht.');
            redirect('/news.php');
        }

        $title=trim($_POST['title']??'');
        $body=news_sanitize_html((string)($_POST['body']??''));
        if(mb_strlen($title)<3 || mb_strlen($title)>120 || (news_body_text_length($body)<3 && !str_contains($body,'<img '))){
            flash('danger','Bitte Titel und Nachricht vollständig ausfüllen.');
            redirect('/news.php');
        }
        $pdo->prepare("UPDATE news_posts SET title=?,body=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$title,$body,$id]);
        flash('success','Neuigkeit aktualisiert.');
        redirect('/news.php');
    }
}

$posts=$pdo->query("
    SELECT n.*,u.first_name,u.last_name,u.role,u.is_treasurer,u.is_leader,u.is_secretary
    FROM news_posts n
    JOIN users u ON u.id=n.author_id
    ORDER BY n.published_at DESC,n.id DESC
")->fetchAll();

$pageTitle='Neuigkeiten';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="eyebrow">Intern</div>
      <h1 class="section-title h2 mb-1">Neuigkeiten</h1>
      <p class="text-secondary mb-0">Mitteilungen und Informationen für den Zug.</p>
    </div>
    <?php if($canWrite): ?>
      <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#newsModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i> Neuigkeit schreiben</button>
    <?php endif;?>
  </div>

  <?php if(!$posts): ?>
    <div class="panel p-5 text-center">
      <div class="fs-1 mb-3">📣</div>
      <h2 class="h5 fw-bold">Noch keine Neuigkeiten</h2>
      <p class="text-secondary mb-0">Sobald eine Mitteilung veröffentlicht wird, erscheint sie hier für alle Mitglieder.</p>
    </div>
  <?php else: ?>
    <div class="news-list">
      <?php foreach($posts as $post):
        $isOwn=(int)$post['author_id']===(int)$u['id'];
        $mayModify=has_role('admin') || $isOwn;
        $office=[];
        if($post['role']==='spiess') $office[]='Spieß';
        if((int)$post['is_leader']) $office[]='Zugführer';
        if((int)$post['is_treasurer']) $office[]='Kassierer';
        if((int)$post['is_secretary']) $office[]='Schriftführer';
        if($post['role']==='admin') $office[]='Admin';
      ?>
      <article class="panel news-post p-4 mb-3" id="news-<?=(int)$post['id']?>">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div class="min-w-0">
            <h2 class="news-title h4 mb-2"><?=e($post['title'])?></h2>
            <div class="small text-secondary">
              <?=e($post['first_name'].' '.$post['last_name'])?>
              <?php if($office): ?> · <?=e(implode(' · ',array_unique($office)))?><?php endif;?>
              · <?=e(date('d.m.Y H:i',strtotime($post['published_at'])))?> Uhr
              <?php if($post['updated_at']!==$post['published_at']): ?> · bearbeitet<?php endif;?>
            </div>
          </div>
          <?php if($mayModify): ?>
            <div class="d-flex flex-wrap gap-1">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newsModal"
                data-mode="edit" data-id="<?=(int)$post['id']?>"
                data-title="<?=e($post['title'])?>">Bearbeiten</button>
              <form method="post" onsubmit="return confirm('Diese Neuigkeit wirklich löschen?');">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=(int)$post['id']?>">
                <button class="btn btn-sm btn-outline-danger">Löschen</button>
              </form>
            </div>
          <?php endif;?>
        </div>
        <div class="news-body"><?=news_body_html($post['body'])?></div>
      </article>
      <?php endforeach;?>
    </div>
  <?php endif;?>
</section></div></div>

<?php if($canWrite): ?>
<div class="modal fade" id="newsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="newsModalTitle">Neuigkeit schreiben</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" id="newsAction" value="create">
        <input type="hidden" name="id" id="newsId">
        <div class="mb-3"><label class="form-label">Überschrift</label><input class="form-control" id="newsTitle" name="title" maxlength="120" required></div>
        <div>
          <label class="form-label">Text</label>
          <div class="wysiwyg-shell">
            <div class="wysiwyg-toolbar" role="toolbar" aria-label="Text formatieren">
              <button type="button" class="wysiwyg-btn" data-cmd="bold" title="Fett"><i class="bi bi-type-bold"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="italic" title="Kursiv"><i class="bi bi-type-italic"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="underline" title="Unterstrichen"><i class="bi bi-type-underline"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="strikeThrough" title="Durchgestrichen"><i class="bi bi-type-strikethrough"></i></button>
              <span class="wysiwyg-sep"></span>
              <button type="button" class="wysiwyg-btn wysiwyg-text-btn" data-block="p" title="Absatz">Text</button>
              <button type="button" class="wysiwyg-btn wysiwyg-text-btn" data-block="h2" title="Große Überschrift">H2</button>
              <button type="button" class="wysiwyg-btn wysiwyg-text-btn" data-block="h3" title="Kleine Überschrift">H3</button>
              <span class="wysiwyg-sep"></span>
              <button type="button" class="wysiwyg-btn" data-cmd="insertUnorderedList" title="Aufzählung"><i class="bi bi-list-ul"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="insertOrderedList" title="Nummerierte Liste"><i class="bi bi-list-ol"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="formatBlock" data-value="blockquote" title="Zitat"><i class="bi bi-blockquote-left"></i></button>
              <span class="wysiwyg-sep"></span>
              <button type="button" class="wysiwyg-btn" data-cmd="justifyLeft" title="Linksbündig"><i class="bi bi-text-left"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="justifyCenter" title="Zentriert"><i class="bi bi-text-center"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="justifyRight" title="Rechtsbündig"><i class="bi bi-text-right"></i></button>
              <span class="wysiwyg-sep"></span>
              <button type="button" class="wysiwyg-btn" id="newsImageBtn" title="Bild hochladen"><i class="bi bi-image"></i></button>
              <input type="file" id="newsImageInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
              <button type="button" class="wysiwyg-btn" id="newsLinkBtn" title="Link einfügen"><i class="bi bi-link-45deg"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="unlink" title="Link entfernen"><i class="bi bi-link"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="removeFormat" title="Formatierung entfernen"><i class="bi bi-eraser"></i></button>
              <span class="wysiwyg-sep"></span>
              <button type="button" class="wysiwyg-btn" data-cmd="undo" title="Rückgängig"><i class="bi bi-arrow-counterclockwise"></i></button>
              <button type="button" class="wysiwyg-btn" data-cmd="redo" title="Wiederholen"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div id="newsEditor" class="wysiwyg-editor" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Neuigkeit schreiben …"></div>
          </div>
          <textarea class="visually-hidden" id="newsBody" name="body"></textarea>
          <div class="form-text">Formatierungen und hochgeladene Bilder werden gespeichert. JPG, PNG, WebP und GIF bis 8 MB.</div>
          <div class="small mt-2" id="newsUploadStatus" aria-live="polite"></div>
        </div>
        <div class="form-check mt-4" id="newsPushWrap">
          <input class="form-check-input" type="checkbox" name="send_push" value="1" id="newsSendPush">
          <label class="form-check-label fw-bold" for="newsSendPush">Als Push-Benachrichtigung an alle senden</label>
          <div class="form-text">Optional. Der Beitrag wird immer gespeichert; Push wird nur an Geräte mit aktivierten Benachrichtigungen geschickt.</div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-brand" id="newsSubmitButton">Veröffentlichen</button></div>
    </form>
  </div>
</div>
<script>
const newsPosts = <?=json_encode(array_reduce($posts,function($carry,$post){
  $carry[(string)$post['id']] = [
    'title'=>$post['title'],
    'body'=>news_body_html($post['body']),
  ];
  return $carry;
},[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

const newsModal=document.getElementById('newsModal');
const newsForm=newsModal.querySelector('form');
const editor=document.getElementById('newsEditor');
const hiddenBody=document.getElementById('newsBody');

function focusEditor(){
  editor.focus({preventScroll:true});
}

function runEditorCommand(command,value=null){
  focusEditor();
  document.execCommand(command,false,value);
}

document.querySelectorAll('.wysiwyg-btn[data-cmd]').forEach(btn=>{
  btn.addEventListener('mousedown',e=>e.preventDefault());
  btn.addEventListener('click',()=>{
    const command=btn.dataset.cmd;
    const value=btn.dataset.value || null;
    runEditorCommand(command,value);
  });
});

document.querySelectorAll('.wysiwyg-btn[data-block]').forEach(btn=>{
  btn.addEventListener('mousedown',e=>e.preventDefault());
  btn.addEventListener('click',()=>runEditorCommand('formatBlock',btn.dataset.block));
});

document.getElementById('newsLinkBtn').addEventListener('mousedown',e=>e.preventDefault());
document.getElementById('newsLinkBtn').addEventListener('click',()=>{
  const selection=window.getSelection();
  if(!selection || selection.isCollapsed){
    alert('Markiere zuerst den Text, der verlinkt werden soll.');
    return;
  }
  let url=prompt('Link eingeben (https://…, mailto:… oder /interner-pfad):','https://');
  if(!url) return;
  url=url.trim();
  if(!/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)){
    alert('Bitte einen gültigen http(s)-, mailto-, tel- oder internen Link verwenden.');
    return;
  }
  runEditorCommand('createLink',url);
});


const imageBtn=document.getElementById('newsImageBtn');
const imageInput=document.getElementById('newsImageInput');
const uploadStatus=document.getElementById('newsUploadStatus');
let savedEditorRange=null;

function rememberEditorSelection(){
  const selection=window.getSelection();
  if(selection && selection.rangeCount && editor.contains(selection.anchorNode)){
    savedEditorRange=selection.getRangeAt(0).cloneRange();
  }
}

function restoreEditorSelection(){
  if(!savedEditorRange) return;
  const selection=window.getSelection();
  selection.removeAllRanges();
  selection.addRange(savedEditorRange);
}

editor.addEventListener('keyup',rememberEditorSelection);
editor.addEventListener('mouseup',rememberEditorSelection);
editor.addEventListener('touchend',rememberEditorSelection);

imageBtn.addEventListener('mousedown',e=>{
  e.preventDefault();
  rememberEditorSelection();
});
imageBtn.addEventListener('click',()=>{
  rememberEditorSelection();
  imageInput.value='';
  imageInput.click();
});

imageInput.addEventListener('change',async()=>{
  const file=imageInput.files?.[0];
  if(!file) return;

  if(file.size > 8*1024*1024){
    uploadStatus.textContent='Das Bild ist größer als 8 MB.';
    return;
  }

  imageBtn.disabled=true;
  uploadStatus.textContent='Bild wird hochgeladen …';

  try{
    const form=new FormData();
    form.append('csrf',<?=json_encode(csrf_token())?>);
    form.append('image',file);

    const res=await fetch('/news-image-upload.php',{
      method:'POST',
      body:form,
      cache:'no-store'
    });

    let data={};
    try{data=await res.json();}catch(e){}
    if(!res.ok || !data.ok) throw new Error(data.error || 'Bild-Upload fehlgeschlagen.');

    focusEditor();
    restoreEditorSelection();

    const alt=(file.name||'Bild').replace(/\.[^.]+$/,'');
    const imageHtml='<img src="'+String(data.url).replace(/"/g,'&quot;')+'" alt="'+alt.replace(/[&<>"']/g,'')+'" style="width:100%">';
    document.execCommand('insertHTML',false,imageHtml+'<p><br></p>');
    hiddenBody.value=editor.innerHTML;
    rememberEditorSelection();
    uploadStatus.textContent='Bild wurde eingefügt.';
  }catch(err){
    uploadStatus.textContent=err.message;
  }finally{
    imageBtn.disabled=false;
  }
});


let imageResizeBox=null;
let resizeTarget=null;

function removeImageResizeBox(){
  imageResizeBox?.remove();
  imageResizeBox=null;
  resizeTarget=null;
}

function showImageResizeBox(img){
  removeImageResizeBox();
  resizeTarget=img;

  const box=document.createElement('div');
  box.className='wysiwyg-image-resize-box';
  box.contentEditable='false';
  box.innerHTML='<span class="wysiwyg-image-size-label"></span><span class="wysiwyg-image-resize-handle" title="Bildgröße ziehen"></span>';
  editor.parentElement.appendChild(box);
  imageResizeBox=box;

  function place(){
    if(!resizeTarget || !imageResizeBox) return;
    const er=editor.getBoundingClientRect();
    const ir=resizeTarget.getBoundingClientRect();
    imageResizeBox.style.left=(ir.left-er.left+editor.scrollLeft)+'px';
    imageResizeBox.style.top=(ir.top-er.top+editor.scrollTop)+'px';
    imageResizeBox.style.width=ir.width+'px';
    imageResizeBox.style.height=ir.height+'px';
    const pct=Math.round((ir.width/editor.clientWidth)*100);
    imageResizeBox.querySelector('.wysiwyg-image-size-label').textContent=pct+'%';
  }

  place();
  const handle=box.querySelector('.wysiwyg-image-resize-handle');

  handle.addEventListener('pointerdown',ev=>{
    ev.preventDefault();
    ev.stopPropagation();
    handle.setPointerCapture(ev.pointerId);
    const startX=ev.clientX;
    const startWidth=resizeTarget.getBoundingClientRect().width;
    const editorWidth=editor.clientWidth;

    const move=e=>{
      const px=Math.max(editorWidth*.2,Math.min(editorWidth,startWidth+(e.clientX-startX)));
      const pct=Math.max(20,Math.min(100,Math.round((px/editorWidth)*100)));
      resizeTarget.style.width=pct+'%';
      hiddenBody.value=editor.innerHTML;
      place();
    };
    const end=e=>{
      handle.releasePointerCapture(e.pointerId);
      handle.removeEventListener('pointermove',move);
      handle.removeEventListener('pointerup',end);
      handle.removeEventListener('pointercancel',end);
      hiddenBody.value=editor.innerHTML;
    };

    handle.addEventListener('pointermove',move);
    handle.addEventListener('pointerup',end);
    handle.addEventListener('pointercancel',end);
  });

  editor.addEventListener('scroll',place,{once:true});
  window.addEventListener('resize',place,{once:true});
}

editor.addEventListener('click',e=>{
  const img=e.target.closest('img');
  if(img && editor.contains(img)){
    e.preventDefault();
    showImageResizeBox(img);
  }else{
    removeImageResizeBox();
  }
});

editor.addEventListener('input',()=>{
  if(resizeTarget && !editor.contains(resizeTarget)) removeImageResizeBox();
});

newsModal.addEventListener('show.bs.modal',e=>{
  removeImageResizeBox();
  const b=e.relatedTarget;
  const edit=b?.dataset.mode==='edit';
  const post=edit ? newsPosts[String(b.dataset.id)] : null;

  document.getElementById('newsModalTitle').textContent=edit?'Neuigkeit bearbeiten':'Neuigkeit schreiben';
  document.getElementById('newsAction').value=edit?'edit':'create';
  document.getElementById('newsPushWrap').hidden=edit;
  document.getElementById('newsSendPush').checked=false;
  document.getElementById('newsSubmitButton').textContent=edit?'Änderungen speichern':'Veröffentlichen';
  document.getElementById('newsId').value=edit?b.dataset.id:'';
  document.getElementById('newsTitle').value=edit?(post?.title||''):'';
  editor.innerHTML=edit?(post?.body||''):'<p><br></p>';
  hiddenBody.value=editor.innerHTML;
});

newsModal.addEventListener('shown.bs.modal',()=>{
  if(!document.getElementById('newsTitle').value){
    document.getElementById('newsTitle').focus();
  }else{
    focusEditor();
  }
});

editor.addEventListener('input',()=>{ hiddenBody.value=editor.innerHTML; });

newsForm.addEventListener('submit',e=>{
  hiddenBody.value=editor.innerHTML;
  const plain=(editor.innerText||'').trim();
  if(plain.length<3){
    e.preventDefault();
    alert('Bitte einen Nachrichtentext eingeben.');
    focusEditor();
  }
});
</script>
<?php endif;?>

<?php if($canWrite && (($_GET['create'] ?? '') === '1')): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const el=document.getElementById('newsModal');
  if(el && window.bootstrap){ bootstrap.Modal.getOrCreateInstance(el).show(); }
});
</script>
<?php endif; ?>
<?php require __DIR__.'/includes/footer.php';?>
