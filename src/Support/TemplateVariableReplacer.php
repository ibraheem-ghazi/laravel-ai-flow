<?php

/** @noinspection PhpConditionCheckedByNextConditionInspection */

namespace IbraheemGhazi\LaravelAiFlow\Support;

use Illuminate\Support\Arr;

class TemplateVariableReplacer
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function replaceInString(string $subject, array $params): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([^}|]+?)\s*(?:\|\s*([^}]*?))?\s*}}/',
            static function (array $matches) use ($params): string {
                $key = trim($matches[1]);
                $fallback = array_key_exists(2, $matches) ? trim($matches[2]) : null;

                return self::resolvePlaceholder($key, $fallback, $params);
            },
            $subject
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function replaceInArray(array $subject, array $params): array
    {
        $resolved = [];

        foreach ($subject as $key => $value) {
            if (is_string($value)) {
                $resolved[$key] = self::replaceInString($value, $params);

                continue;
            }

            if (is_array($value)) {
                $resolved[$key] = self::replaceInArray($value, $params);

                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function resolvePlaceholder(string $key, ?string $fallback, array $params): string
    {
        $value = data_get($params, $key);

        if (self::isBlank($value)) {
            return $fallback ?? '';
        }

        return self::formatValue($value);
    }

    private static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && $value === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    private static function formatValue(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        if (Arr::isAssoc($value)) {
            $pairs = [];

            foreach ($value as $pairKey => $pairValue) {
                $pairs[] = $pairKey.'='.self::formatValue($pairValue);
            }

            return implode(',', $pairs);
        }

        return implode(',', array_map(
            static fn (mixed $item): string => self::formatValue($item),
            $value
        ));
    }
}
