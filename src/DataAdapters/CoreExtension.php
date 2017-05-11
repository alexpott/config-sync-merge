<?php

namespace alexpott\ConfigSyncMerge\DataAdapters;

use alexpott\ConfigSyncMerge\DataAdapterInterface;

class CoreExtension implements DataAdapterInterface
{

    /**
     * {@inheritdoc}
     */
    public function applies($name) {
        $name = (array) $name;
        return in_array('core.extension', $name, TRUE);
    }

    /**
     * {@inheritdoc}
     */
    public function read($name, array $storages)
    {
        $merged_data = [];
        $profile = NULL;
        // The profile from the first should be the used install profile.
        // All other profiles should be removed from the module list
        // Everything else should be merged.
        /** @var \Drupal\Core\Config\StorageInterface $storage */
        foreach($storages as $key => $storage) {
            $data = $storage->read($name);
            if (empty($merged_data)) {
                $merged_data = $data;
            }
            else {
                // Remove the profile from the installed list. There can be only
                // one.
                unset($data['module'][$data['profile']]);
                $merged_data['module'] = array_merge($data['module'], $merged_data['module']);
                $merged_data['theme'] = array_merge($data['theme'], $merged_data['theme']);
            }
        }
        // Do a module sort.
        $merged_data['module'] = $this->moduleSort($merged_data['module']);
        // Sort themes by name.
        ksort($merged_data['theme']);
        return $merged_data;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $names, array $storages)
    {
        return ['core.extension' => $this->read('core.extension', $storages)];
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data, array $storages)
    {
        // We only want to write new things to the first storage.
        $current = $this->read('core.extension', $storages);
        $new_modules = array_diff_key($data['module'], $current['module']);
        $new_themes = array_diff_key($data['theme'], $current['theme']);

        $removed_modules = array_diff_key($current['module'], $data['module']);
        $removed_themes = array_diff_key($current['theme'], $data['theme']);

        // If there are no changes all is good.
        if (empty($new_modules) && empty($new_themes) && empty($removed_modules) && empty($removed_themes)) {
            return [];
        }

        $top_level_data = $storages[0]->read('core.extension');
        if ($top_level_data) {
            if (array_diff_key($removed_modules, $top_level_data['module']) !== []) {
                // We're removing from core.extension that can not be changed.
                throw new \RuntimeException('Unexpected module removal: ' . implode(', ', array_keys($removed_modules)));
            }
            if (array_diff_key($removed_themes, $top_level_data['theme']) !== []) {
                // We're removing from core.extension that can not be changed.
                throw new \RuntimeException('Unexpected theme removal: ' . implode(', ', array_keys($removed_themes)));
            }
            // Add new modules in.
            $top_level_data['module'] = array_merge($top_level_data['module'], $new_modules);
            $top_level_data['theme'] = array_merge($top_level_data['theme'], $new_themes);
            // Remove any modules.
            $top_level_data['module'] = array_diff_key($top_level_data['module'], $removed_modules);
            $top_level_data['theme'] = array_diff_key($top_level_data['theme'], $removed_themes);
        }
        else {
            $top_level_data = $current;
            $top_level_data['module'] = $new_modules;
            $top_level_data['theme'] = $new_themes;
        }
        // Do a module sort.
        $top_level_data['module'] = $this->moduleSort($top_level_data['module']);
        // Sort themes by name.
        ksort($top_level_data['theme']);
        return $top_level_data;
    }

    /**
     * Sorts module data.
     *
     * This is a copy of module_config_sort().
     * @param array $data
     * @return array
     */
    private function moduleSort(array $data) {
        // PHP array sorting functions such as uasort() do not work with both keys and
        // values at the same time, so we achieve weight and name sorting by computing
        // strings with both information concatenated (weight first, name second) and
        // use that as a regular string sort reference list via array_multisort(),
        // compound of "[sign-as-integer][padded-integer-weight][name]"; e.g., given
        // two modules and weights (spaces added for clarity):
        // - Block with weight -5: 0 0000000000000000005 block
        // - Node  with weight  0: 1 0000000000000000000 node
        $sort = [];
        foreach ($data as $name => $weight) {
            // Prefix negative weights with 0, positive weights with 1.
            // +/- signs cannot be used, since + (ASCII 43) is before - (ASCII 45).
            $prefix = (int) ($weight >= 0);
            // The maximum weight is PHP_INT_MAX, so pad all weights to 19 digits.
            $sort[] = $prefix . sprintf('%019d', abs($weight)) . $name;
        }
        array_multisort($sort, SORT_STRING, $data);
        return $data;
    }
}
