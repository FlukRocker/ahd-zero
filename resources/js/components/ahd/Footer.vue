<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-24 relative overflow-hidden" style="border-top: 1px solid hsl(var(--border-ahd));">
        <div class="max-w-[1440px] mx-auto px-6 lg:px-10 py-14">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-8">
                <div class="col-span-2">
                    <div class="flex items-center gap-2 mb-4">
                        <div
                            class="relative w-10 h-10 rounded-lg flex items-center justify-center"
                            style="background: linear-gradient(135deg, hsl(var(--accent)), hsl(var(--accent) / 0.6));"
                        >
                            <span class="text-white font-display text-2xl italic leading-none" style="transform: translateY(-1px);">z</span>
                        </div>
                        <div class="font-display text-[28px] italic leading-none">Anime HD Zero</div>
                    </div>
                    <p class="text-[14px] max-w-sm" style="color: hsl(var(--fg-muted));">
                        ดูอนิเมะออนไลน์ ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD รับชมได้ทุกเรื่อง
                    </p>
                </div>
                <div>
                    <div
                        class="font-mono text-[10px] tracking-[0.22em] uppercase mb-3"
                        style="color: hsl(var(--fg-faint));"
                    >รับชม</div>
                    <ul class="space-y-2 text-[14px]">
                        <li><Link href="/category/1" class="u-grow">ซับไทย</Link></li>
                        <li><Link href="/category/2" class="u-grow">พากย์ไทย</Link></li>
                        <li><Link href="/category/3" class="u-grow">เดอะมูฟวี่</Link></li>
                    </ul>
                </div>
                <div>
                    <div
                        class="font-mono text-[10px] tracking-[0.22em] uppercase mb-3"
                        style="color: hsl(var(--fg-faint));"
                    >เรียกดู</div>
                    <ul class="space-y-2 text-[14px]">
                        <li><Link href="/studios" class="u-grow">สตูดิโอ</Link></li>
                        <li><Link href="/voice-actors" class="u-grow">นักพากย์</Link></li>
                        <li><Link href="/staff" class="u-grow">ทีมงาน</Link></li>
                    </ul>
                </div>
                <div>
                    <div
                        class="font-mono text-[10px] tracking-[0.22em] uppercase mb-3"
                        style="color: hsl(var(--fg-faint));"
                    >บัญชี</div>
                    <ul class="space-y-2 text-[14px]">
                        <li><Link href="/member/login" class="u-grow">เข้าสู่ระบบ</Link></li>
                        <li><Link href="/settings/profile" class="u-grow">ตั้งค่า</Link></li>
                    </ul>
                </div>
            </div>
            <div
                class="mt-12 pt-6 flex flex-col md:flex-row justify-between gap-4 items-center"
                style="border-top: 1px solid hsl(var(--border-ahd));"
            >
                <div class="font-mono text-[11px]" style="color: hsl(var(--fg-faint));">
                    © {{ year }} Anime HD Zero
                </div>
                <div class="flex gap-4 text-[12px] font-mono" style="color: hsl(var(--fg-faint));">
                    <a href="#">ข้อตกลง</a>
                    <a href="#">ความเป็นส่วนตัว</a>
                    <a href="#">DMCA</a>
                </div>
            </div>
        </div>
    </footer>
</template>
