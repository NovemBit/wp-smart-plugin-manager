<?php

namespace NovemBit\wp\plugins\spm;

use NovemBit\wp\plugins\spm\plugins\Plugins;

class Bootstrap
{

    public const SLUG = 'smart-plugin-manager';

    /**
     * @var Plugins
     * */
    public $plugins;

    /**
     * @var self
     * */
    private static $instance;

    private $plugin_file;

    /**
     * @param string|null $plugin_file
     * @return self
     */
    public static function instance(?string $plugin_file = null): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::SLUG;
    }

    /**
     * Bootstrap constructor.
     * @param $plugin_file
     */
    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;

        register_activation_hook($this->getPluginFile(), [$this, 'install']);

        register_deactivation_hook($this->getPluginFile(), [$this, 'uninstall']);

        if (is_admin()) {
            $this->adminInit();
        }

        $this->plugins = new Plugins($this);
    }

    /**
     * @return void
     * @uses adminMenu
     * @uses enqueueAssets
     */
    public function adminInit(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
    }

    /**
     * @return void
     */
    public function adminMenu(): void
    {
        add_menu_page(
            __('SPM', 'novembit-spm'),
            __('SPM', 'novembit-spm'),
            'manage_options',
            $this->getName(),
            [$this, 'adminContent'],
            'dashicons-admin-site-alt',
            75
        );
    }

    public function adminContent(): void
    {
        ?>
        <h1>Smart Plugin Manager</h1>
        <?php
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        $mu = WPMU_PLUGIN_DIR . '/' . $this->getName() . '.php';
        $content = '<?php' . PHP_EOL;
        $content .= ' // This is auto generated file' . PHP_EOL;
        $content .= 'include_once WP_PLUGIN_DIR."/' . $this->getName() . '/' . $this->getName() . '.php";';
        return file_put_contents($mu, $content);
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        $mu = WPMU_PLUGIN_DIR . '/' . $this->getName() . '.php';
        return unlink($mu);
    }

    /**
     * @return mixed
     */
    public function getPluginFile()
    {
        return $this->plugin_file;
    }

    /**
     * @return mixed
     */
    public function getPluginDirUrl()
    {
        return plugin_dir_url($this->getPluginFile());
    }

    /**
     * @return mixed
     */
    public function getPluginBasename()
    {
        return plugin_basename($this->getPluginFile());
    }

    /**
     * @return void
     */
    public function enqueueAssets(): void
    {
        global $plugin_page;
        if( strpos( $plugin_page, $this->getName() ) !== false ) {
            wp_enqueue_style($this->getName(), $this->getPluginDirUrl() . '/assets/style/admin.css', null, '1.0.1');
        }
    }
}