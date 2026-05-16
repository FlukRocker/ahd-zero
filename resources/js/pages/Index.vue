<script setup lang="ts">
import Hero from '@/components/ahd/Hero.vue';
import PosterCard from '@/components/ahd/PosterCard.vue';
import Rail from '@/components/ahd/Rail.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useAppMeta, useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItem, toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { bunnyImg } from '@/lib/img';
import { organizationJsonLd, siteJsonLd } from '@/lib/schema';
import { Head } from '@inertiajs/vue3';
import { useHead } from '@unhead/vue';
import { computed } from 'vue';

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta?: unknown;
}

const props = defineProps<{
    anime: Paginator<AnimeRecord>;
    recommended?: AnimeRecord[];
    popular?: AnimeRecord[];
}>();

const heroItems = computed(() => {
    const source = (
        props.recommended && props.recommended.length >= 3
            ? props.recommended
            : props.anime.data
    ).slice(0, 5);
    return source.map(toCardItem);
});

const popularItems = computed(() =>
    toCardItems(props.popular ?? props.anime.data.slice(0, 10)),
);
const latestItems = computed(() => toCardItems(props.anime.data));

const { appName, appUrl } = useAppMeta();

useSeo(() => ({
    title: 'ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD',
    description:
        'ดูอนิเมะออนไลน์ฟรี รวมอนิเมะใหม่ล่าสุด ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD ดูง่าย ลื่นไหล อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero',
    type: 'website',
    schema: [
        siteJsonLd(appName.value, appUrl.value),
        organizationJsonLd(appName.value, appUrl.value),
    ],
}));

// Preload the first hero image — that <img> is the LCP element. Use the
// Bunny-optimized URL at the same width the Hero <img> requests so the
// preload exactly matches the eventual src (otherwise the browser fetches
// both the original and the optimized variant — wasted bytes).
useHead(() => {
    const first = heroItems.value[0];
    if (!first?.poster) return {};
    const optimized = bunnyImg(first.poster, { width: 480, format: 'webp' }) ?? first.poster;
    return {
        link: [
            {
                rel: 'preload',
                as: 'image',
                href: optimized,
                fetchpriority: 'high',
            },
        ],
    };
});

useAutoReveal();
</script>

