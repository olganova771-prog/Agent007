<?php
if (!defined('ABSPATH')) exit;

class UWSB_Admin {
    public static function init(){
        add_action('admin_menu',[__CLASS__,'menu']);
        add_action('admin_post_uwsb_create_project',[__CLASS__,'create']);
        add_action('admin_post_uwsb_generate',[__CLASS__,'generate']);
        add_action('admin_post_uwsb_publish',[__CLASS__,'publish']);
        add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
        add_action('admin_notices',[__CLASS__,'db_notice']);
    }

    public static function assets($hook){
        if(strpos($hook,'uwsb')===false) return;
        wp_enqueue_style('uwsb-admin',UWSB_URL.'assets/admin.css',[],UWSB_VERSION);
        wp_enqueue_script('uwsb-admin',UWSB_URL.'assets/admin.js',[],UWSB_VERSION,true);
    }

    public static function db_notice(){
        if(!current_user_can('manage_options')) return;
        $error=get_option('uwsb_db_error');
        if($error) echo '<div class="notice notice-error"><p>'.esc_html('Site Factory: '.$error).'</p></div>';
    }

    public static function menu(){
        add_menu_page('Site Factory','Site Factory','manage_options','uwsb',[__CLASS__,'dashboard'],'dashicons-layout',3);
        add_submenu_page('uwsb','Новый проект','Новый проект','manage_options','uwsb-new',[__CLASS__,'wizard']);
    }

    public static function demo_product(){
        return [
            'name_uk'=>'Портативна LED-лампа Aurora Mini',
            'name_ru'=>'Портативная LED-лампа Aurora Mini',
            'short_uk'=>'Компактна акумуляторна лампа для робочого столу, читання та резервного освітлення.',
            'short_ru'=>'Компактная аккумуляторная лампа для рабочего стола, чтения и резервного освещения.',
            'description_uk'=>'Aurora Mini — переносна LED-лампа з трьома рівнями яскравості, сенсорним керуванням і заряджанням через USB-C. Підходить для дому, робочого місця та поїздок.',
            'description_ru'=>'Aurora Mini — переносная LED-лампа с тремя уровнями яркости, сенсорным управлением и зарядкой через USB-C. Подходит для дома, рабочего места и поездок.',
            'features_uk'=>"3 рівні яскравості\nUSB-C заряджання\nВбудований акумулятор\nСенсорна кнопка\nКомпактний корпус",
            'features_ru'=>"3 уровня яркости\nUSB-C зарядка\nВстроенный аккумулятор\nСенсорная кнопка\nКомпактный корпус",
            'benefits_uk'=>"Не потребує постійного підключення до розетки\nЗручно переносити між кімнатами\nМ'яке світло для читання та роботи",
            'benefits_ru'=>"Не требует постоянного подключения к розетке\nУдобно переносить между комнатами\nМягкий свет для чтения и работы",
            'limitations_uk'=>"Не є професійним студійним освітленням\nЧас роботи залежить від обраної яскравості",
            'limitations_ru'=>"Не является профессиональным студийным освещением\nВремя работы зависит от выбранной яркости",
            'use_cases_uk'=>"Робочий стіл\nЧитання\nНічне освітлення\nПоїздки",
            'use_cases_ru'=>"Рабочий стол\nЧтение\nНочное освещение\nПоездки",
            'variants_uk'=>"Білий корпус\nЧорний корпус",
            'variants_ru'=>"Белый корпус\nЧерный корпус",
            'facts_uk'=>"Живлення від вбудованого акумулятора\nЗаряджання через USB-C\nТри режими яскравості",
            'facts_ru'=>"Питание от встроенного аккумулятора\nЗарядка через USB-C\nТри режима яркости",
            'faq_uk'=>"Чи працює без кабелю? :: Так, від вбудованого акумулятора.\nЯк заряджається? :: Через USB-C.",
            'faq_ru'=>"Работает без кабеля? :: Да, от встроенного аккумулятора.\nКак заряжается? :: Через USB-C.",
            'queries_uk'=>"портативна led лампа\nнастільна лампа usb-c\nакумуляторна лампа",
            'queries_ru'=>"портативная led лампа\nнастольная лампа usb-c\nаккумуляторная лампа",
            'forbidden_uk'=>"Не заявляти медичний ефект\nНе вигадувати сертифікацію",
            'forbidden_ru'=>"Не заявлять медицинский эффект\nНе выдумывать сертификацию",
            'price'=>'899',
            'currency'=>'UAH',
            'sku'=>'AURORA-MINI-DEMO',
            'geo_notes'=>'Демо-товар: локальні адреси та строки доставки не задані.',
        ];
    }

