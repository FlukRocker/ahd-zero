<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="relative mt-24 overflow-hidden"
        style="border-top: 1px solid hsl(var(--border-ahd))"
    >
        <div class="mx-auto max-w-[1440px] px-6 py-14 lg:px-10">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-5">
                <div class="col-span-2">
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="relative flex h-10 w-10 items-center justify-center rounded-lg"
                            style="
                                background: linear-gradient(
                                    135deg,
                                    hsl(var(--accent)),
                                    hsl(var(--accent) / 0.6)
                                );
                            "
                        >
                            <span
                                class="font-display text-2xl leading-none text-white italic"
                                style="transform: translateY(-1px)"
                                >z</span
                            >
                        </div>
                        <div
                            class="font-display text-[28px] leading-none italic"
                        >
                            Anime HD Zero
                        </div>
                    </div>
                    <p
                        class="max-w-sm text-[14px]"
                        style="color: hsl(var(--fg-muted))"
                    >
                        ดูอนิเมะออนไลน์ ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD
                        รับชมได้ทุกเรื่อง
                    </p>
                </div>
                <div>
                    <div
                        class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        รับชม
                    </div>
                    <ul class="space-y-2 text-[14px]">
                        <li>
                            <Link href="/category/1" class="u-grow"
                                >ซับไทย</Link
                            >
                        </li>
                        <li>
                            <Link href="/category/2" class="u-grow"
                                >พากย์ไทย</Link
                            >
                        </li>
                        <li>
                            <Link href="/category/3" class="u-grow"
                                >เดอะมูฟวี่</Link
                            >
                        </li>
                    </ul>
                </div>
                <div>
                    <div
                        class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        เรียกดู
                    </div>
                    <ul class="space-y-2 text-[14px]">
                        <li>
                            <Link href="/studios" class="u-grow">สตูดิโอ</Link>
                        </li>
                        <li>
                            <Link href="/voice-actors" class="u-grow"
                                >นักพากย์</Link
                            >
                        </li>
                        <li>
                            <Link href="/staff" class="u-grow">ทีมงาน</Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <div
                        class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        บัญชี
                    </div>
                    <ul class="space-y-2 text-[14px]">
                        <li>
                            <Link href="/member/login" class="u-grow"
                                >เข้าสู่ระบบ</Link
                            >
                        </li>
                        <li>
                            <Link href="/member/settings/profile" class="u-grow"
                                >ตั้งค่า</Link
                            >
                        </li>
                    </ul>
                </div>
            </div>
            <div
                class="mt-12 flex flex-col items-center justify-between gap-4 pt-6 md:flex-row"
                style="border-top: 1px solid hsl(var(--border-ahd))"
            >
                <div
                    class="font-mono text-[11px]"
                    style="color: hsl(var(--fg-faint))"
                >
                    © {{ year }} Anime HD Zero
                </div>
                <div
                    class="flex gap-4 font-mono text-[12px]"
                    style="color: hsl(var(--fg-faint))"
                >
                    <a href="#">ข้อตกลง</a>
                    <a href="#">ความเป็นส่วนตัว</a>
                    <a href="#">DMCA</a>
                </div>
            </div>
        </div>
    </footer>
</template>
