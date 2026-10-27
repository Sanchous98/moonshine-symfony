<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Command;

use Composer\InstalledVersions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('moonshine:install', 'Install MoonShine: routes, config, assets and default pages')]
final class InstallCommand extends Command
{
    private const PACKAGE_DIR = __DIR__ . '/../..';

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $fs = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('copy', null, InputOption::VALUE_NONE, 'Copy assets instead of symlinking them')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $this->copy($io, '/recipe/config/routes/moonshine.yaml', '/config/routes/moonshine.yaml', $force);
        $this->copy($io, '/recipe/config/packages/moonshine.yaml', '/config/packages/moonshine.yaml', $force);

        foreach (['Resources', 'Pages', 'Layouts'] as $directory) {
            $this->fs->mkdir($this->projectDir . '/src/MoonShine/' . $directory);
        }

        $this->copy($io, '/stubs/Dashboard.php', '/src/MoonShine/Pages/Dashboard.php', $force);
        $this->copy($io, '/stubs/LoginPage.php', '/src/MoonShine/Pages/LoginPage.php', $force);

        $this->installAssets($io, (bool) $input->getOption('copy'));

        $io->success('MoonShine installed. Open the admin panel at /admin');

        return Command::SUCCESS;
    }

    private function copy(SymfonyStyle $io, string $from, string $to, bool $force): void
    {
        $target = $this->projectDir . $to;

        if (! $force && $this->fs->exists($target)) {
            $io->writeln(\sprintf('Skipped existing <comment>%s</comment>', ltrim($to, '/')));

            return;
        }

        $this->fs->copy(self::PACKAGE_DIR . $from, $target, true);
        $io->writeln(\sprintf('Created <info>%s</info>', ltrim($to, '/')));
    }

    private function installAssets(SymfonyStyle $io, bool $copy): void
    {
        $source = InstalledVersions::getInstallPath('moonshine/ui');
        $source = $source !== null ? realpath($source . '/dist') : false;

        if ($source === false) {
            $io->warning('Package "moonshine/ui" was not found, assets were not installed');

            return;
        }

        $target = $this->projectDir . '/public/vendor/moonshine';

        if (is_link($target) || $this->fs->exists($target)) {
            $this->fs->remove($target);
        }

        if ($copy) {
            $this->fs->mirror($source, $target);
            $io->writeln('Copied assets to <info>public/vendor/moonshine</info>');

            return;
        }

        $this->fs->mkdir(\dirname($target));
        $this->fs->symlink(rtrim($this->fs->makePathRelative($source, \dirname($target)), '/'), $target);
        $io->writeln('Linked assets to <info>public/vendor/moonshine</info>');
    }
}
