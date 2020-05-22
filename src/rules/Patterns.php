<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use Exception;
use NovemBit\wp\plugins\spm\Bootstrap;

class Patterns
{

    use Registrable;

    /**
     * @var Rules
     * */
    public $parent;
    /**
     * @var array
     * */
    private static $settings;

    /**
     * @var array
     * */
    public static $config;

    /**
     * @return array|Option[]
     */
    public static function getSettings(): array
    {
        if (!isset(self::$settings)) {
            self::$settings = [
                'patterns' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'type' => Option::TYPE_GROUP,
                        'values' => [],
                        'main_params' => ['col' => 2],
                        'template' => [
                            'name' => [
                                'label' => 'Name',
                                'type' => Option::TYPE_TEXT,
                                'required' => true,
                                'main_params' => ['col' => 1]
                            ],
                            'label' => [
                                'label' => 'Label',
                                'type' => Option::TYPE_TEXT,
                                'required' => true,
                                'main_params' => ['col' => 1]
                            ],
                            'rules' => [
                                'main_params' => ['col' => 2],
                                'type' => Option::TYPE_GROUP,
                                'method' => Option::METHOD_MULTIPLE,
                                'template' => Rules::getRulesSettings(),
                                'label' => 'Rules'
                            ],
                        ],
                        'label' => 'Patterns'
                    ]
                ),
            ];
        }
        return self::$settings;
    }


    public static function getConfig(): array
    {
        if (!isset(self::$config)) {
            self::$config = Option::expandOptions(
                self::getSettings(),
                self::getName(),
                [
                    'serialize' => true,
                    'single_option' => true
                ]
            );
        }
        return self::$config;
    }

    /**
     * Patterns constructor.
     * @param Rules $parent
     */
    public function __construct(Rules $parent)
    {
        $this->parent = $parent;

        add_filter(
            'wp-lib-option/' . self::getName() . '/expanded-option',
            function (array $config) {
                self::overwriteRegistered($config['patterns'], $this->predefined());
                self::overwriteRegistered($config['patterns'], $this->getRegistered());
                return $config;
            }
        );

        if (is_admin()) {
            $this->adminInit();
        }
    }


    public function predefined(): array
    {
        $generated_patterns = [
            // Is Backend
            [
                'name' => 'backend',
                'label' => 'Backend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'is_admin',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '1',
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ],
            // Is frontend
            [
                'name' => 'frontend',
                'label' => 'Frontend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'defined',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '0',
                                        'params' => [
                                            'WP_ADMIN'
                                        ],
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ],
            [
                'name' => 'wp_doing_ajax',
                'label' => 'WP Ajax request',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'wp_doing_ajax',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '1',
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ]
        ];

        if ((Bootstrap::getConfig()['rules']['patterns']['wp_rewrite_rules'] ?? false)) {
            $rewrite_rules = get_option('rewrite_rules', []);
            foreach ($rewrite_rules as $rule => $rewrite) {
                $generated_patterns[] = [
                    'name' => $rule,
                    'label' => 'RR: ' . $rule,
                    'rules' =>
                        [
                            [
                                'rule' =>
                                    [
                                        [
                                            'type' => $this->parent::TYPE_HOOK,
                                            'key' => $this->parent->parent->helpers->getHookName('RequestPath'),
                                            'compare' => Variables::COMPARE_REGEXP,
                                            'value' => '#' . $rule . '#',
                                            'logic' => $this->parent::LOGIC_AND
                                        ],
                                    ],
                                'logic' => $this->parent::LOGIC_AND,
                            ],
                        ],
                ];
            }
        }

        return apply_filters(self::getName() . '-predefined', $generated_patterns);
    }

    /**
     * @param array $patterns
     * @param array|null $verbose
     * @return bool
     */
    public function checkPatterns(array $patterns, ?array &$verbose = null): bool
    {
        foreach ($patterns as $pattern_name) {
            $pattern = $this->getPattern($pattern_name);

            if (isset($pattern['rules']) && $this->parent->checkRules($pattern['rules'], $verbose[$pattern_name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getPattern($name)
    {
        return Arrays::ufind(
            self::getPatterns(),
            $name,
            'name',
            null,
            static function ($a, $b) {
                return $a === $b;
            }
        );
    }

    /**
     * @return array
     */
    public static function getPatterns(): array
    {
        return self::getConfig()['patterns'] ?? [];
    }

    /**
     * @return array
     */
    public static function getPatternsMap(): array
    {
        $list = [];
        foreach (self::getPatterns() as $filter) {
            if (isset($filter['name'])) {
                $list[$filter['name']] = $filter['label'] ?? $filter['name'];
            }
        }

        return $list;
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return Rules::getName() . '-patterns';
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
     * @var void
     * @uses tabContent
     */
    public function adminMenu(): void
    {
        add_filter(
            Rules::getName() . '-tabs',
            function ($tabs) {
                $tabs['patterns'] = ['label' => 'Patterns', 'content' => [$this, 'tabContent']];
                return $tabs;
            },
            11
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function tabContent(): void
    {
        Option::printForm(
            self::getName(),
            self::getSettings(),
            [
                'wrap_params' => ['style' => 'width:100%;max-width:calc( 100% - 20px );'],
                'serialize' => true,
                'single_option' => true
            ]
        );
    }

}