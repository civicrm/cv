<?php

namespace Civi\Cv;

function cvdispatcher_global_func() {
  CvDispatcherTest::addLog('called ' . __FUNCTION__);
}

/**
 * @group std
 * @group util
 */
class CvDispatcherTest extends \PHPUnit\Framework\TestCase {

  protected static $log;

  protected function setUp(): void {
    parent::setUp();
    static::$log = [];
  }

  public function testIgnoreUnrelated() {
    $d = new CvDispatcher();
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('myapp.foo');
    });
    $d->addListener('myapp.bar', function ($event) {
      static::addLog('unrelated');
    });
    $d->dispatch(new CvEvent(['data' => 'yoyo']), 'myapp.foo')->getArguments();
    $this->assertEquals(['myapp.foo'], static::$log);
  }

  public function testCallbackTypes() {
    $d = new CvDispatcher();
    $d->addListener('myapp.foo', function ($event) {
      static::addLog("foo one data=" . $event['data']);
    });
    $d->addListener('myapp.foo', __NAMESPACE__ . '\\cvdispatcher_global_func');
    $d->addListener('myapp.foo', [__CLASS__, 'addFooThree']);

    $r = $d->dispatch(new CvEvent(['data' => 'yoyo']), 'myapp.foo')->getArguments();
    $this->assertEquals('yoyo', $r['data']);
    $this->assertEquals([
      'foo one data=yoyo',
      'called Civi\\Cv\\cvdispatcher_global_func',
      'foo three data=yoyo',
    ], static::$log);
  }

  public function testAlter() {
    $d = new CvDispatcher();
    $d->addListener('myapp.foo', function ($event) {
      $event['data'] .= ' first';
    });
    $d->addListener('myapp.foo', function ($event) {
      $event['data'] .= ' second';
    });
    $r = $d->dispatch(new CvEvent(['data' => 'seed']), 'myapp.foo')->getArguments();
    $this->assertEquals('seed first second', $r['data']);
  }

  public function testPriority() {
    $d = new CvDispatcher();
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.3.1');
    }, 3);
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.-200.1');
    }, -200);
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.1.1');
    }, 1);
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.2.1');
    }, 2);
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.1.2');
    }, 1);
    $d->addListener('myapp.foo', function ($event) {
      static::addLog('foo.1.3');
    }, 1);
    $d->addListener('*.foo', function ($event) {
      static::addLog('wildFoo.1.1');
    }, 1);
    $d->addListener('*.foo', function ($event) {
      static::addLog('wildFoo.2.1');
    }, 2);

    $d->dispatch(new CvEvent([]), 'myapp.foo');
    $this->assertEquals([
      'foo.-200.1',
      'foo.1.1',
      'foo.1.2',
      'foo.1.3',
      'wildFoo.1.1',
      'foo.2.1',
      'wildFoo.2.1',
      'foo.3.1',
    ], static::$log);
  }

  public static function addLog(string $message) {
    static::$log[] = $message;
  }

  public static function addFooThree($event) {
    static::addLog("foo three data=" . $event['data']);
  }

}
