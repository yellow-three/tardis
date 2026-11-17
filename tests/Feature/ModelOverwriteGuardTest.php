<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('gadgets', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->modelFile = app_path('Models/Gadget.php');
    File::ensureDirectoryExists(dirname($this->modelFile));
    File::put($this->modelFile, "<?php // the host's own model\n");
});

afterEach(function () {
    File::delete($this->modelFile);
    Schema::dropIfExists('gadgets');
});

/**
 * The button is hidden when a model exists, but a Livewire method can be
 * called directly, and with force on it replaced app/Models/User.php or any
 * other hand-written model with a stub.
 */
test('generating a model from the explorer never overwrites an existing one', function () {
    Livewire::test('tardis::pages.database.edit', ['table' => 'gadgets'])
        ->call('generateModel');

    expect(File::get($this->modelFile))->toBe("<?php // the host's own model\n");
});
