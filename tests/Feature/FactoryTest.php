<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_factory_creates_an_isolated_user_with_a_hashed_password(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }
}
