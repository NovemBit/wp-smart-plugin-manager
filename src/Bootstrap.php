<?php

namespace NovemBit\wp\plugins\spm;

use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\wp\lib\option\v2\Option;
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

    private $settings;

    /**
     * @var array
     * */
    private $config;

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
     * @param array $data
     * @return string
     */
    public function getAuthorizedActionFormDescription(array $data): string
    {
        $html = '';
        $value = $data['value'] ?? null;
        if ($value !== null) {
            $action = explode('>', $data['name'])[0] ?? null;
            $url = site_url() . '/?' . $this->getName() . '-' . $action . '-secret=' . $value;
            $html = HTML::tag('a', $url, ['href' => $url, 'target' => '_blank']);
        }
        return $html;
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

        $this->settings = [
            'emergency' => [
                'active' => new Option(
                    [
                        'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                        'default' => true,
                        'type' => Option::TYPE_BOOL,
                        'label' => 'Enable emergency restore'
                    ]
                ),
                'secret' => new Option(
                    [
                        'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                        'default' => md5(AUTH_KEY),
                        'type' => Option::TYPE_TEXT,
                        'label' => 'Secret code',
                        'description' => [$this, 'getAuthorizedActionFormDescription']
                    ]
                )
            ],
            'debug' => [
                'active' => new Option(
                    [
                        'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                        'default' => true,
                        'label' => 'Enable debug tools',
                        'type' => Option::TYPE_BOOL
                    ]
                ),
                'secret' => new Option(
                    [
                        'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                        'default' => md5(LOGGED_IN_KEY),
                        'label' => 'Secret code',
                        'type' => Option::TYPE_TEXT,
                        'description' => [$this, 'getAuthorizedActionFormDescription']
                    ]
                )
            ]
        ];

        $this->config = Option::expandOptions($this->settings, $this->getName());

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
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
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
        Option::printForm($this->getName(), $this->settings);
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
        if (strpos($plugin_page, $this->getName()) !== false) {
            wp_enqueue_style($this->getName(), $this->getPluginDirUrl() . '/assets/style/admin.css', null, '1.0.1');
        }
    }

    /**
     * @param string $action
     * @return bool
     */
    private function authorizedAction(string $action): bool
    {
        $active = $this->getConfig()[$action]['active'] ?? false;
        $secret = $this->getConfig()[$action]['secret'] ?? null;
        return (
            $active &&
            $secret !== null &&
            Environment::request($this->getName() . '-' . $action . '-secret') === $secret
        );
    }

    /**
     * @return bool
     */
    public function authorizedDebug(): bool
    {
        return $this->authorizedAction('debug');
    }

    /**
     * @return bool
     */
    public function authorizedEmergency(): bool
    {
        return $this->authorizedAction('emergency');
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}