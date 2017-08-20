<?php

namespace alexpott\ConfigSyncMerge\ConfigFilter;

use Drupal\config_filter\Plugin\ConfigFilterBase;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ConfigSyncMergeFilter.
 */
class ConfigSyncMergeFilter extends ConfigFilterBase {

  /**
   * The File storage to read the inherited data from.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * ConfigSyncMergeFilter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage to read from.
   */
  public function __construct($plugin_id, StorageInterface $storage) {
    parent::__construct([], $plugin_id, []);
    $this->storage = $storage;
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
    return new static($this->pluginId, $this->storage->createCollection($collection));
  }

  /**
   * {@inheritdoc}
   */
  public function filterGetAllCollectionNames(array $collections) {
    return array_unique(array_merge($collections, $this->storage->getAllCollectionNames()));
  }

}
