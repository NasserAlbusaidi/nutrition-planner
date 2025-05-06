<?php

namespace App\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UserProfile extends Component
{

    public $weight_kg;
    public $ftp_watts;
    public $sweat_level;
    public $salt_loss_level;

    protected function rules()
    {
        return [
            'weight_kg' => ['nullable', 'numeric', 'min:30', 'max:200'],
            'ftp_watts' => ['nullable', 'integer', 'min:50', 'max:800'],
            'sweat_level' => ['nullable', Rule::in(['light', 'average', 'heavy'])],
            'salt_loss_level' => ['nullable', Rule::in(['low', 'average', 'high'])],
        ];
    }

    // Save the updated profile data
    public function save()
    {
        $this->validate();

        Auth::user()->update([
            'weight_kg' => $this->weight_kg,
            'ftp_watts' => $this->ftp_watts,
            'sweat_level' => $this->sweat_level,
            'salt_loss_level' => $this->salt_loss_level,
        ]);

        // Flash a success message to the session
        session()->flash('message', 'Profile successfully updated.');

        // Optional: Redirect or refresh data if needed
        // return redirect()->route('profile.edit'); // Or just let Livewire re-render
    }

    public function mount()
    {
        $user = Auth::user();
        $this->weight_kg = $user->weight_kg;
        $this->ftp_watts = $user->ftp_watts;
        $this->sweat_level = $user->sweat_level;
        $this->salt_loss_level = $user->salt_loss_level;
    }
    public function render()
    {
        return view('livewire.user-profile')->layout('layouts.app', ['title' => 'User Profile']);
    }
}
