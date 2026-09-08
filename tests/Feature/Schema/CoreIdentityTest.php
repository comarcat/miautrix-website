<?php

namespace Tests\Feature\Schema;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E2-T1 — core identity and profile schema (blueprint §4).
 */
class CoreIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_creates_users_and_profiles_tables_with_the_columns_in_the_blueprint(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('profiles'));
        $this->assertTrue(Schema::hasColumns('profiles', [
            'id', 'user_id', 'full_name', 'headline', 'bio', 'avatar_media_id',
            'location', 'availability_status', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    public function test_a_new_user_has_two_factor_confirmed_at_null_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_profiles_user_id_is_unique_at_the_database_level(): void
    {
        $user = User::factory()->create();

        Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Admin',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);

        $this->expectException(QueryException::class);

        Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Admin Duplicate',
            'headline' => 'Software Engineer',
            'bio' => 'Bio text.',
        ]);
    }
}
