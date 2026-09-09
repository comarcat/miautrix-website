<x-layouts::app
    title="Contact — miautrix"
    description="Get in touch."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Contact']]" />

        {{-- Found in review: --breakpoint-xs is a 375px MEDIA-QUERY breakpoint token
             (app.css), not a content-width design token — using it as max-w-* pinned this
             page (and every other page's body copy) to phone width even on desktop. Removed
             everywhere; content now fills the same --container-content width the header/nav
             already use. --}}
        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Contact</h1>
            <p class="text-body text-muted-foreground">
                Have a question or want to work together? Send a message below.
            </p>
        </section>

        <section>
            @livewire('contact-form')
        </section>
    </div>
</x-layouts::app>
