@props(['type', 'id'])

@php
    // Members and admins may post; everyone else reads. The list itself is
    // public either way — /api/comments/{type}/{id} sits outside the auth group.
    $canPost = $memberAuth !== null || $authUser !== null;
    $turnstileKey = $siteConfig['turnstileSiteKey'] ?? null;
@endphp

<section x-data="commentSection('{{ $type }}', {{ (int) $id }}, {{ $canPost ? 'true' : 'false' }})">
    <x-section-header eyebrow="พูดคุย" title="ความคิดเห็น" />

    @if ($canPost)
        <form x-on:submit.prevent="submit()" class="mb-8">
            <textarea x-model="body" rows="3" maxlength="5000"
                      placeholder="เขียนความคิดเห็น..."
                      class="w-full rounded-xl border p-3 text-[14px]"
                      style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev)); color: hsl(var(--fg));"></textarea>

            @if ($turnstileKey)
                <div class="cf-turnstile mt-3" data-sitekey="{{ $turnstileKey }}" data-theme="auto"></div>
            @endif

            <div class="mt-3 flex items-center gap-3">
                <button type="button" x-on:click="submit()"
                        x-bind:disabled="posting || ! body.trim()"
                        class="btn btn-primary"
                        x-text="posting ? 'กำลังส่ง...' : 'ส่งความคิดเห็น'">ส่งความคิดเห็น</button>
                <span x-show="error" x-text="error" class="text-[13px]"
                      style="color: hsl(var(--accent)); display: none"></span>
            </div>
        </form>
    @else
        {{-- Guests still see every comment above; only composing is gated. --}}
        <div class="mb-8 rounded-xl border p-5 text-center"
             style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
            <p class="text-[14px]" style="color: hsl(var(--fg-muted))">
                เข้าสู่ระบบเพื่อร่วมแสดงความคิดเห็น
            </p>
            <div class="mt-4 flex items-center justify-center gap-3">
                <a href="/member/login" class="btn btn-primary">เข้าสู่ระบบ</a>
                @if ($siteConfig['registrationEnabled'] ?? false)
                    <a href="/member/register" class="btn btn-ghost">สมัครสมาชิก</a>
                @endif
            </div>
        </div>
    @endif

    <p x-show="loading" class="py-8 text-center text-[13px]" style="color: hsl(var(--fg-faint))">
        กำลังโหลดความคิดเห็น...
    </p>

    <p x-show="! loading && comments.length === 0"
       class="py-8 text-center text-[13px]"
       style="display: none; color: hsl(var(--fg-faint))">
        ยังไม่มีความคิดเห็น
    </p>

    <ol class="flex flex-col gap-5">
        <template x-for="c in comments" :key="c._id">
            <li class="rounded-xl border p-4"
                style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
                <div class="flex items-center gap-3">
                    <template x-if="c.user_avatar">
                        <img :src="c.user_avatar" alt="" width="32" height="32"
                             loading="lazy" decoding="async"
                             class="h-8 w-8 rounded-full object-cover">
                    </template>
                    <template x-if="! c.user_avatar">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-[13px]"
                              style="background: hsl(var(--bg-soft)); color: hsl(var(--fg-muted))"
                              x-text="(c.user_name || '?').charAt(0)"></span>
                    </template>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-medium" x-text="c.user_name"></span>
                            <template x-if="c.is_admin">
                                <span class="rounded px-1.5 py-0.5 font-mono text-[10px]"
                                      style="background: hsl(var(--accent)); color: hsl(var(--accent-fg))">ADMIN</span>
                            </template>
                        </div>
                        <div class="font-mono text-[11px]" style="color: hsl(var(--fg-faint))"
                             x-text="when(c.created_at)"></div>
                    </div>
                </div>

                {{-- x-text, never x-html: comment bodies are user input. --}}
                <p class="mt-3 text-[14px] whitespace-pre-line" style="color: hsl(var(--fg-muted))"
                   x-show="! c.deleted_by" x-text="c.body"></p>
                <p class="mt-3 text-[13px] italic" x-show="c.deleted_by"
                   style="display: none; color: hsl(var(--fg-faint))">ความคิดเห็นนี้ถูกลบแล้ว</p>

                <template x-if="c.replies && c.replies.length">
                    <ol class="mt-4 flex flex-col gap-3 border-l pl-4"
                        style="border-color: hsl(var(--border-ahd))">
                        <template x-for="r in c.replies" :key="r._id">
                            <li>
                                <div class="flex items-center gap-2">
                                    <span class="text-[12px] font-medium" x-text="r.user_name"></span>
                                    <span class="font-mono text-[10px]" style="color: hsl(var(--fg-faint))"
                                          x-text="when(r.created_at)"></span>
                                </div>
                                <p class="mt-1 text-[13px] whitespace-pre-line"
                                   style="color: hsl(var(--fg-muted))"
                                   x-show="! r.deleted_by" x-text="r.body"></p>
                                <p class="mt-1 text-[12px] italic" x-show="r.deleted_by"
                                   style="display: none; color: hsl(var(--fg-faint))">ความคิดเห็นนี้ถูกลบแล้ว</p>
                            </li>
                        </template>
                    </ol>
                </template>
            </li>
        </template>
    </ol>

    <div class="mt-6 text-center" x-show="page < lastPage" style="display: none">
        <button type="button" x-on:click="loadMore()" class="btn btn-ghost">โหลดเพิ่มเติม</button>
    </div>
</section>

@if ($canPost && $turnstileKey)
    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif
