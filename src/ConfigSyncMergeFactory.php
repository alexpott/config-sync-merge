<?php

namespace alexpott\ConfigSyncMerge;

use alexpott\ConfigSyncMerge\ConfigFilter\ConfigSyncMergeFilter;
use Drupal\config_filter\Config\FilteredStorage;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;

/**
 *
 *
 * @author Alex Pott
 */
class ConfigSyncMergeFactory {

    /**
     * @var \Drupal\Core\Site\Settings
     */
    protected $settings;

    /**
     * Core sync storage.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $coreSyncStorage;

    /**
     * ConfigStorage constructor.
     *
     * @param Settings $settings
     * @param StorageInterface $coreSyncStorage
     */
    public function __construct(Settings $settings, StorageInterface $coreSyncStorage) {
        $this->settings = $settings;
        $this->coreSyncStorage = $coreSyncStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSync()
    {
        $manager = new ConfigSyncMergeConfigFilterManager($this->settings);
        // Create the ConfigStorage with the filtered storage already.
        // This bypasses the setup needed for the tests and tests
        // ConfigSyncMergeConfigFilterManager instead.
        return new ConfigStorage(new FilteredStorage($this->coreSyncStorage, $manager->getFiltersForStorages(['config.storage.sync'])));
    }

}
