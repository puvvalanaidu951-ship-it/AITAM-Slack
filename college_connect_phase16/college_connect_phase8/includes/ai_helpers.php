<?php
/**
 * Local AI-assist prototype. It uses transparent keyword heuristics and does not call an external AI service.
 * Treat every output as a suggestion; admins remain responsible for moderation decisions.
 */
function cc_ai_classify_question(string $title,string $body,array $categories): array {
    $text=mb_strtolower($title.' '.$body);
    $rules=[
      'admissions'=>['admission','eligibility','application','joining','fee','fees','cutoff','counselling','counseling'],
      'academics'=>['exam','semester','timetable','syllabus','subject','course','marks','attendance','assignment','library','lab'],
      'placements'=>['placement','interview','internship','resume','cv','job','career','recruiter','aptitude','coding','salary'],
      'campus-life'=>['hostel','bus','transport','canteen','club','event','campus','sports','library timing']
    ];
    $scores=[];
    foreach($categories as $c){$slug=(string)($c['slug']??'');$scores[(int)$c['id']]=0;foreach(($rules[$slug]??[]) as $word){if(mb_strpos($text,$word)!==false)$scores[(int)$c['id']]++;}}
    arsort($scores);$id=(int)(array_key_first($scores)??0);$score=(int)($scores[$id]??0);
    $confidence=$score===0?0.25:min(0.94,0.42+($score*0.12));
    $name='';foreach($categories as $c)if((int)$c['id']===$id){$name=(string)$c['name'];break;}
    return ['category_id'=>$id,'category_name'=>$name,'confidence'=>$confidence,'scores'=>$scores];
}
function cc_ai_risk_scan(string $title,string $body): array {
    $text=mb_strtolower($title.' '.$body);$flags=[];
    $patterns=[
      'threat_or_violence'=>['i will kill','kill you','shoot you','bomb threat','hurt them','attack them'],
      'self_harm'=>['kill myself','end my life','suicide','self harm','hurt myself'],
      'personal_data'=>['password is','my password','otp is','aadhaar number','credit card number','bank account number'],
      'abuse_or_harassment'=>['i will dox','personal address is','go harass','target this person']
    ];
    foreach($patterns as $type=>$terms)foreach($terms as $term)if(mb_strpos($text,$term)!==false){$flags[]=$type;break;}
    $level=!empty($flags)?'review':'low';
    return ['level'=>$level,'flags'=>$flags,'reason'=>$flags?'Keyword patterns detected; human review required.':'No configured risk patterns detected. This is not a guarantee of safety.'];
}
function cc_ai_find_duplicate(string $title,string $body,int $excludeId=0): ?array {
    $words=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($title),-1,PREG_SPLIT_NO_EMPTY);
    $words=array_values(array_unique(array_filter($words,fn($w)=>mb_strlen($w)>=4)));
    if(!$words)return null;
    $terms=array_slice($words,0,6);$where=[];$params=[];
    foreach($terms as $i=>$term){$where[]='(title LIKE ? OR body LIKE ?)';$params[]='%'.$term.'%';$params[]='%'.$term.'%';}
    $sql='SELECT id,title,body,created_at FROM questions WHERE status="published"'.($excludeId>0?' AND id<>?':'').' AND ('.implode(' OR ',$where).') ORDER BY created_at DESC LIMIT 30';
    if($excludeId>0)array_unshift($params,$excludeId);
    $s=db()->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$best=null;$bestScore=0;
    $newTokens=array_unique($words);
    foreach($rows as $row){$old=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower((string)$row['title']),-1,PREG_SPLIT_NO_EMPTY);$old=array_unique($old);$inter=count(array_intersect($newTokens,$old));$union=count(array_unique(array_merge($newTokens,$old)));$score=$union?($inter/$union):0;if($score>$bestScore){$bestScore=$score;$best=$row;}}
    return $bestScore>=0.28&&$best?['id'=>(int)$best['id'],'title'=>$best['title'],'score'=>$bestScore]:null;
}
function cc_ai_suggested_answer(string $title,string $body,string $category): string {
    return 'Suggested starting point (please verify): For this '.$category.' question, check the latest official college notice or relevant department page first. If the information is not published, contact the responsible college office and confirm dates, eligibility, fees, and requirements directly. If you share the official notice or more context, the community can help interpret it. This is an automated template, not a verified answer.';
}
function cc_ai_save_insight(int $questionId,string $title,string $body,array $categories): void {
    $classification=cc_ai_classify_question($title,$body,$categories);
    $risk=cc_ai_risk_scan($title,$body);
    $duplicate=cc_ai_find_duplicate($title,$body,$questionId);
    $answer=cc_ai_suggested_answer($title,$body,$classification['category_name']?:'general');
    $s=db()->prepare('INSERT INTO ai_question_insights (question_id,suggested_category_id,classification_confidence,duplicate_question_id,duplicate_score,risk_level,risk_flags_json,risk_reason,suggested_answer,review_status) VALUES (?,?,?,?,?,?,?,?,?,"pending") ON DUPLICATE KEY UPDATE suggested_category_id=VALUES(suggested_category_id),classification_confidence=VALUES(classification_confidence),duplicate_question_id=VALUES(duplicate_question_id),duplicate_score=VALUES(duplicate_score),risk_level=VALUES(risk_level),risk_flags_json=VALUES(risk_flags_json),risk_reason=VALUES(risk_reason),suggested_answer=VALUES(suggested_answer),review_status="pending"');
    $s->execute([$questionId,$classification['category_id']?:null,$classification['confidence'],$duplicate['id']??null,$duplicate['score']??null,$risk['level'],json_encode($risk['flags'],JSON_UNESCAPED_UNICODE),$risk['reason'],$answer]);
}


