<?php

namespace alexpott\ConfigSyncMerge;

use alexpott\ConfigSyncMerge\ConfigFilter\ConfigSyncMergeFilter;
use Drupal\config_filter\ConfigFilterManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Site\Settings;

/**
 *
 *
 * @author Alex Pott
 */
class ConfigSyncMergeConfigFilterManager implements ConfigFilterManagerInterface {

  /**
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * ConfigStorage constructor.
   *
   * @param Settings $settings
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getFiltersForStorages(array $storage_names, array $excluded = []) {
    $filters = [];
    if (in_array('config.storage.sync', $storage_names)) {
      $directories = $this->settings->get('config_sync_merge_directories', []);
      $directories = array_diff($directories, $excluded);
      foreach ($directories as $directory) {
        $filters[$directory] = new ConfigSyncMergeFilter($directory, new FileStorage($directory));
      }
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterInstance($directory) {
    if (in_array($directory, $this->settings->get('config_sync_merge_directories', []))) {
      return new ConfigSyncMergeFilter($directory, new FileStorage($directory));
    }

    return null;
  }

}
