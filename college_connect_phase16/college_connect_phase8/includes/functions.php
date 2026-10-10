<?php
function db(): PDO {
 static $pdo=null; if($pdo instanceof PDO)return $pdo;
 $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
 ]); return $pdo;
}
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf_token():string{if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));return $_SESSION['csrf_token'];}
function csrf_field():string{return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';}
function verify_csrf():void{$t=$_POST['csrf_token']??'';if(!is_string($t)||empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$t)){http_response_code(419);exit('Your form session expired. Refresh the page and try again.');}}
function set_flash(string $type,string $message):void{$_SESSION['flash']=['type'=>$type,'message'=>$message];}
function get_flash():?array{if(empty($_SESSION['flash']))return null;$f=$_SESSION['flash'];unset($_SESSION['flash']);return $f;}
function redirect(string $path):never{header('Location: '.$path);exit;}
function current_user():?array{
 if(empty($_SESSION['user_id']))return null;
 try{$s=db()->prepare('SELECT id,full_name,email,role,department,graduation_year,affiliation_verified,status FROM users WHERE id=? LIMIT 1');$s->execute([(int)$_SESSION['user_id']]);$u=$s->fetch();
 if(!$u||$u['status']!=='active'){unset($_SESSION['user_id']);return null;}return $u;}catch(Throwable $x){return null;}
}
function require_login():array{$u=current_user();if(!$u){set_flash('error','Please log in to access that page.');redirect('login.php');}return $u;}
function require_role(array $roles):array{
 $u=require_login();if(!in_array($u['role'],$roles,true)){http_response_code(403);$pageTitle='Access denied';require __DIR__.'/header.php';echo '<section class="section-block"><div class="empty-state"><h3>Access denied</h3><p>Your account does not have permission to access this area.</p><a class="btn btn-primary" href="index.php">Return home</a></div></section>';require __DIR__.'/footer.php';exit;}return $u;
}
function time_ago(string $d):string{$n=max(0,time()-strtotime($d));if($n<3600)return max(1,(int)($n/60)).'m ago';if($n<86400)return (int)($n/3600).'h ago';if($n<604800)return (int)($n/86400).'d ago';return date('M j',strtotime($d));}
function shorten(string $t,int $l):string{return (function_exists('mb_strlen')&&mb_strlen($t)>$l)?mb_substr($t,0,$l-1).'…':(strlen($t)>$l?substr($t,0,$l-1).'…':$t);}
function get_categories():array{return db()->query('SELECT id,name,slug,description,icon,color FROM categories WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();}
function get_recent_questions(int $limit=5):array{$s=db()->prepare('SELECT q.id,q.title,q.body,q.created_at,c.name AS category_name,COALESCE(u.full_name,"Community member") AS author_name,(SELECT COUNT(*) FROM answers a WHERE a.question_id=q.id AND a.status="published") AS answer_count FROM questions q LEFT JOIN categories c ON c.id=q.category_id LEFT JOIN users u ON u.id=q.user_id WHERE q.status="published" AND q.moderation_status="allowed" ORDER BY q.created_at DESC LIMIT :lim');$s->bindValue(':lim',$limit,PDO::PARAM_INT);$s->execute();return $s->fetchAll();}
function search_questions(string $q='',int $cat=0):array{$sql='SELECT q.id,q.title,q.body,q.created_at,c.name AS category_name,COALESCE(u.full_name,"Community member") AS author_name,(SELECT COUNT(*) FROM answers a WHERE a.question_id=q.id AND a.status="published") AS answer_count FROM questions q LEFT JOIN categories c ON c.id=q.category_id LEFT JOIN users u ON u.id=q.user_id WHERE q.status="published" AND q.moderation_status="allowed"';$p=[];if($q!==''){$sql.=' AND (q.title LIKE :s1 OR q.body LIKE :s2)';$p[':s1']='%'.$q.'%';$p[':s2']='%'.$q.'%';}if($cat>0){$sql.=' AND q.category_id=:cat';$p[':cat']=$cat;}$sql.=' ORDER BY q.created_at DESC LIMIT 100';$s=db()->prepare($sql);$s->execute($p);return $s->fetchAll();}
function get_announcements(int $limit=3):array{$s=db()->prepare('SELECT title,body,label,created_at FROM announcements WHERE is_published=1 ORDER BY created_at DESC LIMIT :lim');$s->bindValue(':lim',$limit,PDO::PARAM_INT);$s->execute();return $s->fetchAll();}

function audit_log(int $actorId,string $action,string $entityType,?int $entityId=null,string $details=''):void{
 try{$s=db()->prepare('INSERT INTO admin_audit_logs (actor_id,action,entity_type,entity_id,details,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())');$s->execute([$actorId,substr($action,0,100),substr($entityType,0,50),$entityId,substr($details,0,2000),substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);}catch(Throwable $e){error_log('AITAM SLACK audit log failed: '.$e->getMessage());}
}
