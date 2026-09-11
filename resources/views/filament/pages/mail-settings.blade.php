<x-filament-panels::page>
    {{-- BUG FIXED (found live, reported as "mail configuration is empty"): this view used to
         be a bare skeleton (a placeholder comment and nothing else) that never rendered the
         form schema at all — MailSettings::form()/mount()/save() all existed and worked
         (covered by MailSettingsTest), the page itself just never displayed them, so every
         visit showed a blank page under the "Mail settings" title with only the header's
         "Send test email" action visible. {{ $this->form }} is Filament's standard accessor
         for the schema named 'form' (see MailSettings::form()'s ->statePath('data')), the
         same mechanism vendor/filament/forms' own LivewireFormView.stub uses. --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
