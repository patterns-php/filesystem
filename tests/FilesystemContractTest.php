<?php

declare(strict_types=1);

namespace Patterns\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Patterns\FileStats;
use Patterns\IFilesystem;
use Patterns\IStatable;
use Patterns\Tests\Fakes\FakeFilesystem;
use Patterns\Tests\Fakes\FakeStatableFilesystem;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

final class FilesystemContractTest extends TestCase
{
    // -----------------------------------------------------------------------
    // The port must not grow
    // -----------------------------------------------------------------------

    public function testIFilesystemDeclaresExactlySevenMethods(): void
    {
        $this->assertSame(
            ['deleteDir', 'deleteFile', 'ensureDir', 'exists', 'readDir', 'readFile', 'writeFile'],
            self::methods(IFilesystem::class)
        );
    }

    public function testStatIsNotPartOfThePort(): void
    {
        $this->assertNotContains(
            'stat',
            self::methods(IFilesystem::class),
            'describing a path is a capability (IStatable), not a promise every backend can keep'
        );
    }

    public function testPermissionBitsAreNotPartOfThePort(): void
    {
        $this->assertNotContains('chmod', self::methods(IFilesystem::class));
    }

    public function testDerivableOperationsAreNotPartOfThePort(): void
    {
        foreach (['clear', 'clearDirectory', 'copyFile', 'copyDirectory', 'searchAndDestroy'] as $derivable) {
            $this->assertNotContains($derivable, self::methods(IFilesystem::class));
        }
    }

    public function testNoOperationCarriesAnEraSuffix(): void
    {
        foreach (self::methods(IFilesystem::class) as $method) {
            $this->assertStringNotContainsString('Sync', $method);
            $this->assertStringNotContainsString('Async', $method);
        }
    }

    // -----------------------------------------------------------------------
    // The port is typed
    // -----------------------------------------------------------------------

    public function testThePortIsFullyTyped(): void
    {
        $expected = [
            'exists'     => 'bool',
            'readFile'   => 'string',
            'writeFile'  => 'void',
            'deleteFile' => 'void',
            'readDir'    => 'array',
            'ensureDir'  => 'void',
            'deleteDir'  => 'void',
        ];

        foreach ($expected as $method => $return) {
            $this->assertSame(
                $return,
                self::returnTypeName(new ReflectionMethod(IFilesystem::class, $method)),
                $method . '() must return ' . $return
            );
        }
    }

    public function testEveryOperationTakesAStringPathFirst(): void
    {
        foreach (self::methods(IFilesystem::class) as $method) {
            $params = (new ReflectionMethod(IFilesystem::class, $method))->getParameters();

            $this->assertSame('path', $params[0]->getName(), $method . '() takes a path first');
            $this->assertSame('string', self::paramTypeName($params[0]), $method . '() takes a string path');
        }
    }

    public function testOnlyWriteFileTakesASecondArgument(): void
    {
        foreach (self::methods(IFilesystem::class) as $method) {
            $params = (new ReflectionMethod(IFilesystem::class, $method))->getParameters();

            if ($method === 'writeFile') {
                $this->assertCount(2, $params);
                $this->assertSame('data', $params[1]->getName());
                $this->assertSame('string', self::paramTypeName($params[1]));

                continue;
            }

            $this->assertCount(1, $params, $method . '() takes a path and nothing else');
        }
    }

    // -----------------------------------------------------------------------
    // The capability
    // -----------------------------------------------------------------------

    public function testIStatableDeclaresExactlyOneMethod(): void
    {
        $this->assertSame(['stat'], self::methods(IStatable::class));
    }

    public function testTheCapabilityDoesNotExtendThePort(): void
    {
        $this->assertFalse(
            is_subclass_of(IStatable::class, IFilesystem::class),
            'a capability that extended the port would be a requirement'
        );

        $this->assertFalse(is_subclass_of(IFilesystem::class, IStatable::class));
    }

    public function testThePortIsSatisfiableWithoutTheCapability(): void
    {
        $plain = new FakeFilesystem();

        $this->assertInstanceOf(IFilesystem::class, $plain);
        $this->assertNotInstanceOf(IStatable::class, $plain, 'the port alone is a complete implementation');
    }

    public function testAnAdapterCanOptIntoTheCapability(): void
    {
        $statable = new FakeStatableFilesystem();

        $this->assertInstanceOf(IFilesystem::class, $statable);
        $this->assertInstanceOf(IStatable::class, $statable);
    }