<template>
    <Head title="หน้าแรก" />
    <FrontLayout>
        <Hero v-if="heroItems.length" :items="heroItems" variant="editorial" />

        <section
            v-if="popularItems.length"
            class="reveal mx-auto mt-20 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader eyebrow="กำลังมาแรง" title="ยอดนิยม" />
            <Rail :items="popularItems" layout="poster" />
        </section>

        <section class="reveal mx-auto mt-24 max-w-[1440px] px-6 lg:px-10">
            <SectionHeader eyebrow="อัปเดตล่าสุด" title="ตอนใหม่ล่าสุด" />
            <StaggerGrid>
                <PosterCard
                    v-for="item in latestItems"
                    :key="item.id"
                    :item="item"
                />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>

        <section
            class="reveal mx-auto mt-24 mb-20 max-w-[920px] px-6 lg:px-10"
            aria-labelledby="about-anime-hd-zero"
        >
            <h2
                id="about-anime-hd-zero"
                class="font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                เกี่ยวกับ ANIME HD ZERO
            </h2>

            <article class="about-block">
                <h3 class="font-display italic">
                    ดูการ์ตูนที่ ANIME HD ZERO ได้อะไรบ้าง
                </h3>
                <p>
                    Anime HD Zero
                    เป็นเว็บการ์ตูนที่ทุกๆคนสามารถเข้าถึงได้ทุกเพศทุกวัย
                    เพราะเป็นเว็บที่รวบรวม anime ที่มีมาทุกยุคทุกสมัย
                    ไม่ว่าจะเป็นอนิเมะเก่าหรือใหม่
                    รวมไปถึงอนิเมะที่หาดูยาก พวกเราได้รวบรวมมาไว้หมดแล้ว
                    เพราะฉะนั้นทุกคนสามารถ รับชมการ์ตูน
                    ได้เพลิดเพลิน ทั้งในมือถือ ทีวี คอม(pc) หรือแม้แต่
                    แท็บเล็ต ได้ก่อนใครในเว็บ Anime HD Zero
                </p>
            </article>

            <article class="about-block">
                <h3 class="font-display italic">
                    การ์ตูน ANIME อัพเดทใหม่ในปี 2025
                </h3>
                <p>
                    ปี2025 ถ้าหากพูดถึงเว็บการ์ตูน ก็ต้องนึกถึง Anime HD
                    Zero อยู่เเล้ว
                    เพราะเว็บของเรามีการ์ตูนอนิเมะใหม่ๆ
                    ที่อัพเดทในปี 2025 มาเพียบ
                    ไม่ว่าจะเป็นการ์ตูนใน iqiy, netflix, bilibili, wetv
                    หรือแม้กระทั่ง prime, trueid, disney+, hbo, max
                    หรือการ์ตูนอนิเมะนอกกระแส
                    เราก็ได้รวบรวมมาไว้หมดแล้ว ไม่ว่าจะเป็นแนว
                    แอคชั่น (action), ผจญภัย (adventure), ตลก (comedy),
                    อาชญากรรม (crime), ดราม่า (drama), แฟนตาซี (fantasy),
                    สยองขวัญ (horror), ประวัติศาสตร์ (history),
                    สงคราม (war), เพลง (musical), โรแมนติก (romance),
                    ไซไฟ (sci-fi), ฟิล์มนัวร์ (film noir),
                    ฟีลกู๊ด (feel good), ระทึกขวัญ (thriller),
                    กีฬา (sports), วิทยาศาสตร์ (science fiction),
                    ผี (horror) หรือแม้แต่ ครอบครัว (family)
                    หากคุณกลัวพูดคุยกับเพื่อนๆหรือคนบนโลกออนไลน์ไม่รู้เรื่อง
                    ต้องรีบมาดูที่เว็บนี้
                    ดูฟรีไม่ต้องชำระรายเดือนและดูเร็วกว่าใครต้อง
                    Anime HD Zero
                </p>
            </article>

            <article class="about-block">
                <h3 class="font-display italic">
                    เว็บ Anime ที่มี พากย์ไทย และ ซับไทย
                </h3>
                <p>
                    หากท่านใดอยากรับชมอนิเมะ
                    เเบบที่มีอรรถรสของออริจินัล
                    มีการพากย์เสียงที่มีอารมณ์รวม
                    เรามีแบบซับไทยให้ทุกคนได้เลือกรับชม หรือ
                    ท่านใดอยากรับชมอนิเมะ โดยที่ไม่ต้องนั่งอ่านซับ
                    เพราะต้องมองซับเลยไม่ทันได้เห็นภาพที่น่าตื่นตาตื่นใจใน
                    อนิเมะ จึงต้องย้อนกลับไปซ้ำๆ หรือ
                    ทำกิจกรรมยามว่างทั่วไปพร้อมๆกับการรับชม
                    ท่านสามารถเลือกรับชมได้ในแบบพากย์ไทยได้
                    เท่านี้ท่านก็สามารถดูการ์ตูนไปทำกิจกรรมไปได้พร้อมๆกัน
                    เพราะฉะนั้นคุณจะมัวรอทำอะไรอยู่
                    การ์ตูนใหม่ๆเข้ามาเรื่อยๆแล้วนะ
                    ระวังจะไม่ทันชาวบ้านละ
                </p>
            </article>

            <article class="about-block">
                <h3 class="font-display italic">
                    การ์ตูน Anime สมัยนี้มีให้เลือกรับชมได้หลากหลาย
                </h3>
                <p>
                    ในยุคปัจจุบันไม่ว่าจะเด็กเล็ก เด็กนักเรียน นักศึกษา
                    รวมไปถึงผู้ใหญ่วัยทำงาน
                    ใครๆก็รับชมการ์ตูนอนิเมะกันทั้งนั้น
                    เพราะการ์ตูนอนิเมะอยู่คู่ยุคคู่สมัยมานาน
                    พวกเราเลยได้ตามหาการ์ตูนทุกยุคทุกแนวที่มีมาเพื่อให้ทุกคนได้รับชมกัน
                    แต่ไม่ใช่เฉพาะแค่การ์ตูนอนิเมะญี่ปุ่นนะ
                    เพราะทั้งการ์ตูนของต่างประเทศ อย่างเช่น จีน, เกาหลี,
                    อเมริกา เราก็มีให้ด้วย ถือว่าเว็บ Anime HD Zero
                    เป็นเว็บที่มีการ์ตูนหลากหลาย เอาใจแฟนๆหลากหลายสไตล์
                    หลากหลายเชื้อชาติแบบสุดๆ
                </p>
            </article>
        </section>
    </FrontLayout>
</template>

<style scoped>
.about-block {
    margin-top: 36px;
    padding-left: 20px;
    border-left: 2px solid hsl(var(--accent));
}

.about-block h3 {
    font-size: clamp(24px, 3.4vw, 34px);
    line-height: 1.2;
    margin-bottom: 14px;
    color: hsl(var(--fg));
}

.about-block p {
    font-size: 15px;
    line-height: 1.85;
    color: hsl(var(--fg-muted));
    word-break: break-word;
}

@media (max-width: 640px) {
    .about-block {
        padding-left: 14px;
    }

    .about-block p {
        font-size: 14px;
        line-height: 1.8;
    }
}
</style>
