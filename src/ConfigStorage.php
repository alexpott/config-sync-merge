<?php

namespace alexpott\ConfigSyncMerge;

use alexpott\ConfigSyncMerge\Exception\InvalidStorage;
use alexpott\ConfigSyncMerge\Exception\UnsupportedMethod;
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
     * @var array
     */
    private $dataAdapters;

    /**
     * ConfigStorage constructor.
     *
     * @param StorageInterface[] $storages
     * @param array $dataAdapters
     * @param string $collection
     */
    public function __construct(array $storages, array $dataAdapters = [], $collection = StorageInterface::DEFAULT_COLLECTION) {
        if (empty($storages)) {
            throw new InvalidStorage('ConfigStorage requires at least one storage to be passed to the constructor');
        }
        /** @var \Drupal\Core\Config\StorageInterface $storage */
        foreach ($storages as $key => $storage) {
            if (!($storage instanceof StorageInterface)) {
                throw new InvalidStorage('All storages must implement \Drupal\Core\Config\StorageInterface');
            }
            if ($storage->getCollectionName() !== $collection) {
                $storages[$key] = $storage->createCollection($collection);
            }
        }
        $this->storages = $storages;
        $this->dataAdapters = $dataAdapters;
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
        if ($dataAdapter = $this->getDataAdapter($name)) {
            return $dataAdapter->read($name, $this->storages);
        }
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
        if ($dataAdapter = $this->getDataAdapter($names)) {
            $data = $dataAdapter->readMultiple($names, $this->storages);
        }
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
        if ($dataAdapter = $this->getDataAdapter($name)) {
            $data = $dataAdapter->write($name, $data, $this->storages);
            if (empty($data)) {
                return TRUE;
            }
        }
        // Only need to write if data is different.
        elseif ($this->read($name) === $data) {
            return TRUE;
        }
        return $this->storages[0]->write($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        // We only delete from the first storage.
        return $this->storages[0]->delete($name);
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
        // We only delete from the first storage.
        return $this->storages[0]->deleteAll($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection)
    {
        return new static(
            $this->storages,
            $this->dataAdapters,
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
        return $this->storages[0]->getCollectionName();
    }

    /**
     * Gets a data adapter.
     *
     * @param $name
     *   The configuration name being operated on.
     *
     * @return DataAdapterInterface|false
     */
    protected function getDataAdapter($name) {
        foreach ($this->dataAdapters as $dataAdapter) {
            if ($dataAdapter->applies($name)) {
                return $dataAdapter;
            }
        }
        return FALSE;
    }
}