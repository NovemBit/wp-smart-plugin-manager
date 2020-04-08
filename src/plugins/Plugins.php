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
        $this->plugins = get_plugins();

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
                )
            ];
        }

        if (is_admin()) {
            $this->adminInit();
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

    private function getPluginActions(string $plugin):string{
        $html = '<div class="row actions">';
        $html .= '<a class="button button-primary"></a>';
        $html .= $this->getPluginActiveStatusBadge($plugin);
        $html .='</div>';
        return $html;
    }

    private function getPluginActiveStatusBadge(string $plugin, bool $with_label = true): string
    {
        $class = 'plugin-active-status-badge';
        if (is_plugin_active($plugin)) {
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