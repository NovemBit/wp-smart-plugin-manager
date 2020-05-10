<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\plugins\Plugins;
use NovemBit\wp\plugins\spm\system\Component;

/**
 * @property Rules $parent
 * */
class Patterns extends Component
{

    /**
     * @var array
     * */
    private static $settings;

    /**
     * @var array
     * */
    public static $config;

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
                        'before_set_value' => static function (Option $option, &$value) {
                            $map = Option::getOption('_asd_relation_map', '_asd', [], true);
                            foreach ($value as $group){

                                foreach ($group['plugins'] as $item) {
                                    $row = [$item, $group['name']];
                                    if (!in_array($row, $map, true)) {
                                        $map[] = $row;
                                    }
                                }

                                foreach ($map as $key => $row) {
                                    if (($row[1] === $group['name']) && !in_array($row[0], $group['plugins'], true)) {
                                        unset($map[$key]);
                                    }
                                }
                            }
                            Option::setOption('_asd_relation_map', '_asd', $map, true);
                            return false;
                        },
                        'before_get_value' => static function (Option $option, &$value) {
                            $map = Option::getOption('_asd_relation_map', '_asd', [], true);
                            foreach ($value as &$group) {
                                $group['plugins'] = [];
                                foreach ($map as $item) {
                                    if ($item[1] === $group['name']) {
                                        $group['plugins'][] = $item[0];
                                    }
                                }
                            }
                        },
                        'template' => [
                            'name' => [
                                'label' => 'Name',
                                'type' => Option::TYPE_TEXT,
                                'required' => true,
                                'main_params' => ['col' => 2]
                            ],
                            'label' => [
                                'label' => 'Label',
                                'type' => Option::TYPE_TEXT,
                                'required' => true,
                                'main_params' => ['col' => 2]
                            ],
                            'plugins' => [
                                'method' => Option::METHOD_MULTIPLE,
                                'label' => 'Plugins',
                                'relation' => [
                                    'parent' => Plugins::getName(),
                                    'with' => [Plugins::class, 'getSettings'],
                                    'name' => null,
                                    'label' => 'name',
                                ],
                            ],
                            'rules' => [
                                'main_params' => ['col' => 2],
                                'type' => Option::TYPE_GROUP,
                                'method' => Option::METHOD_MULTIPLE,
                                'template' => Rules::getRulesSettings()
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
     */
    public function init(): void
    {

        add_filter(
            'wp-lib-option/' . self::getName() . '/expanded-option',
            function ($config) {
                self::overwritePatterns($config['patterns'], $this->predefined());
                self::overwritePatterns($config['patterns'], $this->getRegistered());
                return $config;
            }
        );
    }

    public function run(): void
    {
        if (is_admin()) {
            $this->adminInit();
        }
    }

    public function predefined(): array
    {
        $generated_patterns = [
            /*// Is Backend
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
            ]*/
        ];

        if (($this->parent::getConfig()['common']['include_wp_rewrite_rules_as_patterns'] ?? false)) {
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
     */
    public function register(array $patterns): void
    {
        $registered_patterns = Option::getOption('registered_patterns', self::getName(), []);
        self::overwritePatterns($registered_patterns, $patterns);
        Option::setOption('registered_patterns', self::getName(), $registered_patterns, true);
    }

    /**
     * @return array
     */
    public function getRegistered(): array
    {
        return Option::getOption('registered_patterns', self::getName(), [], true);
    }

    /**
     * @param string $name
     */
    public function removeRegistered(string $name): void
    {
        $registered_patterns = Option::getOption('registered_patterns', self::getName(), []);

        $index = Arrays::ufind($registered_patterns, 'name', $name);

        unset($registered_patterns[$index]);

        Option::setOption('registered_patterns', self::getName(), $registered_patterns, true);
    }

    /**
     * @param array $patterns
     * @param array $predefined_patterns
     * @return void
     */
    public static function overwritePatterns(array &$patterns, array $predefined_patterns): void
    {
        foreach ($predefined_patterns as $predefined_pattern) {
            $name = $predefined_pattern['name'] ?? null;
            if ($name !== null) {
                $existing_rule = Arrays::ufind(
                    $patterns,
                    $name,
                    'name'
                );
                if ($existing_rule === null) {
                    unset($patterns[$existing_rule]);
                    $patterns[] = $predefined_pattern;
                }
            }
        }
    }

    /**
     * @param array $patterns
     * @return bool
     */
    public function checkPatterns(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = $this->getPattern($pattern);


            if (isset($pattern['rules']) && $this->parent->checkRules($pattern['rules'])) {
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
    public function getPatternsMap(): array
    {
        $list = [];
        foreach (self::getPatterns() as $pattern) {
            if (isset($pattern['name'])) {
                $list[$pattern['name']] = $pattern['label'] ?? $pattern['name'];
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
        add_action('admin_menu', [$this, 'adminMenu'], 11);
    }

    /**
     * @var void
     * @uses tabContent
     */
    public function adminMenu(): void
    {
        $this->parent->tabs['patterns'] = ['label' => 'Patterns', 'content' => [$this, 'tabContent']];
    }

    /**
     * @return void
     * @throws \Exception
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