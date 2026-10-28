<?php
namespace FacetasDrawer;

final class FilterEngine
{
    public static function decode($encoded)
    {
        if (!is_string($encoded) || strlen($encoded) > 8192 || strpos($encoded, 'fd1.') !== 0) {
            return self::normalize([]);
        }
        $json = base64_decode(strtr(substr($encoded, 4), '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true, 16);
        return self::normalize(is_array($data) ? $data : []);
    }

    public static function encode(array $state)
    {
        if (!$state['groups'] && $state['min'] === null && $state['max'] === null && !$state['stock']) {
            return '';
        }
        $state['groups'] = (object) $state['groups'];
        return 'fd1.' . rtrim(strtr(base64_encode(json_encode($state)), '+/', '-_'), '=');
    }

    public static function normalize(array $input)
    {
        $state = ['groups' => [], 'min' => null, 'max' => null, 'stock' => false];
        $groups = isset($input['groups']) && is_array($input['groups']) ? $input['groups'] : [];
        foreach (array_slice($groups, 0, 50, true) as $key => $values) {
            if (!preg_match('/^(brand|a[1-9][0-9]*|f[1-9][0-9]*)$/D', (string) $key) || !is_array($values)) {
                continue;
            }
            $ids = [];
            foreach (array_slice($values, 0, 100) as $value) {
                if (is_scalar($value) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)) {
                    $ids[] = (string) $value;
                }
            }
            if ($ids) {
                $state['groups'][$key] = array_values(array_unique($ids));
            }
        }
        foreach (['min', 'max'] as $key) {
            if (isset($input[$key]) && is_scalar($input[$key]) && is_numeric($input[$key]) && is_finite((float) $input[$key]) && (float) $input[$key] >= 0) {
                $state[$key] = round((float) $input[$key], 6);
            }
        }
        if ($state['min'] !== null && $state['max'] !== null && $state['min'] > $state['max']) {
            list($state['min'], $state['max']) = [$state['max'], $state['min']];
        }
        $state['stock'] = isset($input['stock']) && $input['stock'] === true;
        return $state;
    }

    public static function matches(array $product, array $state)
    {
        if (($state['min'] !== null && $product['price'] < $state['min']) || ($state['max'] !== null && $product['price'] > $state['max']) || ($state['stock'] && $product['quantity'] <= 0)) {
            return false;
        }
        $attributes = [];
        foreach ($state['groups'] as $key => $values) {
            if ($key[0] === 'a') {
                $attributes[$key] = $values;
            } elseif (!array_intersect($values, isset($product['groups'][$key]) ? $product['groups'][$key] : [])) {
                return false;
            }
        }
        if (!$attributes) {
            return true;
        }
        // Selected attribute groups must match one real combination, not separate variants.
        foreach ($product['variants'] as $variant) {
            $matches = true;
            foreach ($attributes as $values) {
                if (!array_intersect($values, $variant['attributes'])) {
                    $matches = false;
                    break;
                }
            }
            if ($matches && (!$state['stock'] || $variant['quantity'] > 0)) {
                return true;
            }
        }
        return false;
    }

    public static function filter(array $products, array $state)
    {
        return array_values(array_filter($products, function ($product) use ($state) {
            return self::matches($product, $state);
        }));
    }

    public static function counts(array $products, array $state, array $groups)
    {
        foreach ($groups as $key => &$group) {
            foreach ($group['options'] as &$option) {
                $probe = $state;
                $probe['groups'][$key] = [(string) $option['id']];
                $option['count'] = count(self::filter($products, $probe));
            }
            unset($option);
        }
        unset($group);
        return array_values($groups);
    }
}
