<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
use diazoxide\wp\lib\option\v2\Option;

trait Registrable
{
    /**
     * @param array $data
     */
    public function register(array $data): void
    {
        $registered_data = Option::getOption('registered', static::getName(), []);
        self::overwriteRegistered($registered_data, $data);
        Option::setOption('registered', static::getName(), $registered_data, true);
    }

    /**
     * @return array
     */
    public function getRegistered(): array
    {
        return Option::getOption('registered', static::getName(), [], true);
    }

    /**
     * @param string $name
     */
    public function removeRegistered(string $name): void
    {
        $registered_data = Option::getOption('registered', static::getName(), [], true);

        $index = Arrays::ufind($registered_data, 'name', $name);

        unset($registered_data[$index]);

        Option::setOption('registered', static::getName(), $registered_data, true);
    }

    /**
     * @param array $data
     * @param array $predefined_data
     * @return void
     */
    public static function overwriteRegistered(array &$data, array $predefined_data): void
    {
        foreach ($predefined_data as $item) {
            $name = $item['name'] ?? null;
            if ($name !== null) {
                $existing_rule = Arrays::ufind(
                    $data,
                    $name,
                    'name'
                );
                if ($existing_rule === null) {
                    $data[] = $item;
                }
            }
        }
    }

    abstract public static function getName(): string;

}