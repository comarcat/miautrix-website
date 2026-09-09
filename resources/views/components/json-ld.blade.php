@props(['data'])

{{--
    E5-T1 (§9 step 25) — generic JSON-LD emitter: every page passes its own fully-formed
    schema.org array (Person/Organization/CreativeWork/BlogPosting — whichever fits that
    page) rather than this component knowing about any specific schema type. Flags matter:
    JSON_UNESCAPED_SLASHES keeps URLs readable, JSON_UNESCAPED_UNICODE keeps names/titles as
    real UTF-8 rather than \uXXXX escapes — neither affects the script tag's validity.
--}}
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
