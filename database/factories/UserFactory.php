<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            // Default password is a strong, non-weak value so that tests
            // which check "the password was NOT changed to X" can use a
            // sentinel like 'password' or 'password123' and reliably get
            // false from Hash::check(). This value is intentionally NOT
            // in the NotWeakPassword deny-list.
            'password_hash' => static::$password ??= Hash::make('NondonFactory2025'),
            'assigned_branch_id' => Branch::inRandomOrder()->first()?->id ?? Branch::factory(),
            'is_online_channel' => false,
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'locked_by' => null,
            'password_changed_at' => now(),
            'force_password_change' => false,
            'last_login_at' => null,
            'last_login_ip' => null,
            'active_role_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign the user to a specific branch.
     */
    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_branch_id' => $branch->id,
        ]);
    }

    /**
     * Mark the user as an online channel member.
     */
    public function onlineChannel(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online_channel' => true,
        ]);
    }

    /**
     * Assign a role to the user.
     */
    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName) {
            $user->assignRole($roleName);
        });
    }
}
