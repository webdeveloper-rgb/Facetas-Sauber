<?php
namespace FacetasDrawer;

/** Presentation only: never changes product matching or identifiers in saved URLs. */
final class FacetOrganizer
{
    public static function settings($input)
    {
        $input = is_array($input) ? $input : [];
        $settings = ['hide_uniform' => !isset($input['hide_uniform']) || !in_array($input['hide_uniform'], [false, 0, '0'], true), 'rules' => []];
        $rules = isset($input['rules']) && is_array($input['rules']) ? $input['rules'] : [];
        foreach (array_slice($rules, 0, 1000, true) as $key => $rule) {
            if (!preg_match('/^(brand|[af][1-9][0-9]*)$/D', (string) $key) || !is_array($rule)) {
                continue;
            }
            $mode = isset($rule['mode']) && in_array($rule['mode'], ['auto', 'show', 'hide'], true) ? $rule['mode'] : 'auto';
            $position = isset($rule['position']) && is_scalar($rule['position']) && preg_match('/^[0-9]{1,4}$/D', (string) $rule['position']) ? (int) $rule['position'] : null;
            if ($mode !== 'auto' || $position !== null) {
                $settings['rules'][$key] = ['mode' => $mode, 'position' => $position];
            }
        }
        return $settings;
    }

    public static function organize(array $products, array $groups, array $state, array $settings, $locale = 'en-US')
    {
        $settings = self::settings($settings);
        $collator = class_exists('Collator') ? new \Collator(str_replace('-', '_', $locale)) : null;
        if ($collator) {
            $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        }
        $visible = [];
        foreach ($groups as $group) {
            $key = $group['id'];
            $selected = !empty($state['groups'][$key]);
            $rule = isset($settings['rules'][$key]) ? $settings['rules'][$key] : ['mode' => 'auto', 'position' => null];
            // Count coverage on the complete category, so applying filters does not
            // reshuffle or remove useful groups. Sparse single-value groups can be useful.
            $coverage = [];
            $optionIds = array_fill_keys(array_column($group['options'], 'id'), true);
            foreach ($products as $product) {
                $values = isset($product['groups'][$key]) ? $product['groups'][$key] : [];
                if ($key[0] === 'a') {
                    $values = [];
                    foreach ($product['variants'] as $variant) {
                        foreach ($variant['attributes'] as $id) {
                            if (isset($optionIds[$id])) {
                                $values[] = $id;
                            }
                        }
                    }
                }
                foreach (array_unique($values) as $id) {
                    $coverage[$id] = isset($coverage[$id]) ? $coverage[$id] + 1 : 1;
                }
            }
            $useful = false;
            foreach ($group['options'] as $option) {
                $hits = isset($coverage[$option['id']]) ? $coverage[$option['id']] : 0;
                if ($hits > 0 && $hits < count($products)) {
                    $useful = true;
                    break;
                }
            }
            // Multiple attribute choices can narrow combinations after another
            // attribute or stock filter, even if all products offer every choice.
            if ($key[0] === 'a' && count($group['options']) > 1) {
                $useful = true;
            } elseif ($key[0] === 'a' && count($group['options']) === 1 && $coverage) {
                $onlyId = (string) $group['options'][0]['id'];
                foreach ($products as $product) {
                    foreach ($product['variants'] as $variant) {
                        if (!in_array($onlyId, $variant['attributes'], true)) {
                            $useful = true;
                        }
                    }
                }
            }
            $group['displayed'] = $selected || ($rule['mode'] !== 'hide' && ($rule['mode'] === 'show' || !$settings['hide_uniform'] || $useful));
            usort($group['options'], function ($a, $b) use ($key, $collator) {
                if ($key[0] === 'a') {
                    $compare = ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
                    if ($compare) {
                        return $compare;
                    }
                }
                // Compare numeric values only within an identical unit. Do not infer
                // conversions or collapse distinct feature-value IDs with equal labels.
                $pattern = '/^\s*([+-]?[0-9]+(?:[.,][0-9]+)?)\s*([^0-9]*)$/u';
                if ($key[0] !== 'a' && preg_match($pattern, $a['label'], $na) && preg_match($pattern, $b['label'], $nb) && trim($na[2]) === trim($nb[2])) {
                    $compare = (float) str_replace(',', '.', $na[1]) <=> (float) str_replace(',', '.', $nb[1]);
                } else {
                    $compare = $collator ? $collator->compare($a['label'], $b['label']) : strnatcasecmp($a['label'], $b['label']);
                }
                return $compare ?: ((int) $a['id'] <=> (int) $b['id']);
            });
            $group['priority'] = $rule['position'];
            $visible[] = $group;
        }
        usort($visible, function ($a, $b) {
            // Explicit overrides come first; automatic groups retain stable native order.
            $manual = ($a['priority'] === null ? 1 : 0) <=> ($b['priority'] === null ? 1 : 0);
            if ($manual) {
                return $manual;
            }
            if ($a['priority'] !== null && $a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            $type = function ($group) { return $group['id'] === 'brand' ? 0 : ($group['id'][0] === 'a' ? 1 : 2); };
            return ($type($a) <=> $type($b)) ?: (($a['position'] ?? 0) <=> ($b['position'] ?? 0)) ?: strnatcmp($a['id'], $b['id']);
        });
        return array_values($visible);
    }
}
