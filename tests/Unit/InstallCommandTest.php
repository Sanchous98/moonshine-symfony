<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Unit;

use MoonShine\Symfony\Command\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class InstallCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/moonshine-install-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testInstallCreatesSkeleton(): void
    {
        $tester = new CommandTester(new InstallCommand($this->projectDir));

        self::assertSame(0, $tester->execute([]));

        self::assertFileExists($this->projectDir . '/config/routes/moonshine.yaml');
        self::assertFileExists($this->projectDir . '/config/packages/moonshine.yaml');
        self::assertFileExists($this->projectDir . '/src/MoonShine/Pages/Dashboard.php');
        self::assertFileExists($this->projectDir . '/src/MoonShine/Pages/LoginPage.php');
        self::assertDirectoryExists($this->projectDir . '/src/MoonShine/Resources');
        self::assertTrue(is_link($this->projectDir . '/public/vendor/moonshine'));
        self::assertFileExists($this->projectDir . '/public/vendor/moonshine/assets/main.css');
    }

    public function testInstallKeepsExistingFiles(): void
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->projectDir . '/src/MoonShine/Pages/Dashboard.php', '<?php // custom');

        $tester = new CommandTester(new InstallCommand($this->projectDir));
        $tester->execute(['--copy' => true]);

        self::assertStringEqualsFile($this->projectDir . '/src/MoonShine/Pages/Dashboard.php', '<?php // custom');
        self::assertFalse(is_link($this->projectDir . '/public/vendor/moonshine'));
        self::assertFileExists($this->projectDir . '/public/vendor/moonshine/assets/main.css');
    }
}
