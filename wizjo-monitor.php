<?php
/**
 * Plugin Name:       Wizjo Monitor
 * Plugin URI:        https://github.com/studio-wizjo/tools-monitor-wordpress
 * Description:       Udostępnia monitoringowi Wizjo Tools stan tej witryny: baza, dysk, cron, aktualizacje. Wystawia jeden adres chroniony tokenem i nic poza tym nie robi.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Wizjo
 * Author URI:        https://tools.ewizjo.pl
 * License:           GPL-2.0-or-later
 * Text Domain:       wizjo-monitor
 *
 * Wtyczka zapisuje własny token w opcjach i wykonuje krótki test zapisu w
 * katalogu uploads, po którym natychmiast usuwa plik próbny. Nie dodaje nic
 * do frontendu i nie dzwoni nigdzie sama - to monitoring pyta ją o stan.
 */
if (! defined('ABSPATH')) {
    exit;
}

define('WIZJO_MONITOR_VERSION', '1.1.1');
define('WIZJO_MONITOR_CONTRACT', 1);
define('WIZJO_MONITOR_OPTION', 'wizjo_monitor_token');

/**
 * Rejestracja adresu diagnostycznego.
 *
 * W REST API WordPressa, a nie własnym przepisaniu adresu: REST ma gotowe
 * routowanie, nagłówki i obsługę błędów, a każde własne rozwiązanie różniłoby
 * się od nich w drobiazgach akurat na tej jednej witrynie, na której coś
 * pójdzie nie tak.
 */
add_action('rest_api_init', function () {
    register_rest_route('wizjo-monitor/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'wizjo_monitor_report',
        'permission_callback' => 'wizjo_monitor_authorised',
    ]);
});

/**
 * Token porównywany czasem stałym.
 *
 * `hash_equals`, nie `===`: porównanie, które kończy się na pierwszej różnej
 * literze, mierzalnie zdradza, ile znaków się zgadza. Przy adresie dostępnym
 * publicznie to jedyna rzecz, która stoi między obcym a diagnostyką serwera.
 */
function wizjo_monitor_authorised(WP_REST_Request $request)
{
    $stored = (string) get_option(WIZJO_MONITOR_OPTION, '');

    if ($stored === '') {
        return new WP_Error(
            'wizjo_monitor_not_configured',
            'Wtyczka Wizjo Monitor nie ma jeszcze ustawionego tokenu.',
            ['status' => 503]
        );
    }

    $given = (string) $request->get_header('x-wizjo-token');

    if ($given === '' || ! hash_equals($stored, $given)) {
        return new WP_Error('wizjo_monitor_forbidden', 'Nieprawidłowy token.', ['status' => 403]);
    }

    return true;
}

/**
 * Stan witryny, zebrany w jedno.
 *
 * Każda pozycja odpowiada na pytanie, które ktoś naprawdę zadaje o godzinie
 * trzeciej w nocy - i podaje przy tym liczbę, a nie samo "ok", żeby dało się
 * zobaczyć, że coś **zbliża się** do awarii, zamiast dowiedzieć się dopiero
 * po niej.
 */
function wizjo_monitor_report()
{
    $checks = [];

    $checks[] = wizjo_monitor_database();
    $checks[] = wizjo_monitor_disk();
    $checks[] = wizjo_monitor_uploads();
    $checks[] = wizjo_monitor_cron();
    $checks[] = wizjo_monitor_updates();
    $checks[] = wizjo_monitor_debug();
    $checks[] = wizjo_monitor_php();

    $checks = array_values(array_filter($checks));

    return new WP_REST_Response([
        'wizjo' => WIZJO_MONITOR_CONTRACT,
        'status' => wizjo_monitor_roll_up($checks),
        'generated_at' => gmdate('c'),
        'app' => [
            'name' => get_bloginfo('name'),
            'platform' => 'wordpress',
            'version' => get_bloginfo('version'),
            'environment' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production',
            'debug' => (bool) (defined('WP_DEBUG') && WP_DEBUG),
            'url' => home_url(),
            'agent' => WIZJO_MONITOR_VERSION,
        ],
        'platform' => [
            'php' => PHP_VERSION,
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : null,
            'os' => PHP_OS_FAMILY,
        ],
        'checks' => $checks,
        'metrics' => wizjo_monitor_metrics(),
    ], 200);
}

