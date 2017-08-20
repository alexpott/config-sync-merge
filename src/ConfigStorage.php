<?php

namespace alexpott\ConfigSyncMerge;

use alexpott\ConfigSyncMerge\ConfigFilter\ConfigSyncMergeFilter;
use alexpott\ConfigSyncMerge\Exception\InvalidStorage;
use alexpott\ConfigSyncMerge\Exception\UnsupportedMethod;
use Drupal\config_filter\Config\FilteredStorage;

use Drupal\Core\Config\StorageInterface;

/**
 * Class ConfigStorage manages configuration stored in multiple config storages.
 *
 * Only the first storage passed in is written to or deleted from. The other storages are treated as read-only.
 *
 * @author Alex Pott
 */
class ConfigStorage implements StorageInterface {

    /**
     * The wrapped storage.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $storage;

    /**
     * ConfigStorage constructor.
     *
     * @param StorageInterface[]|FilteredStorage $storages
     * @param string $collection
     * @throws \alexpott\ConfigSyncMerge\Exception\InvalidStorage
     */
    public function __construct($storages, $collection = StorageInterface::DEFAULT_COLLECTION) {
        if ($storages instanceof FilteredStorage) {
            $this->storage = $storages;
        }
        else {
            // This is just to conform with what the tests set up.
            // This class is only left here to satisfy the tests.
            $filters = [];
            if (empty($storages)) {
                throw new InvalidStorage('ConfigStorage requires at least one storage to be passed to the constructor');
            }
            $main = array_shift($storages);
            if (!($main instanceof StorageInterface)) {
                throw new InvalidStorage('All storages must implement \Drupal\Core\Config\StorageInterface');
            }
            if ($main->getCollectionName() !== $collection) {
                $main = $main->createCollection($collection);
            }
            foreach ($storages as $key => $storage) {
                if (!($storage instanceof StorageInterface)) {
                    throw new InvalidStorage('All storages must implement \Drupal\Core\Config\StorageInterface');
                }
                if ($storage->getCollectionName() !== $collection) {
                    $storages[$key] = $storage->createCollection($collection);
                }

                $filters[] = new ConfigSyncMergeFilter($key, $storages[$key]);
            }

            // Set the first storage as the main one and the others as filters.
            $this->storage = new FilteredStorage($main, $filters);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return $this->storage->exists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        return $this->storage->read($name);
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $names)
    {
        return $this->storage->readMultiple($names);
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        return $this->storage->write($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        return $this->storage->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name)
    {
        throw new UnsupportedMethod('Renaming is not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->storage->encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->storage->decode($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function listAll($prefix = '')
    {
        return $this->storage->listAll($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll($prefix = '')
    {
        return $this->storage->deleteAll($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection)
    {
        // This will escape the decoration.
        return $this->storage->createCollection($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCollectionNames()
    {
        return $this->storage->getAllCollectionNames();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionName()
    {
        return $this->storage->getCollectionName();
    }

}