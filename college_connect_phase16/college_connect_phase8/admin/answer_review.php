<?php
require_once __DIR__.'/../includes/bootstrap.php';
$admin=require_role(['admin']);$pageTitle='AI answer review';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$reviewId=filter_input(INPUT_POST,'review_id',FILTER_VALIDATE_INT)?:0;$action=(string)($_POST['action']??'');
 if($reviewId>0&&in_array($action,['approve','dismiss'],true)){
  $s=db()->prepare('SELECT answer_id FROM ai_answer_reviews WHERE id=?');$s->execute([$reviewId]);$answerId=(int)$s->fetchColumn();
  if($answerId){
   $status=$action==='approve'?'published':'hidden';$decision=$action==='approve'?'published':'dismissed';
   db()->beginTransaction();
   try{db()->prepare('UPDATE answers SET status=? WHERE id=?')->execute([$status,$answerId]);db()->prepare('UPDATE ai_answer_reviews SET decision=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?')->execute([$decision,(int)$admin['id'],$reviewId]);db()->commit();set_flash('success',$action==='approve'?'Answer approved and published.':'Answer dismissed and kept hidden.');}
   catch(Throwable $e){db()->rollBack();set_flash('error','Could not update this answer.');}
  }
 }
 redirect('answer_review.php');
}
$rows=db()->query('SELECT r.*,a.body answer_body,a.status answer_status,q.title question_title,q.body question_body,COALESCE(u.full_name,"Community member") answer_author FROM ai_answer_reviews r JOIN answers a ON a.id=r.answer_id JOIN questions q ON q.id=r.question_id LEFT JOIN users u ON u.id=a.user_id ORDER BY (r.decision="pending_review") DESC,(r.risk_level="review") DESC,r.created_at DESC LIMIT 150')->fetchAll();
require __DIR__.'/../includes/header.php';?>
<section class="page-hero"><span class="eyebrow">ANSWER QUALITY</span><h1>AI-assisted answer review</h1><p>Answers that are unsafe, unrelated, spam-like, or uncertain stay hidden until an administrator reviews them.</p></section><section class="section-block">
<div class="flash-info">Safety uses the OpenAI Moderation API. Relevance and spam checks compare the answer with its actual parent question; concise paraphrases can still pass. Automated checks can make mistakes, so review the context before deciding.</div>
<?php if(!$rows): ?><div class="empty-state"><h3>No answer reviews yet</h3><p>New answers submitted after the Phase 6 migration will appear here.</p></div><?php endif; ?>
<?php foreach($rows as $r):$flags=json_decode((string)$r['risk_flags_json'],true)?:[];?><article class="account-card ai-review-card"><div class="question-meta"><span class="status-chip status-<?=e((string)$r['decision'])?>"><?=e($r['decision'])?></span><span class="status-chip"><?=e($r['risk_level']==='review'?'Safety review':'Safety check passed')?></span><span>Relevance score <?=number_format((float)$r['relevance_score']*100,0)?>%</span><span><?=e(str_replace('_',' ',$r['relevance_status']))?></span></div><h2>Question: <?=e($r['question_title'])?></h2><p><?=e(shorten($r['question_body'],280))?></p><h3>Answer by <?=e($r['answer_author'])?></h3><p><?=nl2br(e($r['answer_body']))?></p><div class="ai-risk"><strong>Classifier signals:</strong> <?=e($flags?implode(', ',$flags):'No safety or spam flags detected')?> <p><?=e($r['reason'])?></p></div><p><a href="../question.php?id=<?=(int)$r['question_id']?>">Open discussion ↗</a></p><?php if(in_array($r['decision'],['pending_review','blocked'],true)):?><div class="moderation-buttons"><form method="post"><?=csrf_field()?><input type="hidden" name="review_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="approve"><button class="btn btn-small btn-primary" type="submit">Approve and publish</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="review_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="dismiss"><button class="btn btn-small btn-secondary" type="submit">Dismiss / keep hidden</button></form></div><?php endif;?></article><?php endforeach;?></section><?php require __DIR__.'/../includes/footer.php';?>