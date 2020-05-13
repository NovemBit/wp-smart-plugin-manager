<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\plugins\Plugins;
use NovemBit\wp\plugins\spm\system\Component;

/**
 * @property Rules $parent
 * */
class Filters
{

    use Registrable;

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
                'filters' => new Option(
                    [
                        'default' => [],
                        'method' => Option::METHOD_MULTIPLE,
                        'type' => Option::TYPE_GROUP,
                        'values' => [],
                        'main_params' => ['col' => 2],
                        'before_set_value' => static function (Option $option, &$value) {
                            $map = Option::getOption(Plugins::filtersRelationMapName(), Bootstrap::getName(), [], true);
                            foreach ($value as $group) {
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
                            Option::setOption(Plugins::filtersRelationMapName(), Bootstrap::getName(), $map, true);
                            return true;
                        },
                        'before_get_value' => static function (Option $option, &$value) {
                            $map = Option::getOption(Plugins::filtersRelationMapName(), Bootstrap::getName(), [], true);
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
                                'main_params' => ['col' => 1]
                            ],
                            'label' => [
                                'label' => 'Label',
                                'type' => Option::TYPE_TEXT,
                                'required' => true,
                                'main_params' => ['col' => 1]
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
                            'patterns' => [
                                'method' => Option::METHOD_MULTIPLE,
                                'label' => 'Patterns',
                                'values' => Patterns::getPatternsMap(),

                            ],
                            'rules' => [
                                'main_params' => ['col' => 2],
                                'type' => Option::TYPE_GROUP,
                                'method' => Option::METHOD_MULTIPLE,
                                'template' => Rules::getRulesSettings(),
                                'label' => 'Rules'
                            ],

                        ],

                        'label' => 'Filters'
                    ]
                ),
            ];
        }
        return self::$settings;
    }


    /**
     * @return array
     */
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
     * Filters constructor.
     *
     * @param Rules $parent
     */
    public function __construct(Rules $parent)
    {
        $this->parent = $parent;
        add_filter(
            'wp-lib-option/' . self::getName() . '/expanded-option',
            function ($config) {
                self::overwriteRegistered($config['filters'], $this->predefined());
                self::overwriteRegistered($config['filters'], $this->getRegistered());
                return $config;
            }
        );
        if (is_admin()) {
            $this->adminInit();
        }
    }


    public function predefined(): array
    {
        $generated_filters = [];
        return apply_filters(self::getName() . '-predefined', $generated_filters);
    }

    /**
     * @param array $filters
     * @param array|null $verbose
     * @return bool
     */
    public function checkFilters(array $filters, ?array &$verbose = null): bool
    {
        foreach ($filters as $filter_name) {
            $filter = $this->getFilter($filter_name);

            if (
                (isset($filter['rules']) && $this->parent->checkRules($filter['rules'], $verbose[$filter_name]['rules'])) ||
                (isset($filter['patterns']) && $this->parent->patterns->checkPatterns(
                        $filter['patterns'],
                        $verbose[$filter_name]['patterns']
                    ))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getFilter($name)
    {
        return Arrays::ufind(
            self::getFilters(),
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
    public static function getFilters(): array
    {
        return self::getConfig()['filters'] ?? [];
    }

    /**
     * @return array
     */
    public function getFiltersMap(): array
    {
        $list = [];
        foreach (self::getFilters() as $filter) {
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
        return Rules::getName() . '-filters';
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
        $this->parent->tabs['filters'] = ['label' => 'Filters', 'content' => [$this, 'tabContent']];
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