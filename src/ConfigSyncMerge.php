<?php

namespace alexpott\ConfigSyncMerge;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;

/**
 * Class ConfigSyncMerge manages configuration stored in multiple config storages.
 *
 * @author Alex Pott
 */
class ConfigSyncMerge implements StorageInterface {

    /**
     * @var \Drupal\Core\Site\Settings
     */
    protected $settings;

    /**
     * Sync storages.
     *
     * The first storage will always be core's sync storage.
     *
     * @var \Drupal\Core\Config\StorageInterface[]
     */
    protected $storages = [];

    /**
     * The storage collection.
     *
     * @var string
     */
    protected $collection;

    /**
     * ConfigSyncMerge constructor.
     *
     * @param Settings $settings
     * @param StorageInterface $coreSyncStorage
     * @param string $collection
     */
    public function __construct(Settings $settings, StorageInterface $coreSyncStorage, $collection = StorageInterface::DEFAULT_COLLECTION) {
        $this->settings = $settings;
        if ($coreSyncStorage->getCollectionName() !== $collection) {
            $coreSyncStorage = $coreSyncStorage->createCollection($collection);
        }
        $this->storages[] = $coreSyncStorage;
        $this->collection = $collection;
        foreach ($this->settings->get('config_sync_merge_directories', []) as $directory) {
            $this->storages[$directory] = new FileStorage($directory, $collection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        foreach($this->storages as $storage) {
            if ($storage->exists($name)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        foreach($this->storages as $storage) {
            if ($storage->exists($name)) {
                return $storage->read($name);
            }
        }
        return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $names)
    {
        $data = [];
        foreach($this->storages as $storage) {
            $names = array_diff($names, array_keys($data));
            $data = array_merge($data, $storage->readMultiple($names));
        }
        ksort($data);
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        foreach($this->storages as $storage) {
            if ($storage->exists($name)) {
                return $storage->write($name, $data);
            }
        }
        // Fallback to writing to the core sync directory if we are not replacing something.
        return $this->storages[0]->write($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $deleted = FALSE;
        foreach($this->storages as $storage) {
            if ($storage->exists($name) && $storage->delete($name)) {
                $deleted = TRUE;
            }
        }
        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name)
    {
        $renamed = FALSE;
        foreach($this->storages as $storage) {
            if ($storage->exists($name) && $storage->rename($name, $new_name)) {
                $renamed = TRUE;
            }
        }
        return $renamed;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->storages[0]->encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->storages[0]->decode($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function listAll($prefix = '')
    {
        $list = [];
        foreach($this->storages as $storage) {
            $list = array_merge($list, $storage->listAll($prefix));
        }
        $list = array_unique($list);
        sort($list);
        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll($prefix = '')
    {
        $deleted = FALSE;
        foreach($this->storages as $storage) {
            if ($storage->deleteAll($prefix)) {
                $deleted = TRUE;
            }
        }
        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection)
    {
        return new static(
            $this->settings,
            $this->storages[0]->createCollection($collection),
            $collection
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCollectionNames()
    {
        $collections = [];
        foreach($this->storages as $storage) {
            $collections = array_merge($collections, $storage->getAllCollectionNames());
        }
        $collections = array_unique($collections);
        sort($collections);
        return $collections;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionName()
    {
        return $this->collection;
    }


}