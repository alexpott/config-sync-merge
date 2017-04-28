<?php

namespace alexpott\ConfigSyncMerge\Exception;

/**
 * Thrown when calling an unsupported method on a ConfigStorage object.
 *
 * @see \alexpott\ConfigSyncMerge\ConfigStorage::rename()
 */
class UnsupportedMethod extends \RuntimeException
{
}