/** Najgorszy stan spośród pozycji - jedna zepsuta psuje całość. */
function wizjo_monitor_roll_up(array $checks)
{
    $statuses = wp_list_pluck($checks, 'status');

    if (in_array('failing', $statuses, true)) {
        return 'failing';
    }

    return in_array('warning', $statuses, true) ? 'warning' : 'ok';
}

function wizjo_monitor_check($key, $label, $status, $message = null)
{
    return ['key' => $key, 'label' => $label, 'status' => $status, 'message' => $message];
}

function wizjo_monitor_database()
{
    global $wpdb;

    $result = $wpdb->get_var('SELECT 1');

    if ((string) $result !== '1') {
        return wizjo_monitor_check('database', 'Baza danych', 'failing', 'Zapytanie kontrolne nie przeszło.');
    }

    return wizjo_monitor_check('database', 'Baza danych', 'ok');
}

/**
 * Wolne miejsce na dysku.
 *
 * Zapełniony dysk nie wywraca WordPressa od razu - najpierw przestają działać
 * kopie zapasowe i wysyłka plików, a strona nadal odpowiada kodem 200. Z zewnątrz
 * wygląda to na pełne zdrowie aż do dnia, w którym baza nie ma gdzie zapisać.
 */
function wizjo_monitor_disk()
{
    $free = @disk_free_space(ABSPATH);
    $total = @disk_total_space(ABSPATH);

    if (! $free || ! $total) {
        return wizjo_monitor_check('disk', 'Miejsce na dysku', 'ok', 'Hosting nie podaje tej informacji.');
    }

    $percent = (int) round(($free / $total) * 100);
    $message = wizjo_monitor_format_bytes($free).' wolnego ('.$percent.'%).';

    // Na hostingach współdzielonych procent często dotyczy całej partycji
    // serwera, a nie limitu konkretnego konta. Twardą awarię zgłaszamy więc
    // dopiero przy naprawdę małej liczbie wolnych bajtów. Możliwość faktycznego
    // zapisu sprawdza osobno wizjo_monitor_uploads().
    if ($free < 256 * 1024 * 1024) {
        return wizjo_monitor_check('disk', 'Miejsce na dysku', 'failing', $message);
    }

    if ($percent < 15) {
        return wizjo_monitor_check('disk', 'Miejsce na dysku', 'warning', $message);
    }

    return wizjo_monitor_check('disk', 'Miejsce na dysku', 'ok', $message);
}

function wizjo_monitor_uploads()
{
    $uploads = wp_upload_dir();

    if (! class_exists('WP_Filesystem_Direct')) {
        require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
    }

    $filesystem = new WP_Filesystem_Direct(null);

    if (! empty($uploads['error']) || ! $filesystem->is_writable($uploads['basedir'])) {
        return wizjo_monitor_check('uploads', 'Katalog plików', 'failing', 'Nie da się zapisać do katalogu uploads.');
    }

    $probe = trailingslashit($uploads['basedir']).'.wizjo-monitor-'.wp_generate_password(12, false, false).'.tmp';
    $written = $filesystem->put_contents($probe, 'ok', FS_CHMOD_FILE);
    $read = $written ? $filesystem->get_contents($probe) : false;
    $deleted = ! $filesystem->exists($probe) || $filesystem->delete($probe);

    if (! $written || $read !== 'ok') {
        if ($filesystem->exists($probe)) {
            $filesystem->delete($probe);
        }

        return wizjo_monitor_check('uploads', 'Katalog plików', 'failing', 'Próbny zapis w katalogu uploads nie przeszedł.');
    }

    if (! $deleted) {
        return wizjo_monitor_check('uploads', 'Katalog plików', 'warning', 'Zapis działa, ale nie udało się usunąć pliku próbnego.');
    }

    return wizjo_monitor_check('uploads', 'Katalog plików', 'ok');
}

/**
 * Czy cron WordPressa w ogóle chodzi.
 *
 * Zadanie zaległe o kilka minut jest normalne - cron WP-a rusza przy odwiedzinach.
 * Zaległe o godziny znaczy, że na witrynę nikt nie wchodzi albo cron jest
 * wyłączony, a wtedy cicho nie działają kopie, wysyłka maili i aktualizacje.
 */
