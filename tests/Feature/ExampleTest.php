<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_students_can_still_access_the_join_screen(): void
    {
        $this->get(route('client.join'))
            ->assertOk()
            ->assertSee('data-student-panel="register"', false)
            ->assertSee('data-student-panel="login"', false);

        $this->get(route('client.login'))
            ->assertOk()
            ->assertSee('data-initial-mode="login"', false);
    }
}
