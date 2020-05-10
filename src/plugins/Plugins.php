<?php


namespace NovemBit\wp\plugins\spm\plugins;

use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\helpers\URL;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use Exception;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\rules\Patterns;
use NovemBit\wp\plugins\spm\rules\Rules;
use NovemBit\wp\plugins\spm\system\Component;

/**
 * @property Bootstrap $parent
 *
 * */
class Plugins extends Component
{

    /**
     * @var $config
     * */
    private static $config;

    /**
     * @var array
     * */
    private static $settings;

    /**
     * @var array
     * */
    private $orig_active_plugins;

    private $active_plugins = [];


    /**
     * Actions
     * */
    public const ACTION_PLUGIN_ACTIVATE = 'plugin_activate';

    /**
     * @return string
     */
    public static function getName(): string
    {
        return Bootstrap::getName() . '-plugins';
    }

    public static function getSettings(): array
    {
        if (!isset(self::$settings)) {
            self::$settings = [];
            $plugins = Bootstrap::instance()->getAllPlugins();

            foreach ($plugins as $file => $plugin_data) {
                add_filter(
                    'wp-lib-option/' . self::getName() . '/form-nested-label',
                    [self::class, 'setNestedFieldName'],
                    10,
                    1
                );

                add_filter(
                    'wp-lib-option/' . self::getName() . '/form-before-nested-fields',
                    [self::class, 'setBeforeNestedFields'],
                    10,
                    2
                );

                $plugins_list = $plugins;
                unset($plugins_list[$file]);
                self::$settings[$file] = [
                    'name' => $plugin_data['Name'] ?? $file,
                    'patterns' => Bootstrap::instance()->isEnabledPatterns() ? new Option(
                        [
                            'label' => 'Patterns',
                            'method' => Option::METHOD_MULTIPLE,
                            'relation' => [
                                'parent' => Patterns::getName(),
                                'with' => [Patterns::class, 'getSettings'],
                                'name' => 'patterns',
                                'key' => 'name',
                                'label' => 'label',
                            ],
                            'before_set_value' => static function (Option $option, &$value) use ($file) {
                                $map = Option::getOption('_asd_relation_map', '_asd', [], true);
                                foreach ($value as $item) {
                                    $row = [$file, $item];
                                    if (!in_array($row, $map, true)) {
                                        $map[] = $row;
                                    }
                                }
                                foreach ($map as $key => $row) {
                                    if (($row[0] === $file) && !in_array($row[1], $value, true)) {
                                        unset($map[$key]);
                                    }
                                }
                                Option::setOption('_asd_relation_map', '_asd', $map, true);
                                return false;
                            },
                            'before_get_value' => static function (Option $option, &$value) use ($file) {
                                $map = Option::getOption('_asd_relation_map', '_asd', [], true);
                                $value = [];
                                foreach ($map as $item) {
                                    if ($item[0] === $file) {
                                        $value[] = $item[1];
                                    }
                                }
                            },
                            'main_params' => ['col' => 1],
                        ]
                    ) : [],
                    'rules' => Bootstrap::instance()->isEnabledCustomRules() ? new Option(
                        [
                            'default' => [],
                            'method' => Option::METHOD_MULTIPLE,
                            'type' => Option::TYPE_GROUP,
                            'values' => [],
                            'main_params' => ['col' => '1'],
                            'template' => Rules::getRulesSettings(),
                            'label' => 'Rules'
                        ]
                    ) : [],
                ];
            }

            /**
             * Sort plugins
             * Actives first
             * */
            uksort(
                self::$settings,
                static function ($a, $b) {
                    if (Bootstrap::instance()->isPluginActive($a)) {
                        return 0;
                    }
                    return 1;
                }
            );
        }

        return self::$settings;
    }

    public static function getConfig(): array
    {
        if (!isset(self::$config)) {
            self::$config = Option::expandOptions(
                self::getSettings(),
                self::getName(),
                ['serialize' => true, 'single_option' => true]
            );
        }
        return self::$config;
    }

