<?php


namespace NovemBit\wp\plugins\spm\plugins;

use diazoxide\helpers\Environment;
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
     * Statuses
     * */
    public const STATUS_SYSTEM_DEFAULT = 'default';
    public const STATUS_FORCE_DISABLED = 'force_disabled';
    public const STATUS_FORCE_ENABLED = 'force_enabled';
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
                        'default' => self::STATUS_SYSTEM_DEFAULT,
                        'label' => 'Status',
                        'values' => [
                            self::STATUS_SYSTEM_DEFAULT => 'System default',
                            self::STATUS_FORCE_DISABLED => 'Force disabled',
                            self::STATUS_FORCE_ENABLED => 'Force enabled',
                            self::STATUS_SMART => 'Smart control',
                        ]
                    ]
                ),
                'priority' => new Option(
                    [
                        'default' => 10,
                        'label' => 'Priority',
                        'markup' => Option::MARKUP_NUMBER,
                    ]
                ),
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
                'rules' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'type' => Option::TYPE_GROUP,
                        'values' => [],
                        'main_params' => ['col' => '1'],
                        'template' => $this->parent->rules::getRulesSettings(),
                        'label' => 'Rules'
                    ]
                ),
            ];
        }

        $this->config = Option::expandOptions($this->settings, $this->getName());

        $this->initActivePlugins();

        if (is_admin()) {
            $this->adminInit();
        }
    }

    private function isPluginActive(string $plugin): bool
    {
        return $this->plugins[$plugin]['custom_data']['is_active'] ?? false;
    }


    /**
     * @return void
     */
    private function resetActivePlugins(): void
    {
        add_filter(
            'option_active_plugins',
            function () {
                return $this->getOrigActivePlugins();
            },
            PHP_INT_MAX - 9
        );
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
            add_filter(
                'option_active_plugins',
                static function () {
                    return [];
                },
                PHP_INT_MAX - 10
            );

            $this->unsetTheme();

            return;
        }

        $tree = [];
        $active_plugins = [];

        $config = $this->getConfig();

        foreach ($config as $plugin => &$data) {
            if (!$this->isPluginActive($plugin)
                || in_array($plugin, $active_plugins, true)
            ) {
                continue;
            }

            $status = $data['status'] ?? self::STATUS_SYSTEM_DEFAULT;

            if ($status === self::STATUS_FORCE_ENABLED) {
                $tree[$plugin] = [];
                $active_plugins[] = $plugin;
                continue;
            }

            if ($status === self::STATUS_FORCE_DISABLED) {
                continue;
            }

            if ($status === self::STATUS_SMART) {
                $rules = array_values($data['rules'] ?? []);
                $rules_patterns = array_values($data['rules_patterns'] ?? []);
                $force_required = $data['force_required'] ?? false;


                if ($force_required
                    || $this->parent->rules->patterns->checkPatterns($rules_patterns)
                    || $this->parent->rules->checkRules($rules)) {
                    if ($force_required) {
                        $demanding_from = &$data['demanding_from'][$plugin];
                    } else {
                        $demanding_from = &$tree[$plugin];
                    }

                    $required = $data['require'] ?? [];
                    foreach ($required as $_plugin) {
                        if (
                            $_plugin !== $plugin
                            && $this->isPluginActive($_plugin)
                            && !in_array($_plugin, $active_plugins, true)
                        ) {
                            $_config = $config[$_plugin];
                            $_config['force_required'] = true;
                            $_config['demanding_from'] = &$demanding_from;
                            unset($config[$_plugin]);
                            $config[$_plugin] = $_config;
                        }
                    }
                } else {
                    continue;
                }
            } else {
                $tree[$plugin] = [];
            }


            $active_plugins[] = $plugin;
        }
        unset($data);


        if ($this->parent->authorizedDebug()) {
            $debug = '<h1>Active Plugins Tree</h1>';
            $debug .= '<pre>' . $this->printPluginsTree($tree) . '</pre>';
            wp_die($debug);
        }

        add_filter(
            'option_active_plugins',
            static function () use ($active_plugins) {
                return $active_plugins;
            },
            PHP_INT_MAX - 10
        );
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

            $this->resetActivePlugins();
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