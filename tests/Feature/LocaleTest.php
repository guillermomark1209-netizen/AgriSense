<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_choose_tagalog_for_the_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'tl'])
            ->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aking mga Pananim')
            ->assertSee('<html lang="tl">', false);
    }

    public function test_user_can_choose_bisaya_for_the_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'ceb']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akong mga Tanom')
            ->assertSee('<html lang="ceb">', false);
    }

    public function test_locale_selection_rejects_unsupported_languages(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
