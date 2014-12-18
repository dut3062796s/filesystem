<?php

namespace React\Tests\Filesystem;

use React\Filesystem\Eio\PermissionFlagResolver;
use React\Filesystem\EioAdapter;

class EioFilesystemTest extends \PHPUnit_Framework_TestCase
{

    public function testEioExtensionInstalled()
    {
        $this->assertTrue(function_exists('eio_init'));
    }

    public function testInterface()
    {
        $this->assertInstanceOf(
            'React\Filesystem\AdapterInterface',
            new EioAdapter($this->getMock('React\EventLoop\LoopInterface'))
        );
    }

    public function testGetLoop()
    {
        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $filesystem = new EioAdapter($loop);
        $this->assertSame($loop, $filesystem->getLoop());
    }

    public function testCallEioCallsProvider()
    {
        $pathName = 'foo.bar';
        return [
            [
                'unlink',
                'eio_unlink',
                [
                    $pathName,
                ],
                [
                    $pathName,
                ],
            ],
            [
                'rename',
                'eio_rename',
                [
                    $pathName,
                    str_rot13($pathName),
                ],
                [
                    $pathName,
                    str_rot13($pathName),
                ],
            ],
            [
                'chmod',
                'eio_chmod',
                [
                    $pathName,
                    123,
                ],
                [
                    $pathName,
                    123,
                ],
            ],
            [
                'chown',
                'eio_chown',
                [
                    $pathName,
                    1,
                    2,
                ],
                [
                    $pathName,
                    1,
                    2,
                ],
            ],
            [
                'ls',
                'eio_readdir',
                [
                    $pathName,
                ],
                [
                    $pathName,
                    EIO_READDIR_DIRS_FIRST,
                ],
                false,
            ],
            [
                'ls',
                'eio_readdir',
                [
                    $pathName,
                    112,
                ],
                [
                    $pathName,
                    112,
                ],
                false,
            ],
            [
                'mkdir',
                'eio_mkdir',
                [
                    $pathName,
                ],
                [
                    $pathName,
                    (new PermissionFlagResolver())->resolve(EioAdapter::CREATION_MODE),
                ],
            ],
            [
                'mkdir',
                'eio_mkdir',
                [
                    $pathName,
                    'rwxrwxrwx',
                ],
                [
                    $pathName,
                    (new PermissionFlagResolver())->resolve('rwxrwxrwx'),
                ],
            ],
            [
                'rmdir',
                'eio_rmdir',
                [
                    $pathName,
                ],
                [
                    $pathName,
                ],
            ],
            [
                'close',
                'eio_close',
                [
                    $pathName,
                ],
                [
                    $pathName,
                ],
            ],
            [
                'read',
                'eio_read',
                [
                    $pathName,
                    123,
                    456,
                ],
                [
                    $pathName,
                    123,
                    456,
                ],
            ],
            [
                'write',
                'eio_write',
                [
                    $pathName,
                    'abc',
                    3,
                    456,
                ],
                [
                    $pathName,
                    'abc',
                    3,
                    456,
                ],
            ],
        ];
    }

    /**
     * @dataProvider testCallEioCallsProvider
     */
    public function testCallEioCalls($externalMethod, $internalMethod, $externalCallArgs, $internalCallArgs, $errorResultCode = -1)
    {
        $promise = $this->getMock('React\Promise\PromiseInterface');

        $filesystem = $this->getMock('React\Filesystem\EioAdapter', [
            'callEio',
        ], [
            $this->getMock('React\EventLoop\LoopInterface'),
        ]);

        $filesystem
            ->expects($this->once())
            ->method('callEio')
            ->with($internalMethod, $internalCallArgs, $errorResultCode)
            ->will($this->returnValue($promise))
        ;

        $this->assertSame($promise, call_user_func_array([$filesystem, $externalMethod], $externalCallArgs));
    }

    public function testCallEio()
    {
        $filename = 'foo.bar';
        $loop = $this->getMock('React\EventLoop\StreamSelectLoop', [
            'futureTick',
        ]);

        $loop
            ->expects($this->once())
            ->method('futureTick')
            ->with($this->isType('callable'))
            ->will($this->returnCallback(function ($resolveCb) {
                $resolveCb();
            }))
        ;

        $filesystem = $this->getMock('React\Filesystem\EioAdapter', [
            'executeDelayedCall',
        ], [
            $loop,
        ]);

        $filesystem
            ->expects($this->once())
            ->method('executeDelayedCall')
            ->with('eio_stat', [
                $filename,
            ], -1, $this->isInstanceOf('React\Promise\Deferred'))
        ;

        $this->assertInstanceOf('React\Promise\PromiseInterface', $filesystem->stat($filename));
    }
}
