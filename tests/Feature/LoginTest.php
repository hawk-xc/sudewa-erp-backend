<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_is_admin_login_working()
    {
        // 1. Setup: Create the admin role (required for Spatie)
        Role::create(['name' => 'admin']);

        // 2. Create the user
        $user = User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'firstname' => 'Admin',
            'lastname' => 'Test',
            'email' => 'admin@deraly.id',
            'password' => bcrypt('password'),
            'secure_password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // 3. Assign role
        $user->assignRole('admin');

        // 4. Act: Log the user in
        $this->actingAs($user, 'api');

        // 5. Assert: Check if user is logged in and is admin
        $this->assertTrue(Auth::check());
        $this->assertTrue($user->isAdmin());
    }
}

