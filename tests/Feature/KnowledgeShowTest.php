<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use Tests\TestCase;

class KnowledgeShowTest extends TestCase
{
    public function test_show_renders_edit_link(): void
    {
        $doc = KnowledgeDocument::find(14);
        $this->withoutExceptionHandling()
            ->get('/knowledge/' . $doc->id)
            ->assertOk()
            ->assertSee('/knowledge/14/edit');
    }

    public function test_edit_renders(): void
    {
        $this->withoutExceptionHandling()
            ->get('/knowledge/14/edit')
            ->assertOk()
            ->assertSee('Editar Documento');
    }

    public function test_show_404_when_missing(): void
    {
        $this->get('/knowledge/999999')
            ->assertNotFound();
    }
}
