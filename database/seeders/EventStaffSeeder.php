<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EventStaffSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'name' => config('event-staff.name'),
            'email' => strtolower(trim((string) config('event-staff.email'))),
            'password' => config('event-staff.password'),
        ];

        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:72'],
        ])->validate();

        $user = User::firstOrNew(['email' => $data['email']]);
        $user->name = $data['name'];
        $user->is_staff = true;
        if (! $user->exists || ! Hash::check($data['password'], $user->password)) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
    }
}
