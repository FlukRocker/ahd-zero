<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

</script>

<template>
    <Head title="Index" />
    
    <DashboardLayout>
         <!-- Content Sheet -->
        <div class="flex-1 rounded-xl border border-dashed border-[#3f3f46] bg-[#121212]/50 relative overflow-hidden">
            <!-- Inner Dashboard Grid Placeholder -->
             <div class="absolute inset-0 p-8">
                <!-- This area intentionally left blank/structured as placeholder to match screenshot -->
             </div>
        </div>
    </DashboardLayout>
</template>
