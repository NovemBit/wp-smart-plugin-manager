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
            $this->settings[$file] = [
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

    public function setNestedFieldName($label, $route, $parent): string
    {
        $label = $this->plugins[$route]['Name'] ?? $label;
        $label .= ' (';
        $label .= $this->plugins[$route]['Version'] ?? '?';
        $label .= ')';
        return $label;
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