<x-layouts::app
    title="Contact — miautrix"
    description="Get in touch."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Contact']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Contact</h1>
            <p class="max-w-(--breakpoint-xs) text-body text-muted-foreground">
                Have a question or want to work together? Send a message below.
            </p>
        </section>

        <section class="max-w-(--breakpoint-xs)">
            @livewire('contact-form')
        </section>
    </div>
</x-layouts::app>
