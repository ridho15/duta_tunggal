<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        // Generate username from email (before @)
        $validated['username'] = Str::before($validated['email'], '@');
        
        // Ensure username is unique
        $originalUsername = $validated['username'];
        $counter = 1;
        while (User::where('username', $validated['username'])->exists()) {
            $validated['username'] = $originalUsername . $counter;
            $counter++;
        }
        
        // Set first_name and last_name from name
        $nameParts = explode(' ', $validated['name'], 2);
        $validated['first_name'] = $nameParts[0];
        $validated['last_name'] = $nameParts[1] ?? null;
        
        // Generate kode_user (user code)
        $validated['kode_user'] = 'USR' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}
