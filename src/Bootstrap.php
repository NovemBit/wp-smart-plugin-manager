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
use NovemBit\wp\plugins\spm\system\Component;
use RuntimeException;
use WP_Admin_Bar;

/**
 * @property Plugins $plugins
 * @property Integrations $integrations
 * @property Helpers $helpers
 * @property Rules $rules
 * */
class Bootstrap extends Component
{

    public const SLUG = 'smart-plugin-manager';

    /**
     * Statuses
     * */
    public const STATUS_ENABLE_WHEN = 'enable_when';
    public const STATUS_DISABLE_WHEN = 'disable_when';
    public const STATUS_SMART = 'smart';

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
    private static $settings;

    /**
     * @var array
     * */
    private static $config;


    /**
     * @var array
     * */
    private $all_plugins = [];

    /**
     * @return array|string[]
     */
    public static function components(): array
    {
        return [
            'helpers' => Helpers::class,
            'integrations' => Integrations::class,
            'rules' => Rules::class,
            'plugins' => Plugins::class
        ];
    }

    /**
     * @param string|null $plugin_file
     * @return self
     */
    public static function instance(?string $plugin_file = null): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$instance->plugin_file = $plugin_file;
        }

        return self::$instance;
    }

    /**
     * @return array
     */
    public function getAllPlugins(): array
    {
        return $this->all_plugins;
    }

    /**
     * @return array
     */
    public function getAllPluginsMap(): array
    {
        $result = [];

        foreach ($this->getAllPlugins() as $file => $data) {
            $result[$file] = $data['Name'] ?? $file;
        }
        return $result;
    }

    /**
     * Set All plugins
     * @return void
     */
    public function setAllPlugins(): void
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $this->all_plugins = get_plugins();

        unset($this->all_plugins[self::getSelfPlugin()]);

        foreach ($this->all_plugins as $plugin => &$data) {
            $data['custom_data']['is_active'] = is_plugin_active($plugin);
        }
    }

    /**
     * @return bool
     */
    public function isEnabledPatterns(): bool
    {
        return ($this->rules::getConfig()['plugins']['patterns'] ?? false);
    }

    /**
     * @return bool
     */
    public function isEnabledCustomRules(): bool
    {
        return $this->rules::getConfig()['plugins']['rules'] ?? false;
    }

    /**
     * Is Plugin activated from Core
     *
     * @param string $plugin
     * @return bool
     */
    public function isPluginActive(string $plugin): bool
    {
        return $this->getAllPlugins()[$plugin]['custom_data']['is_active'] ?? false;
    }

    /**
     * @return string
     */
    public static function getSelfPlugin(): string
    {
        return sprintf('%1$s/%1$s.php', self::getName());
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return self::SLUG;
    }

    /**
     * @param array $data
     * @return string
     */
    public static function getAuthorizedActionFormDescription(array $data): string
    {
        $html = '';
        $value = $data['value'] ?? null;
        if ($value !== null) {
            $action = explode('>', $data['name'])[0] ?? null;
            $url = site_url() . '/?' . self::getName() . '-' . $action . '-secret=' . $value;
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

        $secret = self::getConfig()['debug']['secret'] ?? null;
        if ($secret === null) {
            return null;
        }

        return URL::addQueryVars(
            $url,
            self::getName() . '-debug-secret',
            $secret
        );
    }

    public static function getSettings():array{
        if(!isset(self::$settings)){
            self::$settings =  [
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
                            'description' => [self::class, 'getAuthorizedActionFormDescription']
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
                            'description' => [self::class, 'getAuthorizedActionFormDescription']
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
        }
        return self::$settings;
    }

    public static function getConfig():array{
        if(!isset(self::$config)){
            self::$config = Option::expandOptions(
                self::getSettings(),
                self::getName(),
                ['serialize' => true, 'single_option' => true]
            );
        }

        return self::$config;
    }

    /**
     * Bootstrap constructor.
     */
    protected function init(): void
    {
        $this->setAllPlugins();

        if (is_admin()) {
            $this->adminInit();
        }

        $this->commonInit();
    }

    /**
     * @param WP_Admin_Bar $admin_bar
     */
    public function adminBarMenu($admin_bar): void
    {
        $admin_bar->add_menu(
            array(
                'id' => self::getName(),
                'title' => __('SPM', 'novembit-spm'),
                'meta' => array(
                    'title' => __('Smart Plugin Manager', 'novembit-spm'),
                ),
            )
        );

        $admin_bar->add_menu(
            array(
                'id' => self::getName() . '-settings',
                'parent' => self::getName(),
                'href' => admin_url('admin.php?page=' . self::getName()),
                'title' => 'Settings',
                'meta' => array(
                    'title' => 'Settings',
                ),
            )
        );

        if (self::getConfig()['debug']['active'] ?? false) {
            $admin_bar->add_menu(
                array(
                    'id' => self::getName() . '-debug',
                    'parent' => self::getName(),
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
        register_activation_hook($this->getPluginFile(), [$this, 'install']);

        register_deactivation_hook($this->getPluginFile(), [$this, 'uninstall']);

        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
    }

    /**
     * @return void
     * @uses enqueueCommonAssets
     */
    public function commonInit(): void
    {
        /**
         * @uses adminBarMenu
         * */
        add_action('admin_bar_menu', [$this, 'adminBarMenu'], 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueueCommonAssets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueCommonAssets'));

        $this->integrations->run();
        $this->rules->run();
        $this->plugins->run();
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
            self::getName(),
            [$this, 'adminContent'],
            'dashicons-admin-site-alt',
            75
        );
    }

    /**
     * Admin Content
     * @throws \Exception
     */
    public function adminContent(): void
    {
        Option::printForm(
            self::getName(),
            self::getSettings(),
            [
                'serialize' => true,
                'single_option' => true
            ]
        );
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
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $mu = WPMU_PLUGIN_DIR . '/' . self::getName() . '.php';
        $content = '<?php' . PHP_EOL;
        $content .= ' // This is auto generated file' . PHP_EOL;
        $content .= 'include_once WP_PLUGIN_DIR."/' . self::getName() . '/' . self::getName() . '.php";';
        return file_put_contents($mu, $content);
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        $mu = WPMU_PLUGIN_DIR . '/' . self::getName() . '.php';
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
        if (strpos($plugin_page, self::getName()) !== false) {
            wp_enqueue_style(self::getName(), $this->getPluginDirUrl() . '/assets/style/admin.css', null, '1.0.1');
        }
    }

    /**
     * @return void
     */
    public function enqueueCommonAssets(): void
    {
        wp_enqueue_style(self::getName(), $this->getPluginDirUrl() . '/assets/style/common.css', null, '1.0.1');
    }

    /**
     * @param string $action
     * @return bool
     * @see authorizedDebug
     * @see authorizedEmergency
     */
    private function authorizedAction(string $action): bool
    {
        $active = self::getConfig()[$action]['active'] ?? false;
        $secret = self::getConfig()[$action]['secret'] ?? null;
        return (
            $active &&
            $secret !== null &&
            Environment::request(self::getName() . '-' . $action . '-secret') === $secret
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

}