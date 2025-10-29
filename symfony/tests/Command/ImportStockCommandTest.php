<?php

namespace App\Tests\Command;

use App\Command\ImportStockCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @coversDefaultClass \App\Command\ImportStockCommand
 */
class ImportStockCommandTest extends TestCase
{
    private ImportStockCommand $command;

    /**
     * This method runs before each test, we mock the EntityManager because we don't need a real database
     */
    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->command = new ImportStockCommand($em);
    }

    /**
     * Helper to call private methods using Reflection.
     */
    private function callPrivate(string $method, array $args = [])
    {
        $ref = new ReflectionClass($this->command);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->command, $args);
    }

    /** 
     * @covers ::normalizePrice
     * Tests price normalization
     */
    public function testNormalizePrice(): void
    {
        $this->assertSame('87.00', $this->callPrivate('normalizePrice', ['87,00']));
        $this->assertSame('99.50', $this->callPrivate('normalizePrice', ['99.5']));
        $this->assertSame('0.00', $this->callPrivate('normalizePrice', ['abc']));
    }

    /** 
     * @covers ::normalizeQuantity 
     * Tests quantity normalization
     */
    public function testNormalizeQuantity(): void
    {
        $this->assertSame(31, $this->callPrivate('normalizeQuantity', ['>30', 31]));
        $this->assertSame(11, $this->callPrivate('normalizeQuantity', ['>10', 11]));
        $this->assertSame(25, $this->callPrivate('normalizeQuantity', ['25']));
        $this->assertSame(0, $this->callPrivate('normalizeQuantity', ['xyz']));
        $this->assertSame(0, $this->callPrivate('normalizeQuantity', [null]));
    }

    /** 
     * @covers ::normalizeNullable 
     * Tests converting empty or "null" values into real null
     */
    public function testNormalizeNullable(): void
    {
        $this->assertSame('123456', $this->callPrivate('normalizeNullable', ['123456']));
        $this->assertNull($this->callPrivate('normalizeNullable', ['null']));
        $this->assertNull($this->callPrivate('normalizeNullable', ['']));
        $this->assertNull($this->callPrivate('normalizeNullable', [null]));
    }

    /** 
     * @covers ::cleanValue
     * Tests cleaning of whitespace, tabs, quotes, etc.
     */
    public function testCleanValue(): void
    {
        $this->assertSame('test', $this->callPrivate('cleanValue', [' "test" ']));
        $this->assertSame('abc', $this->callPrivate('cleanValue', ["\tabc\n"]));
        $this->assertSame('', $this->callPrivate('cleanValue', [null]));
    }

    /** 
     * @covers ::smartSplit
     * Tests splitting CSV lines by tab or semicolon
     */
    public function testSmartSplit(): void
    {
        $tabLine = "code1\tcode2\tname\tbrand\t>30\t99,99\t1234567890123";
        $res1 = $this->callPrivate('smartSplit', [$tabLine]);
        $this->assertCount(7, $res1);
        $this->assertSame('>30', $res1[4]);

        $semiLine = 'A001;5;10,00;MPN123;1234567890123;Bosch';
        $res2 = $this->callPrivate('smartSplit', [$semiLine]);
        $this->assertCount(6, $res2);
        $this->assertSame('MPN123', $res2[3]);
    }

    /** 
     * Tests failure when file path is invalid
     */
    public function testFileValidationFailure(): void
    {
        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);

        $input->method('getArgument')
            ->willReturnMap([
                ['file', '/nonexistent/path.csv'],
                ['supplier', 'lorotom'],
            ]);

        $status = $this->command->run($input, $output);
        $this->assertSame(1, $status); // Command::FAILURE
    }
}
