<?php
/*
Plugin Name: Dox2TA Opendota Toolkit
Description: ابزارهای نمایش داده‌های Dota 2 از OpenDota (رکوردها و هیروها) به صورت شورت‌کد. نویسنده: MirzaFreddy
Version: 1.0.0
Author: MirzaFreddy
Text Domain: Dox2TA-opendota-toolkit
Domain Path: /languages
*/

if (!defined('ABSPATH')) { exit; }

class Opendota_Records_Plugin {
    private $version = '1.0.0';
    private $textdomain = 'mirza-opendota-toolkit';

    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('odr_refresh_daily_cache', [$this, 'refresh_daily_cache']);
    }

    private function get_hero_map() {
        static $map = null;
        if ($map !== null) return $map;
        $stats = $this->get_hero_stats();
        if (is_wp_error($stats) || !is_array($stats)) { $map = []; return $map; }
        $m = [];
        foreach ($stats as $h) {
            if (!isset($h['id'])) continue;
            $img_path = '';
            if (!empty($h['img'])) { $img_path = $h['img']; }
            elseif (!empty($h['icon'])) { $img_path = $h['icon']; }
            // Build full URL using Steam CDN as base
            $base = 'https://cdn.cloudflare.steamstatic.com';
            $full = $img_path ? ($base . $img_path) : '';
            $m[intval($h['id'])] = [
                'name' => $h['localized_name'] ?? '',
                'img'  => $full,
            ];
        }
        $map = $m;
        return $map;
    }

    private function time_ago_fa($epoch) {
        if (empty($epoch)) return '';
        $diff = time() - intval($epoch);
        if ($diff < 0) $diff = 0;
        $units = [
            ['sec', 60, 'ثانیه پیش'],
            ['min', 60, 'دقیقه پیش'],
            ['hour', 24, 'ساعت پیش'],
            ['day', 30, 'روز پیش'],
            ['month', 12, 'ماه پیش'],
            ['year', PHP_INT_MAX, 'سال پیش'],
        ];
        $val = $diff; $label = 'ثانیه پیش';
        foreach ($units as $u) {
            if ($val < $u[1]) { $label = $u[2]; break; }
            $val = floor($val / $u[1]);
        }
        return number_format_i18n(max(0, $val)) . ' ' . $label;
    }

    public function enqueue_assets() {
        $handle = 'opendota-records-styles';
        $src = plugins_url('assets/opendota-records.css', __FILE__);
        wp_register_style($handle, $src, [], $this->version);
        wp_enqueue_style($handle);

        $js_handle = 'opendota-table-sort';
        $js_src = plugins_url('assets/opendota-table-sort.js', __FILE__);
        wp_register_script($js_handle, $js_src, [], $this->version, true);
        wp_enqueue_script($js_handle);
    }

    public function register_shortcodes() {
        add_shortcode('opendota_records', [$this, 'shortcode_records']);
        add_shortcode('opendota_all_tables', [$this, 'shortcode_all_tables']);
        add_shortcode('opendota_heroes_pro', [$this, 'shortcode_heroes_pro']);
        add_shortcode('opendota_heroes_public', [$this, 'shortcode_heroes_public']);
        add_shortcode('opendota_heroes_turbo', [$this, 'shortcode_heroes_turbo']);
    }

    public function register_admin_menu() {
        add_menu_page(
            'جعبه ابزار OpenDota - راهنما',
            'راهنمای OpenDota',
            'manage_options',
            'opendota-records-help',
            [$this, 'render_help_page'],
            'dashicons-editor-help',
            80
        );
    }

    public function render_help_page() {
        echo '<div class="wrap" dir="rtl" style="text-align:right">';
        echo '<h1>راهنمای افزونه Mirza OpenDota Toolkit</h1>';
        echo '<p>این افزونه داده‌های هفتگی Dota 2 را از OpenDota دریافت و در جداول فارسی با طراحی گلس‌مورفیسم و نوارهای پیشرفت نمایش می‌دهد.</p>';
        echo '<h2>۱. شورت‌کدهای رکوردها</h2>';
        echo '<p>برای نمایش رکوردهای هفتگی در دسته‌بندی‌های مختلف از شورت‌کد زیر استفاده کنید:</p>';
        echo '<pre>[opendota_records metric="duration" period="week" limit="10"]</pre>';
        echo '<p><strong>پارامترها:</strong></p>';
        echo '<ul>';
        echo '<li><strong>metric</strong> (ضروری): یکی از مقادیر زیر</li>';
        echo '<ul>';
        echo '<li>duration — بیشترین زمان بازی</li>';
        echo '<li>kills — بیشترین کشتن</li>';
        echo '<li>deaths — بیشترین مرگ</li>';
        echo '<li>assists — بیشترین کمک</li>';
        echo '<li>gpm — بیشترین طلا در دقیقه</li>';
        echo '<li>xpm — بیشترین تجربه در دقیقه</li>';
        echo '<li>last_hits — بیشترین Last Hits</li>';
        echo '<li>denies — بیشترین Denies</li>';
        echo '<li>hero_damage — بیشترین آسیب به هیرو</li>';
        echo '<li>tower_damage — بیشترین آسیب به برج</li>';
        echo '<li>hero_healing — بیشترین درمان هیرو</li>';
        echo '</ul>';
        echo '<li><strong>period</strong>: <code>week</code> (۷ روز اخیر) یا <code>all</code> (کل زمان، در صورت پشتیبانی API).</li>';
        echo '<li><strong>limit</strong> (اختیاری): تعداد ردیف‌ها (پیش‌فرض 10).</li>';
        echo '</ul>';
        echo '<p><strong>مثال‌ها:</strong></p>';
        echo '<pre>';
        echo '[opendota_records metric="duration" period="week" limit="8"]' . "\n";
        echo '[opendota_records metric="kills" limit="5"]' . "\n";
        echo '[opendota_records metric="hero_damage" period="week" limit="12"]';
        echo '</pre>';
        echo '<h2>۲. نمایش همه جداول رکورد</h2>';
        echo '<p>برای نمایش همه دسته‌بندی‌های رکورد پشت سر هم:</p>';
        echo '<pre>[opendota_all_tables period="week" limit="10"]</pre>';
        echo '<p><strong>نکته:</strong> این شورت‌کد تمام متریک‌های بالا را به ترتیب نمایش می‌دهد.</p>';
        echo '<h2>۳. شورت‌کدهای هیروها</h2>';
        echo '<p>این شورت‌کدها آمار کلی هیروها را در سه دسته نمایش می‌دهند و ستون‌ها با کلیک مرتب می‌شوند.</p>';
        echo '<p><strong>پارامترهای مشترک:</strong></p>';
        echo '<ul>';
        echo '<li><strong>limit</strong> (اختیاری): تعداد هیروها برای نمایش (پیش‌فرض 100).</li>';
        echo '<li><strong>sort_by</strong> (اختیاری): ستون مرتب‌سازی پیش‌فرض.</li>';
        echo '</ul>';
        echo '<h3>پروفشنال</h3>';
        echo '<pre>[opendota_heroes_pro limit="10" sort_by="pb"]</pre>';
        echo '<p>ستون‌ها: هیرو (با آواتار)، درصد حضور (پیک+بن)، درصد پیک، درصد بن، درصد برد</p>';
        echo '<p><strong>مقادیر sort_by:</strong> <code>pb</code> (حضور)، <code>pp</code> (پیک)، <code>ban</code> (بن)، <code>pw</code> (برد)</p>';
        echo '<h3>پابلیک (همه رنک‌ها)</h3>';
        echo '<pre>[opendota_heroes_public]</pre>';
        echo '<p>ستون‌ها: هیرو (با آواتار)، درصد پیک کلی، درصد برد کلی، ایمورتال/دی‌واین/انشنت درصد پیک/برد، لجند/آرکان درصد پیک/برد، کروسیدر/گاردین/هرالد درصد پیک/برد</p>';
        echo '<h3>توربو</h3>';
        echo '<pre>[opendota_heroes_turbo limit="5" sort_by="tw"]</pre>';
        echo '<p>ستون‌ها: هیرو (با آواتار)، درصد پیک توربو، درصد برد توربو</p>';
        echo '<p><strong>مقادیر sort_by:</strong> <code>tp</code> (پیک)، <code>tw</code> (برد)</p>';
        echo '<h2>۴. مثال‌های کاربردی برای هیروها</h2>';
        echo '<pre>';
        echo '<!-- ۵ هیرو برتر پروفشنال بر اساس وین ریت -->' . "\n";
        echo '[opendota_heroes_pro limit="5" sort_by="pw"]' . "\n\n";
        echo '<!-- ۱۰ هیرو برتر توربو بر اساس پیک ریت -->' . "\n";
        echo '[opendota_heroes_turbo limit="10" sort_by="tp"]' . "\n\n";
        echo '<!-- ۸ هیرو با بیشترین حضور در پرو -->' . "\n";
        echo '[opendota_heroes_pro limit="8" sort_by="pb"]';
        echo '</pre>';
        echo '<h2>۵. نکات فنی</h2>';
        echo '<ul>';
        echo '<li>کش: آمار هیروها تا ۲۴ ساعت کش می‌شود. رکوردها در صورت موفقیت تا ۲۴ ساعت و در صورت خالی بودن پاسخ API به‌صورت موقت ۵ دقیقه کش می‌شوند.</li>';
        echo '<li>وارم‌آپ روزانه: یک کران روزانه (<code>odr_refresh_daily_cache</code>) چند نمای معمول را پیش‌دریافت می‌کند تا صفحات سریع‌تر بارگذاری شوند.</li>';
        echo '<li>برای به‌روزرسانی سریع، می‌توانید موقتاً مقدار <code>limit</code> را تغییر دهید تا کلید کش عوض شود.</li>';
        echo '<li>ستون‌های جداول هیروها با کلیک مرتب‌سازی می‌شوند (صعودی/نزولی).</li>';
        echo '<li>در جداول رکوردها، ستون «مقدار» نوار پیشرفت نسبی به رنگ قرمز→سبز دارد.</li>';
        echo '<li>تصاویر هیروها از CDN استیم بارگذاری می‌شوند.</li>';
        echo '</ul>';
        echo '<h2>۶. مثال کامل در یک برگه</h2>';
        echo '<pre>';
        echo '<!-- رکوردهای منتخب -->' . "\n";
        echo '[opendota_records metric="duration" limit="8"]' . "\n";
        echo '[opendota_records metric="kills" limit="5"]' . "\n";
        echo '[opendota_records metric="hero_damage" limit="6"]' . "\n\n";
        echo '<!-- همه جداول رکورد -->' . "\n";
        echo '[opendota_all_tables limit="10"]' . "\n\n";
        echo '<!-- آمار هیروها (با پارامترهای پیشرفته) -->' . "\n";
        echo '[opendota_heroes_pro limit="5" sort_by="pw"]' . "\n";
        echo '[opendota_heroes_turbo limit="8" sort_by="tp"]' . "\n";
        echo '[opendota_heroes_public]';
        echo '</pre>';
        echo '<p><strong>نویسنده:</strong> <strong>MirzaFreddy</strong></p>';
        echo '</div>';
    }

    public function shortcode_all_tables($atts) {
        $atts = shortcode_atts([
            'period' => 'week',
            'limit' => 10,
        ], $atts, 'opendota_all_tables');

        $metrics = array_keys($this->metrics());
        $out = '';
        foreach ($metrics as $metric) {
            $out .= $this->render_table($metric, $atts['period'], intval($atts['limit']));
        }
        return $out;
    }

    public function shortcode_records($atts) {
        $atts = shortcode_atts([
            'metric' => 'duration',
            'period' => 'week',
            'limit' => 10,
        ], $atts, 'opendota_records');

        $metric = sanitize_key($atts['metric']);
        $period = sanitize_key($atts['period']);
        $limit = intval($atts['limit']);
        if ($limit <= 0) { $limit = 10; }

        return $this->render_table($metric, $period, $limit);
    }

    private function metrics() {
        return [
            'duration' => [ 'column' => 'duration', 'label' => 'بیشترین زمان بازی', 'format' => 'duration' ],
            'kills' => [ 'column' => 'kills', 'label' => 'بیشترین کشتن (Kills)', 'format' => 'number' ],
            'deaths' => [ 'column' => 'deaths', 'label' => 'بیشترین مرگ (Deaths)', 'format' => 'number' ],
            'assists' => [ 'column' => 'assists', 'label' => 'بیشترین کمک (Assists)', 'format' => 'number' ],
            'gpm' => [ 'column' => 'gold_per_min', 'label' => 'بیشترین GPM', 'format' => 'number' ],
            'xpm' => [ 'column' => 'xp_per_min', 'label' => 'بیشترین XPM', 'format' => 'number' ],
            'last_hits' => [ 'column' => 'last_hits', 'label' => 'بیشترین Last Hits', 'format' => 'number' ],
            'denies' => [ 'column' => 'denies', 'label' => 'بیشترین Denies', 'format' => 'number' ],
            'hero_damage' => [ 'column' => 'hero_damage', 'label' => 'بیشترین آسیب به هیرو', 'format' => 'number' ],
            'tower_damage' => [ 'column' => 'tower_damage', 'label' => 'بیشترین آسیب به برج', 'format' => 'number' ],
            'hero_healing' => [ 'column' => 'hero_healing', 'label' => 'بیشترین درمان هیرو', 'format' => 'number' ],
        ];
    }

    private function render_table($metric, $period, $limit) {
        $metrics = $this->metrics();
        if (!isset($metrics[$metric])) {
            return '<div class="odr-error" dir="rtl" style="color:#b71c1c">دسته‌بندی نامعتبر است.</div>';
        }
        if (!in_array($period, ['week','all'], true)) {
            return '<div class="odr-error" dir="rtl" style="color:#b71c1c">بازه زمانی نامعتبر است.</div>';
        }

        $rows = $this->fetch_records($metrics[$metric]['column'], $limit, $period);

        if (is_wp_error($rows)) {
            return '<div class="odr-error" dir="rtl" style="color:#b71c1c">خطا در دریافت اطلاعات از OpenDota</div>';
        }
        if (empty($rows)) {
            return '<div class="odr-empty" dir="rtl">داده‌ای یافت نشد.</div>';
        }

        // find max value for relative bar
        $max_value = 0;
        foreach ($rows as $rr) { if (isset($rr['value'])) { $max_value = max($max_value, floatval($rr['value'])); } }

        $uid = 'odr_' . uniqid();
        ob_start();
        echo '<div class="odr-skeleton-wrap" id="' . esc_attr($uid) . '_skeleton">';
        echo '<div class="odr-skeleton">';
        for ($i=0; $i<$limit; $i++) {
            echo '<div class="odr-skeleton-row">';
            $cols = ($metrics[$metric]['column'] === 'duration') ? 4 : 5;
            for ($j=0; $j<$cols; $j++) {
                echo '<div class="odr-skeleton-col"></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
        echo '<div class="odr-table-wrap" dir="rtl" id="' . esc_attr($uid) . '">';
        echo '<table class="odr-table">';
        echo '<thead><tr>';
        echo '<th>#</th>';
        echo '<th>بازی</th>';
        if ($metrics[$metric]['column'] !== 'duration') {
            echo '<th>هیرو</th>';
        }
        echo '<th>مقدار</th>';
        echo '<th>زمان</th>';
        echo '</tr></thead><tbody>';

        $i = 1;
        foreach ($rows as $r) {
            $match_id = isset($r['match_id']) ? intval($r['match_id']) : 0;
            $value = isset($r['value']) ? $r['value'] : 0;
            $display = $metrics[$metric]['format'] === 'duration' ? $this->format_duration($value) : number_format_i18n($value);
            $link = $match_id ? 'https://www.opendota.com/matches/' . $match_id : '#';
            echo '<tr>';
            echo '<td>' . number_format_i18n($i) . '</td>';
            echo '<td><a target="_blank" rel="noopener" href="' . esc_url($link) . '">' . esc_html($match_id) . '</a></td>';
            // hero cell (only if not duration)
            if ($metrics[$metric]['column'] !== 'duration') {
                $hero_cell = '';
                if (!empty($r['hero_id'])) {
                    $hero = $this->get_hero_map()[intval($r['hero_id'])] ?? null;
                    if ($hero) {
                        $img = $hero['img'] ?? '';
                        $name = $hero['name'] ?? '';
                        if ($img) {
                            $hero_cell = '<div class="odr-hero" title="' . esc_attr($name) . '">'
                                       . '<img src="' . esc_url($img) . '" alt="' . esc_attr($name) . '" loading="lazy" />'
                                       . '<span class="odr-hero-name">' . esc_html($name) . '</span>'
                                       . '</div>';
                        } else {
                            $hero_cell = '<span class="odr-hero-name">' . esc_html($name) . '</span>';
                        }
                    }
                }
                echo '<td>' . $hero_cell . '</td>';
            }
            // render value with relative bar
            $pct = ($max_value > 0) ? (floatval($value) / $max_value) * 100.0 : 0.0;
            $pct = max(0, min(100, $pct));
            $hue = (int) round(($pct / 100) * 120);
            echo '<td>';
            echo '<div class="odr-val">';
            echo '<span class="odr-val-text">' . esc_html($display) . '</span>';
            echo '<span class="odr-bar"><span class="odr-bar-fill" style="width:' . esc_attr($pct) . '%; background-color:hsl(' . esc_attr($hue) . ',70%,45%)"></span></span>';
            echo '</div>';
            echo '</td>';
            // time ago
            $time_text = $this->time_ago_fa(isset($r['start_time']) ? intval($r['start_time']) : null);
            if ($time_text === '') { $time_text = '—'; }
            echo '<td>' . esc_html($time_text) . '</td>';
            echo '</tr>';
            $i++;
        }

        echo '</tbody></table></div>';
        echo '<script>(function(){var s=document.getElementById("' . esc_js($uid) . '_skeleton"),t=document.getElementById("' . esc_js($uid) . '");if(s&&t){setTimeout(function(){s.classList.add("odr-loaded");t.classList.add("odr-loaded");},300);}})();</script>';
        return ob_get_clean();
    }

    private function format_duration($seconds) {
        $seconds = intval($seconds);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        if ($h > 0) {
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%02d:%02d', $m, $s);
    }

    private function fetch_records($column, $limit = 10, $period = 'week') {
        $limit = max(1, intval($limit));
        $period = ($period === 'all') ? 'all' : 'week';
        $cache_key = 'odr_' . md5($column . '_' . $period . '_' . $limit);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $column_safe = preg_replace('/[^a-z_]/', '', $column);
        $rows = [];
        if ($period === 'all') {
            $records_endpoint = 'https://api.opendota.com/api/records/' . rawurlencode($column_safe);
            $resp = wp_remote_get($records_endpoint, ['timeout' => 20, 'headers' => ['Accept' => 'application/json']]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $payload = json_decode(wp_remote_retrieve_body($resp), true);
                if (is_array($payload)) {
                    $rows = array_map(function($r){
                        return [
                            'match_id' => isset($r['match_id']) ? intval($r['match_id']) : 0,
                            'value' => isset($r['score']) ? floatval($r['score']) : 0,
                            'hero_id' => isset($r['hero_id']) ? intval($r['hero_id']) : null,
                            'start_time' => isset($r['start_time']) ? intval($r['start_time']) : null,
                        ];
                    }, array_slice(array_filter($payload, function($r){
                        return isset($r['score']);
                    }), 0, $limit));
                }
            }
        } else {
            $attempts = [
                ['lobby' => 'ranked'],
                ['lobby' => 'pub_ranked'],
                ['lobby' => 'any'],
            ];
            foreach ($attempts as $a) {
                if ($column_safe === 'duration') {
                    $sql = "SELECT match_id, duration AS value, start_time FROM public.matches ";
                    $sql .= "WHERE start_time >= (extract(epoch from now()) - 7*24*60*60) ";
                    $sql .= "AND duration IS NOT NULL ";
                    $sql .= "AND human_players = 10 ";
                    if ($a['lobby'] === 'ranked') { $sql .= "AND lobby_type = 7 "; }
                    elseif ($a['lobby'] === 'pub_ranked') { $sql .= "AND lobby_type IN (0,7) "; }
                    $sql .= "AND game_mode NOT IN (18,23) ";
                    $sql .= "ORDER BY duration DESC ";
                    $sql .= "LIMIT " . intval($limit);
                } else {
                    $sql = "SELECT pm.match_id, pm.$column_safe AS value, pm.hero_id, m.start_time FROM public.player_matches pm ";
                    $sql .= "JOIN public.matches m ON m.match_id = pm.match_id ";
                    $sql .= "WHERE m.start_time >= (extract(epoch from now()) - 7*24*60*60) ";
                    $sql .= "AND pm.$column_safe IS NOT NULL ";
                    $sql .= "AND m.human_players = 10 ";
                    if ($a['lobby'] === 'ranked') { $sql .= "AND m.lobby_type = 7 "; }
                    elseif ($a['lobby'] === 'pub_ranked') { $sql .= "AND m.lobby_type IN (0,7) "; }
                    $sql .= "AND m.game_mode NOT IN (18,23) ";
                    $sql .= "ORDER BY pm.$column_safe DESC ";
                    $sql .= "LIMIT " . intval($limit);
                }

                $endpoint = 'https://api.opendota.com/api/explorer?sql=' . rawurlencode($sql);
                $response = wp_remote_get($endpoint, ['timeout' => 20, 'headers' => ['Accept' => 'application/json']]);
                if (is_wp_error($response)) { return $response; }
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) { return new WP_Error('opendota_http_error', 'HTTP ' . $code); }
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) { $rows = []; }
                else {
                    $rows = array_map(function($r) {
                        return [
                            'match_id' => isset($r['match_id']) ? intval($r['match_id']) : 0,
                            'value' => isset($r['value']) ? floatval($r['value']) : 0,
                            'hero_id' => isset($r['hero_id']) ? intval($r['hero_id']) : null,
                            'start_time' => isset($r['start_time']) ? intval($r['start_time']) : null,
                        ];
                    }, array_filter($data['rows'] ?? [], function($r) {
                        $value = $r['value'] ?? null;
                        return $value !== null && $value !== '';
                    }));
                }
                if (!empty($rows)) { break; }
            }
        }

        if (empty($rows)) {
            $attempts = [
                ['lobby' => 'ranked'],
                ['lobby' => 'pub_ranked'],
                ['lobby' => 'any'],
            ];
            foreach ($attempts as $a) {
                if ($column_safe === 'duration') {
                    $sql = "SELECT match_id, duration AS value, start_time FROM public.matches ";
                    $sql .= "WHERE start_time >= (extract(epoch from now()) - 7*24*60*60) ";
                    $sql .= "AND duration IS NOT NULL ";
                    $sql .= "AND human_players = 10 ";
                    if ($a['lobby'] === 'ranked') { $sql .= "AND lobby_type = 7 "; }
                    elseif ($a['lobby'] === 'pub_ranked') { $sql .= "AND lobby_type IN (0,7) "; }
                    $sql .= "AND game_mode NOT IN (18,23) ";
                    $sql .= "ORDER BY duration DESC ";
                    $sql .= "LIMIT " . intval($limit);
                } else {
                    $sql = "SELECT pm.match_id, pm.$column_safe AS value, pm.hero_id, m.start_time FROM public.player_matches pm ";
                    $sql .= "JOIN public.matches m ON m.match_id = pm.match_id ";
                    $sql .= "WHERE m.start_time >= (extract(epoch from now()) - 7*24*60*60) ";
                    $sql .= "AND pm.$column_safe IS NOT NULL ";
                    $sql .= "AND m.human_players = 10 ";
                    if ($a['lobby'] === 'ranked') { $sql .= "AND m.lobby_type = 7 "; }
                    elseif ($a['lobby'] === 'pub_ranked') { $sql .= "AND m.lobby_type IN (0,7) "; }
                    $sql .= "AND m.game_mode NOT IN (18,23) ";
                    $sql .= "ORDER BY pm.$column_safe DESC ";
                    $sql .= "LIMIT " . intval($limit);
                }

                $endpoint = 'https://api.opendota.com/api/explorer?sql=' . rawurlencode($sql);
                $response = wp_remote_get($endpoint, ['timeout' => 20, 'headers' => ['Accept' => 'application/json']]);
                if (is_wp_error($response)) { return $response; }
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) { return new WP_Error('opendota_http_error', 'HTTP ' . $code); }
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) { $rows = []; }
                else {
                    $rows = array_map(function($r) {
                        return [
                            'match_id' => isset($r['match_id']) ? intval($r['match_id']) : 0,
                            'value' => isset($r['value']) ? floatval($r['value']) : 0,
                            'hero_id' => isset($r['hero_id']) ? intval($r['hero_id']) : null,
                            'start_time' => isset($r['start_time']) ? intval($r['start_time']) : null,
                        ];
                    }, array_filter($data['rows'] ?? [], function($r) {
                        $value = $r['value'] ?? null;
                        return $value !== null && $value !== '';
                    }));
                }
                if (!empty($rows)) { break; }
            }
        }

        if (empty($rows)) {
            set_transient($cache_key, $rows, 5 * MINUTE_IN_SECONDS);
        } else {
            set_transient($cache_key, $rows, DAY_IN_SECONDS);
        }
        return $rows;
    }

    // ===== Heroes (pro/public/turbo) =====
    public function shortcode_heroes_pro($atts) {
        $atts = shortcode_atts([
            'limit' => 100,
            'sort_by' => 'pb', // pb, pp, ban, pw
        ], $atts, 'opendota_heroes_pro');
        $limit = max(1, intval($atts['limit']));
        $sort_by = in_array($atts['sort_by'], ['pb','pp','ban','pw']) ? $atts['sort_by'] : 'pb';

        $stats = $this->get_hero_stats();
        if (is_wp_error($stats)) return '<div class="odr-error" dir="rtl">خطا در دریافت اطلاعات هیروها</div>';
        $totals = $this->hero_totals($stats);
        // Build rows: Hero, Pro P+B%, Pro Pick%, Pro Ban%, Pro Win%
        $rows = [];
        $total_pro_matches = max(1, intval($totals['pro_pick_total']));
        foreach ($stats as $h) {
            $name = isset($h['localized_name']) ? $h['localized_name'] : '';
            $pro_pick = intval($h['pro_pick'] ?? 0);
            $pro_ban = intval($h['pro_ban'] ?? 0);
            $pro_win = intval($h['pro_win'] ?? 0);
            $pb = ($pro_pick + $pro_ban) / max(1, $total_pro_matches) * 100.0;
            $pp = $pro_pick / max(1, $total_pro_matches) * 100.0;
            $pw = $pro_pick > 0 ? ($pro_win / max(1, $pro_pick) * 100.0) : 0.0;
            $rows[] = [
                'hero' => $name,
                'hero_id' => intval($h['id'] ?? 0),
                'pb' => $pb,
                'pp' => $pp,
                'ban' => ($pro_ban / max(1, $total_pro_matches) * 100.0),
                'pw' => $pw,
            ];
        }
        usort($rows, function($a,$b) use ($sort_by) { return $b[$sort_by] <=> $a[$sort_by]; });
        $rows = array_slice($rows, 0, $limit);
        return $this->render_heroes_table($rows, [
            'hero' => 'هیرو',
            'pb' => 'درصد حضور (پیک+بن)',
            'pp' => 'درصد پیک',
            'ban' => 'درصد بن',
            'pw' => 'درصد برد',
        ]);
    }

    public function shortcode_heroes_public($atts) {
        $stats = $this->get_hero_stats();
        if (is_wp_error($stats)) return '<div class="odr-error" dir="rtl">خطا در دریافت اطلاعات هیروها</div>';
        // Compute totals per bracket groups
        $totals = $this->hero_totals($stats);
        $total_all = max(1, intval($totals['overall_total']));
        $groups = [
            'ida' => ['8','7','6'], // Immortal/Divine/Ancient
            'la'  => ['5','4'],     // Legend/Archon
            'cgh' => ['3','2','1'], // Crusader/Guardian/Herald
        ];
        $group_totals = [
            'ida' => max(1, intval($totals['g_8'] + $totals['g_7'] + $totals['g_6'])),
            'la'  => max(1, intval($totals['g_5'] + $totals['g_4'])),
            'cgh' => max(1, intval($totals['g_3'] + $totals['g_2'] + $totals['g_1'])),
        ];
        $rows = [];
        foreach ($stats as $h) {
            $name = $h['localized_name'] ?? '';
            $pick_all = 0; for ($i=1;$i<=8;$i++) { $pick_all += intval($h['pick'.$i] ?? 0); }
            $win_all = 0; for ($i=1;$i<=8;$i++) { $win_all += intval($h['win'.$i] ?? 0); }
            $overall_p = $pick_all / max(1, $total_all) * 100.0;
            $overall_w = $pick_all>0 ? ($win_all / max(1, $pick_all) * 100.0) : 0.0;

            $ida_pick = intval($h['pick8'] ?? 0) + intval($h['pick7'] ?? 0) + intval($h['pick6'] ?? 0);
            $ida_win  = intval($h['win8'] ?? 0) + intval($h['win7'] ?? 0) + intval($h['win6'] ?? 0);
            $la_pick  = intval($h['pick5'] ?? 0) + intval($h['pick4'] ?? 0);
            $la_win   = intval($h['win5'] ?? 0) + intval($h['win4'] ?? 0);
            $cgh_pick = intval($h['pick3'] ?? 0) + intval($h['pick2'] ?? 0) + intval($h['pick1'] ?? 0);
            $cgh_win  = intval($h['win3'] ?? 0) + intval($h['win2'] ?? 0) + intval($h['win1'] ?? 0);

            $rows[] = [
                'hero' => $name,
                'overall_p' => $overall_p,
                'overall_w' => $overall_w,
                'ida_p' => $ida_pick / max(1, $group_totals['ida']) * 100.0,
                'ida_w' => $ida_pick>0 ? ($ida_win / max(1, $ida_pick) * 100.0) : 0.0,
                'la_p'  => $la_pick / max(1, $group_totals['la']) * 100.0,
                'la_w'  => $la_pick>0 ? ($la_win / max(1, $la_pick) * 100.0) : 0.0,
                'cgh_p' => $cgh_pick / max(1, $group_totals['cgh']) * 100.0,
                'cgh_w' => $cgh_pick>0 ? ($cgh_win / max(1, $cgh_pick) * 100.0) : 0.0,
            ];
        }
        usort($rows, function($a,$b){ return $b['overall_p'] <=> $a['overall_p']; });
        return $this->render_heroes_table($rows, [
            'hero' => 'هیرو',
            'overall_p' => 'درصد پیک کلی',
            'overall_w' => 'درصد برد کلی',
            'ida_p' => 'ایمورتال/دی‌واین/انشنت درصد پیک',
            'ida_w' => 'ایمورتال/دی‌واین/انشنت درصد برد',
            'la_p'  => 'لجند/آرکان درصد پیک',
            'la_w'  => 'لجند/آرکان درصد برد',
            'cgh_p' => 'کروسیدر/گاردین/هرالد درصد پیک',
            'cgh_w' => 'کروسیدر/گاردین/هرالد درصد برد',
        ]);
    }

    public function shortcode_heroes_turbo($atts) {
        $atts = shortcode_atts([
            'limit' => 100,
            'sort_by' => 'tp', // tp, tw
        ], $atts, 'opendota_heroes_turbo');
        $limit = max(1, intval($atts['limit']));
        $sort_by = in_array($atts['sort_by'], ['tp','tw']) ? $atts['sort_by'] : 'tp';

        $stats = $this->get_hero_stats();
        if (is_wp_error($stats)) return '<div class="odr-error" dir="rtl">خطا در دریافت اطلاعات هیروها</div>';
        $totals = $this->hero_totals($stats);
        $total_turbo = max(1, intval($totals['turbo_total']));
        $rows = [];
        foreach ($stats as $h) {
            $name = $h['localized_name'] ?? '';
            $tp = intval($h['turbo_picks'] ?? 0);
            $tw = intval($h['turbo_wins'] ?? 0);
            $rows[] = [
                'hero' => $name,
                'hero_id' => intval($h['id'] ?? 0),
                'tp' => $tp / max(1, $total_turbo) * 100.0,
                'tw' => $tp>0 ? ($tw / max(1, $tp) * 100.0) : 0.0,
            ];
        }
        usort($rows, function($a,$b) use ($sort_by) { return $b[$sort_by] <=> $a[$sort_by]; });
        $rows = array_slice($rows, 0, $limit);
        return $this->render_heroes_table($rows, [
            'hero' => 'هیرو',
            'tp' => 'درصد پیک توربو',
            'tw' => 'درصد برد توربو',
        ]);
    }

    private function get_hero_stats() {
        $cache_key = 'odr_hero_stats_v1';
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
        $endpoint = 'https://api.opendota.com/api/heroStats';
        $response = wp_remote_get($endpoint, ['timeout' => 20, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) return $response;
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) return new WP_Error('opendota_http_error', 'HTTP ' . $code);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return new WP_Error('opendota_bad_body', 'Bad body');
        set_transient($cache_key, $data, DAY_IN_SECONDS);
        return $data;
    }

    private function hero_totals($stats) {
        $t = [
            'pro_pick_total' => 0,
            'overall_total' => 0,
            'turbo_total' => 0,
            'g_1' => 0,'g_2' => 0,'g_3' => 0,'g_4' => 0,'g_5' => 0,'g_6' => 0,'g_7' => 0,'g_8' => 0,
        ];
        foreach ($stats as $h) {
            $t['pro_pick_total'] += intval($h['pro_pick'] ?? 0);
            $tp_all = 0; for ($i=1;$i<=8;$i++) { $tp_all += intval($h['pick'.$i] ?? 0); }
            $t['overall_total'] += $tp_all;
            for ($i=1;$i<=8;$i++) { $t['g_'.$i] += intval($h['pick'.$i] ?? 0); }
            $t['turbo_total'] += intval($h['turbo_picks'] ?? 0);
        }
        return $t;
    }

    private function render_heroes_table($rows, $headers) {
        $uid = 'odr_' . uniqid();
        ob_start();
        echo '<div class="odr-skeleton-wrap" id="' . esc_attr($uid) . '_skeleton">';
        echo '<div class="odr-skeleton">';
        for ($i=0; $i<count($rows); $i++) {
            echo '<div class="odr-skeleton-row">';
            for ($j=0; $j<count($headers); $j++) {
                echo '<div class="odr-skeleton-col"></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
        echo '<div class="odr-table-wrap" dir="rtl" id="' . esc_attr($uid) . '">';
        echo '<table class="odr-table odr-sortable">';
        echo '<thead><tr>';
        foreach ($headers as $key => $label) {
            $is_num = ($key !== 'hero');
            echo '<th data-key="' . esc_attr($key) . '" data-type="' . ($is_num ? 'num' : 'str') . '" class="odr-sortable-col">' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($headers as $key => $_) {
                $val = $r[$key];
                if ($key === 'hero') {
                    $hero_cell = '';
                    if (!empty($r['hero_id'])) {
                        $hero = $this->get_hero_map()[intval($r['hero_id'])] ?? null;
                        if ($hero) {
                            $img = $hero['img'] ?? '';
                            $name = $hero['name'] ?? $val;
                            if ($img) {
                                $hero_cell = '<div class="odr-hero" title="' . esc_attr($name) . '">'
                                           . '<img src="' . esc_url($img) . '" alt="' . esc_attr($name) . '" loading="lazy" />'
                                           . '<span class="odr-hero-name">' . esc_html($name) . '</span>'
                                           . '</div>';
                            } else {
                                $hero_cell = '<span class="odr-hero-name">' . esc_html($name) . '</span>';
                            }
                        }
                    }
                    echo '<td data-key="' . esc_attr($key) . '">' . $hero_cell . '</td>';
                } else {
                    $pct = max(0, min(100, floatval($val)));
                    $pct_text = number_format_i18n(round($pct, 2)) . '%';
                    $hue = (int) round(($pct / 100) * 120); // 0=red, 120=green
                    echo '<td data-key="' . esc_attr($key) . '">';
                    echo '<div class="odr-val">';
                    echo '<span class="odr-val-text">' . esc_html($pct_text) . '</span>';
                    echo '<span class="odr-bar"><span class="odr-bar-fill" style="width:' . esc_attr($pct) . '%; background-color:hsl(' . esc_attr($hue) . ',70%,45%)"></span></span>';
                    echo '</div>';
                    echo '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<script>(function(){var s=document.getElementById("' . esc_js($uid) . '_skeleton"),t=document.getElementById("' . esc_js($uid) . '");if(s&&t){setTimeout(function(){s.classList.add("odr-loaded");t.classList.add("odr-loaded");},300);}})();</script>';
        return ob_get_clean();
    }
    
    public function refresh_daily_cache() {
        $limits = [5, 10, 12];
        $metrics = array_keys($this->metrics());
        foreach ($metrics as $m) {
            foreach ($limits as $l) {
                $this->fetch_records($this->metrics()[$m]['column'], $l);
            }
        }
        $this->get_hero_stats();
    }
}

new Opendota_Records_Plugin();

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('odr_refresh_daily_cache')) {
        wp_schedule_event(time() + 300, 'daily', 'odr_refresh_daily_cache');
    }
});

register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('odr_refresh_daily_cache');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'odr_refresh_daily_cache');
    }
});
