<?php

namespace alexpott\ConfigSyncMerge\Exception;

/**
 * Thrown when creating a ConfigStorage with an invalid storage.
 *
 * @see \alexpott\ConfigSyncMerge\ConfigStorage::__construct()
 */
class InvalidStorage extends \RuntimeException
{
}
