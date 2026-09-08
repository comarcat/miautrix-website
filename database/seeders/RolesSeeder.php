<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds exactly one super_admin role and assigns it to the seeded admin user (§9 step 11).
 * The user itself is created by E2-T7's DatabaseSeeder, from ADMIN_SEED_EMAIL/PASSWORD — this
 * seeder can run standalone before that user exists (E2-T6's own verify does exactly that);
 * it just creates the role and skips the assignment until a matching user shows up.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $email = config('admin.seed_email');

        if (! $email) {
            return;
        }

        $admin = User::where('email', $email)->first();

        if ($admin && ! $admin->hasRole($role)) {
            $admin->assignRole($role);
        }
    }
}
