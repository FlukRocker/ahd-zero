{{-- One chat line. Alpine renders it client-side from the `line` loop variable;
     every value goes through x-text (a text node), never x-html. --}}
<li class="text-sm">
    <span class="font-medium" x-text="line.name"></span>
    <span x-text="line.body"></span>
</li>
