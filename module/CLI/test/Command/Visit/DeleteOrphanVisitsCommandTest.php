<?php

declare(strict_types=1);

namespace ShlinkioTest\Shlink\CLI\Command\Visit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shlinkio\Shlink\CLI\Command\Visit\DeleteOrphanVisitsCommand;
use Shlinkio\Shlink\CLI\Util\ExitCode;
use Shlinkio\Shlink\Core\Model\BulkDeleteResult;
use Shlinkio\Shlink\Core\Visit\VisitsDeleterInterface;
use ShlinkioTest\Shlink\CLI\CliTestUtilsTrait;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteOrphanVisitsCommandTest extends TestCase
{
    use CliTestUtilsTrait;

    private CommandTester $commandTester;
    private MockObject & VisitsDeleterInterface $deleter;

    protected function setUp(): void
    {
        $this->deleter = $this->createMock(VisitsDeleterInterface::class);
        $this->commandTester = $this->testerForCommand(new DeleteOrphanVisitsCommand($this->deleter));
    }

    #[Test]
    public function successMessageIsPrintedAfterDeletion(): void
    {
        $this->deleter->expects($this->once())->method('deleteOrphanVisits')->willReturn(new BulkDeleteResult(5));
        $this->commandTester->setInputs(['yes']);

        $exitCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        self::assertEquals(ExitCode::EXIT_SUCCESS, $exitCode);
        self::assertStringContainsString('You are about to delete all orphan visits.', $output);
        self::assertStringContainsString('Successfully deleted 5 visits', $output);
    }
}
