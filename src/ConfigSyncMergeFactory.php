<?php

namespace alexpott\ConfigSyncMerge;

use alexpott\ConfigSyncMerge\DataAdapters\CoreExtension;
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
        $storages = [$this->coreSyncStorage];
        foreach ($this->settings->get('config_sync_merge_directories', []) as $directory) {
            $storages[$directory] = new FileStorage($directory);
        }
        $adapters = [new CoreExtension()];
        return new ConfigStorage($storages, $adapters);
    }

}