function wizjo_monitor_cron()
{
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        return wizjo_monitor_check('cron', 'Zadania cykliczne', 'ok', 'Wbudowany cron wyłączony - zakładamy cron systemowy.');
    }

    $crons = _get_cron_array();

    if (empty($crons)) {
        return wizjo_monitor_check('cron', 'Zadania cykliczne', 'ok', 'Brak zaplanowanych zadań.');
    }

    $oldest = min(array_keys($crons));
    $late = time() - $oldest;
    $oldestHooks = array_keys($crons[$oldest]);
    $oldestHook = isset($oldestHooks[0]) ? sanitize_text_field((string) $oldestHooks[0]) : 'nieznane zadanie';

    if ($late > 3 * HOUR_IN_SECONDS) {
        return wizjo_monitor_check(
            'cron',
            'Zadania cykliczne',
            'failing',
            'Zadanie '.$oldestHook.' spóźnione o '.round($late / HOUR_IN_SECONDS).' godz.'
        );
    }

    if ($late > HOUR_IN_SECONDS) {
        return wizjo_monitor_check('cron', 'Zadania cykliczne', 'warning', 'Zadanie '.$oldestHook.' zalega ponad godzinę.');
    }

    return wizjo_monitor_check('cron', 'Zadania cykliczne', 'ok');
}

/**
 * Zaległe aktualizacje - osobno bezpieczeństwo, osobno reszta.
 *
 * Aktualizacja wtyczki to nie awaria i nie ma prawa dzwonić w nocy, ale ma
 * prawo stać na żółto: dziura w niezaktualizowanej wtyczce jest najczęstszym
 * powodem, dla którego witryna przestaje być własna.
 */
function wizjo_monitor_updates()
{
    if (! function_exists('get_plugin_updates')) {
        require_once ABSPATH.'wp-admin/includes/update.php';
    }

    $plugins = function_exists('get_plugin_updates') ? (array) get_plugin_updates() : [];
    $themes = function_exists('get_theme_updates') ? (array) get_theme_updates() : [];
    $core = function_exists('get_core_updates') ? (array) get_core_updates() : [];

    $coreOutdated = false;

    foreach ($core as $update) {
        if (isset($update->response) && $update->response === 'upgrade') {
            $coreOutdated = true;
        }
    }

    $count = count($plugins) + count($themes) + ($coreOutdated ? 1 : 0);

    if ($count === 0) {
        return wizjo_monitor_check('updates', 'Aktualizacje', 'ok', 'Wszystko aktualne.');
    }

    $parts = [];

    if ($coreOutdated) {
        $parts[] = 'rdzeń WordPressa';
    }

    if ($plugins) {
        $parts[] = count($plugins).' wtyczek';
    }

    if ($themes) {
        $parts[] = count($themes).' motywów';
    }

    return wizjo_monitor_check('updates', 'Aktualizacje', 'warning', 'Do zaktualizowania: '.implode(', ', $parts).'.');
}

/**
 * Tryb debugowania włączony na produkcji.
 *
 * Ostrzeżenie, nie awaria - ale to on wypisuje odwiedzającym ścieżki na dysku
 * i treść zapytań do bazy, więc na żywej witrynie jest błędem, o którym nikt
 * nie wie, dopóki ktoś nie zajrzy.
 */
function wizjo_monitor_debug()
{
    $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

    if ($environment !== 'production') {
        return wizjo_monitor_check('debug', 'Tryb debugowania', 'ok', 'Środowisko: '.$environment.'.');
    }

    $display = defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : true;

    if (defined('WP_DEBUG') && WP_DEBUG && $display) {
        return wizjo_monitor_check(
            'debug',
            'Tryb debugowania',
            'warning',
            'WP_DEBUG_DISPLAY włączony na produkcji - błędy widzą odwiedzający.'
        );
    }

    return wizjo_monitor_check('debug', 'Tryb debugowania', 'ok');
}

