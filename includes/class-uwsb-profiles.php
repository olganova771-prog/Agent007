<?php
if (!defined('ABSPATH')) exit;

class UWSB_Profiles {
    public static function all(){
        return [
            'creator'=>['label'=>'CREATOR','class'=>'uwsb-creator','hero'=>'split','cards'=>'asym','density'=>'airy','tone'=>'expressive'],
            'businessman'=>['label'=>'BUSINESSMAN','class'=>'uwsb-businessman','hero'=>'corporate','cards'=>'grid','density'=>'balanced','tone'=>'precise'],
            'bandit'=>['label'=>'BANDIT','class'=>'uwsb-bandit','hero'=>'poster','cards'=>'hard','density'=>'dense','tone'=>'direct'],
            'cartel'=>['label'=>'CARTEL','class'=>'uwsb-cartel','hero'=>'cinematic','cards'=>'lux','density'=>'dramatic','tone'=>'controlled'],
            'master'=>['label'=>'MASTER','class'=>'uwsb-master','hero'=>'editorial','cards'=>'minimal','density'=>'measured','tone'=>'authoritative'],
        ];
    }

    public static function keys(){
        return [
            'formality','conversationality','directness','expertise','factual_density','detail','emotionality','friendliness','confidence','neutrality',
            'practicality','benefit_orientation','feature_orientation','use_case_orientation','examples','structuredness','narrativity','questioning_style','direct_address','humor',
            'local_orientation','commercial_orientation','informational_orientation','faq_orientation','syntactic_variation','lexical_diversity','heading_density','list_density','internal_link_density','individuality'
        ];
    }

    public static function labels(){
        return [
            'formality'=>'Формальність','conversationality'=>'Розмовність','directness'=>'Прямота','expertise'=>'Експертність','factual_density'=>'Щільність фактів',
            'detail'=>'Деталізація','emotionality'=>'Емоційність','friendliness'=>'Дружність','confidence'=>'Впевненість','neutrality'=>'Нейтральність',
            'practicality'=>'Практичність','benefit_orientation'=>'Орієнтація на вигоди','feature_orientation'=>'Орієнтація на характеристики','use_case_orientation'=>'Сценарії використання',
            'examples'=>'Приклади','structuredness'=>'Структурованість','narrativity'=>'Наративність','questioning_style'=>'Питальний стиль','direct_address'=>'Пряме звернення',
            'humor'=>'Гумор','local_orientation'=>'Локальність','commercial_orientation'=>'Комерційність','informational_orientation'=>'Інформаційність','faq_orientation'=>'FAQ-орієнтація',
            'syntactic_variation'=>'Синтаксична варіативність','lexical_diversity'=>'Лексична різноманітність','heading_density'=>'Щільність заголовків','list_density'=>'Щільність списків',
            'internal_link_density'=>'Внутрішні посилання','individuality'=>'Індивідуальність'
        ];
    }

    public static function defaults($profile='businessman'){
        $base=['creator'=>62,'businessman'=>72,'bandit'=>76,'cartel'=>70,'master'=>82][$profile]??72;
        $out=[];
        foreach(self::keys() as $key) $out[$key]=$base;
        $overrides=[
            'creator'=>['conversationality'=>78,'emotionality'=>72,'narrativity'=>80,'individuality'=>90,'formality'=>48],
            'businessman'=>['factual_density'=>84,'structuredness'=>88,'confidence'=>82,'commercial_orientation'=>76,'humor'=>20],
            'bandit'=>['directness'=>94,'confidence'=>92,'practicality'=>88,'formality'=>35,'neutrality'=>38],
            'cartel'=>['formality'=>78,'confidence'=>90,'emotionality'=>44,'individuality'=>82,'structuredness'=>76],
            'master'=>['expertise'=>94,'factual_density'=>92,'neutrality'=>84,'detail'=>88,'humor'=>18],
        ];
        foreach(($overrides[$profile]??[]) as $k=>$v) $out[$k]=$v;
        return $out;
    }

    public static function get($key){ $all=self::all(); return $all[$key]??$all['businessman']; }

    public static function sanitize_traits($input,$profile='businessman'){
        $defaults=self::defaults($profile);
        $out=[];
        foreach(self::keys() as $key) $out[$key]=max(0,min(100,absint($input[$key]??$defaults[$key])));
        return $out;
    }

    public static function traits($project_id,$page_key,$profile,$project_traits=[]){
        $base=self::sanitize_traits($project_traits,$profile);
        $out=[];
        foreach(self::keys() as $key){
            $h=hexdec(substr(hash('sha256',$project_id.'|'.$page_key.'|'.$key),0,6));
            $jitter=($h%11)-5;
            $out[$key]=max(0,min(100,$base[$key]+$jitter));
        }
        return $out;
    }

    public static function mode($traits,$key){
        $v=(int)($traits[$key]??50);
        return $v>=67?'high':($v<=33?'low':'medium');
    }

    public static function variant($project_id,$page_key,$slot,$count=3){
        if($count<1) return 0;
        $h=hexdec(substr(hash('sha256',$project_id.'|'.$page_key.'|'.$slot),0,8));
        return $h%$count;
    }
}
