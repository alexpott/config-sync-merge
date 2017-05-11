<?php

namespace Drupal\config_sync_merge\Plugin\ConfigFilter;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfigSyncMergeFilterDeriver.
 */
class ConfigSyncMergeFilterDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * ConfigSyncMergeFilterDeriver constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The sites settings to get the extra directories from.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $directories = $this->settings->get('config_sync_merge_directories', []);
    $weight = -10 - count($directories);
    foreach ($directories as $key => $directory) {
      $this->derivatives[$key] = $base_plugin_definition;
      $this->derivatives[$key]['directory'] = $directory;
      $this->derivatives[$key]['weight'] = $weight;
      $weight++;
    }

    return $this->derivatives;
  }

}
