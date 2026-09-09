<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_link;
use function json_encode;
use function mkdir;
use function rmdir;
use function scandir;
use function shell_exec;
use function sprintf;
use function str_ends_with;
use function symlink;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const JSON_FORCE_OBJECT;

/**
 * Runs the real, Docker-based generator (resources/dev/openapi-codegen-php-client.sh)
 * against a dummy OpenAPI spec exactly the way a consuming project's
 * `vendor/bin/openapi-codegen-php-client.sh` would, so regressions in the
 * generation pipeline (not just the templates) get caught.
 *
 * Requires Docker; the test is skipped when it is not available.
 *
 * @group integration
 */
class GeneratorTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        if (trim((string) shell_exec('command -v docker')) === '') {
            self::markTestSkipped('Docker is required to run the generator integration test.');
        }

        $this->projectRoot = sys_get_temp_dir() . '/openapi-codegen-it-' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->projectRoot === '') {
            return;
        }

        self::removeDirectory($this->projectRoot);
    }

    public function testGenerationProducesAWorkingClient(): void
    {
        $this->scaffold($this->projectRoot, ['clientClass' => null]);

        [$output, $exitCode] = $this->runGenerator($this->projectRoot);

        self::assertSame(0, $exitCode, "generator script failed:\n" . implode("\n", $output));

        foreach (
            [
                'Client/WidgetsClient.php',
                'Client/WidgetsClientInterface.php',
                'Client/ApiWidgetsClientMock.php',
                'Client/AbstractClientBuilder.php',
                'README.md',
                'composer.json',
                'phpstan.neon',
                'phpcs.xml',
                'phpmd.xml',
                'grumphp.yml',
                'rector.php',
                '.gitignore',
            ] as $expectedFile
        ) {
            self::assertFileExists($this->projectRoot . '/' . $expectedFile);
        }

        self::assertFileExists($this->projectRoot . '/Model/Widget.php');
        self::assertFileExists($this->projectRoot . '/Endpoint/ListWidgets.php');
        self::assertFileExists($this->projectRoot . '/Factory/WidgetFactory.php');
        self::assertFileExists($this->projectRoot . '/Default/WidgetDefault.php');

        // schemas ending in .jsonld must be stripped, never turned into a model
        self::assertFileDoesNotExist($this->projectRoot . '/Model/WidgetJsonld.php');

        $widget = (string) file_get_contents($this->projectRoot . '/Model/Widget.php');
        self::assertStringContainsString(
            'DateTimeValue',
            $widget,
            'date-time properties should use the custom DateTimeValue type mapping',
        );
        self::assertStringContainsString(
            'UuidValue',
            $widget,
            'the x-vo-type extension should surface the configured value object type',
        );
        self::assertStringContainsString(
            'prefixNumber2fa',
            $widget,
            'digit-leading property names should be prefixed instead of camelised',
        );

        $client = (string) file_get_contents($this->projectRoot . '/Client/WidgetsClient.php');
        self::assertStringContainsString(
            'class WidgetsClient extends AbstractClient implements WidgetsClientInterface',
            $client,
        );
        self::assertStringContainsString(
            "'/problem/widget-name-already-exists' => \\OpenAPI\\Client\\Model\\WidgetNameAlreadyExists::class,",
            $client,
            'createWidget() should pass its problem-details error responses to performRequest() as an exception map',
        );

        $exceptionModelFile = $this->projectRoot . '/Model/WidgetNameAlreadyExists.php';
        self::assertFileExists($exceptionModelFile);
        $widgetNameAlreadyExists = (string) file_get_contents($exceptionModelFile);
        self::assertStringContainsString(
            'class WidgetNameAlreadyExists extends \Exception implements SpecialKeySupport, JsonSchemaAwareRecord',
            $widgetNameAlreadyExists,
            'a response schema shaped like an RFC7807 problem details object should generate a throwable class',
        );
        self::assertStringContainsString('use ProblemDetailsExceptionLogic;', $widgetNameAlreadyExists);
        self::assertStringNotContainsString('use JsonSchemaAwareRecordLogic;', $widgetNameAlreadyExists);

        $this->assertGeneratedPhpLints();
    }

    public function testAbstractClientClassNameRendersAnAbstractClass(): void
    {
        $this->scaffold($this->projectRoot, ['clientClass' => 'AbstractClient']);

        [$output, $exitCode] = $this->runGenerator($this->projectRoot);

        self::assertSame(0, $exitCode, "generator script failed:\n" . implode("\n", $output));
        self::assertFileExists($this->projectRoot . '/Client/WidgetsAbstractClient.php');
        self::assertFileExists($this->projectRoot . '/Client/WidgetsAbstractClientInterface.php');

        $client = (string) file_get_contents($this->projectRoot . '/Client/WidgetsAbstractClient.php');
        self::assertStringContainsString(
            'abstract class WidgetsAbstractClient extends AbstractClient implements WidgetsAbstractClientInterface',
            $client,
        );
    }

    private function assertGeneratedPhpLints(): void
    {
        $phpFiles = [];
        foreach (['Model', 'Endpoint', 'Factory', 'Default', 'Client'] as $directory) {
            foreach (scandir($this->projectRoot . '/' . $directory) ?: [] as $entry) {
                if (! str_ends_with($entry, '.php')) {
                    continue;
                }

                $phpFiles[] = $this->projectRoot . '/' . $directory . '/' . $entry;
            }
        }

        foreach ($phpFiles as $phpFile) {
            exec(sprintf('php -l %s 2>&1', escapeshellarg($phpFile)), $lintOutput, $lintExit);
            self::assertSame(0, $lintExit, implode("\n", $lintOutput));
        }
    }

    /** Mirrors the abstract createXxx()/createXxxMock() pair the generated AbstractClientBuilder declares for the "Widgets" tag. */
    private static function clientBuilderStub(string $clientClass): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace OpenAPI\Client;

            use TeamBlue\ClientMock\Mock;

            final class ClientBuilder extends AbstractClientBuilder
            {
                protected function createWidgets{$clientClass}(): Widgets{$clientClass}
                {
                    return new Widgets{$clientClass}(\$this->client());
                }

                protected static function createWidgets{$clientClass}Mock(): Mock
                {
                    return new ApiWidgets{$clientClass}Mock();
                }
            }

            PHP;
    }

    /** @param array{clientClass: string|null} $config */
    private function scaffold(string $projectRoot, array $config): void
    {
        $repoRoot = dirname(__DIR__, 2);

        $vendorPackageDir = $projectRoot . '/vendor/team-blue/openapi-codegen-php-client/resources/dev';
        mkdir($vendorPackageDir, 0777, true);
        mkdir($projectRoot . '/vendor/bin', 0777, true);
        mkdir($projectRoot . '/resources/api/templates', 0777, true);

        copy(
            $repoRoot . '/resources/dev/openapi-codegen-php-client.sh',
            $vendorPackageDir . '/openapi-codegen-php-client.sh',
        );
        chmod($vendorPackageDir . '/openapi-codegen-php-client.sh', 0755);

        // A directory symlink is enough for `docker build` to read the Dockerfile/src
        // as its build context, so the actual generator sources never get copied.
        symlink(
            $repoRoot . '/resources/dev/openapi-codegen-php-client',
            $vendorPackageDir . '/openapi-codegen-php-client',
        );

        // Mirrors exactly how Composer installs a package's declared "bin" scripts.
        symlink(
            '../team-blue/openapi-codegen-php-client/resources/dev/openapi-codegen-php-client.sh',
            $projectRoot . '/vendor/bin/openapi-codegen-php-client.sh',
        );

        copy(__DIR__ . '/Fixtures/dummy-openapi-spec.json', $projectRoot . '/resources/api/api-spec.json');

        $cliConfig = [];
        if ($config['clientClass'] !== null) {
            $cliConfig['clientClass'] = $config['clientClass'];
        }

        file_put_contents($projectRoot . '/resources/api/config.json', json_encode($cliConfig, JSON_FORCE_OBJECT));

        // ClientBuilder is abstract by design (src/ClientBuilder/ClientBuilder.php),
        // and AbstractClientBuilder is generated fresh with one abstract
        // createXxx()/createXxxMock() pair per tag: every consuming project
        // hand-writes its own concrete subclass, so rector.php (which always
        // references it) needs one to already exist before generation ever runs.
        $clientClass = $config['clientClass'] ?? 'Client';
        file_put_contents($projectRoot . '/ClientBuilder.php', self::clientBuilderStub($clientClass));

        // phpcbf/rector are invoked by the script itself (not via `composer install`
        // of the freshly generated composer.json, which depends on private packages),
        // so the scaffold provisions its own public-only copies up front.
        file_put_contents($projectRoot . '/composer.json', json_encode([
            'name' => 'team-blue/openapi-codegen-php-client-integration-test-scratch',
            'require' => ['php' => '^8.1'],
            'require-dev' => [
                'squizlabs/php_codesniffer' => '~3.0',
                'rector/rector' => '~2.0',
            ],
            'minimum-stability' => 'stable',
        ]));

        exec(
            sprintf('composer install --no-interaction --quiet --working-dir=%s 2>&1', escapeshellarg($projectRoot)),
            $composerOutput,
            $composerExit,
        );
        self::assertSame(
            0,
            $composerExit,
            "composer install for the test scaffold failed:\n" . implode("\n", $composerOutput),
        );
    }

    /** @return array{0: array<string>, 1: int} */
    private function runGenerator(string $projectRoot): array
    {
        exec(
            sprintf('cd %s && bash vendor/bin/openapi-codegen-php-client.sh 2>&1', escapeshellarg($projectRoot)),
            $output,
            $exitCode,
        );

        return [$output, $exitCode];
    }

    private static function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            if (file_exists($path)) {
                unlink($path);
            }

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::removeDirectory($path . DIRECTORY_SEPARATOR . $entry);
        }

        rmdir($path);
    }
}
