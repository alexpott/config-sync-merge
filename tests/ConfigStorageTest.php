<?php

namespace alexpott\ConfigSyncMerge\Tests;

use alexpott\ConfigSyncMerge\ConfigStorage;
use alexpott\ConfigSyncMerge\Exception\InvalidStorage;
use alexpott\ConfigSyncMerge\Exception\UnsupportedMethod;
use Drupal\Core\Config\FileStorage;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \alexpott\ConfigSyncMerge\ConfigStorage
 */
class ConfigStorageTest extends ConfigSyncMergeTestBase
{

    /**
     * @param string[] $dirs List of directory names relative to the fixtures directory
     * @return \Drupal\Core\Config\StorageInterface[]
     */
    protected function getStorages($dirs) {
        $storages = [$this->core];
        foreach ($this->convertDirsToVfs($dirs) as $directory) {
            $storages[] = new FileStorage($directory);
        }
        return $storages;
    }

    public function testConstructorExceptionInvalidStorage()
    {
        $this->expectException(InvalidStorage::class);
        $this->expectExceptionMessage('All storages must implement \Drupal\Core\Config\StorageInterface');
        new ConfigStorage(['a']);
    }

    public function testConstructorExceptionNoStorage()
    {
        $this->expectException(InvalidStorage::class);
        $this->expectExceptionMessage('ConfigStorage requires at least one storage to be passed to the constructor');
        new ConfigStorage([]);
    }