/** Lightweight keyword/token heuristic. This is not a semantic AI model. */
function cc_ai_answer_relevance(string $questionTitle,string $questionBody,string $answer): array {
    $stop=['this','that','with','from','have','your','about','what','when','where','which','would','could','should','there','their','they','them','then','than','into','also','just','some','more','very','will','want','need','does','dont','not','for','and','the','you','are','was','were','has','had','can','how','why','who','all','any','our','out','use','using','its','it','but','get','one','two','first','please','help'];
    $tokens=function(string $text) use($stop): array {
        $parts=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($text),-1,PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($parts,fn($w)=>mb_strlen($w)>=3&&!in_array($w,$stop,true))));
    };
    $q=$tokens($questionTitle.' '.$questionBody);$a=$tokens($answer);
    if(!$q||!$a)return ['score'=>0.0,'relevant'=>false,'matched'=>[],'reason'=>'Not enough meaningful terms to compare.'];
    $matched=array_values(array_intersect($q,$a));
    $precision=count($matched)/max(1,count($a));
    $coverage=count($matched)/max(1,count($q));
    // Weighted overlap helps longer questions without pretending to understand semantics.
    $score=min(1.0,(0.65*$coverage)+(0.35*$precision));
    return ['score'=>$score,'relevant'=>($score>=0.12 && count($matched)>=2),'matched'=>array_slice($matched,0,12),'reason'=>count($matched)>=2?'Some question terms are present in the answer; this is only a relevance signal.':'Very little key-term overlap; hold for review.'];
}
function cc_ai_save_answer_review(int $answerId,int $questionId,array $risk,array $relevance,string $decision): void {
    $s=db()->prepare('INSERT INTO ai_answer_reviews (answer_id,question_id,relevance_score,relevance_status,matched_terms_json,risk_level,risk_flags_json,reason,decision) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE relevance_score=VALUES(relevance_score),relevance_status=VALUES(relevance_status),matched_terms_json=VALUES(matched_terms_json),risk_level=VALUES(risk_level),risk_flags_json=VALUES(risk_flags_json),reason=VALUES(reason),decision=VALUES(decision),reviewed_by=NULL,reviewed_at=NULL');
    $flags=array_values(array_unique(array_merge($risk['flags']??[],!empty($relevance['spam'])?['spam']:[],!empty($relevance['abusive'])?['abusive']:[])));
    $riskLevel=empty($risk['api_ok'])||!empty($risk['flagged'])||!empty($relevance['spam'])||!empty($relevance['abusive'])?'review':'low';
    $reason='Relevance: '.($relevance['reason']??'Not classified.').' Safety: '.($risk['reason']??'Not classified.');
    if(!empty($risk['diagnostic']))$reason.=' Safety diagnostic: '.cc_openai_sanitize_error((string)$risk['diagnostic']);
    if(!empty($relevance['diagnostic']))$reason.=' Relevance diagnostic: '.cc_openai_sanitize_error((string)$relevance['diagnostic']);
    $score=isset($relevance['score'])&&is_numeric($relevance['score'])?max(0,min(1,(float)$relevance['score'])):0;
    $s->execute([$answerId,$questionId,$score,!empty($relevance['relevant'])?'likely_relevant':'needs_review',json_encode($relevance['matched']??[],JSON_UNESCAPED_UNICODE),$riskLevel,json_encode($flags,JSON_UNESCAPED_UNICODE),mb_substr($reason,0,1000),$decision]);
}


/** Phase 7: stronger lexical relevance scoring; still not semantic AI. */
function cc_ai_answer_relevance_v2(string $questionTitle,string $questionBody,string $answer): array {
    $stop=['this','that','with','from','have','your','about','what','when','where','which','would','could','should','there','their','they','them','then','than','into','also','just','some','more','very','will','want','need','does','dont','not','for','and','the','you','are','was','were','has','had','can','how','why','who','all','any','our','out','use','using','its','it','but','get','one','two','first','please','help','question','answer'];
    $tokens=function(string $text) use($stop): array {
        $parts=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($text),-1,PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($parts,fn($w)=>mb_strlen($w)>=3&&!in_array($w,$stop,true))));
    };
    $titleTokens=$tokens($questionTitle);$bodyTokens=$tokens($questionBody);$answerTokens=$tokens($answer);
    $questionTokens=array_values(array_unique(array_merge($titleTokens,$bodyTokens)));
    if(!$questionTokens||!$answerTokens)return ['score'=>0.0,'relevant'=>false,'matched'=>[],'reason'=>'Not enough meaningful terms to compare.'];
    $matched=array_values(array_intersect($questionTokens,$answerTokens));
    $titleMatched=array_values(array_intersect($titleTokens,$answerTokens));
    $coverage=count($matched)/max(1,count($questionTokens));
    $answerPrecision=count($matched)/max(1,count($answerTokens));
    $titleCoverage=count($titleMatched)/max(1,count($titleTokens));
    $score=min(1.0,0.50*$coverage+0.20*$answerPrecision+0.30*$titleCoverage);
    $relevant=count($matched)>=2 && ($titleCoverage>=0.15 || $coverage>=0.10);
    return ['score'=>$score,'relevant'=>$relevant,'matched'=>array_slice($matched,0,20),'reason'=>$relevant?'Question title/body terms overlap with the answer; relevance is only a heuristic.':'Low overlap with the question title/body; hold for human review.'];
}
