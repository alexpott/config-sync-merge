<?php

namespace alexpott\ConfigSyncMerge\Tests;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\FileStorage;
use org\bovigo\vfs\vfsStream;
use \PHPUnit\Framework\TestCase;

abstract class ConfigSyncMergeTestBase extends TestCase
{

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fixtures;

    /**
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $core;

    /**
     * {@inheritdoc}
     */
    public function setUp() {
        // Ensure that the static file cache factory is unique per test method.
        FileCacheFactory::setPrefix(__CLASS__ . ':' . $this->getName());
        $root = vfsStream::setup('root');
        $this->fixtures = vfsStream::copyFromFileSystem(__DIR__ . '/fixtures', $root);
        $this->core = new FileStorage(vfsStream::url('root') . '/core');
    }

    /**
     * @param string[] $dirs List of directory names relative to the fixtures directory
     * @return string[] List of VFS directory paths.
     */
    protected function convertDirsToVfs($dirs)
    {
        $root = vfsStream::url('root');
        foreach ($dirs as &$dir) {
            $dir = $root . '/' . $dir;
        }
        return $dirs;
    }


    public function dataProviderTestListAll()
    {
        return [
            [[], '', ['core.extension', 'foo.bar']],
            [[], 'foo', ['foo.bar']],
            [[], 'bar', []],
            [['merge1'], '', ['bar.foo', 'core.extension', 'foo.bar']],
            [['merge1'], 'bar', ['bar.foo']],
            [['merge1'], 'baa', []],
            [['merge1', 'merge2'], '', ['baa.baa', 'bar.foo', 'core.extension', 'foo.bar']],
        ];
    }
}
