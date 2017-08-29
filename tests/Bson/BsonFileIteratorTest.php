<?php

declare(strict_types=1);

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use GeckoPackages\Bson\BsonFileIterator;
use GeckoPackages\PHPUnit\Asserts\RangeAssertTrait;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHPUnit 5.2
 *
 * @internal
 *
 * @author SpacePossum
 */
final class BsonFileIteratorTest extends TestCase
{
    use RangeAssertTrait;

    /**
     * @param string|\SplFileInfo $file
     * @param int                 $constructType
     * @param array               $expected
     *
     * @dataProvider provideIteratorCases
     */
    public function testIterator($file, $constructType, array $expected)
    {
        $iterator = new BsonFileIterator($file, $constructType);
        $unWinded = [];
        foreach ($iterator as $index => $item) {
            $this->assertUnsignedInt($index);

            if (\is_string($item) && BsonFileIterator::CONSTRUCT_JSON === $constructType) {
                $item = \json_decode($item, true);
                foreach ($item as $p => $v) {
                    if (\is_float($v)) {
                        $item[$p] = \round($v, 5);
                    }
                }

                $item = \json_encode($item, JSON_PRETTY_PRINT);
            } elseif (\is_array($item) && BsonFileIterator::CONSTRUCT_ARRAY === $constructType) {
                foreach ($item as $p => $v) {
                    if (\is_float($v)) {
                        $item[$p] = \round($v, 5);
                    }
                }
            } elseif ($item instanceof \stdClass && BsonFileIterator::CONSTRUCT_STD === $constructType) {
                foreach ($item as $p => $v) {
                    if (\is_float($v)) {
                        $item->$p = \round($v, 5);
                    }
                }
            }

            $unWinded[$index] = $item;
        }

        if (BsonFileIterator::CONSTRUCT_STD === $constructType) {
            $this->assertCount(\count($expected), $expected);
            $this->assertContainsOnlyInstancesOf(\stdClass::class, $expected);
            for ($i = 0, $count = \count($expected); $i < $count; ++$i) {
                $expect = $expected[$i];
                $actual = $unWinded[$i];
                foreach ($expect as $key => $value) {
                    $this->assertObjectHasAttribute($key, $actual);
                    if ($expect->$key instanceof \stdClass) {
                        $this->assertInstanceOf(\stdClass::class, $actual->$key);
                        foreach ($expect->$key as $k1 => $v1) {
                            $this->assertSame($expect->$key->$k1, $actual->$key->$k1);
                        }

                        continue;
                    }

                    $this->assertSame($expect->$key, $actual->$key);
                }
            }
        } else {
            $this->assertSame($expected, $unWinded);
        }

        $iterator->__destruct();
    }

    public function provideIteratorCases(): array
    {
        $assertDir = $this->getAssetDir();

        $cases = [
            'array indexes.bson' => [
                $assertDir.'system.indexes.bson',
                BsonFileIterator::CONSTRUCT_ARRAY,
                [[
                    'v' => 1,
                    'key' => ['_id' => 1],
                    'ns' => 'gecko.test',
                    'name' => '_id_',
                ]],
            ],
            'array test.bson' => [
                $assertDir.'test.bson',
                BsonFileIterator::CONSTRUCT_ARRAY,
                [
                    [
                        'a' => 1,
                        'b' => null,
                        'c' => false,
                        'd' => [],
                        'e' => [],
                        'f' => 'abc',
                        'g' => PHP_INT_MAX,
                        'h' => \round(-1.11111111, 5),
                        'i' => PHP_INT_MIN,
                        'j' => \round(1.11111111, 5),
                        'k' => 0,
                        'l' => [
                            1, 2, 3,
                        ],
                        '_id' => ['$oid' => '58359204eb70974bcd457cc1'],
                    ],
                    [
                        'a' => 1,
                        'b' => null,
                        'c' => false,
                        'd' => [],
                        'e' => [],
                        'f' => 'abc',
                        'g' => PHP_INT_MAX,
                        'h' => \round(-1.11111111, 5),
                        'i' => PHP_INT_MIN,
                        'j' => \round(1.11111111, 5),
                        'k' => 0,
                        'l' => [
                            1, 2, 3,
                        ],
                        '_id' => ['$oid' => '58359204eb70974bcd457cc2'],
                    ],
                ],
            ],
        ];

        foreach (['array indexes.bson', 'array test.bson'] as $index) {
            /** @var array $case */
            $case = $cases[$index];
            $expected = [];
            foreach ($case[2] as $j => $expect) {
                $expected[$j] = \json_encode($expect, JSON_PRETTY_PRINT);
            }

            $cases['json test.bson'] = [
                $case[0],
                BsonFileIterator::CONSTRUCT_JSON,
                $expected,
            ];

            $expected = [];
            foreach ($case[2] as $j => $expect) {
                if (isset($expect['_id'])) {
                    $expect['_id'] = (object) $expect['_id'];
                }

                $expected[$j] = (object) $expect;
            }

            $cases['std test.bson'] = [
                $case[0],
                BsonFileIterator::CONSTRUCT_STD,
                $expected,
            ];
        }

        return $cases;
    }

