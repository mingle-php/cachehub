<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/autoload.php';

use PHPUnit\Framework\TestCase;
use function Swoole\Coroutine\run;
use Swoole\Coroutine\WaitGroup;

class CacheHubTest extends TestCase
{

    /**
     * key为空
     */
    public function testNullKey()
    {
        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        $cache->key = '';
        try {
            $cache->get();
        } catch (\Mingle\CacheHub\Exception\Exception $e) {
            $this->assertEquals('key is empty', $e->getMessage());
        }
    }

    // public function testRepeatedKey()
    // {
    //     $registerCaches = [
    //         'test' => new TestCache(),
    //         'test_1' => new TestRepeatedCache(),
    //     ];
    //     try {
    //         $cacheHub = Common::getCacheHub($registerCaches);
    //     } catch (\Throwable $e) {
    //         $this->assertEquals('cache[test_1] key[test] is repeated', $e->getMessage());
    //     }
    // }

    public function testGet()
    {

        $cache = [
            'buildLock' => false,
        ];
        $this->_testGet($cache);


        $cache = [
            'buildLock' => true,
        ];
        $this->_testGet($cache);

    }

    public function _testGet($cacheTmp)
    {
        $redis = Common::getRedis();
        $redis->flushDB();

        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        foreach ($cacheTmp as $field => $v) {
            $cache->$field = $v;
        }
        $cache->key = 'test_string';
        $cache->expire = 60;
        $cache->valueFunc = function ($params) {
            if ($params == 1) {
                return 'test_1';
            }
            return 'test';
        };
        $data = $cache->get();
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);
        $key = 'unit_test:test_string';
        $exists = (bool)$redis->exists($key);
        $this->assertTrue($exists);
        $ttl = $redis->ttl($key);

        $cache->get();
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('cachehub_redis', $from);
        $this->assertTrue(($ttl <= 60 && $ttl > 0));
        $this->assertEquals('test', $data);

