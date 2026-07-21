<?php

namespace Tests\Unit;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Interceptors\FileInterceptor;
use Assegai\Core\Interceptors\FileInterceptorOptions;
use Assegai\Core\Rendering\ViewProperties;
use Assegai\Core\Runtimes\RuntimeContext;
use ReflectionMethod;
use Tests\Support\UnitTester;

class SecurityCest
{
    public function _after(): void
    {
        RuntimeContext::flush();
        Request::setInstance(null);
    }

    public function testDocumentPropertiesEscapeHtmlContexts(UnitTester $I): void
    {
        $properties = new ViewProperties(
            title: '</title><script>audit()</script>',
            meta: [
                'props' => [
                    "description' onmouseover='audit()" => "'><img src=x onerror=audit()>",
                ],
            ],
            base: "' onfocus='audit()",
        );

        $html = (string)$properties;

        $I->assertStringNotContainsString('<script>audit()</script>', $html);
        $I->assertStringNotContainsString("onfocus='audit()'", $html);
        $I->assertStringNotContainsString('<img src=x', $html);
        $I->assertStringContainsString('&lt;script&gt;audit()&lt;/script&gt;', $html);
    }

    public function testFileInterceptorGeneratesContainedServerFilename(UnitTester $I): void
    {
        $tmpName = tempnam(sys_get_temp_dir(), 'assegai-upload-');
        file_put_contents($tmpName, 'png-bytes');

        try {
            $request = $this->createRequestWithUpload($tmpName, '../../avatar.png', 9);
            $interceptor = new FileInterceptor('avatar', new FileInterceptorOptions(
                dest: '/srv/app/uploads',
                fileFilter: static fn(array $file): bool => $file['extension'] === 'png',
                limits: ['fileSize' => 1024],
            ));

            $interceptor->intercept($this->createExecutionContext());
            $file = $request->getFile();

            $I->assertSame('avatar.png', $file['original_name']);
            $I->assertStringStartsWith('/srv/app/uploads/', $file['target_path']);
            $I->assertStringNotContainsString('..', $file['target_path']);
            $I->assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $file['stored_name']);
        } finally {
            if (is_string($tmpName) && is_file($tmpName)) {
                unlink($tmpName);
            }
        }
    }

    public function testFileInterceptorEnforcesSizeLimitsAndExecutableExtensionBlocklist(UnitTester $I): void
    {
        $tmpName = tempnam(sys_get_temp_dir(), 'assegai-upload-');
        file_put_contents($tmpName, 'payload');

        try {
            $this->createRequestWithUpload($tmpName, 'payload.png', 2048);
            $I->expectThrowable(BadRequestException::class, function (): void {
                (new FileInterceptor('avatar', new FileInterceptorOptions(limits: ['fileSize' => 3])))
                    ->intercept($this->createExecutionContext());
            });

            $this->createRequestWithUpload($tmpName, 'shell.php', 7);
            $I->expectThrowable(BadRequestException::class, function (): void {
                (new FileInterceptor('avatar'))->intercept($this->createExecutionContext());
            });
        } finally {
            if (is_string($tmpName) && is_file($tmpName)) {
                unlink($tmpName);
            }
        }
    }

    private function createRequestWithUpload(string $tmpName, string $name, int $size): Request
    {
        RuntimeContext::flush();
        $request = Request::createFromRuntimeContext(new RuntimeRequestContext(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/upload',
                'CONTENT_TYPE' => 'application/json',
            ],
            rawBody: '{}',
        ));
        $request->setFile([
            'avatar' => [
                'name' => $name,
                'tmp_name' => $tmpName,
                'type' => 'application/octet-stream',
                'error' => UPLOAD_ERR_OK,
                'size' => $size,
            ],
        ]);
        Request::setInstance($request);
        RuntimeContext::set(Request::class, $request);

        return $request;
    }

    private function createExecutionContext(): ExecutionContext
    {
        return new ExecutionContext(self::class, new ReflectionMethod($this, 'noop'));
    }

    private function noop(): void
    {
    }
}
