<?php

namespace NovemBit\wp\plugins\spm;

use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\helpers\URL;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\helpers\Helpers;
use NovemBit\wp\plugins\spm\integrations\Integrations;
use NovemBit\wp\plugins\spm\plugins\Plugins;
use NovemBit\wp\plugins\spm\rules\Rules;
use WP_Admin_Bar;

class Bootstrap
{

    public const SLUG = 'smart-plugin-manager';

    /**
     * @var Plugins
     * */
    public $plugins;

    /**
     * @var Rules
     * */
    public $rules;

    /**
     * @var Integrations
     * */
    public $integrations;


    /**
     * @var Helpers
     * */
    public $helpers;
    /**
     * @var self
     * */
    private static $instance;

    /**
     * @var string
     * */
    private $plugin_file;

    /**
     * @var array
     * */
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
     * @param string|null $url
     * @return string|null
     */
    private function getDebugUrl(?string $url = null): ?string
    {
        if ($url === null) {
            $url = URL::getCurrent();
        }

        $secret = $this->config['debug']['secret'] ?? null;
        if ($secret === null) {
            return null;
        }

        return URL::addQueryVars(
            $url,
            $this->getName() . '-debug-secret',
            $secret
        );
    }

    /**
     * Bootstrap constructor.
     * @param $plugin_file
     * @uses getAuthorizedActionFormDescription
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
                ),
                'plugins_on_admin_bar' => new Option(
                    [
                        'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                        'default' => true,
                        'label' => 'Show plugins in admin bar.',
                        'type' => Option::TYPE_BOOL
                    ]
                ),
            ]
        ];

        $this->config = Option::expandOptions($this->settings, $this->getName(), ['serialize' => true,]);

        if (is_admin()) {
            $this->adminInit();
        }

        $this->commonInit();

        /**
         * @uses adminBarMenu
         * */
        add_action('admin_bar_menu', [$this, 'adminBarMenu'], 100);

        $this->helpers = new Helpers($this);
        $this->integrations = new Integrations($this);
        $this->rules = new Rules($this);
        $this->plugins = new Plugins($this);
    }

    /**
     * @param WP_Admin_Bar $admin_bar
     */
    public function adminBarMenu($admin_bar): void
    {
        $admin_bar->add_menu(
            array(
                'id' => $this->getName(),
                'title' => __('SPM', 'novembit-spm'),
                'meta' => array(
                    'title' => __('Smart Plugin Manager', 'novembit-spm'),
                ),
            )
        );

        $admin_bar->add_menu(
            array(
                'id' => $this->getName() . '-settings',
                'parent' => $this->getName(),
                'href' => admin_url('admin.php?page=' . $this->getName()),
                'title' => 'Settings',
                'meta' => array(
                    'title' => 'Settings',
                ),
            )
        );

        if ($this->getConfig()['debug']['active'] ?? false) {
            $admin_bar->add_menu(
                array(
                    'id' => $this->getName() . '-debug',
                    'parent' => $this->getName(),
                    'href' => $this->getDebugUrl(),
                    'title' => 'Debug',
                    'meta' => array(
                        'title' => 'Debug',
                    ),
                )
            );
        }
    }

    /**
     * @return void
     * @uses adminMenu
     * @uses enqueueAdminAssets
     */
    public function adminInit(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
    }

    /**
     * @return void
     * @uses enqueueCommonAssets
     */
    public function commonInit(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueueCommonAssets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueCommonAssets'));
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

    /**
     * Admin Content
     */
    public function adminContent(): void
    {
        Option::printForm($this->getName(), $this->settings, ['serialize' => true]);
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        if (!file_exists(WPMU_PLUGIN_DIR)
            && !mkdir($concurrentDirectory = WPMU_PLUGIN_DIR, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
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
     * @see getPluginBasename
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
    public function enqueueAdminAssets(): void
    {
        global $plugin_page;
        if (strpos($plugin_page, $this->getName()) !== false) {
            wp_enqueue_style($this->getName(), $this->getPluginDirUrl() . '/assets/style/admin.css', null, '1.0.1');
        }
    }

    /**
     * @return void
     */
    public function enqueueCommonAssets(): void
    {
        wp_enqueue_style($this->getName(), $this->getPluginDirUrl() . '/assets/style/common.css', null, '1.0.1');
    }

    /**
     * @param string $action
     * @return bool
     * @see authorizedDebug
     * @see authorizedEmergency
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