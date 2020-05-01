<?php

namespace NovemBit\wp\plugins\spm\integrations\brandlight;


use NovemBit\wp\plugins\spm\integrations\Integrations;
use NovemBit\wp\plugins\spm\rules\Patterns;

class Brandlight
{
    /**
     * @var Integrations
     */
    public $parent;

    public const NAME = 'Brandlight stack websites';

    private function getPatterns(): array
    {
        $request_path_hook_name = $this->parent->parent->helpers->getHookName('RequestPath');

        return [
            [
                'label' => 'BL: pages/homepage',
                'name' => 'bl_pages/homepage',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'equal',
                                        'value' => '/',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: post/docs',
                'name' => 'bl_post/docs',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(docs|dc|d)($|(\/.*))#',
                                        'logic' => 'and',
                                    ]
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: ajax/adsl_get_fragments',
                'name' => 'bl_ajax/adsl_get_fragments',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'function',
                                        'key' => 'defined',
                                        'compare' => 'equal',
                                        'value' => '1',
                                        'params' => ['DOING_AJAX'],
                                        'logic' => 'and',
                                    ],
                                    [
                                        'type' => 'request',
                                        'key' => 'action',
                                        'compare' => 'equal',
                                        'value' => 'adsl_get_fragments',
                                        'params' => [],
                                        'logic' => 'and',
                                    ]
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: ajax/woocommerce_get_refreshed_fragments',
                'name' => 'bl_ajax/woocommerce_get_refreshed_fragments',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'function',
                                        'key' => 'defined',
                                        'compare' => 'equal',
                                        'value' => '1',
                                        'params' => ['DOING_AJAX'],
                                        'logic' => 'and',
                                    ],
                                    [
                                        'type' => 'request',
                                        'key' => 'action',
                                        'compare' => 'equal',
                                        'value' => 'woocommerce_get_refreshed_fragments',
                                        'params' => [],
                                        'logic' => 'and',
                                    ]
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: ajax/get_refreshed_fragments',
                'name' => 'bl_ajax/get_refreshed_fragments',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'request',
                                        'key' => 'wc-ajax',
                                        'compare' => 'equal',
                                        'value' => 'get_refreshed_fragments',
                                        'params' => [],
                                        'logic' => 'and',
                                    ]
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: post/wiki',
                'name' => 'bl_post/wiki',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(wiki|wc|w)($|(\/.*))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: url/shop/*',
                'name' => 'bl_url/shop/*',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^/shop(/.*)?#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: post/info',
                'name' => 'bl_post/info',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(info|i)($|(\/.*))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: post/articles/archive',
                'name' => 'bl_post/articles/archive',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(article|ac)($|(\/.*))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: post/help',
                'name' => 'bl_post/help',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(help|hc|h)($|(\/.*))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: wp-admin/product-list',
                'name' => 'bl_wp-admin/product-list',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'starts_with',
                                        'value' => '/wp-admin/edit.php?post_type=product',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/product',
                'name' => 'bl_pages/product',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(p)($|(\/.*))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: wp-admin/uxbuilder',
                'name' => 'bl_wp-admin/uxbuilder',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?((wp-admin/edit\.php\?page=uxbuilder)|(post\.php\?post=asd.*?app=uxbuilder))#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/frontend',
                'name' => 'bl_pages/frontend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#^\/?(wp-admin|wp-cron\.php|wp-json)($|(\/.*))#',
                                        'logic' => 'not'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],


            [
                'label' => 'BL: pages/archive',
                'name' => 'bl_pages/archive',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/shop/$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/my-account',
                'name' => 'bl_pages/my-account',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/my-account/?$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/shopping-lists',
                'name' => 'bl_pages/shopping-lists',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/shopping-lists/?$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/contact-us',
                'name' => 'bl_pages/contact-us',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/contact-us/?$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/cart',
                'name' => 'bl_pages/cart',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/shop/basket/?$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'label' => 'BL: pages/checkout',
                'name' => 'bl_pages/checkout',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'hook',
                                        'key' => $request_path_hook_name,
                                        'compare' => 'regexp',
                                        'value' => '#/shop/checkout/?$#',
                                        'logic' => 'and'
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
        ];
    }

    public function __construct(Integrations $parent)
    {
        $this->parent = $parent;

        add_filter(
            'smart-plugin-manager-rules-patterns-predefined',
            function ($patterns) {
                Patterns::overwritePatterns(
                    $patterns,
                    $this->getPatterns()
                );
                return $patterns;
            }
        );
    }

    public function getName(): string
    {
        return $this->parent->getName() . '-brandlight';
    }
}