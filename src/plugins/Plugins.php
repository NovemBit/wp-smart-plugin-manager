<?php


namespace NovemBit\wp\plugins\spm\plugins;


use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\Bootstrap;

class Plugins
{
    /**
     * @var Bootstrap
     * */
    public $parent;

    private $settings;

    private $plugins;

    public const STATUS_SYSTEM_DEFAULT = 0;
    public const STATUS_FORCE_DISABLED = -1;
    public const STATUS_FORCE_ENABLED = 1;
    public const STATUS_SMART = 2;

    public const ACTION_PLUGIN_ACTIVATE = 'plugin_activate';

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->parent->getName() . '-' . 'plugins';
    }

    /**
     * Plugins constructor.
     * @param Bootstrap $parent
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
                3
            );

            add_filter(
                'wp-lib-option/' . $this->getName() . '/form-before-nested-fields',
                [$this, 'setBeforeNestedFields'],
                10,
                3
            );
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
                'require' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'values' => $plugins,
                        'label' => 'Required plugins'
                    ]
                ),
                'depends' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'values' => $plugins,
                        'label' => 'Depends on plugins'
                    ]
                ),
            ];
        }

        if (is_admin()) {
            $this->adminInit();
        }
    }

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
            $is_active = $this->plugins[$plugin]['custom_data']['is_active'] ?? false;

            $is_active ? deactivate_plugins($plugin) : activate_plugins($plugin);

            wp_redirect(wp_get_referer());
        }
    }

    private function setAllPlugins(): void
    {
        $this->plugins = get_plugins();

        foreach ($this->plugins as $plugin => &$data) {
            $data['custom_data']['is_active'] = is_plugin_active($plugin);
        }
    }

    /**
     * @param $label
     * @param $route
     * @param $parent
     * @return string
     */
    public function setNestedFieldName($label, $route, $parent): string
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
     * @param $parent
     * @return string
     */
    public function setBeforeNestedFields($content, $route, $parent): string
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

    private function getPluginActions(string $plugin): string
    {
        global $wp;


        $current_url = add_query_arg($wp->query_vars, admin_url($wp->request));
        $current_url = add_query_arg(['plugin' => base64_encode($plugin)], $current_url);

        $is_active = $this->plugins[$plugin]['custom_data']['is_active'] ?? false;

        $label = $is_active ? __('Deactivate', 'novembit-spm') : __('Activate', 'novembit-spm');

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
     * @return bool
     */
    private function isPluginActive(string $plugin): bool
    {
        return $this->plugins[$plugin]['custom_data']['is_active'] ?? false;
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
     */
    public function adminInit(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
    }

    /**
     * @return void
     */
    public function adminMenu(): void
    {
        add_submenu_page(
            $this->parent->getName(),
            __('Plugins'),
            __('Plugins'),
            'manage_options',
            $this->getName(),
            [$this, 'adminContent']
        );
    }


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
        ?>
        <h1>Smart Plugin Manager - Plugins</h1>
        <?php
        Option::printForm($this->getName(), $this->settings);
    }
}