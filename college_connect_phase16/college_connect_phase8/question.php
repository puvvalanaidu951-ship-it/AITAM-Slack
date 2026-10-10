<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai_helpers.php';
require_once __DIR__ . '/includes/openai_moderation.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0; $user=current_user(); $errors=[];
$stmt=db()->prepare('SELECT q.*,c.name AS category_name,COALESCE(u.full_name,"Community member") AS author_name,u.id AS author_id FROM questions q JOIN categories c ON c.id=q.category_id LEFT JOIN users u ON u.id=q.user_id WHERE q.id=? AND q.status="published" AND q.moderation_status="allowed" LIMIT 1');$stmt->execute([$id]);$question=$stmt->fetch();
if(!$question){http_response_code(404);$pageTitle='Question not found';require __DIR__.'/includes/header.php';echo '<section class="section-block"><div class="empty-state"><h2>Question not found</h2><a class="btn btn-primary" href="questions.php">Browse questions</a></div></section>';require __DIR__.'/includes/footer.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $user=require_login();verify_csrf();$action=(string)($_POST['action']??'');
 if($action==='answer'){
  $diagnosticId=cc_phase16_diagnostic_id();
  cc_phase16_log_diagnostic($diagnosticId,'answer_submission',['stage'=>'submission_start','request_reached_openai'=>false]);
  $body=trim((string)($_POST['body']??''));if(mb_strlen($body)<5||mb_strlen($body)>10000)$errors[]='Your answer must be between 5 and 10,000 characters.';
  if(!$errors){
   $risk=cc_phase16_risk_scan('', $body, null, $diagnosticId);
   $relevance=!empty($risk['api_ok'])&&!empty($risk['flagged'])
    ? ['score'=>0.0,'classification'=>'uncertain','relevant'=>false,'spam'=>false,'abusive'=>false,'confidence'=>0.0,'matched'=>[],'reason'=>'Relevance check skipped after a safety block.','api_ok'=>false,'diagnostic'=>'']
    : (empty($risk['api_ok'])
     ? ['score'=>0.0,'classification'=>'uncertain','relevant'=>false,'spam'=>false,'abusive'=>false,'confidence'=>0.0,'matched'=>[],'reason'=>'Relevance check skipped because safety moderation did not complete.','api_ok'=>false,'diagnostic'=>$risk['diagnostic']??'']
     : cc_phase16_answer_relevance((string)$question['title'], (string)$question['body'], $body, null, $diagnosticId));
   $safetyUnavailable=empty($risk['api_ok']);
   $decision=cc_phase16_answer_decision($risk,$relevance);
   $checksUnavailable=$safetyUnavailable||(!empty($risk['api_ok'])&&empty($risk['flagged'])&&empty($relevance['api_ok']));
   if(!cc_phase16_answer_can_persist($risk,$relevance,$decision)){
    $reasonCode=$safetyUnavailable?'safety_unavailable'
     :(!empty($risk['flagged'])?'safety_flagged'
     :(empty($relevance['api_ok'])?'relevance_unavailable'
     :(!empty($relevance['spam'])||!empty($relevance['abusive'])?'spam_or_abuse'
     :(($relevance['classification']??(!empty($relevance['relevant'])?'relevant':'irrelevant'))==='irrelevant'?'answer_irrelevant':'relevance_uncertain'))));
    cc_phase16_log_submission_decision(
     'answer',
     (int)$user['id'],
     $checksUnavailable?'retry':'blocked',
     $reasonCode,
     !$checksUnavailable
    );
    cc_phase16_log_diagnostic($diagnosticId,'answer_submission',[
     'stage'=>'checks_rejected_or_unavailable',
     'moderation_flagged'=>!empty($risk['flagged']),
     'relevance_decision_valid'=>!empty($relevance['api_ok']),
     'database_insert_succeeded'=>false,
     'error_category'=>$reasonCode
    ]);
    if($checksUnavailable)set_flash('error','We could not complete the required safety and relevance checks. Your answer was not saved; please try again later. Reference: '.$diagnosticId);
    elseif(!empty($risk['flagged'])||!empty($relevance['spam'])||!empty($relevance['abusive']))set_flash('error','Your answer could not be accepted because it did not pass the required safety or spam checks.');
    elseif(($relevance['classification']??(!empty($relevance['relevant'])?'relevant':'irrelevant'))==='irrelevant')set_flash('error','Your answer was not saved because it does not appear to address this question.');
    else set_flash('error','We could not confidently verify that your answer is relevant. Please revise it and try again.');
    redirect('question.php?id='.$id.'#answers');
   }
   try{
    db()->beginTransaction();
    $parentCheck=db()->prepare('SELECT id FROM questions WHERE id=? AND status="published" AND moderation_status="allowed" FOR UPDATE');
    $parentCheck->execute([$id]);
    if(!$parentCheck->fetchColumn())throw new RuntimeException('The parent question is no longer available for answers.');
    $ins=db()->prepare('INSERT INTO answers (question_id,user_id,body,status) VALUES (?,?,?,"published")');
    $ins->execute([$id,(int)$user['id'],$body]);$answerId=(int)db()->lastInsertId();
    cc_ai_save_answer_review($answerId,$id,$risk,$relevance,$decision);
    cc_phase16_record_moderation('answer',$answerId,$risk,$relevance,'published');
    if(!empty($question['author_id'])&&(int)$question['author_id']!==(int)$user['id'])db()->prepare('INSERT INTO notifications (user_id,type,title,message,related_type,related_id) VALUES (?,?,?,?,?,?)')->execute([(int)$question['author_id'],'answer','New answer to your question','Someone answered: '.$question['title'],'question',$id]);
    db()->commit();
    cc_phase16_log_diagnostic($diagnosticId,'answer_submission',[
     'stage'=>'database_commit',
     'database_insert_succeeded'=>true,
     'moderation_flagged'=>false,
     'relevance_decision_valid'=>true
    ]);
    set_flash('success','Your answer has been published after OpenAI safety and relevance checks.');
    redirect('question.php?id='.$id.'#answers');
   }catch(Throwable $e){
    if(db()->inTransaction())db()->rollBack();
    $redact=[(string)$body,(string)$question['title'],(string)$question['body']];
    $redact=array_values(array_filter($redact,static fn($value)=>$value!==''));
    $safeError=cc_openai_sanitize_error(str_replace($redact,'[content redacted]',$e->getMessage()));
    cc_phase16_log_diagnostic($diagnosticId,'answer_submission',[
     'stage'=>'database_or_transaction_failure',
     'database_insert_succeeded'=>false,
     'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():'',
     'error_category'=>get_class($e),
     'error_message'=>$safeError
    ]);
    $errors[]='Could not post your answer. Please try again later or contact an administrator. Reference: '.$diagnosticId;
   }
  }
 } elseif($action==='bookmark') {
  $check=db()->prepare('SELECT id FROM question_bookmarks WHERE user_id=? AND question_id=?');$check->execute([(int)$user['id'],$id]);
  if($check->fetch()) { db()->prepare('DELETE FROM question_bookmarks WHERE user_id=? AND question_id=?')->execute([(int)$user['id'],$id]); set_flash('success','Question removed from your saved list.'); }
  else { db()->prepare('INSERT INTO question_bookmarks (user_id,question_id) VALUES (?,?)')->execute([(int)$user['id'],$id]); set_flash('success','Question saved to your bookmarks.'); }
  redirect('question.php?id='.$id);
 } elseif($action==='vote') {
  $aid=filter_input(INPUT_POST,'answer_id',FILTER_VALIDATE_INT)?:0; $vote=(int)($_POST['vote']??0);
  if(!in_array($vote,[-1,1],true)) { set_flash('error','Invalid vote.'); redirect('question.php?id='.$id.'#answers'); }
  $check=db()->prepare('SELECT id,user_id FROM answers WHERE id=? AND question_id=? AND status="published"');$check->execute([$aid,$id]);$votedAnswer=$check->fetch();
  if(!$votedAnswer) { set_flash('error','That answer is no longer available.'); redirect('question.php?id='.$id.'#answers'); }
  if((int)$votedAnswer['user_id']===(int)$user['id']) { set_flash('error','You cannot vote on your own answer.'); redirect('question.php?id='.$id.'#answers'); }
  $existing=db()->prepare('SELECT vote FROM answer_votes WHERE answer_id=? AND user_id=?');$existing->execute([$aid,(int)$user['id']]);$old=$existing->fetchColumn();
  if((int)$old===$vote) { db()->prepare('DELETE FROM answer_votes WHERE answer_id=? AND user_id=?')->execute([$aid,(int)$user['id']]); }
  else { db()->prepare('INSERT INTO answer_votes (answer_id,user_id,vote) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vote=VALUES(vote)')->execute([$aid,(int)$user['id'],$vote]); }
  set_flash('success','Your feedback has been saved.'); redirect('question.php?id='.$id.'#answer-'.$aid);
 } elseif($action==='accept'){
  if((int)($question['author_id']??0)!==(int)$user['id']&&$user['role']!=='admin'){http_response_code(403);exit('Only the question author can accept an answer.');}
  $aid=filter_input(INPUT_POST,'answer_id',FILTER_VALIDATE_INT)?:0;$check=db()->prepare('SELECT id,user_id FROM answers WHERE id=? AND question_id=? AND status="published"');$check->execute([$aid,$id]);$a=$check->fetch();
  if($a){db()->beginTransaction();try{db()->prepare('UPDATE answers SET is_accepted=0 WHERE question_id=?')->execute([$id]);db()->prepare('UPDATE answers SET is_accepted=1 WHERE id=? AND question_id=?')->execute([$aid,$id]);if($a['user_id']&&(int)$a['user_id']!==(int)$user['id'])db()->prepare('INSERT INTO notifications (user_id,type,title,message,related_type,related_id) VALUES (?,?,?,?,?,?)')->execute([(int)$a['user_id'],'accepted','Your answer was accepted','Your answer was accepted for: '.$question['title'],'question',$id]);db()->commit();set_flash('success','Accepted answer updated.');}catch(Throwable $e){db()->rollBack();set_flash('error','Could not update accepted answer.');}}
  redirect('question.php?id='.$id.'#answers');
 }
}
$as=db()->prepare('SELECT a.*,COALESCE(u.full_name,"Community member") AS author_name,u.id AS author_id,u.role AS author_role,(SELECT COALESCE(SUM(v.vote),0) FROM answer_votes v WHERE v.answer_id=a.id) AS vote_score,(SELECT v.vote FROM answer_votes v WHERE v.answer_id=a.id AND v.user_id=?) AS my_vote FROM answers a LEFT JOIN users u ON u.id=a.user_id WHERE a.question_id=? AND a.status="published" ORDER BY a.is_accepted DESC,a.created_at ASC');$as->execute([$user?(int)$user['id']:0,$id]);$answers=$as->fetchAll();$pageTitle='Question details';$saved=false;if($user){$bs=db()->prepare('SELECT id FROM question_bookmarks WHERE user_id=? AND question_id=?');$bs->execute([(int)$user['id'],$id]);$saved=(bool)$bs->fetch();}require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow"><?=e($question['category_name'])?></span><h1><?=e($question['title'])?></h1><p>Asked by <?=e($question['author_name'])?> · <?=e(time_ago($question['created_at']))?></p></section>
<section class="section-block"><article class="account-card question-detail"><p><?=nl2br(e($question['body']))?></p><?php if($user): ?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="bookmark"><button class="btn btn-secondary btn-small" type="submit"><?= $saved ? '★ Saved — remove bookmark' : '☆ Save question' ?></button></form><?php endif; ?><div class="question-meta"><span class="topic-pill"><?=e($question['category_name'])?></span><span><?=count($answers)?> answers</span></div></article>
<div id="answers" class="section-heading compact"><div><h2>Community answers</h2><p>Share helpful, respectful guidance.</p></div></div>
<?php if($errors): ?><div class="flash flash-error"><?php foreach($errors as $error): ?><p><?=e($error)?></p><?php endforeach; ?></div><?php endif; ?>
<?php if(!$answers): ?><div class="empty-state"><h3>No answers yet</h3><p>Be the first to help.</p></div><?php endif; ?>
<?php foreach($answers as $answer): ?><article id="answer-<?=(int)$answer['id']?>" class="account-card answer-card"><?php if($answer['is_accepted']): ?><span class="topic-pill accepted-pill">✓ Accepted answer</span><?php endif; ?><p><?=nl2br(e($answer['body']))?></p><div class="question-meta"><span><?=e($answer['author_name'])?></span><span><?=e($answer['author_role']??'community')?></span><span><?=e(time_ago($answer['created_at']))?></span><span>Helpful score: <?= (int)$answer['vote_score'] ?></span></div><?php if($user && (int)($answer['author_id']??0)!==(int)$user['id']): ?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="vote"><input type="hidden" name="answer_id" value="<?=(int)$answer['id']?>"><button class="btn btn-small btn-secondary" name="vote" value="1" type="submit" <?=((int)($answer['my_vote']??0)===1)?'disabled':''?>>👍 Helpful</button><button class="btn btn-small btn-secondary" name="vote" value="-1" type="submit" <?=((int)($answer['my_vote']??0)===-1)?'disabled':''?>>👎 Not helpful</button><?php if($answer['my_vote']): ?><span class="topic-pill">Your vote: <?=((int)$answer['my_vote']===1)?'Helpful':'Not helpful'?></span><?php endif; ?></form><?php endif; ?>
<?php if($user&&((int)($question['author_id']??0)===(int)$user['id']||$user['role']==='admin')&&!$answer['is_accepted']): ?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="accept"><input type="hidden" name="answer_id" value="<?=(int)$answer['id']?>"><button class="btn btn-small btn-secondary" type="submit">Accept answer</button></form><?php endif; ?></article><?php endforeach; ?>
<div id="answer-form" class="account-card"><h2>Your answer</h2><p class="muted">Answers are checked for safety, spam, and relevance before they are saved. If checks are unavailable or uncertain, the answer will not be saved.</p><?php if(!$user): ?><p><a href="login.php">Log in</a> to post an answer.</p><?php else: ?><form method="post" class="account-form"><?=csrf_field()?><input type="hidden" name="action" value="answer"><label for="body">Answer details</label><textarea id="body" name="body" rows="5" minlength="5" maxlength="10000" required placeholder="Write a helpful answer..."></textarea><button class="btn btn-primary" type="submit">Post answer ↗</button></form><?php endif; ?></div><a class="filter-chip" href="questions.php">← Back to questions</a></section>
<?php require __DIR__.'/includes/footer.php'; ?>