    /**
     * Plugins constructor.
     * @uses setBeforeNestedFields
     * @uses handleRequestActions
     * @uses setNestedFieldName
     */
    public function init(): void
    {
        add_action('init', [$this, 'handleRequestActions']);

        $this->initActivePlugins();
    }

    /**
     * @return void
     */
    public function run(): void
    {
        /**
         * @uses adminBarMenu
         * */
        add_action('admin_bar_menu', [$this, 'adminBarMenu'], 100);

        if (is_admin()) {
            $this->adminInit();
        }
    }

    /**
     * @param $admin_bar
     */
    public function adminBarMenu($admin_bar): void
    {
        $admin_bar->add_menu(
            array(
                'id' => self::getName(),
                'parent' => Bootstrap::getName(),
                'href' => admin_url('admin.php?page=' . self::getName()),
                'title' => 'Plugins',
                'meta' => array(
                    'title' => 'Plugins',
                ),
            )
        );
    }

    /**
     * Is Plugin activated from Core
     *
     * @param string $plugin
     * @return bool
     */
    private function isPluginActive(string $plugin): bool
    {
        return $this->parent->getAllPlugins()[$plugin]['custom_data']['is_active'] ?? false;
    }

    /**
     * @return array
     */
    public function resetActivePlugins(): array
    {
        /**
         * Remove after usage to fix active_plugins option loses
         * */
        return $this->getOrigActivePlugins();
    }

    /**
     * @return void
     */
    public function unsetTheme(): void
    {
        add_filter('template', '__return_null');
        add_filter('option_template', '__return_null');
        add_filter('option_stylesheet', '__return_null');
    }

    /**
     * @return void
     */
    private function initActivePlugins(): void
    {
        $this->orig_active_plugins = get_option('active_plugins');

        $this->active_plugins = $this->orig_active_plugins;

        unset($this->active_plugins[array_search($this->parent::getSelfPlugin(), $this->active_plugins)]);

        if (Variables::compare(
            Variables::COMPARE_STARTS_WITH,
            Environment::server('REQUEST_URI'),
            '/wp-admin/plugins.php'
        )) {
            return;
        }

        if (Variables::compare(
            Variables::COMPARE_STARTS_WITH,
            Environment::server('REQUEST_URI'),
            '/wp-admin/admin.php?page=' . Bootstrap::getName()
        )) {
            /**
             * @uses cleanActivePlugins
             * */
            add_filter('option_active_plugins', [$this, 'cleanActivePlugins'], PHP_INT_MAX);

            $this->unsetTheme();

            return;
        }

        $config = self::getConfig();

        foreach ($config as $plugin => $data) {
            if (!Bootstrap::instance()->isPluginActive($plugin)) {
                continue;
            }

            $rules = array_values($data['rules'] ?? []);
            $patterns = array_values($data['patterns'] ?? []);

            if (($this->checkPatterns($patterns) || $this->checkRules($rules))) {
                unset($this->active_plugins[array_search($plugin, $this->active_plugins, true)]);
            }
        }

        if (
            ($this->parent::getConfig()['debug']['active'] ?? false) &&
            ($this->parent::getConfig()['debug']['plugins_on_admin_bar'] ?? false)
        ) {
            foreach ($this->parent->getAllPlugins() as $plugin => $params) {
                $status = Environment::get(self::getPluginHash($plugin));
                if (($status === '0') && in_array($plugin, $this->active_plugins, true)) {
                    unset($this->active_plugins[array_search($plugin, $this->active_plugins, true)]);
                } elseif (($status === '1') && !in_array($plugin, $this->active_plugins, true)) {
                    $this->active_plugins[] = $plugin;
                }
            }

            /**
             * @uses adminToolbar
             * */
            add_action('wp_before_admin_bar_render',[$this, 'adminBarPlugins']);
        }

        if ($this->parent->authorizedDebug()) {
            $debug = '<h1>Active Plugins</h1>';
            $debug .= '<pre>' . $this->printPluginsList($this->active_plugins) . '</pre>';
            wp_die($debug);
        }

        /**
         * @uses overwriteActivePlugins
         * */
        add_filter('option_active_plugins', [$this, 'overwriteActivePlugins'], PHP_INT_MAX, 0);
    }

