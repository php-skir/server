<?php

declare(strict_types=1);

namespace Skir\Server\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Skir\Server\Studio\StudioRenderer;
use Skir\Server\Tests\TestCase;

final class StudioRendererTest extends TestCase
{
    #[Test]
    public function it_serves_the_official_studio_app(): void
    {
        Route::skirRpc('/studio')->studio();

        $this
            ->get('/studio?studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('<skir-studio-app></skir-studio-app>', escape: false)
            ->assertSee('<script src="https://cdn.jsdelivr.net/npm/skir-studio/dist/skir-studio-standalone.js"></script>', escape: false);
    }

    #[Test]
    public function the_studio_app_script_is_configurable(): void
    {
        config(['skir-server.studio_app_js_url' => 'https://assets.example.test/studio.js']);
        Route::skirRpc('/self-hosted-studio')->studio();

        $this
            ->get('/self-hosted-studio?studio')
            ->assertOk()
            ->assertSee('<script src="https://assets.example.test/studio.js"></script>', escape: false);
    }

    #[Test]
    public function head_requests_can_open_studio_with_a_suppressed_body(): void
    {
        Route::skirRpc('/head-studio')->studio();

        $this
            ->call('HEAD', '/head-studio?studio')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertContent('');
    }

    #[Test]
    public function it_escapes_the_app_script_url(): void
    {
        $html = app(StudioRenderer::class)->render('https://example.test/studio.js"></script><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;/script&gt;', $html);
    }
}
