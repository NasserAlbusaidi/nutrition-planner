<?php

namespace App\Livewire;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination; // Optional: For pagination if list gets long

class PantryManager extends Component
{
    use WithPagination; // Optional

    // Form properties
    public $productId; // Used for editing
    public $name;
    public $type;
    public $carbs_g;
    public $sodium_mg;
    public $caffeine_mg;
    public $serving_size_description;
    public $serving_volume_ml;

    // Modal/Form visibility control
    public $showModal = false;
    public $isEditMode = false;

    // Validation rules
    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['gel', 'bar', 'drink_mix', 'chews', 'real_food', 'other'])],
            'carbs_g' => ['required', 'numeric', 'min:0'],
            'sodium_mg' => ['required', 'numeric', 'min:0'],
            'caffeine_mg' => ['required', 'numeric', 'min:0'],
            'serving_size_description' => ['required', 'string', 'max:255'],
            'serving_volume_ml' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    // Reset form fields and close modal
    public function resetForm()
    {
        $this->resetErrorBag();
        $this->reset(['productId', 'name', 'type', 'carbs_g', 'sodium_mg', 'caffeine_mg', 'serving_size_description', 'serving_volume_ml', 'isEditMode', 'showModal']);
    }

    // Show the modal for adding a new product
    public function addProductModal()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->showModal = true;
    }

    // Show the modal for editing an existing product
    public function editProductModal(Product $product)
    {
        // Optional: Authorization check if needed (though products are scoped later)
        // if ($product->user_id !== Auth::id()) {
        //     abort(403);
        // }

        $this->resetForm();
        $this->isEditMode = true;
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->type = $product->type;
        $this->carbs_g = $product->carbs_g;
        $this->sodium_mg = $product->sodium_mg;
        $this->caffeine_mg = $product->caffeine_mg;
        $this->serving_size_description = $product->serving_size_description;
        $this->serving_volume_ml = $product->serving_volume_ml;
        $this->showModal = true;
    }

    // Store a new product or update an existing one
    public function saveProduct()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'carbs_g' => $this->carbs_g,
            'sodium_mg' => $this->sodium_mg,
            'caffeine_mg' => $this->caffeine_mg,
            'serving_size_description' => $this->serving_size_description,
            'serving_volume_ml' => $this->serving_volume_ml ?: null, // Store null if empty
        ];

        if ($this->isEditMode && $this->productId) {
            // Update existing product
            $product = Auth::user()->products()->findOrFail($this->productId);
            $product->update($data);
            session()->flash('message', 'Product updated successfully.');
        } else {
            // Create new product
            Auth::user()->products()->create($data);
            session()->flash('message', 'Product added successfully.');
        }

        $this->resetForm();
    }

    // Delete a product
    public function deleteProduct(Product $product)
    {
        // Optional: Authorization check
        // if ($product->user_id !== Auth::id()) {
        //     abort(403);
        // }

        $product->delete();
        session()->flash('message', 'Product deleted successfully.');
    }

    // Render the component view with user's products
    public function render()
    {
        $products = Auth::user()->products()->orderBy('name')->paginate(10); // Paginate results

        return view('livewire.pantry-manager', [
            'products' => $products,
        ])->layout('layouts.app', ['title' => 'Pantry Manager']);
    }
}
