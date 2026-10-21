<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

/**
 * Filament's login page, with "Onthoud mij" ticked (the admin is only used on Ine's own devices),
 * and Enter logs in from every field.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill(['remember' => true]);
    }

    public function getFormContentComponent(): Component
    {
        // Enter on the checkbox doesn't submit a form by itself. And Chrome hands a saved password to the page
        // only after the first key press, so Livewire may still hold an empty one: pass every field on again first.
        return parent::getFormContentComponent()->extraAttributes([
            'x-on:keydown.enter' => <<<'JS'
                if ($event.target.matches('input')) {
                    $event.preventDefault();
                    $el.querySelectorAll('input').forEach((input) => input.dispatchEvent(new Event('input', { bubbles: true })));
                    $nextTick(() => $el.requestSubmit());
                }
                JS,
        ]);
    }
}