    public function testIteratorSplFile()
    {
        $cases = $this->provideIteratorCases();
        $case = \reset($cases);
        $this->testIterator(new \SplFileInfo($case[0]), $case[1], $case[2]);
    }

    public function testIteratorFileEmpty()
    {
        $iterator = new BsonFileIterator($this->getAssetDir().'empty.bson');
        $this->assertCount(0, $iterator);
    }

    public function testIteratorInvalidConstructionValueInt()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^Construct type must be any of integers \"1, 2, 3\" got \"777\".$#');

        new BsonFileIterator($this->getAssetDir().'test.bson', 777);
    }

    public function testIteratorInvalidFileIsCorruptedBsonData()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageRegExp('#^Invalid data at item \#1, size 12435439 exceeds max. unpack size 372.$#');

        $iterator = new BsonFileIterator($this->getAssetDir().'test.corrupt.bson');
        $this->assertCount(0, $iterator);  // not a real assert, but causes the iterator to real all
    }

    public function testIteratorInvalidFileIsDir()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^directory\#\"/.*tests/Bson\" is not a file.$#');

        new BsonFileIterator(__DIR__);
    }

    public function testIteratorInvalidFileIsNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^\"test123\" is not a file.$#');

        new BsonFileIterator('test123');
    }

    public function testIteratorInvalidFileIsOtherType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^integer\#\"1\" is not a file.$#');

        new BsonFileIterator(1);
    }

    public function testIteratorInvalidFileIsSmall()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageRegExp('#^Invalid data at item \#1, size 1633771873 exceeds max. unpack size 4.$#');

        $iterator = new BsonFileIterator($this->getAssetDir().'small.bson');
        $this->assertCount(0, $iterator);
    }

    public function testIteratorInvalidJsonDecodeMaxDepthValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^Expected integer > 0 for JSON decode max depth, got \"integer\#-1\".$#');

        new BsonFileIterator(
            $this->getAssetDir().'empty.bson',
            BsonFileIterator::CONSTRUCT_ARRAY,
            5242880,
            -1
        );
    }

    public function testIteratorInvalidMaxUnpackSizeValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#^Expected integer > 0 for max. unpack size, got \"integer\#-1\".$#');

        new BsonFileIterator(__FILE__, 1, -1);
    }

    /**
     * @param int $constructType
     *
     * @dataProvider provideIteratorMaxDepthReached
     */
    public function testIteratorMaxDepthReached($constructType)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageRegExp('#^Invalid JSON \"Maximum stack depth exceeded\" at item \#1.$#');

        $cases = $this->provideIteratorCases();
        $case = \reset($cases);
        $iterator = new BsonFileIterator(
            $case[0],
            $constructType,
            5242880,
            1
        );

        $this->assertCount(0, $iterator); // not a real assert, but causes the iterator to real all
    }

    public function provideIteratorMaxDepthReached(): array
    {
        return [[BsonFileIterator::CONSTRUCT_ARRAY], [BsonFileIterator::CONSTRUCT_STD]];
    }

    private function getAssetDir(): string
    {
        return \realpath(__DIR__.'/../assets').'/';
    }
}
