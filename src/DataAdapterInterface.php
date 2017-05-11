<?php

namespace alexpott\ConfigSyncMerge;

/**
 *
 *
 * @author Alex Pott
 */
interface DataAdapterInterface
{
    public function applies($name);

    /**
     * @param string $name
     * @param \Drupal\Core\Config\StorageInterface[] $storages
     * @return array
     */
    public function read($name, array $storages);

    /**
     * @param array $names
     * @param \Drupal\Core\Config\StorageInterface[] $storages
     * @return array
     */
    public function readMultiple(array $names, array $storages);

    /**
     * @param string $name
     * @param array $data
     * @param \Drupal\Core\Config\StorageInterface[] $storages
     * @return array
     *   Data to write to the first storage if it is empty nothing with be
     *   written.
     */
    public function write($name, array $data, array $storages);

}
