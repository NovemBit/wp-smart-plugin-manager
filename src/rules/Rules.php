<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\helpers\URL;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\plugins\Plugins;
use NovemBit\wp\plugins\spm\system\Component;

/**
 * @property Patterns $patterns
 * @property Bootstrap $parent
 * */
class Rules extends Component
{

    public const TYPE_REQUEST = 'request';
    public const TYPE_GET = 'get';
    public const TYPE_POST = 'post';
    public const TYPE_COOKIE = 'cookie';
    public const TYPE_SERVER = 'server';
    public const TYPE_HOOK = 'hook';
    public const TYPE_FUNCTION = 'function';

    public const LOGIC_AND = 'and';
    public const LOGIC_OR = 'or';
    public const LOGIC_NOT = 'not';


    protected static function components(): array
    {
        return [
            'patterns' => Patterns::class,
        ];
    }

    /**
     * @var array
     * */
    public $tabs = [];

    /**
     * @var array
     * */
    public static $settings;

    /**
     * @var array
     * */
    public static $config;

    public static function getSettings():array{
        if(!isset(self::$settings)){
            self::$settings = [
                'plugins' => [
                    'rules' => new Option(
                        [
                            'default' => false,
                            'type' => Option::TYPE_BOOL,
                            'label' => 'Rules',
                            'description' => 'Each plugin can have custom specific rules.'
                        ]
                    ),
                    'patterns' => new Option(
                        [
                            'default' => true,
                            'type' => Option::TYPE_BOOL,
                            'label' => 'Patterns',
                            'description' => 'Each plugin can have custom specific patterns.'
                        ]
                    )
                ],
                'patterns' => [
                    'active' => new Option(
                        [
                            'default' => true,
                            'type' => Option::TYPE_BOOL,
                            'label' => 'Enable patterns',
                            'description' => 'Enable patterns system.'
                        ]
                    ),
                    'include_wp_rewrite_rules_as_patterns' => new Option(
                        [
                            'default' => false,
                            'type' => Option::TYPE_BOOL,
                            'label' => 'Include WordPress rewrite rules as patterns',
                            'description' => HTML::tag(
                                'div',
                                [
                                    ['span', 'Include WordPress rewrite rules as patterns.'],
                                    ['h4', 'In plugins patterns field pattern names starting with RR']
                                ]
                            )
                        ]
                    ),
                ]
            ];
        }
        return self::$settings;
    }

    /**
     * @return array
     */
    public static function getConfig():array{
        if(!isset(self::$config)) {
            self::$config = Option::expandOptions(
                self::getSettings(),
                self::getName(),
                ['serialize' => true, 'single_option' => true]
            );
        }
        return self::$config;
    }
    /**
     * Patterns constructor.
     */
    public function init(): void
    {
    }

