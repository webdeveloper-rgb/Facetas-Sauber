<?php
namespace FacetasDrawer;

/** PrestaShop AdminModules enforces configure permissions; POSTs also verify its token. */
final class FilterConfiguration
{
    private $module;

    public function __construct(\FacetasDrawer $module)
    {
        $this->module = $module;
    }

    public function render()
    {
        $context = \Context::getContext();
        $escape = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };
        $label = function ($text) use ($escape) { return $escape($this->module->l($text, 'filterconfiguration')); };
        $catalog = [['id' => 'brand', 'name' => $this->module->l('Brand'), 'type' => $this->module->l('Brand')]];
        foreach (\AttributeGroup::getAttributesGroups($context->language->id) as $group) {
            $catalog[] = ['id' => 'a' . $group['id_attribute_group'], 'name' => $group['public_name'], 'type' => $this->module->l('Attribute')];
        }
        foreach (\Feature::getFeatures($context->language->id) as $feature) {
            $catalog[] = ['id' => 'f' . $feature['id_feature'], 'name' => $feature['name'], 'type' => $this->module->l('Feature')];
        }
        $settings = $this->module->getFilterSettings();
        $feedback = '';
        if (\Tools::isSubmit('submitFacetasSettings')) {
            $token = \Tools::getValue('token');
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($token) || !hash_equals(\Tools::getAdminTokenLite('AdminModules'), $token) || !$this->module->getPermission('configure')) {
                return $this->module->displayError($this->module->l('Unable to save. Reload this page and try again.'));
            }
            // The submitted key list is restricted to real, discoverable catalog groups.
            $posted = \Tools::getValue('fd_rules', []);
            $uniform = \Tools::getValue('fd_hide_uniform', '1');
            $valid = is_array($posted) && in_array($uniform, ['0', '1'], true);
            $rules = [];
            foreach ($catalog as $group) {
                if (!is_array($posted) || !isset($posted[$group['id']])) {
                    continue;
                }
                $rule = $posted[$group['id']];
                if (!is_array($rule)) {
                    $valid = false;
                    break;
                }
                $mode = $rule['mode'] ?? 'auto';
                $position = $rule['position'] ?? '';
                if (!in_array($mode, ['auto', 'show', 'hide'], true) || !is_scalar($position) || ($position !== '' && !preg_match('/^[0-9]{1,4}$/D', (string) $position))) {
                    $valid = false;
                    break;
                }
                $rules[$group['id']] = ['mode' => $mode, 'position' => $position];
            }
            if (!$valid) {
                $feedback = $this->module->displayError($this->module->l('Choose a valid visibility mode and a priority from 0 to 9999, or leave priority blank.'));
            } else {
                // Preserve rules for groups belonging to other shops in a multishop context.
                $settings = FacetOrganizer::settings(['hide_uniform' => $uniform, 'rules' => array_replace($settings['rules'], $rules)]);
                $saved = \Configuration::updateValue('FD_DRAWER_SETTINGS', json_encode($settings));
                $feedback = $saved ? $this->module->displayConfirmation($this->module->l('Filter settings saved.')) : $this->module->displayError($this->module->l('The settings could not be saved.'));
            }
        }
        $url = $context->link->getAdminLink('AdminModules', true) . '&configure=facetasdrawer';
        $html = $feedback . '<section class="panel"><h3>' . $label('Automatic filter organization') . '</h3><p>' . $label('Features, attributes and brands are discovered from the products in each category. New catalog values appear automatically. No API key or indexing job is required.') . '</p><p>' . $label('Automatic order: brands, attributes, then features. Attribute groups, sizes and features follow their PrestaShop positions. Feature values use natural numeric and alphabetical ordering.') . '</p><p>' . $label('Disable the native Faceted search module so only one provider handles category listings.') . '</p><form method="post" action="' . $escape($url) . '"><input type="hidden" name="token" value="' . $escape(\Tools::getAdminTokenLite('AdminModules')) . '"><div class="form-group"><label for="fd-hide-uniform">' . $label('Hide filters that cannot narrow the category') . '</label><select class="form-control" id="fd-hide-uniform" name="fd_hide_uniform"><option value="1"' . ($settings['hide_uniform'] ? ' selected' : '') . '>' . $label('Yes — automatic') . '</option><option value="0"' . (!$settings['hide_uniform'] ? ' selected' : '') . '>' . $label('No — show all discovered filters') . '</option></select></div><p>' . $label('Selected filters remain visible so customers can remove them. Priority overrides appear first; smaller numbers come first. Leave priority blank to use automatic order.') . '</p><div class="table-responsive"><table class="table"><thead><tr><th>' . $label('Filter') . '</th><th>' . $label('Type') . '</th><th>' . $label('Visibility') . '</th><th>' . $label('Priority') . '</th></tr></thead><tbody>';
        foreach ($catalog as $group) {
            $id = $group['id'];
            $rule = $settings['rules'][$id] ?? ['mode' => 'auto', 'position' => null];
            $html .= '<tr><td><label for="fd-mode-' . $id . '">' . $escape($group['name']) . '</label></td><td>' . $escape($group['type']) . '</td><td><select class="form-control" id="fd-mode-' . $id . '" name="fd_rules[' . $id . '][mode]">';
            foreach (['auto' => 'Automatic', 'show' => 'Always show', 'hide' => 'Hide'] as $value => $text) {
                $html .= '<option value="' . $value . '"' . ($rule['mode'] === $value ? ' selected' : '') . '>' . $label($text) . '</option>';
            }
            $html .= '</select></td><td><input class="form-control" type="number" min="0" max="9999" id="fd-priority-' . $id . '" name="fd_rules[' . $id . '][position]" value="' . $escape($rule['position'] ?? '') . '" aria-label="' . $label('Priority') . ': ' . $escape($group['name']) . '"></td></tr>';
        }
        return $html . '</tbody></table></div><button class="btn btn-primary" type="submit" name="submitFacetasSettings" value="1">' . $label('Save settings') . '</button></form></section>';
    }
}