    /**
     * @param $patterns
     * @return bool
     */
    private function checkPatterns(array $patterns): bool
    {
        return Bootstrap::instance()->isEnabledPatterns() &&
            $this->parent->rules->patterns->checkPatterns($patterns);
    }

    /**
     * @param array $rules
     * @return bool
     */
    private function checkRules(array $rules): bool
    {
        return Bootstrap::instance()->isEnabledCustomRules() && $this->parent->rules->checkRules($rules);
    }

    /**
     * @param string $plugin
     * @return string
     */
    private static function getPluginHash(string $plugin): string
    {
        return substr(md5(self::getName() . $plugin), 0, 10);
    }

    public function adminBarPlugins(): void
    {
        global $wp_admin_bar;
        foreach ($this->parent->getAllPlugins() as $plugin => $data) {
            $active = in_array($plugin, $this->active_plugins, true);
            $title = $this->parent->getAllPlugins()[$plugin]['Name'] ?? $plugin;

            $url_key = self::getPluginHash($plugin);
            $url = URL::removeQueryVars(URL::getCurrent(), $url_key);
            $url = URL::addQueryVars($url, $url_key, $active ? '0' : '1');

            $wp_admin_bar->add_node(
                [
                    'id' => self::getName() . '-' . $plugin,
                    'parent' => self::getName(),
                    'title' => HTML::tag(
                        'span',
                        $title,
                        [
                            'style' => 'color:' . ($active ? 'green' : 'red')
                        ]
                    ),
                    'href' => $url
                ]
            );
        }
    }

    /**
     * @return array
     */
    public function overwriteActivePlugins(): array
    {
        /**
         * Remove after usage to fix active_plugins option loses
         * */
        remove_filter('option_active_plugins', [$this, 'overwriteActivePlugins'], PHP_INT_MAX);

        return $this->active_plugins;
    }

    /**
     * @return array
     */
    public function cleanActivePlugins(): array
    {
        /**
         * Remove after usage to fix active_plugins option loses
         * */
        remove_filter('option_active_plugins', [$this, 'cleanActivePlugins'], PHP_INT_MAX);

        return [];
    }


    /**
     * @param array $plugins
     * @return string
     */
    private function printPluginsList(array $plugins): string
    {
        $html = '';
        foreach ($plugins as $plugin) {
            $plugin = $this->parent->getAllPlugins()[$plugin]['Name'] ?? $plugin;
            $html .= HTML::tag('p', $plugin);
        }
        return $html;
    }

    /**
     * @return void
     */
    public function handleRequestActions(): void
    {
        if (isset($_GET[self::getName()])
            && wp_verify_nonce($_GET[self::getName()], self::ACTION_PLUGIN_ACTIVATE)
        ) {
            $plugin = $_GET['plugin'] ?? null;

            if (!$plugin) {
                return;
            }

            $plugin = base64_decode($plugin);

            $is_active = Bootstrap::instance()->isPluginActive($plugin);

            /**
             * @uses resetActivePlugins
             * */
            add_filter('option_active_plugins', [$this, 'resetActivePlugins'], PHP_INT_MAX - 9);

            $is_active ? deactivate_plugins($plugin) : activate_plugins($plugin);

            wp_redirect(wp_get_referer());
        }
    }

    /**
     * @param $label
     * @return string
     */
    public static function setNestedFieldName($label): string
    {
        $plugin = $label;
        $label = Bootstrap::instance()->getAllPlugins()[$plugin]['Name'] ?? $label;
        $label .= ' (';
        $label .= Bootstrap::instance()->getAllPlugins()[$plugin]['Version'] ?? '?';
        $label .= ') ';
        $label .= self::getPluginActiveStatusBadge($plugin, false);
        return $label;
    }

