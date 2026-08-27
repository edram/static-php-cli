<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('curl')]
class curl extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-curl')]
    #[PatchDescription('Preserve macOS framework flags during the libcurl link check')]
    public function patchLibraryCheckForMacOS(): void
    {
        if (SystemTarget::getTargetOS() !== 'Darwin') {
            return;
        }

        $config_path = "{$this->getBuildDir()}/config.m4";
        FileSystem::replaceFileStr(
            $config_path,
            '  PHP_CHECK_LIBRARY(curl,curl_easy_perform,',
            "  curl_save_LDFLAGS=\"\$LDFLAGS\"\n  LDFLAGS=\"\$LDFLAGS \$CURL_LIBS\"\n  PHP_CHECK_LIBRARY(curl,curl_easy_perform,",
        );
        FileSystem::replaceFileStr(
            $config_path,
            "    \$CURL_LIBS\n  ])\n\n  PHP_NEW_EXTENSION(curl,",
            "    \$CURL_LIBS\n  ])\n  LDFLAGS=\"\$curl_save_LDFLAGS\"\n\n  PHP_NEW_EXTENSION(curl,",
        );
    }

    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-curl')]
    #[PatchDescription('Inject secur32.lib into SPC_EXTRA_LIBS for Schannel SSL support')]
    public function addSecur32LibForWindows(): void
    {
        // curl on Windows uses Schannel (USE_WINDOWS_SSPI=ON, CURL_USE_SCHANNEL=ON),
        // which requires secur32.lib for SSL/TLS functions (SslEncryptPackage, etc.).
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, 'secur32.lib')) {
            putenv('SPC_EXTRA_LIBS=' . trim("{$extra_libs} secur32.lib"));
        }
    }
}
