@props(['catId', 'bookmarked' => false])

@if ($memberAuth)
    <button type="button" class="btn btn-ghost"
        data-bookmarked="{{ $bookmarked ? 'true' : 'false' }}"
        x-data="bookmarkToggle({{ (int) $catId }}, {{ $bookmarked ? 'true' : 'false' }})"
        x-on:click="toggle()"
        x-bind:disabled="busy"
        x-bind:aria-pressed="on ? 'true' : 'false'">
        <span x-text="on ? '✓ อยู่ในรายการ' : '+ เพิ่มในรายการ'">{{ $bookmarked ? '✓ อยู่ในรายการ' : '+ เพิ่มในรายการ' }}</span>
    </button>
@else
    {{-- Guests get a plain link, styled identically so there is no shift. --}}
    <a href="/member/login" class="btn btn-ghost" data-bookmarked="guest">+ เพิ่มในรายการ</a>
@endif
