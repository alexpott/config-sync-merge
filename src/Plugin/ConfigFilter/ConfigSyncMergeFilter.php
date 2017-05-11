<?php

namespace Drupal\config_sync_merge\Plugin\ConfigFilter;

use Drupal\config_filter\Plugin\ConfigFilterBase;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ConfigSyncMergeFilter.
 *
 * @ConfigFilter(
 *   id = "config_sync_merge",
 *   label = @Translation("Config Sync Merge"),
 *   status = TRUE,
 *   weight = -10,
 *   storages = {"config.storage.sync"},
 *   deriver = "\Drupal\config_sync_merge\Plugin\ConfigFilter\ConfigSyncMergeFilterDeriver"
 * )
 */
class ConfigSyncMergeFilter extends ConfigFilterBase implements ContainerFactoryPluginInterface {

  /**
   * The File storage to read the inherited data from.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * ConfigSyncMergeFilter constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage to read from.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StorageInterface $storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new FileStorage($configuration['directory'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function filterRead($name, $data) {
    // Read from our storage if it doesn't exist in the sync storage.
    if (!$this->getSourceStorage()->exists($name) && $this->storage->exists($name)) {
      return $this->storage->read($name);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterWrite($name, array $data) {
    // Write nothing if the data exists in our storage and is the same.
    if ($this->storage->exists($name) && $this->storage->read($name) == $data) {
      return NULL;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterWriteEmptyIsDelete($name) {
    if ($this->storage->exists($name)) {
      // Delete the empty ones that exist in our storage.
      return TRUE;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function filterExists($name, $exists) {
    if ($this->storage->exists($name)) {
      return TRUE;
    }
    return $exists;
  }

  /**
   * {@inheritdoc}
   */
  public function filterDelete($name, $delete) {
    // Do nothing.
    return $delete;
  }

  /**
   * {@inheritdoc}
   */
  public function filterReadMultiple(array $names, array $data) {
    // Return the data from our storage but let it be overwritten by the data.
    return array_merge($this->storage->readMultiple($names), $data);
  }

  /**
   * {@inheritdoc}
   */
  public function filterRename($name, $new_name, $rename) {
    if ($this->storage->exists($name)) {
      // Do not allow renaming of config that exists in our storage.
      return FALSE;
    }
    return $rename;
  }

  /**
   * {@inheritdoc}
   */
  public function filterListAll($prefix, array $data) {
    // The data here are just the config names, merge them.
    return array_unique(array_merge($data, $this->storage->listAll($prefix)));
  }

  /**
   * {@inheritdoc}
   */
  public function filterDeleteAll($prefix, $delete) {
    // Do nothing.
    return $delete;
  }

  /**
   * {@inheritdoc}
   */
  public function filterCreateCollection($collection) {
    return new static($this->configuration, $this->pluginId, $this->pluginDefinition, $this->storage->createCollection($collection));
  }

  /**
   * {@inheritdoc}
   */
  public function filterGetAllCollectionNames(array $collections) {
    return array_unique(array_merge($collections, $this->storage->getAllCollectionNames()));
  }

}