        $cache->get('', true);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);

        $data = $cache->get(1);
        $key = 'unit_test:test_string:1';
        $exists = (bool)$redis->exists($key);
        $this->assertTrue($exists);
        $ttl = $redis->ttl($key);
        $this->assertTrue(($ttl <= 60 && $ttl > 0));
        $this->assertEquals('test_1', $data);

        $cache->wrapFunc = function ($data) {
            return $data . '_wrap';
        };
        $data = $cache->get();
        $this->assertEquals('test_wrap', $data);

        $data = $cache->getFromCache();
        $this->assertEquals('test_wrap', $data);

        $data = $cache->get('', true);
        $this->assertEquals('test_wrap', $data);

        $data = $cache->get(1);
        $this->assertEquals('test_1_wrap', $data);

        $data = $cache->getFromCache(1);
        $this->assertEquals('test_1_wrap', $data);

        $data = $cache->get(1, true);
        $this->assertEquals('test_1_wrap', $data);
    }


    public function testUpdate()
    {
        $redis = Common::getRedis();
        $redis->flushDB();

        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        $cache->expire = 300;
        $cache->key = 'test_update';
        $cache->valueFunc = function ($params) {
            return 'test_update';
        };
        $key = 'unit_test:test_update';
        $redisValue = $redis->get($key);
        $this->assertTrue(empty($redisValue));
        $ret = $cache->update();
        $this->assertTrue($ret);
        $redisValue = $redis->get($key);
        $this->assertEquals('test_update', $redisValue);
    }

    public function testSet()
    {
        $redis = Common::getRedis();
        $redis->flushDB();

        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        $cache->expire = 300;
        $cache->key = 'test_set';
        $cache->valueFunc = function ($params) {
            return 'test_set';
        };
        $key = 'unit_test:test_set';
        $ret = $cache->set('', 'test_set111');
        $this->assertTrue($ret);
        $data = $cache->get();
        $this->assertEquals('test_set111', $data);
        $ttl = $redis->ttl($key);
        $this->assertTrue(($ttl <= 300 && $ttl > 0));
    }

    public function testGetArray()
    {
        $cache = [
            'buildLock' => false,
        ];
        $this->_testGetArray($cache);


        $cache = [
            'buildLock' => true,
        ];
        $this->_testGetArray($cache);

    }

    public function _testGetArray($cacheTmp)
    {
        $redis = Common::getRedis();
        $redis->flushDB();
        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        foreach ($cacheTmp as $field => $v) {
            $cache->$field = $v;
        }
        $cache->key = 'test_array';
        $cache->expire = 60;
        $cache->valueFunc = function ($params) {
            if ($params == 1) {
                return ['test_1'];
            }
            return ['test'];
        };
        $data = $cache->get();
        $key = 'unit_test:test_array';
        $exists = (bool)$redis->exists($key);
        $this->assertTrue($exists);
        $ttl = $redis->ttl($key);
        $this->assertTrue(($ttl <= 60 && $ttl > 0));
        $this->assertEquals(['test'], $data);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:["test"]', $cacheData);

        $data = $cache->get(1);

        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);

        $key = 'unit_test:test_array:1';
        $exists = (bool)$redis->exists($key);
        $this->assertTrue($exists);
        $ttl = $redis->ttl($key);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:["test_1"]', $cacheData);

        $cache->get();
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('cachehub_redis', $from);

        $this->assertTrue(($ttl <= 60 && $ttl > 0));
        $this->assertEquals(['test_1'], $data);
    }

    public function testGetNull()
    {
        $cache = [
            'buildLock' => false,
        ];
        $this->_testGetNull($cache);


        $cache = [
            'buildLock' => true,
        ];
        $this->_testGetNull($cache);
    }

    public function _testGetNull($cacheTmp)
    {
        $redis = Common::getRedis();
        $redis->flushDB();
        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        foreach ($cacheTmp as $field => $v) {
            $cache->$field = $v;
        }
        $cache->key = 'test_null';
        $cache->isCacheNull = true;
        $cache->nullValue = '';
        $cache->nullExpire = 60;
        $cache->valueFunc = function ($params) {
            return '';
        };
        $key = 'unit_test:test_null';
        $data = $cache->get();
        $this->assertNull($data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);
        $ttl = $redis->ttl($key);
        $this->assertTrue(($ttl <= 60 && $ttl > 0));

        $data = $cache->get();
        $this->assertNull($data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('cachehub_redis', $from);


        $redis->flushDB();
        $cache->nullValue = 'cachehub_null';
        $data = $cache->get();
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_null', $cacheData);
        $this->assertNull($data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);

        $redis->flushDB();
        $cache->isCacheNull = false;
        $cache->valueFunc = function ($params) {
            return null;
        };
        $data = $cache->get();
        $this->assertNull($data);
        $exists = (bool)$redis->exists($key);
        $this->assertFalse($exists);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);

        $data = $cache->get();
        $this->assertNull($data);
        $exists = (bool)$redis->exists($key);
        $this->assertFalse($exists);
        $from = $cache->getDataFrom();
        $this->assertEquals('build', $from);
    }

    public function testVersion()
    {
        $cache = [
            'buildLock' => false,
        ];
        $this->_testVersion($cache);


        $cache = [
            'buildLock' => true,
        ];
        $this->_testVersion($cache);
    }

    public function _testVersion($cacheTmp)
    {
        $redis = Common::getRedis();
        $redis->flushDB();
        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches);
        $cache = $cacheHub->getCache('test');
        foreach ($cacheTmp as $field => $v) {
            $cache->$field = $v;
        }
        $cache->addVersion = true;
        $cache->version = 1;
        $cache->valueFunc = function ($params) {
            return 'test_1';
        };
        $cache->key = 'test_version';
        $key = 'unit_test:test_version';

        $data = $cache->get();
        $this->assertEquals('test_1', $data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:{"cachehub_version":1,"data":"test_1"}', $cacheData);

        $data = $cache->get();
        $this->assertEquals('test_1', $data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('cachehub_redis', $from);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:{"cachehub_version":1,"data":"test_1"}', $cacheData);

        $cache->version = 2;
        $cache->valueFunc = function ($params) {
            return 'test_2';
        };
        $data = $cache->get();
        $this->assertEquals('test_2', $data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('build', $from);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:{"cachehub_version":2,"data":"test_2"}', $cacheData);

        $data = $cache->get();
        $this->assertEquals('test_2', $data);
        $from = $cache->getDataFrom();
        $cache->clearDataFrom();
        $this->assertEquals('cachehub_redis', $from);
        $cacheData = $redis->get($key);
        $this->assertEquals('cachehub_json:{"cachehub_version":2,"data":"test_2"}', $cacheData);
    }

    public function testLock()
    {
        $redis = new RedisPool();
        $redis->flushDB();

        $registerCaches = [
            'test' => 'TestCache',
        ];
        $cacheHub = Common::getCacheHub($registerCaches, $redis);
        $cache = $cacheHub->getCache('test');
        $cache->buildLock = true;
        $cache->buildWaitMod = 1;
        $cache->buildWaitTime = 10;
        $cache->buildWaitCount = 5;
        $cache->valueFunc = function ($params) {
            return 'test_lock';
        };

        $cache->get();
        $lockValue = $redis->get("unit_test:test_lock");
        $this->assertTrue(empty($lockValue));


        $redis->flushDB();
        \Swoole\Runtime::enableCoroutine();
        $fromArr = [];
        run(function () use (&$fromArr, $cacheHub) {
            $wg = new WaitGroup(5);
            for ($i = 0; $i < 5; $i++) {
                \Swoole\Coroutine::create(function () use ($wg, $i, &$fromArr, $cacheHub) {
                    $cache = $cacheHub->getCache('test', true);
                    $cache->buildLock = true;
                    $cache->buildWaitMod = 1;
                    $cache->buildWaitTime = 10;
                    $cache->buildWaitCount = 5;
                    $cache->valueFunc = function ($params) {
                        return 'test_lock';
                    };
                    $cache->wrapFunc = function ($data) {
                        return $data . '_wrap';
                    };
                    $data = $cache->get();
                    $this->assertEquals('test_lock_wrap', $data);
                    // usleep(1);
                    $from = $cache->getDataFrom();
                    $fromArr[] = $from;
                    $cache->clearDataFrom();
                    $wg->done();
                });
            }
            $wg->wait();
        });

        $buildArr = [];
        $redisArr = [];
        foreach ($fromArr as $v) {
            if ($v == 'build') {
                $buildArr[] = 1;
            } elseif($v == 'cachehub_redis') {
                $redisArr[] = 1;
            }
        }
        $this->assertEquals(1, count($buildArr));
        $this->assertEquals(4, count($redisArr));

        $redis->flushDB();
        $fromArr = [];
        run(function () use (&$fromArr, $cacheHub) {
            $wg = new WaitGroup(5);
            for ($i = 0; $i < 5; $i++) {
                \Swoole\Coroutine::create(function () use ($wg, $i, &$fromArr, $cacheHub) {
                    $cache = $cacheHub->getCache('test', true);
                    $cache->buildLock = false;
                    $cache->buildWaitMod = 1;
                    $cache->buildWaitTime = 10;
                    $cache->buildWaitCount = 5;
                    $cache->valueFunc = function ($params) {
                        return 'test_lock';
                    };
                    $cache->get();
                    // usleep(1);
                    $from = $cache->getDataFrom();
                    $fromArr[] = $from;
                    $cache->clearDataFrom();
                    $wg->done();
                });
            }
            $wg->wait();
        });

        $buildArr = [];
        $redisArr = [];
        foreach ($fromArr as $v) {
            if ($v == 'build') {
                $buildArr[] = 1;
            } elseif($v == 'cachehub_redis') {
                $redisArr[] = 1;
            }
        }
        $this->assertTrue(count($buildArr) > 3);


        $redis->flushDB();
        $fromArr = [];
        run(function () use (&$fromArr, $cacheHub) {
            $wg = new WaitGroup();
            for ($i = 0; $i < 2; $i++) {
                $wg->add();
                \Swoole\Coroutine::create(function () use ($wg, $i, &$fromArr, $cacheHub) {
                    $cache = $cacheHub->getCache('test', true);
                    $cache->buildLock = true;
                    $cache->buildWaitMod = 1;
                    $cache->buildWaitTime = 10;
                    $cache->buildWaitCount = 5;
                    $cache->valueFunc = function ($params) {
                        sleep(1);
                        return 'test_lock';
                    };
                    $cache->get();
                    // usleep(1);
                    $from = $cache->getDataFrom();
                    $fromArr[] = $from;
                    $cache->clearDataFrom();
                    $wg->done();
                });
            }
            $wg->wait();
        });

        $buildArr = [];
        $redisArr = [];
        foreach ($fromArr as $v) {
            if ($v == 'build') {
                $buildArr[] = 1;
            } elseif($v == 'cachehub_redis') {
                $redisArr[] = 1;
            }
        }
        $this->assertTrue(count($buildArr) == 2);

        $redis->flushDB();
        $fromArr = [];
        $isTimeout = 0;
        run(function () use (&$fromArr, &$isTimeout, $cacheHub) {
            $wg = new WaitGroup();
            for ($i = 0; $i < 2; $i++) {
                $wg->add();
                \Swoole\Coroutine::create(function () use ($wg, $i, &$fromArr, &$isTimeout, $cacheHub) {
                    try {
                        $cache = $cacheHub->getCache('test', true);
                        $cache->buildLock = true;
                        $cache->buildWaitMod = 2;
                        $cache->buildWaitTime = 10;
                        $cache->buildWaitCount = 5;
                        $cache->valueFunc = function ($params) {
                            sleep(1);
                            return 'test_lock';
                        };
                        $cache->get();
                        // usleep(1);
                        $from = $cache->getDataFrom();
                        $fromArr[] = $from;
                        $cache->clearDataFrom();
                    } catch (\Mingle\CacheHub\Exception\Exception $e) {
                        if ($e->getMessage() == 'build data timeout') {
                            $isTimeout++;
                        }
                    }
                    $wg->done();
                });
            }
            $wg->wait();
        });

        $buildArr = [];
        $redisArr = [];
        foreach ($fromArr as $v) {
            if ($v == 'build') {
                $buildArr[] = 1;
            } elseif($v == 'cachehub_redis') {
                $redisArr[] = 1;
            }
        }
        $this->assertTrue(count($buildArr) == 1);
        $this->assertEquals(1, $isTimeout);
    }

}
