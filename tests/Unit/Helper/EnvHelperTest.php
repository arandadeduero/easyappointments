<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class EnvHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['EA_TEST_PLAIN', 'EA_TEST_QUOTED', 'EA_TEST_EXISTING', 'EA_TEST_EMPTY'] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    public function testParseEnvContentsSkipsCommentsBlankLinesAndInvalidNames(): void
    {
        $contents = "# A comment\n\nEA_TEST_PLAIN=value\nNOT A VARIABLE\n1INVALID=x\nEA_TEST_EMPTY=\n";

        $this->assertSame(['EA_TEST_PLAIN' => 'value', 'EA_TEST_EMPTY' => ''], parse_env_contents($contents));
    }

    public function testParseEnvContentsUnwrapsQuotedValuesAndKeepsSpecialCharacters(): void
    {
        $contents = "EA_TEST_QUOTED=\"p4ss#word=with spaces\"\nEA_TEST_PLAIN='single'\n";

        $this->assertSame(
            ['EA_TEST_QUOTED' => 'p4ss#word=with spaces', 'EA_TEST_PLAIN' => 'single'],
            parse_env_contents($contents),
        );
    }

    public function testLoadEnvFileDefinesVariablesWithoutOverridingExistingOnes(): void
    {
        putenv('EA_TEST_EXISTING=from-environment');

        $path = tempnam(sys_get_temp_dir(), 'ea-env');

        file_put_contents($path, "EA_TEST_PLAIN=from-file\nEA_TEST_EXISTING=from-file\n");

        try {
            $loaded = load_env_file($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(['EA_TEST_PLAIN' => 'from-file'], $loaded);
        $this->assertSame('from-file', getenv('EA_TEST_PLAIN'));
        $this->assertSame('from-environment', getenv('EA_TEST_EXISTING'));
    }

    public function testLoadEnvFileIgnoresMissingFile(): void
    {
        $this->assertSame([], load_env_file(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.env'));
    }

    public function testEnvReturnsDefaultForMissingOrEmptyVariables(): void
    {
        putenv('EA_TEST_EMPTY=');

        $this->assertSame('fallback', env('EA_TEST_UNDEFINED_' . uniqid(), 'fallback'));
        $this->assertSame('fallback', env('EA_TEST_EMPTY', 'fallback'));

        putenv('EA_TEST_PLAIN=defined');

        $this->assertSame('defined', env('EA_TEST_PLAIN', 'fallback'));
    }
}
