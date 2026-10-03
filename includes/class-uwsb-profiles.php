<?php
if (!defined('ABSPATH')) exit;
class UWSB_Profiles {
    public static function all(){ return [
        'creator'=>['label'=>'CREATOR','class'=>'uwsb-creator','hero'=>'split','cards'=>'asym','density'=>'airy','tone'=>'expressive'],
        'businessman'=>['label'=>'BUSINESSMAN','class'=>'uwsb-businessman','hero'=>'corporate','cards'=>'grid','density'=>'balanced','tone'=>'precise'],
        'bandit'=>['label'=>'BANDIT','class'=>'uwsb-bandit','hero'=>'poster','cards'=>'hard','density'=>'dense','tone'=>'direct'],
        'cartel'=>['label'=>'CARTEL','class'=>'uwsb-cartel','hero'=>'cinematic','cards'=>'lux','density'=>'dramatic','tone'=>'controlled'],
        'master'=>['label'=>'MASTER','class'=>'uwsb-master','hero'=>'editorial','cards'=>'minimal','density'=>'measured','tone'=>'authoritative'],
    ]; }
    public static function get($key){ $all=self::all(); return $all[$key]??$all['businessman']; }
    public static function traits($project_id,$page_key,$profile){
        $keys=['formality','conversationality','directness','expertise','factual_density','detail','emotionality','friendliness','confidence','neutrality','practicality','benefit_orientation','feature_orientation','use_case_orientation','examples','structuredness','narrativity','questioning_style','direct_address','humor','local_orientation','commercial_orientation','informational_orientation','faq_orientation','syntactic_variation','lexical_diversity','heading_density','list_density','internal_link_density','individuality'];
        $base=['creator'=>65,'businessman'=>72,'bandit'=>78,'cartel'=>74,'master'=>82][$profile]??70; $out=[];
        foreach($keys as $k){ $h=hexdec(substr(hash('sha256',$project_id.'|'.$page_key.'|'.$profile.'|'.$k),0,6)); $out[$k]=max(20,min(95,$base+(($h%31)-15))); }
        return $out;
    }
    public static function variant($project_id,$page_key,$slot,$count=3){ if($count<1)return 0; $h=hexdec(substr(hash('sha256',$project_id.'|'.$page_key.'|'.$slot),0,8)); return $h%$count; }
}