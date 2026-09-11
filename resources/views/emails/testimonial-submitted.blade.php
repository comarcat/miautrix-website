<x-mail::message>
# New endorsement submitted

**From:** {{ $testimonial->name }}
@if ($testimonial->organization)
({{ $testimonial->organization }}@if ($testimonial->role), {{ $testimonial->role }}@endif)
@endif

**Contact ({{ $testimonial->contact_type }}):** {{ $testimonial->contact_value }}

{{ $testimonial->body }}

It's pending review — approve or reject it in the admin panel before it appears on
/endorsements.
</x-mail::message>
