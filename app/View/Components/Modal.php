<?php

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * E4-T3 — class-based (not anonymous) so a stable, unique id can be generated once per
 * instance server-side: aria-labelledby, the open/close event names, and the trigger button
 * all need to agree on the same id, and that has to happen before the view ever renders.
 */
class Modal extends Component
{
    public string $id;

    public function __construct(
        public string $title,
        ?string $id = null,
    ) {
        $this->id = $id ?? 'modal-' . Str::random(8);
    }

    public function render()
    {
        return view('components.modal');
    }
}