/** Wersja PHP, która przestała dostawać poprawki bezpieczeństwa. */
function wizjo_monitor_php()
{
    if (version_compare(PHP_VERSION, '8.1', '<')) {
        return wizjo_monitor_check('php', 'Wersja PHP', 'warning', PHP_VERSION.' nie ma już wsparcia.');
    }

    return wizjo_monitor_check('php', 'Wersja PHP', 'ok', PHP_VERSION);
}

/** Liczby, które warto widzieć na wykresie, a nie tylko w komunikacie. */
function wizjo_monitor_metrics()
{
    global $wpdb;

    $free = @disk_free_space(ABSPATH);
    $total = @disk_total_space(ABSPATH);
    $crons = _get_cron_array();
    $oldest = ! empty($crons) ? min(array_keys($crons)) : null;
    $oldestHooks = $oldest !== null ? array_keys($crons[$oldest]) : [];

    return [
        'disk_free_bytes' => $free ? (int) $free : null,
        'disk_total_bytes' => $total ? (int) $total : null,
        'disk_free_percent' => $free && $total ? (int) round(($free / $total) * 100) : null,
        'cron_late_seconds' => $oldest !== null ? max(0, time() - $oldest) : 0,
        'cron_oldest_hook' => isset($oldestHooks[0]) ? sanitize_text_field((string) $oldestHooks[0]) : null,
        'posts' => (int) wp_count_posts()->publish,
        'users' => (int) count_users()['total_users'],
        'db_size_mb' => (float) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1)
                 FROM information_schema.TABLES WHERE table_schema = %s',
                DB_NAME
            )
        ),
    ];
}

function wizjo_monitor_format_bytes($bytes)
{
    if (function_exists('size_format')) {
        return size_format((int) $bytes, 1);
    }

    return round(((int) $bytes) / 1024 / 1024 / 1024, 1).' GB';
}

/* -------------------------------------------------------------------------
 | Ekran ustawień: jedno pole i adres do skopiowania
 | ---------------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_options_page(
        'Wizjo Monitor',
        'Wizjo Monitor',
        'manage_options',
        'wizjo-monitor',
        'wizjo_monitor_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('wizjo_monitor', WIZJO_MONITOR_OPTION, [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
});

function wizjo_monitor_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $token = (string) get_option(WIZJO_MONITOR_OPTION, '');
    $url = rest_url('wizjo-monitor/v1/health');
    ?>
    <div class="wrap">
        <h1>Wizjo Monitor</h1>

        <p>
            Wklej tutaj token, który pokazał się przy kontroli w panelu
            <a href="https://tools.ewizjo.pl" target="_blank" rel="noopener">Wizjo Tools</a>.
            Bez niego adres diagnostyczny nikogo nie wpuści.
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('wizjo_monitor'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wizjo_monitor_token">Token z Wizjo Tools</label></th>
                    <td>
                        <input
                            type="text"
                            id="wizjo_monitor_token"
                            name="<?php echo esc_attr(WIZJO_MONITOR_OPTION); ?>"
                            value="<?php echo esc_attr($token); ?>"
                            class="regular-text code"
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <p class="description">
                            Token jest jedynym zabezpieczeniem tego adresu. Zmieniony w panelu
                            trzeba zmienić także tutaj.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Adres do wpisania w panelu</th>
                    <td>
                        <input type="text" class="large-text code" readonly value="<?php echo esc_attr($url); ?>" onclick="this.select()" />
                        <p class="description">
                            <?php if ($token === '') { ?>
                                Najpierw zapisz token - do tego czasu adres odpowiada kodem 503.
                            <?php } else { ?>
                                Token jest zapisany. Test wykonaj przyciskiem „Sprawdź, czy działa” w panelu Wizjo Tools.
                            <?php } ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Zapisz'); ?>
        </form>

        <h2>Co ta wtyczka wysyła</h2>
        <p>
            Stan bazy danych, wolne miejsce na dysku, zaległości crona, liczbę oczekujących
            aktualizacji, wersję PHP i rozmiar bazy. <strong>Nie wysyła treści strony,
            danych użytkowników ani niczego z bazy poza jej rozmiarem.</strong>
            Nie dzwoni nigdzie sama - odpowiada wyłącznie na zapytanie z tokenem.
        </p>
    </div>
    <?php
}
