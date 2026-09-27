<?php

namespace Tests\Feature\Platform;

use App\Models\Comms\CommTemplateVersion;
use App\Services\Platform\Templates\TemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Template Engine rendering rules (FRS TPL-02/03/04, DEC-064).
 */
class TemplateServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TemplateService $templates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templates = app(TemplateService::class);
    }

    private function active(string $code, array $version): void
    {
        $draft = $this->templates->saveDraft($code, ['channel' => 'EMAIL', 'category' => 'OPERATIONAL', 'name' => $code] + $version);
        CommTemplateVersion::query()->whereKey($draft->get('version_id'))->update(['status' => 'ACTIVE']);
    }

    public function test_html_variables_are_escaped_and_text_variables_are_not(): void
    {
        $this->active('t.escape', ['body_html' => '<p>{{name}}</p>', 'body_text' => 'Hi {{name}}', 'variables' => [['name' => 'name', 'required' => true]]]);

        $render = $this->templates->render('t.escape', 'EMAIL', ['name' => '<b>R&D</b>']);

        $this->assertSame('<p>&lt;b&gt;R&amp;D&lt;/b&gt;</p>', $render->get('html'));
        $this->assertSame('Hi <b>R&D</b>', $render->get('text'));
    }

    public function test_missing_required_variable_fails_the_render(): void
    {
        $this->active('t.missing', ['body_text' => 'Hi {{name}}', 'variables' => [['name' => 'name', 'required' => true]]]);

        $render = $this->templates->render('t.missing', 'EMAIL', []);

        $this->assertSame(['RENDER_ERROR', ['name']], [$render->code, $render->get('missing')]);
    }

    public function test_undeclared_placeholder_fails_the_render_but_is_highlighted_in_preview(): void
    {
        $this->active('t.unknown', ['body_text' => 'Hi {{nmae}}', 'variables' => [['name' => 'name']]]);
        $version = CommTemplateVersion::query()->whereHas('template', fn ($q) => $q->where('code', 't.unknown'))->firstOrFail();

        $this->assertSame('RENDER_ERROR', $this->templates->render('t.unknown', 'EMAIL', ['name' => 'R'])->code);
        $this->assertStringContainsString('⟦nmae?⟧', (string) $this->templates->renderVersion($version, [], true)->get('text'));
    }

    public function test_saving_after_activation_forks_a_new_draft_and_leaves_the_active_version(): void
    {
        $this->active('t.fork', ['body_text' => 'one']);

        $draft = $this->templates->saveDraft('t.fork', ['channel' => 'EMAIL', 'category' => 'OPERATIONAL', 'name' => 't.fork', 'body_text' => 'two']);

        $this->assertSame(2, $draft->get('version'));
        $this->assertSame('one', $this->templates->render('t.fork', 'EMAIL')->get('text'));
    }
}