    public function testTheContractsNeedNoBaseClass(): void
    {
        foreach ([FakeFilesystem::class, FakeStatableFilesystem::class] as $class) {
            $this->assertFalse(
                (new ReflectionClass($class))->getParentClass(),
                $class . ' must not need to extend anything'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Satisfiable
    // -----------------------------------------------------------------------

    public function testAnInMemoryBackendSatisfiesThePort(): void
    {
        $fs = new FakeFilesystem();

        $this->assertFalse($fs->exists('a/b.txt'));

        $fs->writeFile('a/b.txt', 'hello');
        $this->assertTrue($fs->exists('a/b.txt'));
        $this->assertSame('hello', $fs->readFile('a/b.txt'));

        $fs->writeFile('a/c.txt', 'other');
        $this->assertSame(['b.txt', 'c.txt'], $fs->readDir('a'));

        $fs->deleteFile('a/c.txt');
        $this->assertSame(['b.txt'], $fs->readDir('a'));

        $fs->deleteDir('a');
        $this->assertSame([], $fs->files());
    }

    public function testTheCapabilityCarriesTheSameDataThrough(): void
    {
        $fs = new FakeStatableFilesystem();
        $fs->writeFile('a/b.txt', 'hello');

        $stats = $fs->stat('a/b.txt');

        $this->assertSame('a/b.txt', $stats->path());
        $this->assertSame(5, $stats->size());
        $this->assertFalse($stats->isDir());
    }

    // -----------------------------------------------------------------------
    // FileStats - the shape the capability returns
    // -----------------------------------------------------------------------

    public function testFileStatsExposesExactlyFourFacts(): void
    {
        $this->assertSame(
            ['create', 'isDir', 'jsonSerialize', 'modified', 'path', 'size', 'toArray'],
            self::methods(FileStats::class, true)
        );
    }

    public function testFileStatsIsAPlainStandaloneShape(): void
    {
        $reflection = new ReflectionClass(FileStats::class);

        $this->assertFalse($reflection->getParentClass(), 'FileStats needs no base class');
        $this->assertTrue($reflection->implementsInterface(JsonSerializable::class));
    }

    public function testFileStatsIsImmutable(): void
    {
        foreach (['path', 'size', 'modified', 'isDir'] as $name) {
            $property = new ReflectionProperty(FileStats::class, $name);

            $this->assertTrue($property->isReadOnly(), $name . ' must be readonly');
            $this->assertTrue($property->isPrivate(), $name . ' must not be public API');
        }
    }

    public function testFileStatsCarriesNoPosixDetail(): void
    {
        foreach (['mode', 'isSymbolicLink', 'atime', 'ctime', 'isFile'] as $posix) {
            $this->assertFalse(
                method_exists(FileStats::class, $posix),
                $posix . '() is POSIX detail, and half the backends implementing IStatable cannot fill it'
            );
        }
    }

    public function testFileStatsRoundTripsThroughToArrayAndCreate(): void
    {
        $stats = self::stats();
        $restored = FileStats::create($stats->toArray());

        $this->assertSame($stats->toArray(), $restored->toArray());
        $this->assertSame(json_encode($stats), json_encode($restored));
    }

    public function testFileStatsIsJsonSerializable(): void
    {
        $decoded = json_decode((string) json_encode(self::stats()), true);

        $this->assertIsArray($decoded);
        $this->assertSame(['path', 'size', 'modified', 'isDir'], array_keys($decoded));
        $this->assertSame('reports/2026.json', $decoded['path']);
    }

    public function testFileStatsNormalizesWhateverTheBackendHasAtHand(): void
    {
        $moment = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $fromObject = FileStats::create(['path' => 'a.txt', 'size' => 1, 'modified' => $moment]);
        $fromEpoch = FileStats::create(['path' => 'a.txt', 'size' => 1, 'modified' => $moment->getTimestamp()]);
        $fromString = FileStats::create(['path' => 'a.txt', 'size' => 1, 'modified' => '2026-01-01T12:00:00+00:00']);

        // One instant, whoever supplied it in whatever form...
        $this->assertSame($moment->getTimestamp(), $fromObject->modified()->getTimestamp());
        $this->assertSame($moment->getTimestamp(), $fromEpoch->modified()->getTimestamp());
        $this->assertSame($moment->getTimestamp(), $fromString->modified()->getTimestamp());

        // ...stored in one representation, so equality and JSON agree.
        foreach ([$fromObject, $fromEpoch, $fromString] as $stats) {
            $this->assertStringContainsString('T', $stats->toArray()['modified']);
        }
    }

    public function testFileStatsRefusesAStatWithoutAPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/path is required/');

        FileStats::create(['size' => 1, 'modified' => 'now']);
    }

    public function testFileStatsRefusesAStatWithoutAMoment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/modified is required/');

        FileStats::create(['path' => 'a.txt', 'size' => 1]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private static function stats(): FileStats
    {
        return FileStats::create([
            'path'     => 'reports/2026.json',
            'size'     => 42,
            'modified' => '2026-10-06T08:00:00+00:00',
            'isDir'    => false,
        ]);
    }

    /**
     * Methods a class declares itself - inherited ones do not count as its shape.
     *
     * @return list<string>
     */
    private static function methods(string $class, bool $publicOnly = false): array
    {
        $own = [];

        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            if ($publicOnly && !$method->isPublic()) {
                continue;
            }

            $own[] = $method->getName();
        }

        sort($own);

        return $own;
    }

    private static function returnTypeName(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }

    private static function paramTypeName(ReflectionParameter $param): string
    {
        $type = $param->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }
}