    /**
     * @dataProvider dataProviderTestExists
     */
    public function testExists(array $dirs, $name, $expected)
    {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->exists($name));
    }

    public function dataProviderTestExists()
    {
        return [
            [[], 'foo.bar', TRUE],
            [[], 'bar.foo', FALSE],
            [['merge1'], 'foo.bar', TRUE],
            [['merge1'], 'bar.foo', TRUE],
            [['merge1'], 'baa.baa', FALSE],
            [['merge1', 'merge2'], 'baa.baa', TRUE],
        ];
    }

    /**
     * @dataProvider dataProviderTestRead
     */
    public function testRead(array $dirs, $name, $expected)
    {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->read($name));
    }

    public function dataProviderTestRead()
    {
        return [
            [[], 'foo.bar', ['value' => 'core']],
            [[], 'bar.foo', FALSE],
            [['merge1'], 'foo.bar', ['value' => 'core']],
            [['merge1'], 'bar.foo', ['value' => 'merge1']],
            [['merge1'], 'baa.baa', FALSE],
            [['merge1', 'merge2'], 'baa.baa', ['value' => 'merge2']],
            [['merge1', 'merge2'], 'foo.bar', ['value' => 'core']],
        ];
    }

    /**
     * @dataProvider dataProviderTestReadMultiple
     */
    public function testReadMultiple(array $dirs, array $names, $expected)
    {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->readMultiple($names));
    }

    public function dataProviderTestReadMultiple()
    {
        return [
            [[], ['foo.bar'], ['foo.bar' => ['value' => 'core']]],
            [[], ['bar.foo'], []],
            [['merge1'], ['foo.bar', 'bar.foo'], ['bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1'], ['foo.bar', 'bar.foo', 'baa.baa'], ['bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1', 'merge2'], ['foo.bar', 'bar.foo', 'baa.baa'], ['baa.baa' => ['value' => 'merge2'], 'bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1', 'merge2'], ['a.a', 'b.b'], []],

        ];
    }

    public function testWrite() {
        $config_sync_merge = new ConfigStorage($this->getStorages(['merge1', 'merge2']));
        $config_sync_merge->write('test.write', ['test' => 'data']);
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/test.write.yml'));
        $vfs_dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        foreach ($vfs_dirs as $dir) {
            $this->assertFalse(file_exists($dir . '/test.write.yml'));
        }

        // Ensure that we only write to the first storage when the values are different.
        $config_sync_merge->write('baa.baa', ['value' => 'merge2']);
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/baa.baa.yml'));
        $this->assertSame(['value' => 'merge2'], $config_sync_merge->read('baa.baa'));

        $config_sync_merge->write('baa.baa', ['value' => 'override_merge2_write']);
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/baa.baa.yml'));
        $this->assertSame(['value' => 'override_merge2_write'], $config_sync_merge->read('baa.baa'));
        $this->assertSame(['value' => 'merge2'], $config_sync_merge->decode(file_get_contents($vfs_dirs[1] . '/baa.baa.yml')));
    }

    public function testDelete() {
        $config_sync_merge = new ConfigStorage($this->getStorages(['merge1', 'merge2']));
        $this->assertFalse($config_sync_merge->delete('a.a'));
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/foo.bar.yml'));
        $this->assertTrue($config_sync_merge->delete('foo.bar'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/foo.bar.yml'));
        $vfs_dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        // The only storages are not writable so the file will not be deleted.
        foreach ($vfs_dirs as $dir) {
            $this->assertTrue(file_exists($dir . '/foo.bar.yml'));
        }

        // You can't delete configuration from later storages.
        $this->assertFalse($config_sync_merge->delete('baa.baa'));
        $this->assertTrue($config_sync_merge->exists('baa.baa'));
    }

    public function testRename() {
        $config_sync_merge = new ConfigStorage($this->getStorages(['merge1', 'merge2']));
        $this->expectException(UnsupportedMethod::class);
        $this->expectExceptionMessage('Renaming is not supported');
        $config_sync_merge->rename('foo.bar', 'a.a');
    }

    public function testEncode() {
        $config_sync_merge = new ConfigStorage($this->getStorages([]));
        $this->assertSame("foo: bar\n", $config_sync_merge->encode(['foo' => 'bar']));
    }

    public function testDecode() {
        $config_sync_merge = new ConfigStorage($this->getStorages([]));
        $this->assertSame(['foo' => 'bar'], $config_sync_merge->decode("foo: bar\n"));
    }

    /**
     * @dataProvider dataProviderTestListAll
     */
    public function testListAll(array $dirs, $prefix, $expected)
    {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->listAll($prefix));
    }

    /**
     * @dataProvider dataProviderTestDeleteAll
     */
    public function testDeleteAll(array $dirs, $prefix, $expected, array $list)
    {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->deleteAll($prefix));

        // Ensure that only configuration in the first storage is deleted.
        $this->assertSame([], $this->core->listAll($prefix));
        $this->assertSame($list, $config_sync_merge->listAll());
    }

    public function dataProviderTestDeleteAll()
    {
        return [
            [[], '', TRUE, []],
            [[], 'foo', TRUE, ['core.extension']],
            [[], 'bar', TRUE, ['core.extension', 'foo.bar']],
            [['merge1'], '', TRUE, ['bar.foo', 'core.extension', 'foo.bar']],
            [['merge1', 'merge2'], '', TRUE, ['baa.baa', 'bar.foo', 'core.extension', 'foo.bar']],
        ];
    }

    public function testCreateCollection()
    {
        $storages = $this->getStorages(['merge1', 'merge2']);
        $config_sync_merge = new ConfigStorage($storages);
        $config_sync_merge = $config_sync_merge->createCollection('fr');
        $this->assertSame(['value' => 'fr-core'], $config_sync_merge->read('foo.bar'));
        $this->assertFalse($config_sync_merge->exists('baa.baa'));
        $this->assertSame(['value' => 'fr-merge1'], $config_sync_merge->read('bar.foo'));

        $config_sync_merge = new ConfigStorage($storages, [], 'fr');
        $this->assertSame(['value' => 'fr-core'], $config_sync_merge->read('foo.bar'));
        $this->assertFalse($config_sync_merge->exists('baa.baa'));
        $this->assertSame(['value' => 'fr-merge1'], $config_sync_merge->read('bar.foo'));

        $config_sync_merge = new ConfigStorage($storages, [], 'lol');
        $this->assertSame([], $config_sync_merge->listAll());
        $config_sync_merge = new ConfigStorage($storages);
        $this->assertSame([], $config_sync_merge->createCollection('lol')->listAll());
    }

    public function testGetCollectionName()
    {
        $storages = $this->getStorages(['merge1', 'merge2']);
        $config_sync_merge = new ConfigStorage($storages);
        $this->assertSame('', $config_sync_merge->getCollectionName());
        $config_sync_merge = $config_sync_merge->createCollection('fr');
        $this->assertSame('fr', $config_sync_merge->getCollectionName());

        $config_sync_merge = new ConfigStorage($storages, [], 'fr');
        $this->assertSame('fr', $config_sync_merge->getCollectionName());

        $config_sync_merge = new ConfigStorage($storages, [], 'lol');
        $this->assertSame('lol', $config_sync_merge->getCollectionName());
    }
    /**
     * @dataProvider dataProviderTestGetAllCollectionNames
     */
    public function testGetAllCollectionNames($dirs, $expected) {
        $config_sync_merge = new ConfigStorage($this->getStorages($dirs));
        $this->assertSame($expected, $config_sync_merge->getAllCollectionNames());
    }

    public function dataProviderTestGetAllCollectionNames() {
        return [
            [[], ['fr']],
            [['merge1'], ['fr']],
            [['merge1', 'merge2'], ['de', 'fr']],
        ];
    }
}

namespace Drupal\Core\Config;

if (!function_exists('drupal_chmod')) {
    function drupal_chmod($uri, $mode = NULL) {
        return TRUE;
    }
}

if (!function_exists('drupal_unlink')) {
    function drupal_unlink($uri) {
        return unlink($uri);
    }
}