    public static function demo_config(){
        return [
            'products'=>[self::demo_product()],
            'contacts_uk'=>"Telegram: @example\nEmail: demo@example.com",
            'contacts_ru'=>"Telegram: @example\nEmail: demo@example.com",
            'geo_all_cities'=>true,
            'selected_cities'=>[],
            'geo_index_all'=>false,
            'editorial'=>UWSB_Profiles::defaults('businessman'),
        ];
    }

    public static function dashboard(){
        if(!current_user_can('manage_options')) return;
        global $wpdb;
        $rows=UWSB_DB::projects();
        echo '<div class="wrap uwsb-admin"><div class="uwsb-top"><div><h1>Site Factory 3.0</h1><p>Проект → Site Plan → генерация → QA → публикация.</p></div><a class="button button-primary button-hero" href="'.esc_url(admin_url('admin.php?page=uwsb-new')).'">Новый проект</a></div>';
        echo '<div class="uwsb-list">';
        foreach($rows as $r){
            $planned=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.UWSB_DB::t('pages').' WHERE project_id=%d',(int)$r['id']));
            $generated=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".UWSB_DB::t('pages')." WHERE project_id=%d AND status='generated'",(int)$r['id']));
            echo '<div class="uwsb-project"><h2>'.esc_html($r['name']).'</h2><p>'.esc_html(strtoupper($r['profile']).' · '.$r['site_type']).'</p><div class="uwsb-progress"><span style="width:'.esc_attr($planned?min(100,round($generated/$planned*100)):0).'%"></span></div><p>'.$generated.' / '.$planned.' страниц</p><a class="button" href="'.esc_url(admin_url('admin.php?page=uwsb&project='.(int)$r['id'])).'">Открыть</a></div>';
        }
        echo '</div>';
        if(isset($_GET['project'])) self::project_view(absint($_GET['project']));
        echo '</div>';
    }

    public static function wizard(){
        if(!current_user_can('manage_options')) return;
        $demo=self::demo_product();
        echo '<div class="wrap uwsb-admin"><h1>Новый сайт</h1><p class="uwsb-lead">Быстрый режим: можно изменить готовый тестовый товар и сразу получить Site Plan. Расширенные поля уже заполнены для проверки полного цикла.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" id="uwsb-project-form"><input type="hidden" name="action" value="uwsb_create_project">';
        wp_nonce_field('uwsb_create_project');

        echo '<div class="uwsb-panel"><h2>1. Проект</h2><div class="uwsb-row"><label>Название сайта<input name="name" value="Aurora Demo Site" required></label><label>Тип<select name="site_type"><option value="catalog">Каталог</option><option value="store">Магазин</option><option value="company">Компания / услуги</option><option value="leadgen">Lead generation</option><option value="informational">Информационный</option></select></label></div><label class="uwsb-check"><input type="checkbox" name="secondary_ru" value="1" checked> Создать дополнительную RU-версию</label></div>';

        echo '<div class="uwsb-panel"><div class="uwsb-panel-head"><div><h2>2. Товары / услуги</h2><p>Один полностью заполненный тестовый товар уже внутри.</p></div><button type="button" class="button" data-uwsb-add-product>+ Добавить товар</button></div><div id="uwsb-products">';
        self::product_card(0,$demo,true);
        echo '</div></div>';

        echo '<div class="uwsb-panel"><h2>3. География</h2><p>Встроено <strong>'.(int)UWSB_Geo::count().'</strong> городов Украины. Генерация и индексация разделены.</p><label class="uwsb-check"><input type="checkbox" name="geo_all_cities" value="1" checked> Создать Product+City для всех городов</label><label class="uwsb-check"><input type="checkbox" name="geo_index_all" value="1"> Индексировать geo-страницы без локальных фактов (по умолчанию выключено)</label></div>';

        echo '<div class="uwsb-panel"><h2>4. Контакты</h2><div class="uwsb-row"><label>UA<textarea name="contacts_uk" rows="3">Telegram: @example&#10;Email: demo@example.com</textarea></label><label>RU<textarea name="contacts_ru" rows="3">Telegram: @example&#10;Email: demo@example.com</textarea></label></div></div>';

        $profiles=UWSB_Profiles::all();
        echo '<div class="uwsb-panel"><h2>5. Характер сайта</h2><div class="uwsb-profiles">';
        foreach($profiles as $k=>$p) echo '<label><input type="radio" name="profile" value="'.esc_attr($k).'" '.checked($k,'businessman',false).'><span><strong>'.esc_html($p['label']).'</strong><small>'.esc_html($p['tone']).'</small></span></label>';
        echo '</div><details class="uwsb-details"><summary>30 editorial-параметров</summary><div class="uwsb-traits">';
        $labels=UWSB_Profiles::labels();$defaults=UWSB_Profiles::defaults('businessman');
        foreach(UWSB_Profiles::keys() as $key) echo '<label><span>'.esc_html($labels[$key]??$key).'</span><input type="range" min="0" max="100" name="editorial['.esc_attr($key).']" value="'.(int)$defaults[$key].'"><output>'.(int)$defaults[$key].'</output></label>';
        echo '</div></details></div><p><button class="button button-primary button-hero">Создать Site Plan</button></p></form></div>';
    }

    private static function product_card($i,$p,$expanded=false){
        echo '<div class="uwsb-product-card" data-product><div class="uwsb-panel-head"><h3>Товар <span data-product-number>'.($i+1).'</span></h3><button type="button" class="button-link-delete" data-uwsb-remove-product>Удалить</button></div>';
        echo '<div class="uwsb-row"><label>Название UA<input name="products['.$i.'][name_uk]" value="'.esc_attr($p['name_uk']??'').'" required></label><label>Название RU<input name="products['.$i.'][name_ru]" value="'.esc_attr($p['name_ru']??'').'"></label></div>';
        echo '<div class="uwsb-row"><label>Коротко UA<textarea name="products['.$i.'][short_uk]" rows="2">'.esc_textarea($p['short_uk']??'').'</textarea></label><label>Коротко RU<textarea name="products['.$i.'][short_ru]" rows="2">'.esc_textarea($p['short_ru']??'').'</textarea></label></div>';
        echo '<div class="uwsb-row"><label>Описание UA<textarea name="products['.$i.'][description_uk]" rows="4">'.esc_textarea($p['description_uk']??'').'</textarea></label><label>Описание RU<textarea name="products['.$i.'][description_ru]" rows="4">'.esc_textarea($p['description_ru']??'').'</textarea></label></div>';
        echo '<div class="uwsb-row"><label>Цена<input name="products['.$i.'][price]" value="'.esc_attr($p['price']??'').'"></label><label>Валюта<input name="products['.$i.'][currency]" value="'.esc_attr($p['currency']??'UAH').'"></label><label>SKU<input name="products['.$i.'][sku]" value="'.esc_attr($p['sku']??'').'"></label></div>';
        echo '<details class="uwsb-details" '.($expanded?'open':'').'><summary>Расширенные данные товара</summary>';
        foreach(['features'=>'Характеристики','benefits'=>'Преимущества','limitations'=>'Ограничения','use_cases'=>'Сценарии','variants'=>'Варианты','facts'=>'Факты','faq'=>'FAQ','queries'=>'Запросы','forbidden'=>'Запрещённые утверждения'] as $field=>$label){
            echo '<div class="uwsb-row"><label>'.$label.' UA<textarea name="products['.$i.']['.$field.'_uk]" rows="3">'.esc_textarea($p[$field.'_uk']??'').'</textarea></label><label>'.$label.' RU<textarea name="products['.$i.']['.$field.'_ru]" rows="3">'.esc_textarea($p[$field.'_ru']??'').'</textarea></label></div>';
        }
        echo '<label>Geo notes<textarea name="products['.$i.'][geo_notes]" rows="2">'.esc_textarea($p['geo_notes']??'').'</textarea></label></details></div>';
    }

    public static function create(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('uwsb_create_project');
        $profiles=array_keys(UWSB_Profiles::all());
        $profile=sanitize_key($_POST['profile']??'businessman');
        if(!in_array($profile,$profiles,true)) $profile='businessman';
        $type=sanitize_key($_POST['site_type']??'catalog');
        if(!in_array($type,['catalog','company','leadgen','store','informational'],true)) $type='catalog';
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        if($name==='') wp_die('Название обязательно.');

        $products=UWSB_Planner::normalize_products(wp_unslash($_POST['products']??[]));
        if(!$products) wp_die('Добавьте хотя бы один товар/услугу.');

        $config=[
            'products'=>$products,
            'contacts_uk'=>sanitize_textarea_field(wp_unslash($_POST['contacts_uk']??'')),
            'contacts_ru'=>sanitize_textarea_field(wp_unslash($_POST['contacts_ru']??'')),
            'geo_all_cities'=>!empty($_POST['geo_all_cities']),
            'selected_cities'=>[],
            'geo_index_all'=>!empty($_POST['geo_index_all']),
            'editorial'=>UWSB_Profiles::sanitize_traits(wp_unslash($_POST['editorial']??[]),$profile),
        ];

        $id=UWSB_DB::create_project([
            'name'=>$name,'site_type'=>$type,'primary_lang'=>'uk',
            'secondary_lang'=>!empty($_POST['secondary_ru'])?'ru':'',
            'profile'=>$profile,'config'=>$config
        ]);
        if(!$id) wp_die('Не удалось создать проект.');
        UWSB_Engine::plan_project($id);
        wp_safe_redirect(admin_url('admin.php?page=uwsb&project='.$id));exit;
    }

    private static function project_view($id){
        global $wpdb;
        $p=UWSB_DB::project($id);if(!$p)return;
        $plans=UWSB_DB::plans($id);
        $qa=UWSB_QA::run($id);
        $stats=['planned'=>0,'generated'=>0];
        foreach($plans as $r) $stats[$r['status']] = ($stats[$r['status']]??0)+1;
        $jobs=$wpdb->get_results($wpdb->prepare('SELECT status,COUNT(*) c FROM '.UWSB_DB::t('jobs').' WHERE project_id=%d GROUP BY status',$id),ARRAY_A);
        $jobstats=[];foreach($jobs as $j)$jobstats[$j['status']]=(int)$j['c'];

        echo '<hr><div class="uwsb-project-head"><div><h2>'.esc_html($p['name']).'</h2><p><strong>Site Plan:</strong> '.count($plans).' · Generated: '.(int)($stats['generated']??0).' · Queue: '.(int)($jobstats['queued']??0).' · Failed: '.(int)($jobstats['failed']??0).'</p></div><div><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=uwsb_generate&project='.$id),'uwsb_generate')).'">Generate / Resume 200</a> <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=uwsb_publish&project='.$id),'uwsb_publish')).'">Publish site</a></div></div>';
        echo '<p class="description">Product+City создаются отдельно от решения об индексации: слабые geo-страницы получают noindex, если не включён geo_index_all.</p>';
        echo '<div class="uwsb-table-wrap"><table class="widefat striped"><thead><tr><th>Язык</th><th>Тип</th><th>Page key</th><th>Index</th><th>Статус</th><th>Preview</th></tr></thead><tbody>';
        foreach(array_slice($plans,0,500) as $r) echo '<tr><td>'.esc_html(strtoupper($r['lang'])).'</td><td>'.esc_html($r['page_type']).'</td><td>'.esc_html($r['page_key']).'</td><td>'.($r['indexable']?'INDEX':'NOINDEX').'</td><td>'.esc_html($r['status']).'</td><td>'.($r['wp_post_id']?'<a target="_blank" href="'.esc_url(get_preview_post_link((int)$r['wp_post_id'])).'">Preview</a>':'—').'</td></tr>';
        echo '</tbody></table></div>';
        if(count($plans)>500) echo '<p>Показаны первые 500 строк из '.count($plans).'. Очередь обрабатывает весь Site Plan.</p>';
        echo '<h3>QA</h3>';
        if(!$qa) echo '<p>Нет проблем.</p>'; else { echo '<ul class="uwsb-qa">'; foreach($qa as $i) echo '<li><strong>'.esc_html(strtoupper($i['severity'])).'</strong> '.esc_html($i['code']).' — '.esc_html($i['message']).'</li>'; echo '</ul>'; }
    }

    public static function generate(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('uwsb_generate');
        $id=absint($_GET['project']??0);
        UWSB_Engine::process_jobs(200,$id);
        UWSB_QA::run($id);
        wp_safe_redirect(admin_url('admin.php?page=uwsb&project='.$id));exit;
    }

    public static function publish(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('uwsb_publish');
        $id=absint($_GET['project']??0);
        $r=UWSB_Engine::publish_project($id);
        $args=['page'=>'uwsb','project'=>$id];
        if(is_wp_error($r)) $args['uwsb_error']=$r->get_error_code();
        wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;
    }
}
