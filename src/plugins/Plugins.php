<?php


namespace NovemBit\wp\plugins\spm\plugins;

use diazoxide\helpers\Arrays;
use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\helpers\URL;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\Bootstrap;

class Plugins
{

    /**
     * @var Bootstrap
     * */
    public $parent;

    /**
     * @var array
     * */
    private $settings;

    /**
     * @var $config
     * */
    private $config;

    /**
     * @var array
     * */
    private $plugins;

    /**
     * @var array
     * */
    private $orig_active_plugins;

    /**
     * @var array
     * */
    private $active_plugins = [];

    private $tree = [];
    /**
     * Statuses
     * */
    public const STATUS_SYSTEM_DEFAULT = 'default';
    public const STATUS_FORCE_DISABLED = 'force_disabled';
    public const STATUS_FORCE_ENABLED = 'force_enabled';
    public const STATUS_ENABLE_WHEN = 'enable_when';
    public const STATUS_DISABLE_WHEN = 'disable_when';
    public const STATUS_SMART = 'smart';

    /**
     * Actions
     * */
    public const ACTION_PLUGIN_ACTIVATE = 'plugin_activate';

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->parent->getName() . '-plugins';
    }

    /**
     * Plugins constructor.
     * @param Bootstrap $parent
     * @uses setBeforeNestedFields
     * @uses handleRequestActions
     * @uses setNestedFieldName
     */
    public function __construct(Bootstrap $parent)
    {
        $this->parent = $parent;

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $this->setAllPlugins();

        add_action('init', [$this, 'handleRequestActions']);

        $this->settings = [];

        $plugins = $this->getPluginsMap();

        foreach ($plugins as $file => $name) {
            add_filter(
                'wp-lib-option/' . $this->getName() . '/form-nested-label',
                [$this, 'setNestedFieldName'],
                10,
                1
            );

            add_filter(
                'wp-lib-option/' . $this->getName() . '/form-before-nested-fields',
                [$this, 'setBeforeNestedFields'],
                10,
                2
            );

            $plugins_list = $plugins;
            unset($plugins_list[$file]);
            $this->settings[$file] = [
                'status' => new Option(
                    [
                        'default' => self::STATUS_DISABLE_WHEN,
                        'label' => 'Status',
                        'values' => [
                            self::STATUS_SYSTEM_DEFAULT => 'System default',
                            self::STATUS_FORCE_DISABLED => 'Force disabled',
                            self::STATUS_FORCE_ENABLED => 'Force enabled',
                            self::STATUS_ENABLE_WHEN => 'Enable when',
                            self::STATUS_DISABLE_WHEN => 'Disable when',
                            self::STATUS_SMART => 'Smart control',
                        ]
                    ]
                ),
                /*'priority' => new Option(
                    [
                        'default' => 10,
                        'label' => 'Priority',
                        'markup' => Option::MARKUP_NUMBER,
                    ]
                ),*/
                'require' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'values' => $plugins_list,
                        'label' => 'Required plugins'
                    ]
                ),
                'rules_patterns' => new Option(
                    [
                        'label' => 'Predefined rule patterns',
                        'method' => Option::METHOD_MULTIPLE,
                        'type' => Option::TYPE_TEXT,
                        'values' => $this->parent->rules->patterns->getPatternsMap(),
                        'main_params' => ['col' => '1'],

                    ]
                ),
                'rules' => ($this->parent->rules->config['common']['plugin_custom_rules'] ?? false) ? new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'type' => Option::TYPE_GROUP,
                        'values' => [],
                        'main_params' => ['col' => '1'],
                        'template' => $this->parent->rules::getRulesSettings(),
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
            $this->settings,
            function ($a, $b) {
                if ($this->isPluginActive($a)) {
                    return 0;
                }
                return 1;
            }
        );

        /**
         * Expand config from settings
         * */
        $this->config = Option::expandOptions($this->settings, $this->getName(), ['serialize' => true]);

        $this->initActivePlugins();

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
                'id' => $this->getName(),
                'parent' => $this->parent->getName(),
                'href' => admin_url('admin.php?page=' . $this->getName()),
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
        return $this->plugins[$plugin]['custom_data']['is_active'] ?? false;
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
     * @param $tree
     * @param $plugin
     * @param $count
     */
    private static function inArrayKeysRecursive($tree, $plugin, &$count): void
    {
        foreach ($tree as $_plugin => $item) {
            if ($_plugin === $plugin) {
                $count++;
            }
            if (is_array($item)) {
                self::inArrayKeysRecursive($item, $plugin, $count);
            }
        }
    }

    /**
     * @param $plugin
     * @return bool
     */
    private function inTree($plugin): bool
    {
        self::inArrayKeysRecursive($this->tree, $plugin, $count);
        return $count > 0;
    }

    /**
     * @param array $tree
     * @param string $plugin
     */
    private static function removePluginFromTree(array &$tree, string $plugin): void
    {
        foreach ($tree as $_plugin => &$item) {
            if ($_plugin === $plugin) {
                unset($tree[$_plugin]);
                break;
            }
            if (is_array($tree[$_plugin])) {
                self::removePluginFromTree($tree[$_plugin], $plugin);
            }
        }
    }

    /**
     * @param array $tree
     * @return array
     */
    private static function getActivePluginsListFromTree(array $tree): array
    {
        $active_plugins = [];

        foreach ($tree as $plugin => $item) {
            $active_plugins[] = $plugin;
            if (is_array($item)) {
                $child_tree = self::getActivePluginsListFromTree($item);
                if (!empty($child_tree)) {
                    array_push(
                        $active_plugins,
                        ...$child_tree
                    );
                }
            }
        }
        array_unique($active_plugins);
        return $active_plugins;
    }

    /**
     * @return void
     */
    private function initActivePlugins(): void
    {
        $this->orig_active_plugins = get_option('active_plugins');

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
            '/wp-admin/admin.php?page=' . $this->parent->getName()
        )) {
            /**
             * @uses cleanActivePlugins
             * */
            add_filter('option_active_plugins', [$this, 'cleanActivePlugins'], PHP_INT_MAX);

            $this->unsetTheme();

            return;
        }

        $config = $this->getConfig();

        foreach ($config as $plugin => &$data) {
            if (!$this->isPluginActive($plugin)
                || $this->inTree($plugin)
            ) {
                continue;
            }

            $status = $data['status'] ?? self::STATUS_SYSTEM_DEFAULT;

            if ($status === self::STATUS_FORCE_ENABLED) {
                $this->tree[$plugin] = [];
                continue;
            }

            if ($status === self::STATUS_FORCE_DISABLED) {
                continue;
            }

            if (in_array($status, [self::STATUS_SMART, self::STATUS_ENABLE_WHEN, self::STATUS_DISABLE_WHEN], true)) {
                if ($status === self::STATUS_DISABLE_WHEN) {
                    $this->tree[$plugin] = [];
                }

                $rules = array_values($data['rules'] ?? []);
                $rules_patterns = array_values($data['rules_patterns'] ?? []);
                $force_required = $data['force_required'] ?? false;

                if (
                    $force_required ||
                    (
                        $status === self::STATUS_ENABLE_WHEN
                        && (
                            $this->parent->rules->patterns->checkPatterns($rules_patterns)
                            || $this->parent->rules->checkRules($rules)
                        )
                    ) ||
                    (
                        $status === self::STATUS_DISABLE_WHEN
                        && !(
                            $this->parent->rules->patterns->checkPatterns($rules_patterns)
                            || $this->parent->rules->checkRules($rules)
                        )
                    )
                ) {
                    if ($force_required) {
                        $demanding_from = &$data['demanding_from'][$plugin];
                    } else {
                        $demanding_from = &$this->tree[$plugin];
                    }

                    $required = $data['require'] ?? [];

                    foreach ($required as $_plugin) {
                        if (
                            $_plugin !== $plugin
                            && $this->isPluginActive($_plugin)
                            && !$this->inTree($_plugin)
                        ) {
                            $_config = $config[$_plugin];
                            $_config['force_required'] = true;
                            $_config['demanding_from'] = &$demanding_from;
                            unset($config[$_plugin]);
                            $config[$_plugin] = $_config;
                        }
                    }
                    continue;
                }
            } else {
                $this->tree[$plugin] = [];
            }

            if ($status === self::STATUS_DISABLE_WHEN) {
                self::removePluginFromTree($this->tree, $plugin);
            }
        }
        unset($data);

        $orig_tree = $this->tree;

        if (
            ($this->parent->getConfig()['debug']['active'] ?? false) &&
            ($this->parent->getConfig()['debug']['plugins_on_admin_bar'] ?? false)
        ) {
            foreach ($this->plugins as $plugin => $params) {
                $status = Environment::get(md5($this->getName() . $plugin));
                if (($status === '0') && $this->inTree($plugin)) {
                    self::removePluginFromTree($this->tree, $plugin);
                }
            }

            /**
             * @uses adminToolbar
             * */
            add_action(
                'wp_before_admin_bar_render',
                function () use ($params, $orig_tree) {
                    $this->adminBarTree($this->tree);

                    global $wp_admin_bar;

                    foreach ($this->plugins as $plugin => $data) {
                        $active = $this->inTree($plugin);
                        if ($active) {
                            continue;
                        }
                        $title = $data['Name'] ?? $plugin;

                        $url_key = md5($this->getName() . $plugin);
                        $url = URL::removeQueryVars(URL::getCurrent(), $url_key);
                        //$url = URL::addQueryVars($url, $url_key, '1');

                        $wp_admin_bar->add_node(
                            [
                                'id' => $this->getName() . '-' . $plugin,
                                'parent' => $this->getName(),
                                'title' => HTML::tag(
                                    'span',
                                    $title,
                                    [
                                        'style' => 'color:red'
                                    ]
                                ),

                                'href' => $url
                            ]
                        );
                    }
                },
                100
            );
        }

        if ($this->parent->authorizedDebug()) {
            $debug = '<h1>Active Plugins Tree</h1>';
            $debug .= '<pre>' . $this->printPluginsTree($this->tree) . '</pre>';
            wp_die($debug);
        }

        /**
         * @uses overwriteActivePlugins
         * */
        add_filter('option_active_plugins', [$this, 'overwriteActivePlugins'], PHP_INT_MAX, 0);
    }

    /**
     * @param $tree
     * @param int $level
     */
    private function adminBarTree(array $tree, int $level = 0): void
    {
        global $wp_admin_bar;
        foreach ($tree as $plugin => $item) {
            $_level = $level;
            $title = str_repeat('-', $_level) . ($this->plugins[$plugin]['Name'] ?? $plugin);

            $url_key = md5($this->getName() . $plugin);
            $url = URL::removeQueryVars(URL::getCurrent(), $url_key);
            $url = URL::addQueryVars($url, $url_key, '0');

            $wp_admin_bar->add_node(
                [
                    'id' => $this->getName() . '-' . $plugin,
                    'parent' => $this->getName(),
                    'title' => HTML::tag(
                        'span',
                        $title,
                        [
                            'style' => 'color:green'
                        ]
                    ),
                    'href' => $url
                ]
            );
            if (is_array($item)) {
                $_level++;
                $this->adminBarTree($item, $_level);
            }
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

        return self::getActivePluginsListFromTree($this->tree);
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
     * @param $tree
     * @param int $level
     * @return string
     */
    private function printPluginsTree($tree, $level = 0): string
    {
        $html = '';
        foreach ($tree as $plugin => $_tree) {
            $_level = $level;
            $prefix = str_repeat('――', $_level);
            $plugin = $this->plugins[$plugin]['Name'] ?? $plugin;
            $html .= $prefix . $plugin . PHP_EOL;
            if (!empty($_tree)) {
                $_level++;
                $html .= $this->printPluginsTree($_tree, $_level);
            }
        }
        return $html;
    }

    /**
     * @return void
     */
    public function handleRequestActions(): void
    {
        if (isset($_GET[$this->getName()])
            && wp_verify_nonce($_GET[$this->getName()], self::ACTION_PLUGIN_ACTIVATE)
        ) {
            $plugin = $_GET['plugin'] ?? null;

            if (!$plugin) {
                return;
            }

            $plugin = base64_decode($plugin);

            $is_active = $this->isPluginActive($plugin);

            /**
             * @uses resetActivePlugins
             * */
            add_filter('option_active_plugins', [$this, 'resetActivePlugins'], PHP_INT_MAX - 9);

            $is_active ? deactivate_plugins($plugin) : activate_plugins($plugin);

            wp_redirect(wp_get_referer());
        }
    }

    /**
     * @return string
     */
    private function getSelfPlugin(): string
    {
        return sprintf('%1$s/%1$s.php', $this->parent->getName());
    }

    /**
     * Set All plugins
     * @return void
     */
    private function setAllPlugins(): void
    {
        $this->plugins = get_plugins();
        unset($this->plugins[$this->getSelfPlugin()]);
        foreach ($this->plugins as $plugin => &$data) {
            $data['custom_data']['is_active'] = is_plugin_active($plugin);
        }
    }

    /**
     * @param $label
     * @return string
     */
    public function setNestedFieldName($label): string
    {
        $plugin = $label;
        $label = $this->plugins[$plugin]['Name'] ?? $label;
        $label .= ' (';
        $label .= $this->plugins[$plugin]['Version'] ?? '?';
        $label .= ') ';
        $label .= $this->getPluginActiveStatusBadge($plugin, false);
        return $label;
    }

    /**
     * @param $content
     * @param $route
     * @return string
     */
    public function setBeforeNestedFields($content, $route): string
    {
        $html = '';
        $plugin_data = $this->plugins[$route] ?? null;
        if ($plugin_data) {
            $html .= $this->getPluginActions($route);
            $html .= $this->getPluginInfo($plugin_data);
        }

        $content = $html . $content;
        return $content;
    }

    /**
     * @param string $plugin
     * @return string
     */
    private function getPluginActions(string $plugin): string
    {
        global $wp;

        $current_url = add_query_arg($wp->query_vars, admin_url($wp->request));
        $current_url = add_query_arg(['plugin' => base64_encode($plugin)], $current_url);
        $is_active = $this->isPluginActive($plugin);
        $label = __($is_active ? 'Deactivate' : 'Activate', 'novembit-spm');
        $activate_url = wp_nonce_url($current_url, self::ACTION_PLUGIN_ACTIVATE, $this->getName());

        ob_start();
        ?>
        <div class="plugin-actions">
            <a href="<?php echo $activate_url; ?>" class="button button-default">
                <?php echo $this->getPluginActiveStatusBadge($plugin, false); ?>
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
    private function getPluginActiveStatusBadge(string $plugin, bool $with_label = true): string
    {
        $class = 'plugin-active-status-badge';
        if ($this->isPluginActive($plugin)) {
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
    private function getPluginInfo(array $plugin_data): string
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
            $this->parent->getName(),
            __('Plugins', 'novembit-spm'),
            __('Plugins', 'novembit-spm'),
            'manage_options',
            $this->getName(),
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
        $option_name = $plugin . '>rules_patterns';
        $option = Option::getOption($option_name, $this->getName(), []);

        if (!empty($patterns)) {
            array_push($option, ...$patterns);
        }

        $option = array_unique($option);

        Option::setOption($option_name, $this->getName(), $option);
    }

    /**
     * @return array
     */
    public function getPluginsMap(): array
    {
        $result = [];

        foreach ($this->plugins as $file => $data) {
            $result[$file] = $data['Name'] ?? $file;
        }
        return $result;
    }

    /**
     * Admin Content
     * @return void
     */
    public function adminContent(): void
    {
        Option::printForm(
            $this->getName(),
            $this->settings,
            [
                'title' => 'Smart Plugin Manager - Plugins',
                'ajax_submit' => true,
                'auto_submit' => true,
                'serialize' => true
            ]
        );
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getOrigActivePlugins(): array
    {
        return $this->orig_active_plugins;
    }
}