    public function run(): void
    {
        if (is_admin()) {
            $this->adminInit();
        }

        $this->patterns->run();
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
     * @return string
     */
    public static function getName(): string
    {
        return Bootstrap::getName() . '-rules';
    }

    /**
     * @return void
     * @uses adminContent
     */
    public function adminMenu(): void
    {
        add_submenu_page(
            Bootstrap::getName(),
            __('Rules', 'novembit-spm'),
            __('Rules', 'novembit-spm'),
            'manage_options',
            self::getName(),
            [$this, 'adminContent']
        );

        $this->tabs['default'] = ['label' => 'Rules', 'content' => [$this, 'defaultTabContent']];
    }

    /**
     * Admin Content
     * @return void
     */
    public function adminContent(): void
    {
        $tabs = [];
        $current_url = admin_url('admin.php?page=' . self::getName());
        $active = Environment::get('sub-action') ?? 'default';

        $active_tab = $this->tabs[$active];

        foreach ($this->tabs as $tab => $params) {
            $url = URL::addQueryVars($current_url, 'sub-action', $tab);
            $active_class = $active === $tab ? ' nav-tab-active' : '';
            $tabs[] = [
                'a',
                $params['label'] ?? $tab,
                ['class' => 'nav-tab' . $active_class, 'href' => $url]
            ];
        }
        echo HTML::tag('nav', $tabs, ['class' => 'nav-tab-wrapper']);

        $content = $active_tab['content'] ?? null;

        if (is_callable($content)) {
            $content();
            return;
        }

        if (is_string($content)) {
            echo $content;
        }
    }

    /**
     * Default Tab content
     * @return void
     */
    public function defaultTabContent(): void
    {
        Option::printForm(
            self::getName(),
            self::getSettings(),
            [
                'title' => 'Rules configuration',
                'serialize' => true,
                'single_option' => true
            ]
        );
    }

    /**
     * @return array
     */
    public static function getRulesSettings(): array
    {
        return [
            'rule' => self::getRuleSetting(),
            'logic' => self::getLogicSetting()
        ];
    }

    /**
     * @return array
     */
    public static function getLogicSetting(): array
    {
        return [
            'type' => Option::TYPE_TEXT,
            'label' => 'Logic',
            'values' => [
                self::LOGIC_AND => 'And',
                self::LOGIC_OR => 'Or',
                self::LOGIC_NOT => 'Not',
            ]
        ];
    }

    /**
     * @return array
     */
    public static function getRuleSetting(): array
    {
        return [
            'type' => Option::TYPE_GROUP,
            'method' => Option::METHOD_MULTIPLE,
            'label' => 'Single Rule',
            'main_params' => ['col' => '3'],
            'template' => [
                'type' => [
                    'type' => Option::TYPE_TEXT,
                    'values' => [
                        self::TYPE_REQUEST => 'Request',
                        self::TYPE_GET => 'Get',
                        self::TYPE_POST => 'Post',
                        self::TYPE_COOKIE => 'Cookie',
                        self::TYPE_SERVER => 'Server',
                        self::TYPE_HOOK => 'Hook',
                        self::TYPE_FUNCTION => 'Function'
                    ]
                ],
                'key' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Key',
                ],
                'compare' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Compare operator',
                    'default' => 'equal',
                    'values' => [
                        Variables::COMPARE_EQUAL => 'Equal ( == )',
                        Variables::COMPARE_NOT_EQUAL => 'Not equal ( <> )',
                        Variables::COMPARE_IDENTICAL => 'Identical ( === )',
                        Variables::COMPARE_NOT_IDENTICAL => 'Not Identical ( !== )',
                        Variables::COMPARE_GREATER_THAN => 'Greater than ( > )',
                        Variables::COMPARE_GREATER_THAN_OR_EQUAL => 'Greater than or equal ( >= )',
                        Variables::COMPARE_LESS_THAN => 'Less than ( < )',
                        Variables::COMPARE_LESS_THAN_OR_EQUAL => 'Less than or equal ( <= )',
                        Variables::COMPARE_CONTAINS => 'Contains ( %word% )',
                        Variables::COMPARE_REGEXP => 'Regular expression ( /^(man|woman)$/ )',
                        Variables::COMPARE_STARTS_WITH => 'Starts with',
                        Variables::COMPARE_ENDS_WITH => 'Ends with',
                    ]
                ],
                'value' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Value (accepting magic params)'
                ],
                'params' => [
                    'label' => 'Additional params ( accepting magic params )',
                    'method' => Option::METHOD_MULTIPLE,
                    'type' => Option::TYPE_TEXT
                ],
                'logic' => self::getLogicSetting()
            ],
        ];
    }

    /**
     * Check rule with custom described pattern
     * Included Login types
     *      `LOGIC_AND`
     *      `LOGIC_OR`
     *      `LOGIC_NOT`
     *
     * @param array $rules
     * @return bool
     */
    public function checkRules(array $rules): bool
    {
        $status = null;

        foreach ($rules as $_rules) {
            $logic = $_rules['logic'] ?? self::LOGIC_AND;

            if (isset($_rules['rule'])) {
                $assertion = $this->checkRules(array_values($_rules['rule']));
            } else {
                $type = $_rules['type'] ?? null;
                $key = self::extractVariables($_rules['key'] ?? null);
                $value = self::extractVariables($_rules['value'] ?? null);
                $params = $_rules['params'] ?? [];
                $compare = $_rules['compare'] ?? null;

                if (!$type || !$key) {
                    continue;
                }

                if (in_array($type, [self::TYPE_REQUEST, self::TYPE_GET, self::TYPE_POST, self::TYPE_SERVER], true)) {
                    $_value = call_user_func([Environment::class, $type], $key);
                } elseif ($type === self::TYPE_HOOK) {
                    $_value = apply_filters($key, $params);
                } elseif ($type === self::TYPE_FUNCTION && function_exists($key)) {
                    $_value = $key(...self::extractAllVariables($params));
                } else {
                    continue;
                }

                $_value = apply_filters(self::getName() . '-assertion-' . $type . '-value', $_value, $params);
                $_value = apply_filters(self::getName() . '-assertion-value', $_value, $type, $key, $params);

                $assertion = Variables::compare($compare, $_value, $value);
            }

            if ($logic === 'and') {
                $status = ($status ?? true) && $assertion;
            }
            if ($logic === 'or') {
                $status = ($status ?? true) || $assertion;
            }
            if ($logic === 'not') {
                $status = ($status ?? true) && !$assertion;
            }
        }

        return $status ?? false;
    }

    /**
     * @param array $strings
     * @return array
     */
    private static function extractAllVariables(array $strings): array
    {
        foreach ($strings as &$string) {
            $string = self::extractVariables($string);
        }
        return $strings;
    }

    /**
     * Parse strings like examples
     * https://regex101.com/r/kuPqSO/1
     *
     * @param string $string
     * @return string
     *
     * @example {{$array->key1->key2->val}}
     * @example {{$some_global_key}}
     */
    private static function extractVariables(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }
        return preg_replace_callback(
            '/{{(\$|VAR:|@|CONST:)(.*?)}}/',
            static function ($matches) {
                $type = $matches[1];
                $path_parts = explode('->', $matches[2]);
                $value = null;

                $elem = null;

                foreach ($path_parts as $part) {
                    if ($elem === null) {
                        if ($type === '$' || $type === 'VAR:') {
                            global ${$part};
                            if (!isset(${$part})) {
                                break;
                            }
                            $elem = ${$part};
                        } elseif ($type === '@' || $type === 'CONST:') {
                            $elem = constant($part);
                        } else {
                            break;
                        }
                    } elseif (is_array($elem)) {
                        $elem = $elem[$part] ?? null;
                    } elseif (is_object($elem)) {
                        $elem = $elem->{$elem} ?? null;
                    } else {
                        $elem = null;
                        break;
                    }
                }
                return $elem ?? $matches[0];
            },
            $string
        );
    }

}