    /**
     * @param $content
     * @param $route
     * @return string
     */
    public static function setBeforeNestedFields($content, $route): string
    {
        $html = '';
        $plugin_data = Bootstrap::instance()->getAllPlugins()[$route] ?? null;
        if ($plugin_data) {
            $html .= self::getPluginActions($route);
            $html .= self::getPluginInfo($plugin_data);
        }

        $content = $html . $content;
        return $content;
    }

    /**
     * @param string $plugin
     * @return string
     */
    private static function getPluginActions(string $plugin): string
    {
        global $wp;

        $current_url = add_query_arg($wp->query_vars, admin_url($wp->request));
        $current_url = add_query_arg(['plugin' => base64_encode($plugin)], $current_url);
        $is_active = Bootstrap::instance()->isPluginActive($plugin);
        $label = __($is_active ? 'Deactivate' : 'Activate', 'novembit-spm');
        $activate_url = wp_nonce_url($current_url, self::ACTION_PLUGIN_ACTIVATE, self::getName());

        ob_start();
        ?>
        <div class="plugin-actions">
            <a href="<?php echo $activate_url; ?>" class="button button-default">
                <?php echo self::getPluginActiveStatusBadge($plugin, false); ?>
                <?php echo $label; ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param string $plugin
     * @param bool $with_label
     * @return string
     */
    private static function getPluginActiveStatusBadge(string $plugin, bool $with_label = true): string
    {
        $class = 'plugin-active-status-badge';
        if (Bootstrap::instance()->isPluginActive($plugin)) {
            $label = __('Activated', 'wordpress');
            $class .= ' activated';
        } else {
            $label = __('Deactivated', 'wordpress');
            $class .= ' deactivated';
        }
        return sprintf('<span class="%s">%s</span>', $class, $with_label ? $label : '');
    }

    /**
     * @param array $plugin_data
     * @return string
     */
    private static function getPluginInfo(array $plugin_data): string
    {
        unset($plugin_data['custom_data']);

        $table = '<table class="widefat fixed">';
        $i = 0;
        foreach ($plugin_data as $key => $value) {
            if (!empty($value)) {
                $alternate = ($i % 2) ? 'alternate' : '';
                $table .= sprintf('<tr class="%s"><td>%s</td><td>%s</td></tr>', $alternate, $key, $value);
                $i++;
            }
        }
        $table .= '</table>';

        return sprintf('<div class="plugin-data">%s</div>', $table);
    }

    /**
     * @return void
     * @uses adminMenu
     */
    public function adminInit(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
    }

    /**
     * @return void
     * @uses adminContent
     */
    public function adminMenu(): void
    {
        add_submenu_page(
            Bootstrap::getName(),
            __('Plugins', 'novembit-spm'),
            __('Plugins', 'novembit-spm'),
            'manage_options',
            self::getName(),
            [$this, 'adminContent']
        );
    }

    /**
     * Assign pattern to plugin
     *
     * @param string $plugin
     * @param array $patterns
     */
    public function assignPatterns(string $plugin, array $patterns): void
    {
        $option_name = $plugin . '>patterns';
        $option = Option::getOption($option_name, self::getName(), []);

        if (!empty($patterns)) {
            array_push($option, ...$patterns);
        }

        $option = array_unique($option);

        Option::setOption($option_name, self::getName(), $option);
    }

    /**
     * Admin Content
     * @return void
     * @throws Exception
     */
    public function adminContent(): void
    {
        Option::printForm(
            self::getName(),
            self::getSettings(),
            [
                'title' => 'Smart Plugin Manager - Plugins',
                'ajax_submit' => true,
                'auto_submit' => true,
                'serialize' => true,
                'single_option' => true
            ]
        );
    }

    /**
     * @return array
     */
    public function getOrigActivePlugins(): array
    {
        return $this->orig_active_plugins;
    }
}