<?php
if (!defined('ABSPATH')) exit;

class UWSB_Geo {
    private static $cities=null;

    public static function regions(){
        return [
            'vinnytsia'=>'Вінницька область','volyn'=>'Волинська область','dnipro'=>'Дніпропетровська область','donetsk'=>'Донецька область',
            'zhytomyr'=>'Житомирська область','zakarpattia'=>'Закарпатська область','zaporizhzhia'=>'Запорізька область','ivano-frankivsk'=>'Івано-Франківська область',
            'kyiv'=>'Київська область','kirovohrad'=>'Кіровоградська область','luhansk'=>'Луганська область','lviv'=>'Львівська область',
            'mykolaiv'=>'Миколаївська область','odesa'=>'Одеська область','poltava'=>'Полтавська область','rivne'=>'Рівненська область',
            'sumy'=>'Сумська область','ternopil'=>'Тернопільська область','kharkiv'=>'Харківська область','kherson'=>'Херсонська область',
            'khmelnytskyi'=>'Хмельницька область','cherkasy'=>'Черкаська область','chernivtsi'=>'Чернівецька область','chernihiv'=>'Чернігівська область',
            'crimea'=>'Автономна Республіка Крим'
        ];
    }

    public static function major_cities(){
        return [
            ['Київ','Киев','kyiv','Київська область'],['Харків','Харьков','kharkiv','Харківська область'],['Одеса','Одесса','odesa','Одеська область'],
            ['Дніпро','Днепр','dnipro','Дніпропетровська область'],['Запоріжжя','Запорожье','zaporizhzhia','Запорізька область'],['Львів','Львов','lviv','Львівська область'],
            ['Кривий Ріг','Кривой Рог','kryvyi-rih','Дніпропетровська область'],['Миколаїв','Николаев','mykolaiv','Миколаївська область'],
            ['Вінниця','Винница','vinnytsia','Вінницька область'],['Херсон','Херсон','kherson','Херсонська область'],['Полтава','Полтава','poltava','Полтавська область'],
            ['Чернігів','Чернигов','chernihiv','Чернігівська область'],['Черкаси','Черкассы','cherkasy','Черкаська область'],['Хмельницький','Хмельницкий','khmelnytskyi','Хмельницька область'],
            ['Чернівці','Черновцы','chernivtsi','Чернівецька область'],['Житомир','Житомир','zhytomyr','Житомирська область'],['Суми','Сумы','sumy','Сумська область'],
            ['Рівне','Ровно','rivne','Рівненська область'],['Івано-Франківськ','Ивано-Франковск','ivano-frankivsk','Івано-Франківська область'],
            ['Кропивницький','Кропивницкий','kropyvnytskyi','Кіровоградська область'],['Тернопіль','Тернополь','ternopil','Тернопільська область'],
            ['Луцьк','Луцк','lutsk','Волинська область'],['Ужгород','Ужгород','uzhhorod','Закарпатська область']
        ];
    }

    private static function ascii_slug($text){
        $map=[
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'H','Ґ'=>'G','Д'=>'D','Е'=>'E','Є'=>'Ye','Ж'=>'Zh','З'=>'Z','И'=>'Y','І'=>'I','Ї'=>'Yi','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ь'=>'','Ю'=>'Yu','Я'=>'Ya',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ie','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'iu','я'=>'ia',
            'Ё'=>'Yo','ё'=>'yo','Э'=>'E','э'=>'e','Ъ'=>'','ъ'=>'','Ы'=>'Y','ы'=>'y'
        ];
        $slug=strtolower(strtr((string)$text,$map));
        $slug=preg_replace('/[^a-z0-9]+/','-',$slug);
        return trim($slug,'-');
    }

    public static function all_cities($lang='uk'){
        if(self::$cities===null){
            $path=UWSB_DIR.'data/ukraine-cities.json';
            $raw=is_readable($path)?file_get_contents($path):'';
            $json=json_decode((string)$raw,true);
            self::$cities=is_array($json)&&isset($json['cities'])&&is_array($json['cities'])?$json['cities']:[];
        }

        $ru=[];
        foreach(self::major_cities() as $row) $ru[$row[0]]=$row[1];

        $out=[];
        foreach(self::$cities as $city){
            $name_uk=sanitize_text_field($city['name_uk']??'');
            if($name_uk==='') continue;
            $out[]=[
                'name_uk'=>$name_uk,
                'name_ru'=>$ru[$name_uk]??$name_uk,
                'name'=>$lang==='ru'?($ru[$name_uk]??$name_uk):$name_uk,
                'region'=>sanitize_text_field($city['region_uk']??''),
                'slug'=>self::ascii_slug($city['name_uk']??($city['slug']??'')),
            ];
        }
        return $out;
    }

    public static function count(){ return count(self::all_cities('uk')); }

    public static function by_slug($slug,$lang='uk'){
        $slug=sanitize_title($slug);
        foreach(self::all_cities($lang) as $city) if($city['slug']===$slug) return $city;
        return null;
    }
}
