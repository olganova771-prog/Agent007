<?php
if (!defined('ABSPATH')) exit;
class UWSB_Geo {
    public static function regions(){ return [
        'vinnytsia'=>'Вінницька область','volyn'=>'Волинська область','dnipro'=>'Дніпропетровська область','donetsk'=>'Донецька область',
        'zhytomyr'=>'Житомирська область','zakarpattia'=>'Закарпатська область','zaporizhzhia'=>'Запорізька область','ivano-frankivsk'=>'Івано-Франківська область',
        'kyiv'=>'Київська область','kirovohrad'=>'Кіровоградська область','luhansk'=>'Луганська область','lviv'=>'Львівська область',
        'mykolaiv'=>'Миколаївська область','odesa'=>'Одеська область','poltava'=>'Полтавська область','rivne'=>'Рівненська область',
        'sumy'=>'Сумська область','ternopil'=>'Тернопільська область','kharkiv'=>'Харківська область','kherson'=>'Херсонська область',
        'khmelnytskyi'=>'Хмельницька область','cherkasy'=>'Черкаська область','chernivtsi'=>'Чернівецька область','chernihiv'=>'Чернігівська область',
        'crimea'=>'Автономна Республіка Крим'
    ]; }
    public static function major_cities(){ return [
        ['Київ','Киев','kyiv','kyiv'],['Харків','Харьков','kharkiv','kharkiv'],['Одеса','Одесса','odesa','odesa'],['Дніпро','Днепр','dnipro','dnipro'],
        ['Донецьк','Донецк','donetsk','donetsk'],['Запоріжжя','Запорожье','zaporizhzhia','zaporizhzhia'],['Львів','Львов','lviv','lviv'],
        ['Кривий Ріг','Кривой Рог','kryvyi-rih','dnipro'],['Миколаїв','Николаев','mykolaiv','mykolaiv'],['Маріуполь','Мариуполь','mariupol','donetsk'],
        ['Луганськ','Луганск','luhansk','luhansk'],['Вінниця','Винница','vinnytsia','vinnytsia'],['Макіївка','Макеевка','makiivka','donetsk'],
        ['Севастополь','Севастополь','sevastopol','crimea'],['Сімферополь','Симферополь','simferopol','crimea'],['Херсон','Херсон','kherson','kherson'],
        ['Полтава','Полтава','poltava','poltava'],['Чернігів','Чернигов','chernihiv','chernihiv'],['Черкаси','Черкассы','cherkasy','cherkasy'],
        ['Хмельницький','Хмельницкий','khmelnytskyi','khmelnytskyi'],['Чернівці','Черновцы','chernivtsi','chernivtsi'],['Житомир','Житомир','zhytomyr','zhytomyr'],
        ['Суми','Сумы','sumy','sumy'],['Рівне','Ровно','rivne','rivne'],['Івано-Франківськ','Ивано-Франковск','ivano-frankivsk','ivano-frankivsk'],
        ['Кропивницький','Кропивницкий','kropyvnytskyi','kirovohrad'],['Тернопіль','Тернополь','ternopil','ternopil'],['Луцьк','Луцк','lutsk','volyn'],
        ['Ужгород','Ужгород','uzhhorod','zakarpattia']
    ]; }
    public static function presets($lang='uk'){ $out=[]; foreach(self::major_cities() as $c)$out[]=['name'=>$lang==='ru'?$c[1]:$c[0],'slug'=>$c[2],'region'=>$c[3]]; return $out; }
}